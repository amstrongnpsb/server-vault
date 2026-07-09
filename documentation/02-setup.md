# ServerVault - Setup Dependencies

This document explains how to install the dependencies needed for ServerVault based on `01-introduction.md`.

ServerVault uses Laravel 12, PHP 8.2, Inertia.js, Vue 3, Vite 8, Tailwind CSS 4, shadcn-vue, motion-v, Laravel Reverb, phpseclib, MySQL or PostgreSQL, and Redis.

---

## 1. Required Tools

Install these tools first:

- PHP 8.2 or newer
- Composer 2
- Node.js 22 LTS
- npm
- MySQL 8 or PostgreSQL 17
- Redis 7
- Git
- Docker, optional

Check the installed versions:

```bash
php -v
composer -V
node -v
npm -v
git --version
```

Check database tools:

```bash
mysql --version
psql --version
redis-server --version
```

If you use Docker for MySQL and Redis, the local `mysql`, `psql`, or `redis-server` commands are optional.

---

## 2. Create Laravel Project

Create the Laravel project:

```bash
composer create-project laravel/laravel server-vault
```

Enter the project folder:

```bash
cd server-vault
```

Generate the application key:

```bash
php artisan key:generate
```

---

## 3. Install Laravel Vue Starter Kit

ServerVault uses Laravel with Inertia.js and Vue, so install the Laravel starter kit:

```bash
composer require laravel/breeze --dev
```

Install Breeze with Vue and Inertia:

```bash
php artisan breeze:install vue
```

Install frontend dependencies:

```bash
npm install
```

Run database migrations:

```bash
php artisan migrate
```

This gives the project:

- Login
- Register
- Password reset
- Email verification support
- Inertia.js
- Vue 3
- Vite
- Tailwind CSS

---

## 4. Configure Environment

Copy the environment file if needed:

```bash
cp .env.example .env
```

Update `.env`:

```env
APP_NAME=ServerVault
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=server_vault
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
BROADCAST_CONNECTION=reverb

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Create a MySQL database:

```sql
CREATE DATABASE server_vault;
```

Then run:

```bash
php artisan migrate
```

---

## 5. PostgreSQL Alternative

If using PostgreSQL instead of MySQL, update `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=server_vault
DB_USERNAME=postgres
DB_PASSWORD=
```

Create the PostgreSQL database:

```sql
CREATE DATABASE server_vault;
```

Run migrations:

```bash
php artisan migrate
```

---

## 6. Install Laravel Reverb

Laravel Reverb will be used for real-time terminal streaming.

Install Reverb:

```bash
composer require laravel/reverb
```

Install Reverb configuration:

```bash
php artisan reverb:install
```

Confirm these values in `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=servervault
REVERB_APP_KEY=servervault-local-key
REVERB_APP_SECRET=servervault-local-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Start Reverb:

```bash
php artisan reverb:start
```

---

## 7. Install SSH Dependency

ServerVault uses phpseclib 3 to connect to Linux servers through SSH.

Install it with Composer:

```bash
composer require phpseclib/phpseclib
```

This package will be used for:

- SSH authentication with password
- SSH authentication with private key
- Running remote Linux commands
- Building browser-based terminal sessions

---

## 8. Install Redis PHP Extension

Laravel can use Redis through the PHP Redis extension.

On Linux:

```bash
sudo apt install php-redis
```

On Windows, the easiest option is usually Laravel Herd, Laragon, or Docker.

If the PHP Redis extension is not available, install Predis instead:

```bash
composer require predis/predis
```

Then update `.env`:

```env
REDIS_CLIENT=predis
```

---

## 9. Install Frontend Packages

Install the frontend dependencies needed by the app:

```bash
npm install
```

Install Pinia:

```bash
npm install pinia
```

Install Vue Router:

```bash
npm install vue-router@4
```

Install Axios:

```bash
npm install axios
```

Install xterm.js:

```bash
npm install @xterm/xterm @xterm/addon-fit
```

Install motion-v:

```bash
npm install motion-v
```

Install icons:

```bash
npm install lucide-vue-next
```

---

## 10. Install shadcn-vue

ServerVault can use shadcn-vue for pre-built UI components.

Initialize shadcn-vue:

```bash
npx shadcn-vue@latest init
```

Add common components:

```bash
npx shadcn-vue@latest add button
npx shadcn-vue@latest add input
npx shadcn-vue@latest add label
npx shadcn-vue@latest add card
npx shadcn-vue@latest add table
npx shadcn-vue@latest add badge
npx shadcn-vue@latest add dialog
npx shadcn-vue@latest add dropdown-menu
npx shadcn-vue@latest add select
npx shadcn-vue@latest add tabs
npx shadcn-vue@latest add textarea
npx shadcn-vue@latest add toast
```

Recommended components for ServerVault:

- Button
- Input
- Label
- Card
- Table
- Badge
- Dialog
- Dropdown menu
- Select
- Tabs
- Textarea
- Toast

---

## 11. Configure Pinia

Open `resources/js/app.js` and register Pinia:

```js
import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';

const appName = import.meta.env.VITE_APP_NAME || 'ServerVault';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
```

---

## 12. Docker Setup for MySQL and Redis

If you do not want to install MySQL and Redis directly, use Docker.

Run MySQL 8:

```bash
docker run --name servervault-mysql -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=server_vault -p 3306:3306 -d mysql:8
```

Run Redis 7:

```bash
docker run --name servervault-redis -p 6379:6379 -d redis:7
```

Use this `.env` database config:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=server_vault
DB_USERNAME=root
DB_PASSWORD=root
```

---

## 13. Run the App

Open separate terminal windows.

Start Laravel:

```bash
php artisan serve
```

Start Vite:

```bash
npm run dev
```

Start Reverb:

```bash
php artisan reverb:start
```

Start queue worker:

```bash
php artisan queue:work
```

Default local URLs:

```text
Laravel: http://127.0.0.1:8000
Vite:    http://127.0.0.1:5173
Reverb:  http://127.0.0.1:8080
```

---

## 14. Verification Checklist

Before building features, confirm:

- `php artisan serve` works
- `npm run dev` works
- `php artisan migrate` works
- Login and register pages are available
- Redis is running
- `php artisan queue:work` starts
- `php artisan reverb:start` starts
- phpseclib is installed
- xterm.js is installed
- shadcn-vue components are installed
- motion-v is installed

---

## 15. Next Step

After setup, continue with the database design for:

- Servers
- Server credentials
- Remote sessions
- Health checks
- Audit logs

