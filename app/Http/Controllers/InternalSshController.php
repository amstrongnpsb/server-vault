<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\SshSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalSshController extends Controller
{
    public function validateToken(Request $request): JsonResponse
    {
        $session = SshSession::where('connection_token', $request->token)
            ->where('status', 'pending')
            ->where('token_expires_at', '>', now())
            ->first();

        if (! $session) {
            return response()->json(['valid' => false], 404);
        }

        $session->load('server');

        return response()->json([
            'valid' => true,
            'id' => $session->id,
            'server_id' => $session->server_id,
            'host' => $session->server->host,
            'port' => $session->server->port,
            'username' => $session->server->username,
        ]);
    }

    public function credentials(Request $request): JsonResponse
    {
        $server = Server::findOrFail($request->server_id);

        return response()->json([
            'credentials' => $server->decrypted_credentials,
        ]);
    }

    public function markActive(Request $request): JsonResponse
    {
        $session = SshSession::findOrFail($request->session_id);
        $session->update(['status' => 'active', 'started_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markClosed(Request $request): JsonResponse
    {
        $session = SshSession::findOrFail($request->session_id);
        $session->update(['status' => 'closed', 'ended_at' => now()]);

        return response()->json(['success' => true]);
    }
}
