# ServerVault

ServerVault is a web-based server management app built with Laravel, Vue, and Inertia.

The goal of the app is to help users manage their servers in one place. Users can sign in, organize server records, manage access through roles and permissions, and eventually connect to servers directly from the browser.

## Main Features

- User authentication
- Email verification
- Profile management
- Role and permission management
- Server inventory management
- Secure credential storage
- Browser-based SSH terminal
- Server health monitoring
- Audit logs for important activity

## Tech Stack

- Laravel
- Vue 3
- Inertia.js
- Tailwind CSS
- MySQL or PostgreSQL
- Redis
- Laravel Reverb
- phpseclib
- xterm.js
- Docker

## Quick Start with Docker

### Development (Hybrid - Fast on Windows):
```bash
# Terminal 1: Docker backend
npm run docker:dev:build  # First time
npm run artisan:key
npm run artisan:migrate

# Terminal 2: Vite on Windows
npm install  # First time
npm run dev

# Daily: Just run both terminals
npm run docker:dev  # Terminal 1
npm run dev         # Terminal 2
```

### Production (Full Docker):
```bash
# Build and deploy
npm run docker:prod:build
npm run artisan:prod:migrate
npm run artisan:prod:optimize
```

**Docker Documentation:**
- **📘 Complete Docker Guide**: `documentation/08-docker-guide.md` *(Simple & direct guide - Updated July 2026)*

## Status

ServerVault is currently in development. The authentication foundation, roles, permissions, and dashboard structure are being prepared before building the main server management features.
