# ServerVault

Web-based server management app built with **Laravel 12**, **Vue 3**, and **Inertia.js**. Manage servers, credentials, SSH terminals, and RDP sessions from one place.

## Features

### Server Inventory
- Add, edit, duplicate, and delete server records
- Track host, port, OS, username, and credentials per server
- Server health monitoring with status indicators
- Search/filter servers by name, host, or description
- Detailed server view with databases and services tabs

### Credential Management
- **Encrypted storage** — credentials are encrypted at rest, decrypted only on-demand
- **Reveal/Copy** — view or copy passwords with one click (per-session, never logged)
- Granular permission control over who can view credentials

### Browser-Based SSH Terminal
- Connect via WebSocket through a PHP bridge (`bridge/server.php`)
- Full terminal emulation using **xterm.js** + **phpseclib 3**
- Session management — connect/disconnect without leaving the app

### Browser-Based RDP
- Connect to Windows servers via **Apache Guacamole** (`guacd`) through a custom PHP WebSocket bridge (`bridge/guacd-proxy.php`)
- NLA (Network Level Authentication) support
- Auto-scaling display that fits your browser viewport
- Remote cursor tracking with mouse coordinate scaling
- Clipboard sync between local and remote session
- Keyboard input forwarded to the remote session

### Role-Based Access Control (RBAC)
- Roles: `superadmin` (full access), `admin`, `user`
- Granular permissions per feature (view, create, edit, delete servers, etc.)
- Manage users, roles, and permissions through the web UI

### Other
- User authentication with email verification
- Profile management
- Audit logs for important activity
- Dark/light theme toggle (persisted in localStorage)

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Vue 3 (Composition API), Inertia.js, Tailwind CSS 3 |
| UI Components | shadcn-vue, Reka UI, Lucide icons |
| Database | MySQL 8 / PostgreSQL 17 |
| Cache/Queue | Redis 7 |
| Real-time | Laravel Reverb (WebSocket) |
| SSH | phpseclib 3, xterm.js |
| RDP | Apache Guacamole (`guacd`), custom PHP bridge |
| Container | Docker, Docker Compose |

## Quick Start

### Setup
```bash
cp .env.example .env
# Edit .env with your database credentials and secrets

# Start Docker services
npm run docker:dev:build

# Run migrations and seeders
npm run artisan:key
npm run artisan:migrate
npm run artisan:seed

# Start Vite dev server (separate terminal, faster on Windows)
npm install
npm run dev
```

**Access:** [http://localhost:9002](http://localhost:9002)

### Default Login
```
Email:    superadmin@test.com
Password: superadmin123
```

### Daily Development
```bash
npm run docker:dev      # Terminal 1: Docker backend
npm run dev             # Terminal 2: Vite hot-reload
```

### Production Build
```bash
npm run docker:prod:build
npm run artisan:prod:migrate
npm run artisan:prod:optimize
```

## Environment Variables

Key variables in `.env`:

| Variable | Purpose |
|---|---|
| `APP_URL` | Application URL (used for internal callbacks) |
| `DB_HOST` | Database host (`db` in Docker) |
| `SSH_BRIDGE_INTERNAL_SECRET` | Shared secret for SSH/RDP bridge authentication |
| `GUACD_BRIDGE_WS_URL` | RDP WebSocket URL reference |
| `REDIS_HOST` | Redis host (`redis` in Docker) |

## Project Structure

```
app/
├── Http/
│   ├── Controllers/       # RdpController, ServerController, InternalRdpController, etc.
│   ├── Requests/          # Form validation (StoreServerRequest, etc.)
├── Models/                # Server, RdpSession, User, etc.

resources/js/
├── Pages/Servers/         # Index.vue, RdpViewer.vue
│   ├── Modals/            # ServerModal, ServerDetailModal, DatabaseModal, ServiceModal
├── Components/            # Reusable Vue components
│   ├── ui/                # shadcn-vue components
├── Layouts/               # AuthenticatedLayout, GuestLayout
├── composables/           # Vue composables
├── stores/                # Pinia stores

bridge/
├── guacd-proxy.php        # PHP WebSocket tunnel for RDP (Guacamole protocol)
├── server.php             # PHP WebSocket tunnel for SSH

docker/
├── nginx/                 # Dev and production nginx configs
├── php/                   # PHP configuration

documentation/
├── bugfix/                # Protocol reference and bugfix docs
├── 08-docker-guide.md     # Complete Docker setup guide
```

## Documentation

- **Docker Guide**: `documentation/08-docker-guide.md` — full setup, config, and troubleshooting

## License

Proprietary — internal use.
