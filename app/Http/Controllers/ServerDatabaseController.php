<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerDatabaseRequest;
use App\Http\Requests\UpdateServerDatabaseRequest;
use App\Models\Server;
use App\Models\ServerDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class ServerDatabaseController extends Controller
{
    public function store(StoreServerDatabaseRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        if (! empty($validated['credentials'])) {
            $validated['credentials'] = Crypt::encryptString($validated['credentials']);
        }

        $server->databases()->create($validated);
        Cache::forget("server_details_{$server->id}");

        return back()->with('success', 'Database added successfully.');
    }

    public function update(UpdateServerDatabaseRequest $request, ServerDatabase $serverDatabase): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('credentials', $validated)) {
            if (! empty($validated['credentials'])) {
                $validated['credentials'] = Crypt::encryptString($validated['credentials']);
            } else {
                unset($validated['credentials']);
            }
        }

        $serverDatabase->update($validated);
        Cache::forget("server_details_{$serverDatabase->server_id}");

        return back()->with('success', 'Database updated successfully.');
    }

    public function destroy(ServerDatabase $serverDatabase): RedirectResponse
    {
        $serverDatabase->delete();
        Cache::forget("server_details_{$serverDatabase->server_id}");

        return back()->with('success', 'Database deleted successfully.');
    }
}
