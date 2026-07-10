<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerDatabaseRequest;
use App\Http\Requests\UpdateServerDatabaseRequest;
use App\Models\Server;
use App\Models\ServerDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;

class ServerDatabaseController extends Controller
{
    public function store(StoreServerDatabaseRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();
        
        if (!empty($validated['credentials'])) {
            $validated['credentials'] = Crypt::encryptString($validated['credentials']);
        }

        $server->databases()->create($validated);

        return back()->with('success', 'Database added successfully.');
    }

    public function update(UpdateServerDatabaseRequest $request, ServerDatabase $serverDatabase): RedirectResponse
    {
        $validated = $request->validated();
        
        // If password is provided, encrypt it. Otherwise remove it from validated so it's not overwritten with null (unless intended, but typically empty means don't update password).
        // For simplicity, we'll assume if credentials is provided it overwrites, if null it overwrites with null. 
        // Wait, if it's empty in UI, it might be sent as null.
        if (array_key_exists('credentials', $validated)) {
            if (!empty($validated['credentials'])) {
                $validated['credentials'] = Crypt::encryptString($validated['credentials']);
            } else {
                // If it's a blank string or null but they submitted it, maybe they want to clear it?
                // Let's check if it's an intentional clear or just no change.
                // Normally we don't send it if we don't want to change it.
                // But let's handle encryption safely.
                if ($validated['credentials'] !== null) {
                   $validated['credentials'] = Crypt::encryptString($validated['credentials']);
                }
            }
        }

        $serverDatabase->update($validated);

        return back()->with('success', 'Database updated successfully.');
    }

    public function destroy(ServerDatabase $serverDatabase): RedirectResponse
    {
        $serverDatabase->delete();

        return back()->with('success', 'Database deleted successfully.');
    }
}
