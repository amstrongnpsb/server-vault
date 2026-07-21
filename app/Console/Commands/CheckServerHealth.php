<?php

namespace App\Console\Commands;

use App\Jobs\CheckServerHealth as CheckServerHealthJob;
use App\Models\Server;
use Illuminate\Console\Command;

class CheckServerHealth extends Command
{
    protected $signature = 'servers:check {--server= : Check a specific server by ID}';

    protected $description = 'Check all servers and broadcast status updates';

    public function handle(): int
    {
        $query = Server::query();

        if ($serverId = $this->option('server')) {
            $query->where('id', $serverId);
        }

        $count = 0;
        $query->each(function (Server $server) use (&$count) {
            CheckServerHealthJob::dispatch($server);
            $count++;
        });

        $this->info("Dispatched {$count} health check job(s).");

        return Command::SUCCESS;
    }
}
