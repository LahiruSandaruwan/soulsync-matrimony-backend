<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run roles and permissions seeder first
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@soulsync.com'],
            [
                'name' => 'Admin User', // Add name field
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => bcrypt('password123'),
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'country_code' => 'LK',
                'status' => 'active',
                'profile_status' => 'approved',
                'email_verified_at' => now(),
                'referral_code' => 'ADMIN001',
            ]
        );
        $admin->assignRole('super-admin');

        // Create regular test user
        $user = User::firstOrCreate(
            ['email' => 'test@soulsync.com'],
            [
                'name' => 'Test User', // Add name field
                'first_name' => 'Test',
                'last_name' => 'User',
                'password' => bcrypt('password123'),
                'date_of_birth' => '1995-05-15',
                'gender' => 'female',
                'country_code' => 'LK',
                'status' => 'active',
                'email_verified_at' => now(),
                'referral_code' => 'TEST001',
            ]
        );
        $user->assignRole('user');

        $this->command->info('Test users created successfully!');
        $this->command->info('Admin: admin@soulsync.com / password123');
        $this->command->info('User: test@soulsync.com / password123');
    }
}
