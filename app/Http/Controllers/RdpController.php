<?php

namespace App\Http\Controllers;

use App\Models\RdpSession;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RdpController extends Controller
{
    public function show(Server $server): Response
    {
        return Inertia::render('Servers/RdpViewer', [
            'server' => $server->only('id', 'name', 'host', 'port', 'os'),
        ]);
    }

    public function connect(Server $server): JsonResponse
    {
        $session = RdpSession::create([
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
        ]);
    }
}
