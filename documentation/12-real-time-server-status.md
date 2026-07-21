# Real-Time Server Status Monitoring

Automatically detect whether servers are online/offline and broadcast status changes to the UI in real time via Laravel Reverb.

---

## Current State

| Aspect | Status |
|--------|--------|
| **Status column** | `enum('Online','Offline')` in `servers` table, defaults to `'Offline'` |
| **Setting status** | Only done manually via the edit form in `ServerModal.vue` |
| **`last_checked_at`** | Column exists in DB but never written to |
| **Health checking** | None — no ping, no SSH, no queue job, no command |
| **Reverb server** | Running as `reverb` container on port 8080, proxied at `/ws` |
| **Echo (client)** | Not installed — no `laravel-echo`, no `pusher-js`, `bootstrap.js` only has Axios |
| **Broadcasting events** | None — `app/Events/` and `app/Listeners/` are empty |
| **Queue worker** | Running as `queue` container via Supervisor |
| **Dashboard** | Empty placeholder — no server status summary |
| **Permissions** | No server-specific permissions exist (`view servers`, `create servers`, etc.) |
| **Enums** | Status and OS are plain strings, not PHP enums |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    App Container (Laravel)                    │
│                                                              │
│  Console Command              Queue Job                     │
│  ┌───────────────┐    ┌──────────────────┐                  │
│  │ servers:check │───▶│ CheckServerHealth│──▶ Event         │
│  │ (scheduler)   │    │ (dispatched per   │   ServerStatus  │
│  └───────────────┘    │  server)          │   Changed       │
│                       └──────────────────┘       │          │
│                                                  ▼          │
│                                          Broadcast (Reverb) │
│                                          ┌──────────────┐   │
│                                          │ "server.{id}" │   │
│                                          └──────┬───────┘   │
└─────────────────────────────────────────────────┼───────────┘
                                                  │ WebSocket
                                                  ▼
┌────────────────────────────────────────────────────────────┐
│                   Browser (Vue + Echo)                       │
│                                                              │
│  Echo.private(`server.{id}`)                                 │
│    .listen('ServerStatusChanged', (e) => {                   │
│        updateServerStatus(e.server.id, e.server.status,      │
│                           e.server.last_checked_at);         │
│    })                                                        │
│                                                              │
│  Pinia Store (useServerStore)                                 │
│    - servers: Map<id, Server>                                │
│    - updateStatus(id, status, checkedAt)                     │
│    - onlineCount, offlineCount, totalCount (computed)        │
└────────────────────────────────────────────────────────────┘
```

---

## Implementation Plan

### Phase 1: Foundation

#### 1.1. Add Constants to Server Model

**File:** `app/Models/Server.php`

Add class constants instead of a separate enum file. This keeps status definitions close to their usage and avoids adding an unnecessary file for two values.

```php
class Server extends Model
{
    use HasFactory, HasUuids;

    const STATUS_ONLINE = 'Online';
    const STATUS_OFFLINE = 'Offline';

    // ... existing code ...
}
```

Update existing methods:

```php
public function isOnline(): bool
{
    return $this->status === self::STATUS_ONLINE;
}

public static function getStatusOptions(): array
{
    return [self::STATUS_ONLINE, self::STATUS_OFFLINE];
}
```

The `status` column stays as a plain string — no cast needed.

#### 1.2. Update Form Requests

**Files:** `app/Http/Requests/StoreServerRequest.php`, `app/Http/Requests/UpdateServerRequest.php`

- No changes needed — they already use `Rule::in(Server::getStatusOptions())` which continues to work

---

### Phase 2: Broadcasting Setup

#### 2.1. Install Laravel Echo on Frontend

```bash
npm install laravel-echo pusher-js
```

**File:** `resources/js/bootstrap.js`

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

#### 2.2. Vite Config Update

**File:** `vite.config.js`

Ensure `VITE_*` env vars are passed to the client (they already are via `.env`).

#### 2.3. Create Broadcast Event

**File:** `app/Events/ServerStatusChanged.php`

```php
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
        return new Channel('servers');                        // public: all users see status
        // or:  return new PrivateChannel('server.' . $this->server->id);  // private per-server
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
```

**Decision point: public vs private channel**

| Approach | Pros | Cons |
|----------|------|------|
| **Public channel** (`servers`) | Simpler, no auth callback needed, one listener | All users see all status changes |
| **Private channel** (`server.{id}`) | Granular access control | Need auth routes, more boilerplate |

**Recommendation:** Use a **public channel** named `servers` for now. Server status is not sensitive data. Can upgrade to private later if needed.

#### 2.4. Echo Listener on Dashboard

**File:** `resources/js/Pages/Dashboard.vue`

```javascript
import { onMounted, onUnmounted } from 'vue';
import { useServerStore } from '@/stores/useServerStore';

const serverStore = useServerStore();

onMounted(() => {
    window.Echo.channel('servers')
        .listen('ServerStatusChanged', (e) => {
            serverStore.updateStatus(e.id, e.status, e.last_checked_at);
        });
});

onUnmounted(() => {
    window.Echo.leaveChannel('servers');
});
```

#### 2.5. Create Pinia Store

**File:** `resources/js/stores/useServerStore.js`

```javascript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useServerStore = defineStore('server', () => {
    const servers = ref({}); // Map<id, { id, name, status, last_checked_at }>

    const onlineCount = computed(() =>
        Object.values(servers.value).filter(s => s.status === 'Online').length
    );
    const offlineCount = computed(() =>
        Object.values(servers.value).filter(s => s.status === 'Offline').length
    );
    function updateStatus(id, status, lastCheckedAt) {
        if (servers.value[id]) {
            servers.value[id].status = status;
            servers.value[id].last_checked_at = lastCheckedAt;
        }
    }

    function setServers(list) {
        const map = {};
        list.forEach(s => { map[s.id] = s; });
        servers.value = map;
    }

    return { servers, onlineCount, offlineCount, updateStatus, setServers };
});
```

---

### Phase 3: Health Check Engine

#### 3.1. Create Console Command

**File:** `app/Console/Commands/CheckServerHealth.php`

```php
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

        $query->each(function (Server $server) {
            CheckServerHealthJob::dispatch($server);
        });

        $this->info('Health check jobs dispatched.');
        return Command::SUCCESS;
    }
}
```

#### 3.2. Create Queue Job

**File:** `app/Jobs/CheckServerHealth.php`

This is the core logic. It tries to connect to each server and updates its status.

```php
<?php

namespace App\Jobs;

use App\Events\ServerStatusChanged;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckServerHealth implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $timeout = 30;         // max 30s per check
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

        // Method 1: Quick TCP socket check (port 22)
        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, 5);

            if (is_resource($connection)) {
                fclose($connection);
                return Server::STATUS_ONLINE;
            }
        } catch (\Throwable $e) {
            // Fall through to SSH check
        }

        // Method 2: SSH connection attempt (more reliable)
        try {
            $ssh = new \phpseclib3\Net\SSH2($host, $port, 10);

            if ($ssh->isConnected()) {
                return Server::STATUS_ONLINE;
            }
        } catch (\Throwable $e) {
            // Server is unreachable
        }

        return Server::STATUS_OFFLINE;
    }
}
```

**Design Notes:**

| Approach | Pros | Cons |
|----------|------|------|
| **TCP ping only** | Fast (~5s), no auth needed | Port 22 open ≠ SSH working |
| **Full SSH login** | Gold standard accuracy | Slow, needs credentials, auth failures look like offline |
| **TCP ping + SSH** | Best balance | Slightly more complex |

**Recommendation:** Use **TCP socket check** (`fsockopen`) as the primary method. It's fast, non-blocking, and accurate enough. If the SSH port is open and accepting connections, the server is "Online" from a connectivity perspective. Full SSH authentication is unnecessary for a simple health check.

#### 3.3. Register the Command

**File:** `app/Console/Kernel.php`

```php
protected $commands = [
    Commands\CheckServerHealth::class,
];

protected function schedule(Schedule $schedule): void
{
    $schedule->command('servers:check')->everyMinute()->withoutOverlapping();
}
```

#### 3.4. Alternative: Docker Health Check (if servers are Docker containers on same network)

If the servers being monitored are Docker containers on the same network, you could let Docker handle health checks natively. But since ServerVault manages remote servers, the TCP/SSH approach is more appropriate.

---

### Phase 4: Dashboard Integration

#### 4.1. Dashboard Overview

**File:** `resources/js/Pages/Dashboard.vue`

Add status summary cards:

```html
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <Card class="bg-green-50 border-green-200">
        <CardHeader>Online</CardHeader>
        <CardContent class="text-3xl font-bold">{{ serverStore.onlineCount }}</CardContent>
    </Card>
    <Card class="bg-red-50 border-red-200">
        <CardHeader>Offline</CardHeader>
        <CardContent class="text-3xl font-bold">{{ serverStore.offlineCount }}</CardContent>
    </Card>
</div>
```

#### 4.2. Server Store Initialization

On Dashboard mount, fetch initial server list via Inertia props (passed from `DashboardController`) and populate the store. Then Echo takes over for real-time updates.

---

### Phase 5: Permissions & Security

#### 5.1. Add Server Permissions

**File:** `database/seeders/RoleSeeder.php`

Add permissions:
- `view servers`
- `create servers`
- `edit servers`
- `delete servers`
- `check server health` (for running manual checks)

#### 5.2. Gate Broadcasting

If using private channels, add auth route in `routes/channels.php`:

```php
Broadcast::channel('server.{serverId}', function ($user, $serverId) {
    return $user->can('view servers');
});
```

---

### Phase 6: Manual Check Button

#### 6.1. Add "Check Now" Action

**File:** `resources/js/Pages/Servers/Index.vue`

Add a "Check Now" button per row (or bulk action) that calls a new endpoint:

```php
// routes/web.php
Route::post('/servers/{server}/check', [ServerController::class, 'checkHealth'])
    ->name('servers.check');
```

**File:** `app/Http/Controllers/ServerController.php`

```php
public function checkHealth(Server $server): RedirectResponse
{
    CheckServerHealth::dispatch($server);

    return back()->with('success', 'Health check queued for ' . $server->name);
}
```

---

### Phase 7: 10-Second vs 1-Minute Checks (Future)

For low latency, the 1-minute scheduler interval is not great. Options for the future:

| Option | Latency | Complexity | Resource Usage |
|--------|---------|------------|----------------|
| **Scheduler (every minute)** | ~1 min | Low | Low |
| **Queue with delay** | ~30s | Low | Low |
| **Continuous loop in bridge** | ~10s | Medium | Medium (1 process) |
| **Dedicated health-check container** | ~10s | High | High (1 container) |

**Initial approach:** Scheduler + queue (Phase 3). The 1-minute delay is acceptable for MVP. Can optimize later.

---

## Files to Create

| # | File | Purpose |
|---|------|---------|
| 1 | `app/Events/ServerStatusChanged.php` | Broadcast event for Reverb |
| 2 | `app/Jobs/CheckServerHealth.php` | Queue job that pings a server |
| 3 | `app/Console/Commands/CheckServerHealth.php` | Artisan command to dispatch jobs |
| 4 | `resources/js/stores/useServerStore.js` | Pinia store for real-time server state |

## Files to Modify

| # | File | Change |
|---|------|--------|
| 1 | `app/Models/Server.php` | Add status constants, scopes |
| 2 | `resources/js/bootstrap.js` | Initialize Laravel Echo |
| 3 | `resources/js/Pages/Dashboard.vue` | Add status overview, Echo listener |
| 4 | `resources/js/Pages/Servers/Index.vue` | Live status badge updates, "Check Now" button |
| 5 | `routes/web.php` | Add `servers.check` route |
| 6 | `app/Http/Controllers/ServerController.php` | Add `checkHealth()` method |
| 7 | `database/seeders/RoleSeeder.php` | Add server permissions |
| 8 | `app/Console/Kernel.php` | Register command + schedule |

---

## Sequence Diagram

```
User           Browser              Laravel            Reverb           Queue            Server
 │                │                    │                  │                │                │
 │  Open Dashboard                    │                  │                │                │
 │────────────────▶                   │                  │                │                │
 │                │  GET /dashboard   │                  │                │                │
 │                │──────────────────▶│                  │                │                │
 │                │  Props: servers[] │                  │                │                │
 │                │◀──────────────────│                  │                │                │
 │                │                    │                  │                │                │
 │                │  Echo.channel('servers')             │                │                │
 │                │─────────────────────────────────────▶│                │                │
 │                │                    │                  │                │                │
 │                │                    │  Scheduler tick  │                │                │
 │                │                    │◀─ everyMinute ──│                │                │
 │                │                    │                  │                │                │
 │                │                    │  servers:check   │                │                │
 │                │                    │──────────────────│                │                │
 │                │                    │  dispatch jobs   │                │                │
 │                │                    │─────────────────────────────────▶│                │
 │                │                    │                  │                │                │
 │                │                    │                  │                │  fsockopen/host │
 │                │                    │                  │                │────────────────▶│
 │                │                    │                  │                │◀────────────────│
 │                │                    │                  │                │                │
 │                │                    │                  │                │  UPDATE status  │
 │                │                    │                  │                │  dispatch event │
 │                │                    │                  │                │                │
 │                │                    │◀─────────────────│◀───────────────│                │
 │                │                    │  ServerStatusChanged             │                │
 │                │                    │──────────────────│               │                │
 │                │                    │                  │                │                │
 │                │◀──────────────────────────────────────│                │                │
 │                │  { id, status, last_checked_at }      │                │                │
 │                │                    │                  │                │                │
 │                │  update badge/UI   │                  │                │                │
 │                │  toast notification                    │                │                │
 │                │                    │                  │                │                │
```

## Next Steps

1. Start with **Phase 1** (Enum) — small, safe refactor
2. Then **Phase 2** (Broadcasting) — install Echo, create event, test with `php artisan tinker`
3. Then **Phase 3** (Health check) — build the job, test manually with `php artisan servers:check`
4. Then **Phase 4** (Dashboard) — wire up the store and Echo listener
5. Then **Phase 5-6** (Permissions, manual check)
6. Deploy, monitor, iterate
