# Duplicate Server Feature

## Overview

Allow users to duplicate an existing server entry. Duplication copies the server's configuration fields (name, host, port, username, os, credentials) into a new record. **Relations (databases, services) are NOT duplicated.**

The duplicated server name is appended with `-duplicate` (e.g., `server-01` → `server-01-duplicate`). If the resulting name already exists, increment a counter suffix (e.g., `server-01-duplicate-2`).

---

## Changes Required

### Backend

#### 1. Route

Add a `POST` route in `routes/web.php`:

```php
Route::post('/servers/{server}/duplicate', [ServerController::class, 'duplicate'])
    ->name('servers.duplicate');
```

Place it inside the `auth` + `verified` + permission middleware group alongside existing server routes.

#### 2. Permission

Add permission `duplicate servers` in `config/permission.php`:

```php
'duplicate servers',
```

Assign to `superadmin` and `admin` roles in `RoleSeeder`.

#### 3. Controller Method

Add `duplicate()` method to `ServerController`:

- Authorize: `$this->authorize('create', Server::class)`
- Load the source server with credentials
- Generate unique name (see naming logic below)
- Create new server with copied attributes
- Encrypt copied credentials via `Crypt::encryptString`
- Return Inertia redirect back with success toast

**Naming logic:**

```php
$baseName = $server->name . '-duplicate';
$newName = $baseName;
$counter = 2;
while (Server::where('name', $newName)->exists()) {
    $newName = $baseName . '-' . $counter;
    $counter++;
}
```

#### 4. Policy (optional)

If using a policy, add `duplicate` method or reuse `create`. The route controller uses `$this->authorize('create', Server::class)`.

### Frontend

#### 1. Dropdown Menu Item

In `Servers/Index.vue`, add a "Duplicate" dropdown item inside the existing `DropdownMenu`, between "Connect" and "Edit":

```vue
<DropdownMenuItem @click="duplicateServer(server)">
    <Copy class="mr-2 h-4 w-4" />
    Duplicate
</DropdownMenuItem>
```

Import `Copy` from `lucide-vue-next`.

#### 2. Duplicate Handler

Add method:

```js
const duplicatedServers = ref(new Set());

const duplicateServer = (server) => {
    if (duplicatedServers.value.has(server.id)) return;
    duplicatedServers.value.add(server.id);
    router.post(route('servers.duplicate', server.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Server duplicated successfully');
            duplicatedServers.value.delete(server.id);
        },
        onError: (errors) => {
            toast.error(errors.message || 'Failed to duplicate server');
            duplicatedServers.value.delete(server.id);
        },
        onFinish: () => duplicatedServers.value.delete(server.id),
    });
};
```

#### 3. Toast Notification

On success: `toast.success('Server duplicated successfully')`.
On error: `toast.error('Failed to duplicate server')`.

---

## Files to Modify

| File | Action |
|------|--------|
| `routes/web.php` | Add `servers.duplicate` POST route |
| `app/Http/Controllers/ServerController.php` | Add `duplicate()` method |
| `config/permission.php` | Add `duplicate servers` permission |
| `database/seeders/RoleSeeder.php` | Assign permission to superadmin + admin |
| `resources/js/Pages/Servers/Index.vue` | Add Duplicate dropdown item + handler |

---

## Data Flow

```
User clicks "Duplicate" in dropdown
  → router.post(route('servers.duplicate', server.id))
    → ServerController::duplicate()
      → authorize('create', Server::class)
      → load source server with credentials
      → decrypt credentials
      → generate unique name
      → create new server record with encrypted credentials
      → redirect back with success
    → toast.success shown in browser
```

---

## Notes

- Credentials are decrypted from the source, then re-encrypted for the new server
- The `-duplicate` suffix is applied to the server name only; host, port, username, os are copied verbatim
- No databases, services, or other relations are copied
- Permission `duplicate servers` is checked via the existing `create` ability
