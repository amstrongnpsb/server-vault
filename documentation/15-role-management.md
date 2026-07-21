# Role Management Feature

## Overview

Add a role management page where superadmin can create, edit, and delete roles, and assign which permissions each role has. This manages the `roles` and `role_has_permissions` tables via Spatie Laravel Permission.

---

## Backend

### 1. Migration

Add `display_name` and `description` columns to the `roles` table for better UX:

```php
Schema::table('roles', function (Blueprint $table) {
    $table->string('display_name')->nullable()->after('name');
    $table->text('description')->nullable()->after('display_name');
});
```

### 2. Controller

Create `app/Http/Controllers/RoleController.php`:

| Method | Action |
|--------|--------|
| `index()` | List all roles with their permission counts |
| `store(RoleRequest)` | Create role with selected permissions |
| `update(RoleRequest, Role $role)` | Update role name/description and sync permissions |
| `destroy(Role $role)` | Delete role (prevent deleting superadmin) |

**Authorization:** All methods check `permission:manage roles` (route middleware).

Do NOT allow deleting the `superadmin` role or removing the `manage roles` permission from the `superadmin` role itself (to prevent lockout).

### 3. Form Request

Create `app/Http/Requests/RoleRequest.php`:

```php
public function authorize(): bool
{
    return $this->user()?->can('manage roles') ?? false;
}

public function rules(): array
{
    return [
        'name' => 'required|string|max:255|unique:roles,name,' . $this->route('role')?->id,
        'display_name' => 'nullable|string|max:255',
        'description' => 'nullable|string|max:1000',
        'permissions' => 'nullable|array',
        'permissions.*' => 'string|exists:permissions,name',
    ];
}
```

### 4. Route

Add to `routes/web.php` inside the `['auth', 'verified']` group:

```php
Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:manage roles');
Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:manage roles');
Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:manage roles');
Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:manage roles');
```

### 5. Model (optional)

If custom columns are added to the roles table, ensure `Role` model fillable includes them:

```php
// app/Models/Role.php (if created)
protected $fillable = ['name', 'display_name', 'description', 'guard_name'];
```

Spatie's `Role` model can be extended if needed.

---

## Frontend

### 1. Page

`resources/js/Pages/Roles/Index.vue` — role list with table:

- Name
- Display name
- Permission count
- Actions (Edit, Delete)

### 2. Modal

`resources/js/Pages/Roles/Modals/RoleModal.vue` — create/edit form:

- **Name** (text input, slugified automatically from display name or manual)
- **Display name** (text input)
- **Description** (textarea)
- **Permissions** (grid of checkboxes grouped by resource)

### 3. Permission Checkbox Grid

Display permissions grouped by prefix (e.g., `view servers`, `create servers`, `edit servers`, `delete servers` → group "Servers"):

```vue
<div v-for="(perms, group) in groupedPermissions" :key="group">
    <h3>{{ group }}</h3>
    <div v-for="perm in perms" :key="perm">
        <Checkbox v-model="selectedPermissions" :value="perm" />
        <label>{{ perm }}</label>
    </div>
</div>
```

### 4. Permissions shared via Inertia

Add to `HandleInertiaRequests.php` or pass from controller:

```php
// RoleController@index
return Inertia::render('Roles/Index', [
    'roles' => Role::withCount('permissions')->get(),
    'permissions' => Permission::all()->pluck('name')->toArray(),
    'rolePermissions' => $role->permissions->pluck('name')->toArray(), // for edit
]);
```

### 5. Sidebar Nav

Add to `AuthenticatedLayout.vue` menu items:

```js
{
    title: "Roles",
    routeName: "roles.index",
    icon: Shield,
    activePattern: "roles.*",
    permission: "manage roles",
}
```

Import `Shield` from `lucide-vue-next`.

### 6. Delete Protection

- Prevent deleting `superadmin` role on backend
- Disable delete button for `superadmin` in the frontend
- Show a warning toast when trying to delete a protected role

---

## Files to Create

| File | Description |
|------|-------------|
| `app/Http/Controllers/RoleController.php` | Controller with CRUD |
| `app/Http/Requests/RoleRequest.php` | Form request with validation + authorization |
| `resources/js/Pages/Roles/Index.vue` | Role list page |
| `resources/js/Pages/Roles/Modals/RoleModal.vue` | Create/edit role modal |

## Files to Modify

| File | Action |
|------|--------|
| `routes/web.php` | Add role routes |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Add "Roles" nav item |

---

## Permission Groups (for frontend checkbox grouping)

```
Dashboard: view dashboard
Users: manage users
Roles: manage roles, manage permissions
Servers: view servers, create servers, edit servers, delete servers, check server health, connect servers, duplicate servers
Reports: view reports
Data: export data
```

---

## Protection Rules

1. **Superadmin role** cannot be deleted
2. **Superadmin role** must always have `manage roles` permission (prevent lockout)
3. A user cannot remove their own `manage roles` permission if they are the only one with it (optional)
4. The `superadmin` role name is reserved and cannot be renamed
