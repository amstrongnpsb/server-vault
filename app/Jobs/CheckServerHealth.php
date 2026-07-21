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

        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, 5);

            if (is_resource($connection)) {
                fclose($connection);

                return Server::STATUS_ONLINE;
            }
        } catch (\Throwable $e) {
            //
        }

        try {
            $ssh = new SSH2($host, $port, 10);

            if ($ssh->isConnected()) {
                return Server::STATUS_ONLINE;
            }
        } catch (\Throwable $e) {
            //
        }

        return Server::STATUS_OFFLINE;
    }
}
