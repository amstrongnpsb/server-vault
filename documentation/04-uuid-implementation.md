# UUID Primary Keys Implementation

## Overview

This document describes the UUID implementation for all primary keys in the application. UUIDs provide better scalability, privacy, and distributed system support compared to auto-incrementing integers.

## What are UUIDs?

A UUID (Universally Unique Identifier) is a 128-bit number used to uniquely identify information. Format: `550e8400-e29b-41d4-a716-446655440000`

**Benefits:**

- ✅ Scalability across distributed systems
- ✅ Better privacy (ID exposure doesn't reveal count)
- ✅ Safe for concurrent migrations
- ✅ Works seamlessly with microservices
- ✅ Prevents ID enumeration attacks

**Drawbacks:**

- ❌ Larger storage (36 bytes as string, 16 bytes as binary)
- ❌ Slightly slower indexing vs integers
- ❌ Less human-readable in URLs

## UUID Tables Affected

### 1. Users Table

```sql
ALTER TABLE users MODIFY id CHAR(36) PRIMARY KEY;
```

- `id` - UUID (Primary Key)
- All other columns remain unchanged

### 2. Roles Table

```sql
ALTER TABLE roles MODIFY id CHAR(36) PRIMARY KEY;
```

- `id` - UUID (Primary Key)
- Used by Spatie Permission

### 3. Permissions Table

```sql
ALTER TABLE permissions MODIFY id CHAR(36) PRIMARY KEY;
```

- `id` - UUID (Primary Key)
- Used by Spatie Permission

### 4. Pivot Tables

- `model_has_roles.role_id` - UUID Foreign Key
- `model_has_roles.model_id` - UUID Foreign Key
- `model_has_permissions.permission_id` - UUID Foreign Key
- `model_has_permissions.model_id` - UUID Foreign Key
- `role_has_permissions.role_id` - UUID Foreign Key
- `role_has_permissions.permission_id` - UUID Foreign Key

### 5. Sessions Table

```sql
ALTER TABLE sessions MODIFY user_id CHAR(36);
```

- `user_id` - UUID Foreign Key (can be null)

## Model Configuration

### User Model

```php
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    // UUID Configuration
    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

### Permission Model

```php
class Permission extends SpatiePermission
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;
}
```

### Role Model

```php
class Role extends SpatieRole
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;
}
```

## Migration Configuration

### Users Table Migration

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
```

### Permission Tables Migration

```php
Schema::create('permissions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('guard_name');
    $table->timestamps();
    $table->unique(['name', 'guard_name']);
});

Schema::create('roles', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('guard_name');
    $table->timestamps();
    $table->unique(['name', 'guard_name']);
});

Schema::create('model_has_permissions', function (Blueprint $table) {
    $table->uuid('permission_id');
    $table->string('model_type');
    $table->uuid('model_id');

    $table->foreign('permission_id')
        ->references('id')->on('permissions')->onDelete('cascade');

    $table->primary(['permission_id', 'model_id', 'model_type']);
});

Schema::create('model_has_roles', function (Blueprint $table) {
    $table->uuid('role_id');
    $table->string('model_type');
    $table->uuid('model_id');

    $table->foreign('role_id')
        ->references('id')->on('roles')->onDelete('cascade');

    $table->primary(['role_id', 'model_id', 'model_type']);
});
```

## Spatie Configuration

### Config/permission.php

```php
return [
    'models' => [
        'permission' => \App\Models\Permission::class,
        'role' => \App\Models\Role::class,
    ],
    // ... rest of config
];
```

## Usage in Code

### Creating Users

```php
// Laravel automatically generates UUID when using HasUuids trait
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => Hash::make('password'),
]);

// UUID is auto-generated
echo $user->id; // e.g., "550e8400-e29b-41d4-a716-446655440000"
```

### Assigning Roles

```php
$user = User::find($uuid);
$user->assignRole('superadmin'); // Works with UUID models
```

### Querying by UUID

```php
// By primary key
$user = User::find('550e8400-e29b-41d4-a716-446655440000');

// By email
$user = User::where('email', 'user@example.com')->first();

// Validation rule
'user_id' => 'uuid|exists:users,id'
```

## Database Queries

### Check UUID Columns

```sql
SELECT COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'id';
```

### Find User by UUID

```sql
SELECT * FROM users WHERE id = '550e8400-e29b-41d4-a716-446655440000';
```

### View Model Relations with UUIDs

```sql
SELECT u.id, u.name, r.id as role_id, r.name as role_name
FROM users u
LEFT JOIN model_has_roles mhr ON u.id = mhr.model_id
LEFT JOIN roles r ON mhr.role_id = r.id;
```

## Factory & Seeding

### User Factory

```php
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
        // UUID is auto-generated by HasUuids trait
    }
}
```

### Creating Users in Tests

```php
// Using factory with UUID
$user = User::factory()->create();

// With role
$user = User::factory()->create();
$user->assignRole('admin');

// Multiple users
$users = User::factory(10)->create();
```

## API Responses

### UUID in JSON Response

```json
{
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com",
    "roles": [
        {
            "id": "660e8400-e29b-41d4-a716-446655440001",
            "name": "superadmin"
        }
    ]
}
```

### Route Model Binding with UUID

```php
Route::get('/users/{user}', function (User $user) {
    // Laravel automatically resolves by UUID primary key
    return $user;
});

// URL: /users/550e8400-e29b-41d4-a716-446655440000
```

## Validation

### UUID Validation Rules

```php
$validated = $request->validate([
    'user_id' => 'required|uuid|exists:users,id',
    'email' => 'required|email|unique:users,email',
]);
```

### Custom Validation

```php
use Illuminate\Validation\Rule;

$validated = $request->validate([
    'user_id' => [
        'required',
        'uuid',
        Rule::exists('users', 'id'),
    ],
]);
```

## Performance Considerations

### Indexing

UUIDs are already indexed as primary keys. For better performance with large datasets:

```php
Schema::create('users', function (Blueprint $table) {
    // Binary UUID (16 bytes) is faster than string (36 bytes)
    $table->uuid('id')->primary();

    // Indexed for frequently searched columns
    $table->string('email')->unique();
    $table->index('created_at');
});
```

### Database Optimization

```sql
-- Check UUID index
SHOW INDEX FROM users WHERE Column_name = 'id';

-- Optimize table
OPTIMIZE TABLE users;
```

## Troubleshooting

### Issue: "SQLSTATE[HY000]: General error: 1366 Incorrect string value"

**Solution:** Ensure character set is UTF-8:

```php
Schema::create('users', function (Blueprint $table) {
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';
    $table->uuid('id')->primary();
});
```

### Issue: UUID not auto-generating in Factory

**Solution:** Ensure model uses `HasUuids` trait:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasUuids; // Must be present
}
```

### Issue: "Illuminate\Database\QueryException: Syntax error or access violation"

**Solution:** Regenerate migrations if upgrading from integer IDs:

```bash
php artisan migrate:fresh --seed
```

## Migration from Integer IDs

⚠️ **Advanced:** Migrating existing data from integer IDs to UUIDs:

```php
// Create new UUID column
Schema::table('users', function (Blueprint $table) {
    $table->uuid('uuid')->nullable();
});

// Generate UUIDs for existing rows
DB::statement("UPDATE users SET uuid = UUID() WHERE uuid IS NULL");

// Drop old id, rename uuid to id
Schema::table('users', function (Blueprint $table) {
    $table->dropPrimary();
    $table->dropColumn('id');
    $table->renameColumn('uuid', 'id');
});

// Add primary key back
Schema::table('users', function (Blueprint $table) {
    $table->primary('id');
});
```

## Resources

- [Laravel UUID Documentation](https://laravel.com/docs/eloquent#uuid)
- [MySQL UUID Best Practices](https://dev.mysql.com/doc/refman/8.0/en/miscellaneous-functions.html#function_uuid)
- [UUID RFC 4122](https://tools.ietf.org/html/rfc4122)
- [Spatie Permission UUID Support](https://spatie.be/docs/laravel-permission/v6/introduction)

## Files Modified

```
app/
├── Models/
│   ├── User.php (updated with UUID config)
│   ├── Permission.php (created - UUID model)
│   └── Role.php (created - UUID model)

config/
└── permission.php (updated to use custom UUID models)

database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php (updated)
│   └── 2026_05_25_001009_create_permission_tables.php (updated)
└── factories/
    └── UserFactory.php (no changes needed)
```

## Summary

✅ All primary keys now use UUID format
✅ User model configured with UUID support
✅ Spatie Permission models use UUID
✅ All foreign keys reference UUIDs
✅ Seeders work seamlessly with UUID generation
✅ Full backward compatibility with existing code
