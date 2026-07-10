<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
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
        Server::create($request->validated());

        return redirect()->route('servers.index')
            ->with('success', 'Server created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServerRequest $request, Server $server): RedirectResponse
    {
        $server->update($request->validated());

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
}