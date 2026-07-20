# Plan: Connect to Server (SSH Terminal)

This document outlines the implementation plan for the "Connect to Server" feature — a browser-based SSH terminal launched from the ServerVault UI. The architecture uses a dedicated real-time transport layer for low-latency duplex I/O (PuTTY-like feel) and persistent session tracking to survive PHP's stateless request model.

> **Auth scope for this version:** password authentication only. SSH key-based auth is intentionally deferred — see Section 9. The `credentials` column already stores an encrypted secret generically, so adding key auth later is an additive migration, not a redesign.

---

## Current State

| Component                                                                                | Status                                                                  |
| ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| Server model with `host`, `port`, `username`, `credentials` fields                       | ✅ Ready                                                                |
| Encrypted credential storage (`Crypt::encryptString` / `decrypted_credentials` accessor) | ✅ Ready                                                                |
| `phpseclib/phpseclib ^3.0`                                                               | ✅ Installed, correct current major version                             |
| `@xterm/xterm ^6.0.0` + `@xterm/addon-fit ^0.11.0`                                       | ✅ Installed, correct current scoped packages                           |
| `Laravel Reverb ^1.10`                                                                   | ✅ Installed — reserved for future real-time features (server health, auto status) |
| Server CRUD (backend + frontend)                                                         | ✅ Complete                                                             |
| Credential reveal/copy UI in ServerDetailModal                                           | ✅ Complete                                                             |

**New addition needed:** a small, persistent WebSocket↔SSH bridge process, separate from the normal PHP-FPM request lifecycle and separate from Reverb.

---

## Architecture Overview

```text
Browser (xterm.js)
   │
   │  1. GET /servers/{server}/terminal
   │     → Laravel checks permission, creates a row in ssh_sessions,
   │       issues a short-lived signed connection token
   ▼
Laravel (auth, permissions, credentials, session bookkeeping)
   │
   │  2. Browser opens a WebSocket directly to the bridge, with the token
   ▼
SSH Bridge Process (ReactPHP, long-running, one event loop for all active sessions)
   │  - validates the token against the ssh_sessions table
   │  - decrypts credentials (via an internal API call back to Laravel, see below)
   │  - opens phpseclib SSH2 connection, keeps it alive for the session's lifetime
   │  - relays keystrokes → SSH2::write() directly, no HTTP, no queue
   │  - relays SSH2::read() output → WebSocket frame directly
   ▼
Remote Server (SSH)
```

Reverb is **not** in this path. It's kept for future real-time features like automatic server health checks and live status updates.

**Deployment note:** this requires a persistent, long-running process (the bridge) — it will not work on request/response-only hosting (e.g. some shared hosting or fully serverless PaaS). If your infra can't run Supervisor-managed processes, run the bridge on a small always-on VM instead of the whole app; it only proxies I/O, so it doesn't need much.

---

## 1. Database: `ssh_sessions`

```php
Schema::create('ssh_sessions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('server_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->string('connection_token'); // short-lived, single-use
    $table->enum('status', ['pending', 'active', 'closed', 'failed'])->default('pending');
    $table->timestamp('token_expires_at');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('ended_at')->nullable();
    $table->timestamps();

    $table->index(['connection_token']);
});
```

This table is the durable source of truth. Any process — Laravel controller, channel authorizer, bridge process, audit log — can query it instead of relying on a PHP array that only exists inside one request.

```php
// app/Models/SshSession.php
class SshSession extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['server_id', 'user_id', 'connection_token', 'status', 'token_expires_at'];
    protected $casts = ['token_expires_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function server() { return $this->belongsTo(Server::class); }
    public function user() { return $this->belongsTo(User::class); }
}
```

---

## 2. Laravel: Issue Connection Token

**File:** `app/Http/Controllers/SshTerminalController.php`

```php
class SshTerminalController extends Controller
{
    public function show(Server $server): Response
    {
        $this->authorize('connect', $server);

        return Inertia::render('Servers/Terminal', [
            'server' => $server->only('id', 'name', 'host', 'port', 'os'),
        ]);
    }

    public function connect(Server $server): JsonResponse
    {
        $this->authorize('connect', $server);

        $session = SshSession::create([
            'id' => (string) Str::uuid(),
            'server_id' => $server->id,
            'user_id' => auth()->id(),
            'connection_token' => Str::random(64),
            'status' => 'pending',
            'token_expires_at' => now()->addSeconds(30), // must be used almost immediately
        ]);

        return response()->json([
            'session_id' => $session->id,
            'token' => $session->connection_token,
            'bridge_url' => config('services.ssh_bridge.ws_url'), // e.g. wss://bridge.internal:8090
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $session = SshSession::where('id', $request->session_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // tell the bridge process to close the socket via its internal control endpoint
        Http::post(config('services.ssh_bridge.control_url') . '/close', [
            'session_id' => $session->id,
        ]);

        $session->update(['status' => 'closed', 'ended_at' => now()]);

        return response()->json(['success' => true]);
    }
}
```

Laravel never opens the SSH2 connection itself and never keeps it alive across requests — it only issues a one-time token and records session metadata. All actual SSH I/O lives entirely inside the bridge process.

**Route (`routes/web.php`)**, same auth/verified group as before:

```php
Route::get('/servers/{server}/terminal', [SshTerminalController::class, 'show'])->name('servers.terminal');
Route::post('/servers/{server}/connect', [SshTerminalController::class, 'connect'])->name('servers.connect');
Route::post('/ssh/disconnect', [SshTerminalController::class, 'disconnect'])->name('ssh.disconnect');
```

---

## 3. The SSH Bridge: ReactPHP Service

This runs as its own long-lived process (via Supervisor), separate from PHP-FPM, using an event loop to hold open connections.

**Dependencies** (already in root `composer.json`):

```json
"react/event-loop": "^1.6",
"react/socket": "^1.17",
"phpseclib/phpseclib": "^3.0"
```

The bridge runs from the root project (no separate `composer.json`), sharing vendor and autoload.

**File:** `bridge/server.php` — custom ReactPHP TCP server with raw WebSocket handshake handling (no Ratchet). See actual source for current implementation.

**Run it under Supervisor:**

```ini
; /etc/supervisor/conf.d/ssh-bridge.conf
[program:ssh-bridge]
command=php /path-to-app/bridge/server.php
autostart=true
autorestart=true
user=www-data
```

---

## 4. Internal Laravel Endpoints

These are small, internal-only routes protected by a shared secret header, used by the bridge to communicate with Laravel.

**File:** `routes/internal.php`

```php
Route::middleware('internal-secret')->group(function () {
    Route::post('/internal/ssh/validate-token', [InternalSshController::class, 'validateToken']);
    Route::post('/internal/ssh/credentials', [InternalSshController::class, 'credentials']);
    Route::post('/internal/ssh/mark-active', [InternalSshController::class, 'markActive']);
    Route::post('/internal/ssh/mark-closed', [InternalSshController::class, 'markClosed']);
});
```

**File:** `app/Http/Controllers/InternalSshController.php`

```php
class InternalSshController extends Controller
{
    public function validateToken(Request $request)
    {
        $session = SshSession::where('connection_token', $request->token)
            ->where('status', 'pending')
            ->where('token_expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json(['valid' => false], 404);
        }

        return response()->json([
            'valid' => true,
            'id' => $session->id,
            'server_id' => $session->server_id,
            'host' => $session->server->host,
            'port' => $session->server->port,
            'username' => $session->server->username,
        ]);
    }

    public function credentials(Request $request)
    {
        $server = Server::findOrFail($request->server_id);
        return response()->json(['credentials' => $server->decrypted_credentials]);
    }

    public function markActive(Request $request) { ... }
    public function markClosed(Request $request) { ... }
}
```

Credentials never reach the browser — they flow Laravel → bridge process over an internal, authenticated channel.

---

## 5. Frontend: `Terminal.vue`

Connects straight to the bridge via WebSocket.

```vue
<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Terminal } from "@xterm/xterm";
import { FitAddon } from "@xterm/addon-fit";
import axios from "axios";
import "@xterm/xterm/css/xterm.css";

const props = defineProps({ server: Object });

const terminalRef = ref(null);
const isConnected = ref(false);
let terminal = null;
let fitAddon = null;
let socket = null;

const initTerminal = () => {
    terminal = new Terminal({
        cursorBlink: true,
        theme: {
            background: "#1a1b26",
            foreground: "#a9b1d6",
            cursor: "#c0caf5",
        },
        fontFamily: "JetBrains Mono, Fira Code, monospace",
        fontSize: 14,
    });
    fitAddon = new FitAddon();
    terminal.loadAddon(fitAddon);
    terminal.open(terminalRef.value);
    fitAddon.fit();

    terminal.onData((data) => {
        if (socket?.readyState === WebSocket.OPEN) {
            socket.send(data);
        }
    });
};

const connect = async () => {
    const { data } = await axios.post(
        route("servers.connect", props.server.id),
    );

    socket = new WebSocket(`${data.bridge_url}?token=${data.token}`);
    socket.onopen = () => {
        isConnected.value = true;
    };
    socket.onmessage = (event) => {
        terminal.write(event.data);
    };
    socket.onclose = () => {
        isConnected.value = false;
    };
    socket.onerror = () => {
        terminal.writeln("\x1b[31mConnection error.\x1b[0m");
    };
};

onMounted(() => {
    initTerminal();
    connect();
});
onBeforeUnmount(() => {
    socket?.close();
});
</script>

<template>
    <Head :title="`Terminal — ${server.name}`" />
    <AuthenticatedLayout>
        <div
            class="flex items-center justify-between p-4 border-b border-border"
        >
            <div class="flex items-center gap-3">
                <div
                    class="h-2 w-2 rounded-full"
                    :class="isConnected ? 'bg-green-500' : 'bg-red-500'"
                />
                <span class="text-sm font-medium text-foreground"
                    >{{ server.name }} — {{ server.host }}</span
                >
            </div>
        </div>
        <div ref="terminalRef" class="flex-1 bg-[#1a1b26] p-2" />
    </AuthenticatedLayout>
</template>
```

---

## 6. Security considerations

- **Permission gate & policy:** `connect servers` permission, `ServerPolicy::connect`.
- **Token as WebSocket credential:** single-use, 30-second expiry.
- **Internal endpoints are not public:** separate route file, shared-secret middleware, firewalled.
- **Session timeout:** bridge process tracks idle time and closes sessions.
- **Audit logging:** log on `mark-active` / `mark-closed`.
- **Rate limiting:** applied at the WebSocket layer in the bridge.
- **Credentials:** decrypted server-side only, never sent to the browser.
- **Auth failure messaging:** on login failure, send a generic "Authentication failed" message — don't distinguish "bad username" from "bad password" in the response, to avoid account/user enumeration.

---

## 7. Implementation order

| Step | Task                                                   | Priority  |
| ---- | ------------------------------------------------------ | --------- |
| 1    | `ssh_sessions` migration + model                       | 🔴 High   |
| 2    | `SshTerminalController` (show, connect, disconnect)    | 🔴 High   |
| 3    | Internal routes + `InternalSshController`              | 🔴 High   |
| 4    | Bridge service (`bridge/server.php`)                   | 🔴 High   |
| 5    | Supervisor config for the bridge process               | 🔴 High   |
| 6    | `Terminal.vue` integration                             | 🟡 Medium |
| 7    | "Connect" action in `Servers/Index.vue`                | 🟡 Medium |
| 8    | `connect servers` permission + `ServerPolicy::connect` | 🟡 Medium |
| 9    | Idle session timeout in the bridge                     | 🟢 Low    |
| 10   | Audit logging on connect/disconnect                    | 🟢 Low    |
| 11   | Terminal resize → PTY window size                      | 🟢 Low    |
| 12   | Reconnect handling, loading/error UI polish            | 🟢 Low    |

---

## 8. Files to create / modify

### New

| File                                                     | Purpose                                          |
| -------------------------------------------------------- | ------------------------------------------------ |
| `database/migrations/xxxx_create_ssh_sessions_table.php` | Session metadata table                           |
| `app/Models/SshSession.php`                              | Eloquent model for session tracking              |
| `app/Http/Controllers/SshTerminalController.php`         | Token issuance, disconnect                       |
| `app/Http/Controllers/InternalSshController.php`         | Internal-only endpoints for the bridge           |
| `routes/internal.php`                                    | Internal route group, shared-secret middleware   |
| `bridge/server.php`                                      | Standalone ReactPHP WebSocket↔SSH bridge (uses root vendor) |
| `resources/js/Pages/Servers/Terminal.vue`                | Terminal page                                    |

### Modified

| File                                    | Change                                                                |
| --------------------------------------- | --------------------------------------------------------------------- |
| `routes/web.php`                        | `servers.terminal`, `servers.connect`, `ssh.disconnect` routes        |
| `resources/js/Pages/Servers/Index.vue`  | "Connect" dropdown action                                             |
| `database/seeders/PermissionSeeder.php` | `connect servers` permission                                          |
| `config/services.php`                   | `ssh_bridge.ws_url`, internal shared secret |

---

## 9. Future enhancements

- **SSH key-based authentication**, in addition to password. Deferred from this version, but designed to be additive: a follow-up migration adds nullable `private_key` / `private_key_passphrase` columns to `servers`, an `auth_method` column on `ssh_sessions` (chosen by the user per-connection, not fixed per-server), and the bridge branches its `SSH2::login()` call based on that. No changes needed to the token flow, controller structure, or `Terminal.vue`.
- SFTP file browser over the same SSH connection
- Saved commands / snippets
- Multi-tab terminal
- Session recording/replay for auditing
- Collaborative terminal (share a session with other users)
- "Test Connection" button in server create/edit
- Connection templates (jump hosts / proxy configs)
