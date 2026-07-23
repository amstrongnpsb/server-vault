# Windows RDP Support

## Overview

This document describes the implementation of RDP (Remote Desktop Protocol) support for Windows servers in ServerVault, using Apache Guacamole as the RDP proxy.

## Architecture

```
Browser (Vue + guacamole-common-js)
  ↓  WebSocket (/rdp-ws)
Nginx (proxies to guacd-proxy:8091)
  ↓  WebSocket
guacd-proxy bridge (ReactPHP, port 8091)
  ↓  Guacamole TCP protocol (port 4822)
guacd (Apache Guacamole proxy daemon)
  ↓  RDP (port 3389)
Windows Server
```

## Components

### 1. guacd (Apache Guacamole proxy daemon)

- Official Docker image: `guacamole/guacd:latest`
- Listens on TCP port 4822 for Guacamole protocol connections
- Translates the Guacamole protocol into RDP (or VNC/SSH) connections
- No authentication at this layer — trust is enforced by the guacd-proxy bridge

### 2. guacd-proxy bridge

- New ReactPHP-based bridge (`bridge/guacd-proxy.php`)
- Same pattern as the existing SSH bridge (`bridge/server.php`)
- Handles WebSocket connections from the browser
- Validates connection token via Laravel internal API
- Fetches decrypted credentials via Laravel internal API
- Opens TCP connection to guacd and performs Guacamole protocol handshake
- Relays data bidirectionally between browser and guacd

### 3. RdpViewer.vue

- New Inertia page at `resources/js/Pages/Servers/RdpViewer.vue`
- Uses `guacamole-common-js` npm package for the Guacamole JS client
- Renders RDP display in a `<canvas>` element
- Same layout as `Terminal.vue` (sidebar, header, full-height display)

### 4. RdpController

- New controller at `app/Http/Controllers/RdpController.php`
- `show()` — renders the RDP viewer Inertia page
- `connect()` — creates an `rdp_sessions` record and returns connection token

### 5. Database

- New `rdp_sessions` table (same schema as `ssh_sessions`)
- New `RdpSession` model

## Connection Flow

1. User clicks "Remote Desktop" on a Windows server in the server list
2. Frontend renders `RdpViewer.vue`
3. `RdpViewer.vue` calls `axios.post(route('servers.rdp-connect', server.id))`
4. `RdpController::connect()` creates an `rdp_sessions` record with a random token
5. Frontend opens a WebSocket to `/rdp-ws?token=...&width=...&height=...`
6. `guacd-proxy` bridge handles the WebSocket upgrade:
   - Extracts token from query string
   - Validates token via `POST /internal/rdp/validate-token`
   - Fetches credentials via `POST /internal/rdp/credentials`
   - Opens TCP connection to guacd:4822
   - Sends Guacamole protocol handshake:
     ```
     select:rdp
     size:width,height,2
     arg:hostname:<server_host>
     arg:port:<server_port>
     arg:username:<decrypted_username>
     arg:password:<decrypted_password>
     arg:ignore-cert:true
     ```
7. guacd connects to the Windows server via RDP
8. All subsequent data is relayed bidirectionally
9. On disconnect, session is marked closed

## Security

- **Token-based auth**: Each connection requires a valid one-time token (expires in 30 seconds if unused)
- **Internal API secret**: Bridge-to-Laravel communication uses a shared `INTERNAL_SECRET` header
- **Credentials never touch the browser**: Decrypted passwords stay in the bridge process
- **Credential reuse**: RDP reuses the same `username` and `credentials` fields from the `servers` table (same as SSH)
- **No domain field**: Skipped for v1 — standalone Windows servers or workgroup machines don't need it

## File Changes

### New files

| File | Description |
|------|-------------|
| `bridge/guacd-proxy.php` | ReactPHP Guacamole proxy bridge |
| `app/Http/Controllers/RdpController.php` | RDP connection controller |
| `app/Models/RdpSession.php` | RDP session model |
| `database/migrations/xxxx_create_rdp_sessions_table.php` | RDP sessions migration |
| `resources/js/Pages/Servers/RdpViewer.vue` | RDP viewer page |

### Modified files

| File | Change |
|------|--------|
| `docker-compose.yml` | Add `guacd` and `guacd-proxy` services |
| `docker/nginx/default.conf` | Add `/rdp-ws` location block |
| `routes/web.php` | Add RDP routes |
| `bootstrap/app.php` | Add internal RDP endpoints |
| `resources/js/Pages/Servers/Index.vue` (or Modal) | OS-aware connect action |

## Guacamole Protocol Reference

The Guacamole protocol is a text-based instruction protocol:

```
<length>.<opcode>,<length>.<arg1>,<length>.<arg2>,...;
```

Each instruction starts with a decimal length, a period, then the value. Multiple arguments are comma-separated. The instruction ends with a semicolon.

Example — select RDP protocol:
```
4.select,3.rdp;
```

Example — set display size:
```
4.size,4.1920,5.1080,1.2;
```

Example — set connection argument:
```
3.arg,8.hostname,9.10.0.5.68;
```

## Display Handling

- On mount, the RDP viewer uses the container's pixel dimensions for initial `size`
- On window resize, a new `size` instruction is sent to guacd
- The Guacamole JS client (`guacamole-common-js`) handles rendering in a `<canvas>`
- Mouse events are captured by the Guacamole mouse handler
- Keyboard events are captured by the Guacamole keyboard handler
- Text input uses the Guacamole clipboard/input mechanisms

## Dependencies

- **Backend**: None — the bridge uses PHP's built-in socket functions to communicate with guacd
- **Frontend**: `guacamole-common-js` npm package (Apache 2.0 license, 0 dependencies)
- **Infrastructure**: `guacamole/guacd` Docker image (Apache 2.0 license, ~15MB)

## Future Considerations

- **Active Directory / Domain users**: Add an optional `domain` field to the server model for `DOMAIN\username` RDP logins
- **Certificate validation**: Add option to specify RDP certificate fingerprint
- **Multiple monitors**: Support multiple display channels
- **Audio**: Guacamole supports audio input/output — could enable microphone/speaker passthrough
- **File transfer**: Guacamole supports drag-and-drop file transfer via RDP drive redirection
- **Clipboard sync**: Enable bidirectional clipboard between browser and remote desktop
