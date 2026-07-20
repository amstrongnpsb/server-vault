<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's roles and permissions.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Create permissions
        $permissions = [
            'view dashboard',
            'manage users',
            'manage roles',
            'manage permissions',
            'view reports',
            'export data',
            'connect servers',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['id' => Str::uuid()]
            );
        }

        // Create roles and assign permissions
        $superadminRole = Role::firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'web'],
            ['id' => Str::uuid()]
        );
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['id' => Str::uuid()]
        );
        $userRole = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web'],
            ['id' => Str::uuid()]
        );

        // Assign all permissions to superadmin
        $superadminRole->syncPermissions(Permission::all());

        // Assign some permissions to admin
        $adminRole->syncPermissions([
            'view dashboard',
            'manage users',
            'view reports',
        ]);

        // Assign basic permissions to user
        $userRole->syncPermissions([
            'view dashboard',
        ]);

        $this->command->info('Roles and permissions created successfully.');
    }
}
