<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\SshSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SshTerminalController extends Controller
{
    public function show(Server $server): Response
    {
        return Inertia::render('Servers/Terminal', [
            'server' => $server->only('id', 'name', 'host', 'port', 'os'),
        ]);
    }

    public function connect(Server $server): JsonResponse
    {
        $session = SshSession::create([
            'id' => (string) Str::uuid(),
            'server_id' => $server->id,
            'user_id' => auth()->id(),
            'connection_token' => Str::random(64),
            'status' => 'pending',
            'token_expires_at' => now()->addSeconds(30),
        ]);

        return response()->json([
            'session_id' => $session->id,
            'token' => $session->connection_token,
            'bridge_url' => config('services.ssh_bridge.ws_url'),
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $session = SshSession::where('id', $request->session_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $session->update(['status' => 'closed', 'ended_at' => now()]);

        return response()->json(['success' => true]);
    }
}
