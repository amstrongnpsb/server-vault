<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerStructureController extends Controller
{
    public function show(Server $server): Response
    {
        $cluster = $this->fetchCluster($server);

        return Inertia::render('Servers/Structure', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'os' => $server->os,
                'status' => $server->status,
            ],
            'initialNodes' => $cluster['nodes']->values(),
            'initialEdges' => $cluster['edges']->values(),
        ]);
    }

    public function fetch(Server $server): JsonResponse
    {
        $cluster = $this->fetchCluster($server);

        return response()->json([
            'nodes' => $cluster['nodes']->values(),
            'edges' => $cluster['edges']->values(),
        ]);
    }

    public function connect(Server $server, Request $request): JsonResponse
    {
        $request->validate([
            'source_server_id' => 'required|uuid|exists:servers,id',
            'target_server_id' => 'required|uuid|exists:servers,id|different:source_server_id',
            'type' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'source_handle' => 'nullable|string|max:50',
            'target_handle' => 'nullable|string|max:50',
        ]);

        if ($request->type === 'Hosts') {
            $existingHost = ServerConnection::where('target_server_id', $request->target_server_id)
                ->where('type', 'Hosts')
                ->exists();

            if ($existingHost) {
                return response()->json(['message' => 'This server already has a Hosts connection.'], 422);
            }
        }

        $exists = ServerConnection::where('source_server_id', $request->source_server_id)
            ->where('target_server_id', $request->target_server_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Connection already exists between these servers.'], 422);
        }

        $connection = ServerConnection::create([
            'source_server_id' => $request->source_server_id,
            'target_server_id' => $request->target_server_id,
            'type' => $request->type,
            'label' => $request->label,
            'source_handle' => $request->source_handle,
            'target_handle' => $request->target_handle,
        ]);

        return response()->json([
            'message' => 'Connection created.',
            'connection' => $this->formatEdge($connection),
        ]);
    }

    public function disconnect(Server $server, ServerConnection $connection): JsonResponse
    {
        $connection->delete();

        return response()->json(['message' => 'Connection deleted.']);
    }

    public function removeNode(Server $server, string $node): JsonResponse
    {
        if ($node === $server->id) {
            return response()->json(['message' => 'Cannot remove the root server.'], 422);
        }

        ServerConnection::where('source_server_id', $node)
            ->orWhere('target_server_id', $node)
            ->delete();

        Server::where('id', $node)->update(['canvas_x' => null, 'canvas_y' => null]);

        return response()->json(['message' => 'Server removed from structure.']);
    }

    public function updatePosition(Server $server, Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
            'canvas_x' => 'nullable|numeric',
            'canvas_y' => 'nullable|numeric',
        ]);

        $target = Server::findOrFail($request->server_id);
        $target->update([
            'canvas_x' => $request->canvas_x,
            'canvas_y' => $request->canvas_y,
        ]);

        return response()->json(['message' => 'Position updated.']);
    }

    private function fetchCluster(Server $root): array
    {
        $visited = collect([$root->id => $root]);
        $queue = collect([$root]);
        $edges = collect();

        while ($queue->isNotEmpty()) {
            $current = $queue->shift();

            $outgoing = ServerConnection::with('targetServer')
                ->where('source_server_id', $current->id)
                ->get();

            foreach ($outgoing as $edge) {
                $key = "{$edge->source_server_id}-{$edge->target_server_id}";
                if (! $edges->has($key)) {
                    $edges->put($key, $this->formatEdge($edge));
                }
                if (! $visited->has($edge->target_server_id)) {
                    $visited->put($edge->target_server_id, $edge->targetServer);
                    $queue->push($edge->targetServer);
                }
            }

            $incoming = ServerConnection::with('sourceServer')
                ->where('target_server_id', $current->id)
                ->get();

            foreach ($incoming as $edge) {
                $key = "{$edge->source_server_id}-{$edge->target_server_id}";
                if (! $edges->has($key)) {
                    $edges->put($key, $this->formatEdge($edge));
                }
                if (! $visited->has($edge->source_server_id)) {
                    $visited->put($edge->source_server_id, $edge->sourceServer);
                    $queue->push($edge->sourceServer);
                }
            }
        }

        return [
            'nodes' => $visited->map(fn (Server $s) => $this->formatNode($s)),
            'edges' => $edges,
        ];
    }

    private function formatNode(Server $server): array
    {
        return [
            'id' => $server->id,
            'name' => $server->name,
            'host' => $server->host,
            'os' => $server->os,
            'status' => $server->status,
            'canvas_x' => $server->canvas_x,
            'canvas_y' => $server->canvas_y,
            'databases' => $server->databases()->get(['id', 'type', 'name'])->toArray(),
            'services' => $server->services()->get(['id', 'name', 'port'])->toArray(),
        ];
    }

    private function formatEdge(ServerConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'source' => $connection->source_server_id,
            'target' => $connection->target_server_id,
            'type' => $connection->type,
            'label' => $connection->label,
            'source_handle' => $connection->source_handle,
            'target_handle' => $connection->target_handle,
        ];
    }
}
