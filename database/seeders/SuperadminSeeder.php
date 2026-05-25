<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    /**
     * Seed the superadmin user.
     */
    public function run(): void
    {
        // Create or update superadmin user
        $superadmin = User::updateOrCreate(
            ['email' => 'superadmin@test.com'],
            [
                'name' => 'Superadmin',
                'password' => Hash::make('superadmin123'),
                'email_verified_at' => now(),
            ]
        );

        // Assign superadmin role
        $superadmin->assignRole('superadmin');

        $this->command->info('Superadmin user created successfully.');
        $this->command->info('Email: superadmin@test.com');
        $this->command->info('Password: superadmin123');
    }
}
