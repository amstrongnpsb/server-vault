<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed users with different roles.
     * Creates 20 admin users and 130 regular users.
     */
    public function run(): void
    {
        // Make sure roles exist
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

        if (!$adminRole || !$userRole) {
            $this->command->error('Roles not found. Please run RoleSeeder first.');
            return;
        }

        $this->command->info('Creating 20 admin users...');
        
        // Create 20 admin users
        for ($i = 1; $i <= 20; $i++) {
            $user = User::create([
                'name' => fake()->name(),
                'email' => "admin{$i}@servervault.test",
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);
            
            $user->assignRole($adminRole);
        }

        $this->command->info('Creating 130 regular users...');

        // Create 130 regular users
        for ($i = 1; $i <= 130; $i++) {
            $user = User::create([
                'name' => fake()->name(),
                'email' => "user{$i}@servervault.test",
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);
            
            $user->assignRole($userRole);
        }

        $this->command->info('Successfully created 150 users (20 admins, 130 users).');
        $this->command->info('Default password for all users: password');
    }
}
