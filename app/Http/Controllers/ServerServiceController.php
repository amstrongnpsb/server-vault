<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerServiceRequest;
use App\Http\Requests\UpdateServerServiceRequest;
use App\Models\Server;
use App\Models\ServerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;

class ServerServiceController extends Controller
{
    public function store(StoreServerServiceRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();
        
        if (!empty($validated['credentials'])) {
            $validated['credentials'] = Crypt::encryptString($validated['credentials']);
        }

        $server->services()->create($validated);

        return back()->with('success', 'Service added successfully.');
    }

    public function update(UpdateServerServiceRequest $request, ServerService $serverService): RedirectResponse
    {
        $validated = $request->validated();
        
        if (array_key_exists('credentials', $validated)) {
            if (!empty($validated['credentials'])) {
                $validated['credentials'] = Crypt::encryptString($validated['credentials']);
            }
        }

        $serverService->update($validated);

        return back()->with('success', 'Service updated successfully.');
    }

    public function destroy(ServerService $serverService): RedirectResponse
    {
        $serverService->delete();

        return back()->with('success', 'Service deleted successfully.');
    }
}
