<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $search = request('search');
        $osFilter = request('os');
        $statusFilter = request('status');

        // Convert comma-separated strings to arrays if needed
        if (is_string($osFilter) && !empty($osFilter)) {
            $osFilter = explode(',', $osFilter);
        }
        if (is_string($statusFilter) && !empty($statusFilter)) {
            $statusFilter = explode(',', $statusFilter);
        }

        $servers = Server::query()
            ->search($search)
            ->filterByOs($osFilter)
            ->filterByStatus($statusFilter)
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Servers/Index', [
            'servers' => $servers,
            'osOptions' => Server::getOsOptions(), // For filter - without "Other"
            'osOptionsWithOther' => array_merge(Server::getOsOptions(), ['Other']), // For modal - with "Other"
            'statusOptions' => Server::getStatusOptions(),
            'filters' => [
                'search' => $search,
                'os' => $osFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (!empty($validated['credentials'])) {
            $validated['credentials'] = Crypt::encryptString($validated['credentials']);
        }

        Server::create($validated);

        return redirect()->route('servers.index')
            ->with('success', 'Server created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServerRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('credentials', $validated)) {
            if (!empty($validated['credentials'])) {
                $validated['credentials'] = Crypt::encryptString($validated['credentials']);
            } else {
                unset($validated['credentials']);
            }
        }

        $server->update($validated);

        return redirect()->route('servers.index')
            ->with('success', 'Server updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();

        return redirect()->route('servers.index')
            ->with('success', 'Server deleted successfully.');
    }

    /**
     * Get server details (databases and services) for the detail modal.
     */
    public function details(Server $server): \Illuminate\Http\JsonResponse
    {
        $cacheKey = "server_details_{$server->id}";

        if (request()->boolean('force')) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        $details = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($server) {
            return [
                'databases' => $server->databases()->get(),
                'services' => $server->services()->get(),
            ];
        });

        return response()->json($details);
    }

    /**
     * Reveal credentials securely via explicit API request
     */
    public function revealCredential(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'type' => 'required|in:server,database,service',
            'id' => 'required|uuid'
        ]);

        $modelClass = match ($request->type) {
            'server' => \App\Models\Server::class,
            'database' => \App\Models\ServerDatabase::class,
            'service' => \App\Models\ServerService::class,
        };

        $model = $modelClass::findOrFail($request->id);

        return response()->json([
            'password' => $model->decrypted_credentials
        ]);
    }
}