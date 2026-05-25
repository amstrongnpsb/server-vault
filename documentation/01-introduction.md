# ServerVault — Tech Stack

## What is ServerVault?

ServerVault is a web-based server management app. It lets you store and organize your server list, manage credentials, and remotely access them directly from the browser — whether they run Ubuntu, Debian, CentOS, or Windows Server. Think of it as your personal server control panel, with a built-in terminal, real-time status monitoring, and a secure credential vault.

---

## Backend

- **Laravel 12** — PHP framework, handles routing, auth, and SSH proxy
- **PHP 8.2**
- **Inertia.js (server-side)** — renders Vue pages directly from Laravel controllers, no separate API needed
- **Laravel Reverb** — WebSocket server for real-time terminal streaming
- **phpseclib 3** — SSH connection library
- **MySQL 8** — primary database
- **Redis 7** — queue and caching

## Frontend

- **Vue 3** — UI framework (Composition API)
- **Inertia.js** — connects Laravel backend to Vue frontend without a separate API layer
- **Vite 8** — build tool and dev server
- **Vue Router 4** — client-side routing
- **Pinia 2** — state management
- **Tailwind CSS 4** — styling
- **shadcn-vue** — pre-built UI components (comes with the Laravel 12 Vue starter kit)
- **motion-v** — animation library (Vue port of Framer Motion) for page transitions, hover effects, and scroll animations
- **Axios** — HTTP client
- **xterm.js** — browser-based SSH terminal

## Requirements

- PHP 8.2
- Composer 2
- Node.js 22 LTS
- MySQL 8 or PostgreSQL 17
- Redis 7
- Docker (optional)
