<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Jobs\CheckServerHealth as CheckServerHealthJob;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        if (is_string($osFilter) && ! empty($osFilter)) {
            $osFilter = explode(',', $osFilter);
        }
        if (is_string($statusFilter) && ! empty($statusFilter)) {
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

        if (! empty($validated['credentials'])) {
            $validated['credentials'] = Crypt::encryptString($validated['credentials']);
        }

        $validated['user_id'] = $request->user()->id;

        $server = Server::create($validated);

        CheckServerHealthJob::dispatch($server);

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
            if (! empty($validated['credentials'])) {
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
    public function details(Server $server): JsonResponse
    {
        $cacheKey = "server_details_{$server->id}";

        if (request()->boolean('force')) {
            Cache::forget($cacheKey);
        }

        $details = Cache::remember($cacheKey, 300, function () use ($server) {
            return [
                'databases' => $server->databases()->get(),
                'services' => $server->services()->get(),
            ];
        });

        return response()->json($details);
    }

    /**
     * Duplicate a server with all its attributes (no relations).
     */
    public function duplicate(Server $server): RedirectResponse
    {
        $baseName = $server->name.'-duplicate';
        $newName = $baseName;
        $counter = 2;
        while (Server::where('name', $newName)->exists()) {
            $newName = $baseName.'-'.$counter;
            $counter++;
        }

        $credentials = $server->decrypted_credentials;

        $newServer = Server::create([
            'user_id' => request()->user()->id,
            'name' => $newName,
            'host' => $server->host,
            'port' => $server->port,
            'username' => $server->username,
            'os' => $server->os,
            'status' => Server::STATUS_OFFLINE,
            'credentials' => $credentials ? Crypt::encryptString($credentials) : null,
        ]);

        return redirect()->route('servers.index')
            ->with('success', "Server duplicated as \"{$newServer->name}\".");
    }

    /**
     * Reveal credentials securely via explicit API request
     */
    public function checkHealth(Server $server): RedirectResponse
    {
        CheckServerHealthJob::dispatch($server);

        return back()->with('success', "Health check queued for {$server->name}.");
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:255',
            'exclude' => 'nullable|array',
            'exclude.*' => 'uuid',
        ]);

        $servers = Server::query()
            ->search($request->input('query'))
            ->when($request->filled('exclude'), function ($q) use ($request) {
                $q->whereNotIn('id', $request->exclude);
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'host', 'os', 'status']);

        return response()->json($servers);
    }

    public function revealCredential(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:server,database,service',
            'id' => 'required|uuid',
        ]);

        $modelClass = match ($request->type) {
            'server' => Server::class,
            'database' => ServerDatabase::class,
            'service' => ServerService::class,
        };

        $model = $modelClass::findOrFail($request->id);

        return response()->json([
            'password' => $model->decrypted_credentials,
        ]);
    }
}
