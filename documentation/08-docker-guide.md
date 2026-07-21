# ServerVault - Docker Guide

Quick guide to run ServerVault with Docker. **Development uses hybrid approach** (Docker backend + native Vite) for fast performance on Windows.

---

## Quick Start

### First Time Setup

```bash
# 1. Copy environment
cp .env.example .env

# 2. Start Docker
npm run docker:dev:build

# 3. Setup Laravel
npm run artisan:key
npm run artisan:migrate
npm run artisan:seed

# 4. Start Vite (separate terminal)
npm install
npm run dev

# Access: http://localhost:9002
```

### Daily Development

```bash
# Terminal 1: Docker backend
npm run docker:dev

# Terminal 2: Vite (fast hot reload)
npm run dev
```

---

## Why Hybrid Development?

**Docker** runs: PHP, Nginx, MySQL, Redis, Queue, Reverb, SSH Bridge, Scheduler  
**Native** runs: Vite dev server

**Reason:** Docker on Windows is slow for file watching. Running Vite natively = instant hot reload.

---

## Essential Commands

### Docker Control
```bash
npm run docker:dev              # Start development
npm run docker:dev:down         # Stop development
npm run docker:dev:logs         # View logs
npm run docker:dev:restart      # Restart containers
npm run docker:ps               # Check container status
```

### Laravel Commands
```bash
npm run artisan:migrate         # Run migrations
npm run artisan:cache:clear     # Clear all caches
npm run docker:dev:shell        # Access container shell
```

### Database & Cache
```bash
npm run db:mysql                # Access MySQL
npm run redis:cli               # Access Redis
```

---

## Configuration

### Environment Variables

**Important:** Use Docker service names, not `localhost`:

```env
# ✅ Correct
DB_HOST=db
REDIS_HOST=redis

# ❌ Wrong
DB_HOST=localhost
REDIS_HOST=127.0.0.1
```

### SSH Bridge Configuration

The SSH terminal bridge runs as a separate service and is proxied through nginx at `/terminal-ws`:
- **Dev:** `ssh-bridge` container, proxied via nginx → `ws://localhost:9002/terminal-ws`
- **Prod:** `ssh-bridge` container, proxied via nginx at `/terminal-ws`

Required `.env` variable:
```env
SSH_BRIDGE_INTERNAL_SECRET=your-secret-key    # Must match config/services.php
SSH_BRIDGE_WS_URL=ws://localhost:9002/terminal-ws  # Frontend WebSocket URL
```

### Broadcast / Reverb Configuration

The queue worker needs to know how to reach the Reverb server to broadcast events:

```env
# Dev (inside Docker network)
REVERB_HOST=reverb          # Docker service name, not localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Production
REVERB_HOST=reverb          # Docker service name
REVERB_PORT=8080
REVERB_SCHEME=https         # Use https if behind TLS
```

The `queue` and `scheduler` containers use `REVERB_HOST=reverb` (service name) while the frontend uses `VITE_REVERB_HOST` for browser connections via nginx at `/ws`.

### Change Application Port

Edit `.env`:
```env
APP_PORT=9002                    # Change to any port
APP_URL=http://localhost:9002
```

Restart:
```bash
npm run docker:dev:down
npm run docker:dev
```

---

## Common Tasks

### After Pulling Code
```bash
npm run composer:install        # If composer.json changed
npm install                     # If package.json changed
npm run artisan:migrate         # Run new migrations
npm run artisan:cache:clear     # Clear caches
```

### Fresh Database
```bash
npm run artisan:migrate:fresh
npm run artisan:seed
```

### Install New Package
```bash
# PHP package
docker-compose exec app composer require vendor/package
npm run docker:dev:restart

# Frontend package
npm install package-name
```

---

## Troubleshooting

### Container Won't Start
```bash
npm run docker:ps               # Check status
npm run docker:dev:logs         # View errors
npm run docker:dev:down         # Stop
npm run docker:dev:build        # Rebuild
```

### Database Connection Failed
Check `.env`:
```env
DB_HOST=db          # Must be 'db', not 'localhost'
```

### Permission Errors
```bash
npm run docker:dev:shell
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
exit
```

### Port Already in Use
Change `APP_PORT` in `.env` to different port (e.g., 9003)

### Everything is Broken
```bash
npm run docker:clean            # ⚠️ Deletes all data
npm run docker:dev:build
npm run artisan:key
npm run artisan:migrate
```

---

## Production Deployment

### Initial Setup

```bash
# 1. Create production environment
cp .env.production.example .env.production

# 2. Edit .env.production - CHANGE THESE:
APP_URL=https://yourdomain.com
DB_PASSWORD=strong_random_password_here
REDIS_PASSWORD=strong_random_password_here
REVERB_APP_ID=unique_id_here     # e.g., 123456
REVERB_APP_KEY=unique_key_here
REVERB_APP_SECRET=unique_secret_here
REVERB_HOST=yourdomain.com
REVERB_SCHEME=https
SSH_BRIDGE_INTERNAL_SECRET=your_secret_here

# 3. Build and deploy
npm run docker:prod:build

# 4. Setup Laravel
docker-compose -f docker-compose.prod.yml exec app php artisan key:generate
npm run artisan:prod:migrate
docker-compose -f docker-compose.prod.yml exec app php artisan db:seed --class=RoleSeeder
npm run artisan:prod:optimize
```

### Updating Production

```bash
git pull
npm run docker:prod:build
npm run artisan:prod:migrate
docker-compose -f docker-compose.prod.yml exec app php artisan db:seed --class=RoleSeeder
npm run artisan:prod:optimize
```

### Production vs Development

| | Development | Production |
|---|---|---|
| Containers | 8 separate | 7 combined |
| Vite | Native (hot reload) | Pre-built assets |
| Opcache | Off | On |
| Debug | On | Off |
| Ports | Exposed | Internal only |

---

## Docker Services

### Development Containers

- **app** - PHP 8.2-FPM
- **nginx** - Web server (port 9002)
- **db** - MySQL 8 (port 3306)
- **redis** - Redis 7 (port 6379)
- **reverb** - WebSocket server (port 8080)
- **queue** - Background jobs
- **scheduler** - Runs `php artisan schedule:work` for cron tasks (health checks)
- **ssh-bridge** - SSH terminal bridge (port 8090, proxied via nginx at `/terminal-ws`)

### Production Containers

- **app** - Nginx + PHP-FPM combined (port 80)
- **reverb** - WebSocket server (port 8080)
- **queue** - Background jobs
- **scheduler** - Runs `php artisan schedule:work` for cron tasks (health checks)
- **db** - MySQL 8 (internal)
- **redis** - Redis 7 (internal)
- **ssh-bridge** - SSH terminal bridge (proxied via nginx at `/terminal-ws`)

---

## Tips

✅ **Do:**
- Keep Docker running during development
- Use `npm run docker:dev:logs` to debug
- Run `npm run artisan:cache:clear` when stuck
- Use Docker service names (`db`, `redis`) in `.env`

❌ **Don't:**
- Use `localhost` for `DB_HOST` or `REDIS_HOST`
- Commit `.env` or `.env.production`
- Stop/start Docker constantly (slow on Windows)
- Forget to change production passwords

---

## Quick Reference

```bash
# Start development
npm run docker:dev              # Terminal 1
npm run dev                     # Terminal 2

# Stop development
npm run docker:dev:down

# View logs
npm run docker:dev:logs

# Check status
npm run docker:ps

# Access container
npm run docker:dev:shell

# Clear cache
npm run artisan:cache:clear

# Database
npm run db:mysql

# Fresh start
npm run docker:clean            # ⚠️ Deletes data
npm run docker:dev:build
```

---

**Need more details?** Check Laravel docs: https://laravel.com/docs

**Last Updated:** July 2026
