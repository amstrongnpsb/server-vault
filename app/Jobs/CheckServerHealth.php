<?php

namespace App\Jobs;

use App\Events\ServerStatusChanged;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class CheckServerHealth implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $timeout = 30;

    public $failOnTimeout = false;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        $start = Carbon::now();

        $status = $this->ping();

        $this->server->update([
            'status' => $status,
            'last_checked_at' => $start,
        ]);

        ServerStatusChanged::dispatch($this->server);

        Log::info("Server [{$this->server->name}] checked: {$status}");
    }

    private function ping(): string
    {
        $host = $this->server->host;
        $port = $this->server->port ?? 22;

        if ($this->tcpConnect($host, $port)) {
            return Server::STATUS_ONLINE;
        }

        try {
            $ssh = new SSH2($host, $port, 10);

            if ($ssh->isConnected()) {
                return Server::STATUS_ONLINE;
            }
        } catch (\Throwable $e) {
            //
        }

        $fallbackPorts = $this->getFallbackPorts();
        foreach ($fallbackPorts as $fallbackPort) {
            if ($fallbackPort === $port) {
                continue;
            }

            if ($this->tcpConnect($host, $fallbackPort)) {
                return Server::STATUS_ONLINE;
            }
        }

        return Server::STATUS_OFFLINE;
    }

    private function tcpConnect(string $host, int $port, int $timeout = 5): bool
    {
        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if (is_resource($connection)) {
                fclose($connection);

                return true;
            }
        } catch (\Throwable $e) {
            //
        }

        return false;
    }

    private function getFallbackPorts(): array
    {
        $ports = [];

        if (strtolower($this->server->os) === 'windows') {
            $ports = [3389, 5985, 5986];
        }

        return $ports;
    }
}
