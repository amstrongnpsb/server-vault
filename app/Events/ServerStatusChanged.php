<?php

namespace App\Events;

use App\Models\Server;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ServerStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Server $server
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('servers');
    }

    public function broadcastAs(): string
    {
        return 'server.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->server->id,
            'status' => $this->server->status,
            'last_checked_at' => $this->server->last_checked_at?->toISOString(),
        ];
    }
}
