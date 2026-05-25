# Spatie Laravel Permission - Superadmin Seeder Setup

## Overview

This document describes the Spatie Laravel Permission implementation for role-based access control (RBAC) with a superadmin user seeder. Spatie's package provides a robust, flexible system for managing roles and permissions.

**Note:** This implementation uses UUID primary keys for all tables. See [UUID Implementation Guide](04-uuid-implementation.md) for details.

## What is Spatie Laravel Permission?

[Spatie/Laravel-Permission](https://spatie.be/docs/laravel-permission) is a package that associates users with roles and permissions through a flexible polymorphic relation system.

**Key Features:**

- Database-driven roles and permissions
- Flexible permission checking
- Role-based and permission-based authorization
- Easy-to-use middleware
- Cache support for better performance

## Database Tables (Created by Spatie)

### 1. Roles Table

```
roles
├── id (Primary Key)
├── name (unique) - Role name
├── guard_name - Guard type (default: 'web')
├── created_at
└── updated_at
```

### 2. Permissions Table

```
permissions
├── id (Primary Key)
├── name (unique) - Permission name
├── guard_name - Guard type (default: 'web')
├── created_at
└── updated_at
```

### 3. Role-Permission Table (Pivot)

```
role_has_permissions
├── permission_id (Foreign Key)
├── role_id (Foreign Key)
└── guard_name
```

### 4. Model-Role Table (Pivot)

```
model_has_roles
├── role_id (Foreign Key)
├── model_id - User ID
├── model_type - Model class
└── guard_name
```

### 5. Model-Permission Table (Pivot)

```
model_has_permissions
├── permission_id (Foreign Key)
├── model_id - User ID
├── model_type - Model class
└── guard_name
```

## Roles

### Predefined Roles:

1. **superadmin** - Full system access
    - Assigned all permissions automatically
2. **admin** - Limited administrative access
    - Permissions: view dashboard, manage users, view reports
3. **user** - Regular user access
    - Permissions: view dashboard

## Permissions

### Available Permissions:

- `view dashboard` - Access to main dashboard
- `manage users` - Create, update, delete users
- `manage roles` - Create, update, delete roles
- `manage permissions` - Create, update, delete permissions
- `view reports` - Access to reports section
- `export data` - Export system data

## Superadmin User

**Default Superadmin Credentials:**

- **Name:** Superadmin
- **Email:** superadmin@test.com
- **Password:** superadmin123
- **Role:** superadmin (All permissions assigned)

## Installation & Setup

### Step 1: Verify Package Installation

The Spatie package should be installed. Verify in `composer.json`:

```bash
composer require spatie/laravel-permission
```

### Step 2: Run Migrations

Create all required tables:

```bash
php artisan migrate
```

This will create:

- roles table
- permissions table
- role_has_permissions pivot table
- model_has_roles pivot table
- model_has_permissions pivot table

### Step 3: Run Seeders

Populate roles, permissions, and superadmin user:

```bash
php artisan db:seed
```

Or run specific seeders:

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=SuperadminSeeder
```

### Step 4: Verify Setup

Check the superadmin user in Tinker:

```bash
php artisan tinker
>>> $user = App\Models\User::where('email', 'superadmin@test.com')->with('roles', 'permissions')->first();
>>> $user->roles;
>>> $user->permissions;
>>> $user->can('manage users'); // Should return true
```

## Usage in Code

### Check Roles

```php
$user = User::find(1);

// Check if user has a role
$user->hasRole('superadmin');
$user->hasRole(['superadmin', 'admin']);
$user->hasAnyRole(['superadmin', 'admin']);

// Check multiple roles
$user->hasAllRoles(['superadmin', 'admin']);
```

### Check Permissions

```php
// Check if user has permission
$user->can('manage users');
$user->hasPermissionTo('manage users');
$user->hasAnyPermission(['manage users', 'manage roles']);
$user->hasAllPermissions(['manage users', 'manage roles']);
```

### Assign Roles

```php
$user = User::find(1);

// Assign single role
$user->assignRole('admin');

// Assign multiple roles
$user->assignRole(['admin', 'user']);

// Sync roles (replace existing)
$user->syncRoles(['superadmin']);
```

### Assign Permissions

```php
$user = User::find(1);

// Assign permission directly to user
$user->givePermissionTo('view dashboard');

// Sync permissions
$user->syncPermissions(['view dashboard', 'manage users']);
```

### Revoke Roles & Permissions

```php
$user = User::find(1);

// Revoke role
$user->removeRole('admin');

// Revoke permission
$user->revokePermissionTo('manage users');

// Revoke all
$user->syncRoles([]);
$user->syncPermissions([]);
```

## Middleware Protection

### Protect Routes

```php
// routes/web.php

// Require specific role
Route::middleware('role:superadmin')->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

// Require specific permission
Route::middleware('permission:manage users')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

// Multiple roles (OR logic)
Route::middleware('role:superadmin|admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show']);
});

// Multiple permissions (OR logic)
Route::middleware('permission:manage users|manage roles')->group(function () {
    Route::get('/management', [ManagementController::class, 'index']);
});
```

### Blade Template Protection

```blade
@can('manage users')
    <a href="/users">Manage Users</a>
@endcan

@role('superadmin')
    <a href="/admin">Admin Panel</a>
@endrole

@hasrole('admin|superadmin')
    <span>You are an admin</span>
@endhasrole
```

## File Structure

```
project/
├── app/
│   └── Models/
│       └── User.php (with HasRoles trait)
├── config/
│   └── permission.php (Spatie config)
├── database/
│   ├── migrations/
│   │   └── 2026_05_25_001009_create_permission_tables.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── RoleSeeder.php
│       └── SuperadminSeeder.php
└── documentation/
    └── 03-superadmin-seeder.md (this file)
```

## Adding New Permissions

### Method 1: Via Seeder

Edit `RoleSeeder.php` and add to the `$permissions` array:

```php
$permissions = [
    'view dashboard',
    'manage users',
    'manage roles',
    'manage permissions',
    'view reports',
    'export data',
    'new permission', // Add here
];
```

### Method 2: Via Code

```php
use Spatie\Permission\Models\Permission;

Permission::create([
    'name' => 'delete users',
    'guard_name' => 'web'
]);
```

## Configuration

The Spatie configuration is stored in `config/permission.php`. Key options:

```php
'models' => [
    'permission' => \Spatie\Permission\Models\Permission::class,
    'role' => \Spatie\Permission\Models\Role::class,
],

'table_names' => [
    'roles' => 'roles',
    'permissions' => 'permissions',
    'model_has_permissions' => 'model_has_permissions',
    'model_has_roles' => 'model_has_roles',
    'role_has_permissions' => 'role_has_permissions',
],

'cache' => [
    'expiration_time' => \DateInterval::createFromDateString('24 hours'),
    'key' => 'spatie.permission.cache',
    'store' => 'default',
],
```

## Security Considerations

⚠️ **Important Security Notes:**

1. **Change Default Password** - Change superadmin password immediately in production

    ```php
    // In SuperadminSeeder.php, use environment variable:
    'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'default_password')),
    ```

2. **Use Strong Passwords** - Enforce strong password requirements for admin accounts

3. **Enable 2FA** - Implement two-factor authentication for admin users

4. **Audit Logging** - Log all admin actions for compliance

5. **IP Whitelisting** - Restrict admin access to specific IP ranges

6. **Permission Caching** - Disable cache in development:

    ```php
    // .env
    PERMISSION_CACHE_DISABLED=true
    ```

7. **Regular Audits** - Review roles and permissions regularly

## Troubleshooting

### Issue: "Method assignRole does not exist"

**Solution:** Ensure User model has `HasRoles` trait:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable {
    use HasRoles;
}
```

### Issue: "Table 'roles' doesn't exist"

**Solution:** Run migrations:

```bash
php artisan migrate
```

### Issue: Permissions not working in middleware

**Solution:** Clear cache:

```bash
php artisan cache:clear
php artisan permission:cache-reset
```

### Issue: "Guard name web not found"

**Solution:** Verify guards in `config/auth.php` match Spatie configuration

## Resources

- [Spatie Laravel Permission Documentation](https://spatie.be/docs/laravel-permission)
- [GitHub Repository](https://github.com/spatie/laravel-permission)
- [Laravel Authorization Docs](https://laravel.com/docs/authorization)

## Next Steps

1. ✅ Install package
2. ✅ Run migrations
3. ✅ Run seeders
4. ✅ Test superadmin access
5. Create API endpoints with permission checks
6. Implement frontend role/permission display
7. Add audit logging for admin actions
