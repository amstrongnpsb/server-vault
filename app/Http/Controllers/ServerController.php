<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
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
            'osOptions' => Server::getOsOptions(),
            'statusOptions' => Server::getStatusOptions(),
            'filters' => [
                'search' => $search,
                'os' => $osFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Servers/Create', [
            'osOptions' => Server::getOsOptions(),
            'statusOptions' => Server::getStatusOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'os' => 'required|in:' . implode(',', Server::getOsOptions()),
            'status' => 'required|in:' . implode(',', Server::getStatusOptions()),
            'description' => 'nullable|string',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'credentials' => 'nullable|string',
        ]);

        Server::create($validated);

        return redirect()->route('servers.index')
            ->with('success', 'Server created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Server $server): Response
    {
        return Inertia::render('Servers/Show', [
            'server' => $server,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Server $server): Response
    {
        return Inertia::render('Servers/Edit', [
            'server' => $server,
            'osOptions' => Server::getOsOptions(),
            'statusOptions' => Server::getStatusOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'os' => 'required|in:' . implode(',', Server::getOsOptions()),
            'status' => 'required|in:' . implode(',', Server::getStatusOptions()),
            'description' => 'nullable|string',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'credentials' => 'nullable|string',
        ]);

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
}