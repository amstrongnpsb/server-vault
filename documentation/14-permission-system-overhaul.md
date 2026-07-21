# Permission System Overhaul

## Problem

Permissions are defined but largely unenforced. Of 13 permissions, only 2 (`connect servers`, `create servers`) are actually checked — and even those are inconsistent. The frontend has zero permission awareness. Any authenticated user can access any feature.

**See `documentation/13-duplicate-server.md` for the full audit.**

---

## Goals

1. **Every route/action is guarded** by the appropriate permission
2. **Frontend hides actions** the user cannot perform
3. **Single source of truth** — permissions defined once, enforced in 3 layers (route, controller, frontend)
4. **Ownership-based access** for server resources (user can only edit/delete their own servers, superadmin/admin can edit/delete any)

---

## Prerequisite: Add `user_id` to servers table

A new migration adds `user_id` (nullable FK → users). The controller sets it automatically on create/duplicate.

```php
$table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
```

## Three-Layer Enforcement Model

```
Route Middleware  →  prevents unauthorized page loads & URL access
Controller/Policy →  prevents unauthorized state changes (defense in depth)
Frontend (v-if)   →  prevents confusion (hides buttons the user can't use)
```

---

## Changes Required

### Layer 1: Route Middleware

Add Spatie middleware aliases in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
    ]);
})
```

Then guard routes with `->middleware(['permission:...'])`:

| Route | Middleware |
|-------|-----------|
| `GET /servers` | `permission:view servers` |
| `POST /servers` | `permission:create servers` |
| `PUT /servers/{server}` | `permission:edit servers` |
| `DELETE /servers/{server}` | `permission:delete servers` |
| `POST /servers/{server}/duplicate` | `permission:create servers` |
| `POST /servers/{server}/check` | `permission:check server health` |
| `GET /servers/{server}/terminal` | `permission:connect servers` |
| `POST /servers/{server}/connect` | `permission:connect servers` |
| `POST /ssh/disconnect` | `permission:connect servers` |
| `GET /users` | `permission:manage users` |
| `POST /users` | `permission:manage users` |
| `PUT /users/{user}` | `permission:manage users` |
| `DELETE /users/{user}` | `permission:manage users` |
| `POST /servers/{server}/databases` | `permission:edit servers` |
| `PUT /databases/{serverDatabase}` | `permission:edit servers` |
| `DELETE /databases/{serverDatabase}` | `permission:edit servers` |
| `POST /servers/{server}/services` | `permission:edit servers` |
| `PUT /services/{serverService}` | `permission:edit servers` |
| `DELETE /services/{serverService}` | `permission:edit servers` |
| `POST /credentials/reveal` | `permission:edit servers` |

The auth + verified middleware group already wraps all these routes — add `permission:*` alongside them:

```php
Route::middleware(['auth', 'verified', 'permission:view servers'])->group(function () {
    Route::get('/servers', ...);
    // ...
});
```

For routes with varying permissions, use per-route middleware or a group with `withoutMiddleware` for exceptions.

### Layer 2: Controller / Policy

**ServerPolicy** — add missing methods:

```php
class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view servers');
    }

    public function view(User $user, Server $server): bool
    {
        return $user->can('view servers');
    }

    public function create(User $user): bool
    {
        return $user->can('create servers');
    }

    public function update(User $user, Server $server): bool
    {
        return $user->can('edit servers') && $this->ownsOrAdmin($user, $server);
    }

    public function delete(User $user, Server $server): bool
    {
        return $user->can('delete servers') && $this->ownsOrAdmin($user, $server);
    }

    public function connect(User $user, Server $server): bool
    {
        return $user->can('connect servers');
    }

    public function checkHealth(User $user, Server $server): bool
    {
        return $user->can('check server health');
    }

    private function ownsOrAdmin(User $user, Server $server): bool
    {
        return $user->id === $server->user_id || $user->hasRole('superadmin') || $user->hasRole('admin');
    }
}
```

**Form Requests** — update authorization to delegate to policy:

```php
// StoreServerRequest
public function authorize(): bool
{
    return $this->user()->can('create', Server::class);
}

// UpdateServerRequest
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('server'));
}

// StoreUserRequest / UpdateUserRequest
public function authorize(): bool
{
    return $this->user()->can('manage users');
}
```

Add `$this->authorize()` calls in controllers where form requests aren't used:

- `ServerController::checkHealth` → `$this->authorize('checkHealth', $server)`
- `ServerController::revealCredential` → `$this->authorize('update', $server)` or granular check
- `ServerDatabaseController::store/update/destroy` → `$this->authorize('update', $server)` or `$this->authorize('edit servers')`
- `ServerServiceController::store/update/destroy` → `$this->authorize('update', $server)` or `$this->authorize('edit servers')`
- `UserController::index/store/update/destroy` → `$this->authorize('manage users')`

### Layer 3: Frontend

#### 1. Share permissions via Inertia

In `app/Http/Middleware/HandleInertiaRequests.php`:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
            'can' => $request->user() ? $this->getPermissions($request->user()) : [],
        ],
    ];
}

private function getPermissions(User $user): array
{
    return $user->getAllPermissions()->pluck('name')->toArray();
}
```

#### 2. Access in Vue via `usePage`

```js
import { usePage } from '@inertiajs/vue3';

const can = computed(() => usePage().props.auth.can ?? []);
const hasPermission = (perm) => can.value.includes(perm);
```

#### 3. Guard navigation in `AuthenticatedLayout.vue`

```vue
<NavItem v-if="hasPermission('view servers')" :href="route('servers.index')" />
<NavItem v-if="hasPermission('manage users')" :href="route('users.index')" />
```

#### 4. Guard action buttons in page components

**Servers/Index.vue:**
```vue
<Button v-if="hasPermission('create servers')" @click="openCreateModal">
    Add server
</Button>

<!-- Inside dropdown -->
<DropdownMenuItem v-if="hasPermission('edit servers')" @click="openEditModal(server)">
    Edit
</DropdownMenuItem>
<DropdownMenuItem v-if="hasPermission('delete servers')" @click="openDeleteDialog(server)">
    Delete
</DropdownMenuItem>
<DropdownMenuItem v-if="hasPermission('connect servers')" as-child>
    <Link :href="route('servers.terminal', server.id)">Connect</Link>
</DropdownMenuItem>
<DropdownMenuItem v-if="hasPermission('create servers')" @click="duplicateServer(server)">
    Duplicate
</DropdownMenuItem>
<button v-if="hasPermission('check server health')" @click="checkHealth(server)">
    Check Health
</button>
```

**Users/Index.vue:**
```vue
<Button v-if="hasPermission('manage users')" @click="openCreateModal">
    Add user
</Button>
<DropdownMenuItem v-if="hasPermission('manage users')" @click="openEditModal(user)">
    Edit
</DropdownMenuItem>
<DropdownMenuItem v-if="hasPermission('manage users')" @click="openDeleteDialog(user)">
    Delete
</DropdownMenuItem>
```

#### 5. Create a composable for convenience

`resources/js/composables/usePermission.js`:

```js
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermission() {
    const can = computed(() => usePage().props.auth.can ?? []);

    const hasPermission = (permission) => can.value.includes(permission);

    return { can, hasPermission };
}
```

---

## Files to Modify

| File | Action |
|------|--------|
| `database/migrations/2026_07_21_230842_add_user_id_to_servers_table.php` | Add `user_id` column (created) |
| `app/Http/Controllers/ServerController.php` | Set `user_id` on `store()` and `duplicate()` (done) |
| `bootstrap/app.php` | Register Spatie middleware aliases |
| `routes/web.php` | Add `permission:*` middleware to route groups |
| `app/Policies/ServerPolicy.php` | Add `viewAny`, `view`, `update`, `delete`, `checkHealth` methods; add ownership logic |
| `app/Http/Requests/StoreServerRequest.php` | Add proper `authorize()` |
| `app/Http/Requests/UpdateServerRequest.php` | Add proper `authorize()` |
| `app/Http/Requests/StoreUserRequest.php` | Add proper `authorize()` |
| `app/Http/Requests/UpdateUserRequest.php` | Add proper `authorize()` |
| `app/Http/Controllers/ServerController.php` | Remove redundant `$this->authorize()` (now handled by middleware/request), add to `checkHealth` and `revealCredential` |
| `app/Http/Controllers/UserController.php` | Add `$this->authorize('manage users')` |
| `app/Http/Controllers/ServerDatabaseController.php` | Add authorization |
| `app/Http/Controllers/ServerServiceController.php` | Add authorization |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share permissions to frontend |
| `resources/js/composables/usePermission.js` | Create new composable |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Guard nav items with `v-if="hasPermission(...)"` |
| `resources/js/Pages/Servers/Index.vue` | Guard action buttons with `v-if` |
| `resources/js/Pages/Users/Index.vue` | Guard action buttons with `v-if` |

---

## Notes

- Route middleware is the **primary gate** — it prevents unauthorized users from even loading the page or hitting the URL.
- Policy/Request checks are **defense in depth** — they catch cases where route middleware might be misconfigured.
- Frontend guards are **purely UX** — they prevent confusing disabled buttons or 403 errors when clicking actions.
- Ownership-based access (`ownsOrAdmin`) prevents users from editing/deleting servers they don't own, while superadmin/admin can manage all.
- The `manage users` permission covers all user CRUD operations (no need for granular `create users` / `edit users` / `delete users`).
- For databases and services, `edit servers` is used since they are sub-resources of a server.
- The `manage roles` and `manage permissions` permissions are reserved for a future role/permission management UI.
- `export data` and `view reports` are reserved for future reporting features.
- `duplicate servers` is not a separate permission — it reuses `create servers` since duplication is a form of creation.
