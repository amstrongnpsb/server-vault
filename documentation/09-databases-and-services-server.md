# Plan: Add Databases & Services to Servers

## Goal
Each server can optionally have multiple databases and multiple services (e.g. CCTV on port 9003, Rectifier on port 9004), each with their own port/username/password. A server can have zero, one, or many of either — no fixed column limit.

## Data model

```
servers (1) ──< server_databases (many)
servers (1) ──< server_services (many)
```

`servers` table is unchanged. Two new child tables added, both optional (a server simply has no rows in them if unused).

---

## Migrations

```bash
php artisan make:migration create_server_databases_table
php artisan make:migration create_server_services_table
```

### `server_databases`

```php
public function up(): void
{
    Schema::create('server_databases', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
        $table->string('type'); // Postgresql, Mysql, etc.
        $table->string('name')->nullable();
        $table->integer('port')->nullable();
        $table->string('username')->nullable();
        $table->text('credentials')->nullable(); // encrypted
        $table->timestamps();

        $table->index('server_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('server_databases');
}
```

### `server_services`

```php
public function up(): void
{
    Schema::create('server_services', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
        $table->string('name'); // "CCTV", "Rectifier"
        $table->integer('port');
        $table->string('username')->nullable();
        $table->text('credentials')->nullable(); // encrypted
        $table->text('description')->nullable();
        $table->timestamps();

        $table->index('server_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('server_services');
}
```

Both use `cascadeOnDelete()` so deleting a server automatically removes its databases/services — no orphaned rows.

Run:
```bash
php artisan migrate
```

- [ ] Migrations created
- [ ] Migrations run clean, no errors