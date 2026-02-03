<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LiveProfilesSeeder extends Seeder
{
    /**
     * Seed test users with approved profiles for the Top Live Profiles feature.
     */
    public function run(): void
    {
        $testUsers = [
            [
                'first_name' => 'Amara',
                'last_name' => 'Perera',
                'email' => 'amara.test@soulsync.com',
                'gender' => 'female',
                'date_of_birth' => '1995-03-15',
                'profile' => [
                    'current_city' => 'Colombo',
                    'current_country' => 'Sri Lanka',
                    'height_cm' => 165,
                    'occupation' => 'Software Engineer',
                    'religion' => 'Buddhist',
                    'education_level' => 'bachelor',
                    'marital_status' => 'never_married',
                    'about_me' => 'A passionate developer looking for meaningful connections.',
                ]
            ],
            [
                'first_name' => 'Kasun',
                'last_name' => 'Fernando',
                'email' => 'kasun.test@soulsync.com',
                'gender' => 'male',
                'date_of_birth' => '1992-07-22',
                'profile' => [
                    'current_city' => 'Kandy',
                    'current_country' => 'Sri Lanka',
                    'height_cm' => 175,
                    'occupation' => 'Doctor',
                    'religion' => 'Buddhist',
                    'education_level' => 'master',
                    'marital_status' => 'never_married',
                    'about_me' => 'Medical professional with a love for travel and adventure.',
                ]
            ],
            [
                'first_name' => 'Priya',
                'last_name' => 'Silva',
                'email' => 'priya.test@soulsync.com',
                'gender' => 'female',
                'date_of_birth' => '1997-11-08',
                'profile' => [
                    'current_city' => 'Galle',
                    'current_country' => 'Sri Lanka',
                    'height_cm' => 160,
                    'occupation' => 'Teacher',
                    'religion' => 'Christian',
                    'education_level' => 'bachelor',
                    'marital_status' => 'never_married',
                    'about_me' => 'Educator who values family and traditional values.',
                ]
            ],
            [
                'first_name' => 'Nuwan',
                'last_name' => 'Jayawardena',
                'email' => 'nuwan.test@soulsync.com',
                'gender' => 'male',
                'date_of_birth' => '1990-05-30',
                'profile' => [
                    'current_city' => 'Colombo',
                    'current_country' => 'Sri Lanka',
                    'height_cm' => 178,
                    'occupation' => 'Business Analyst',
                    'religion' => 'Hindu',
                    'education_level' => 'master',
                    'marital_status' => 'never_married',
                    'about_me' => 'Ambitious professional seeking a life partner.',
                ]
            ],
            [
                'first_name' => 'Dilini',
                'last_name' => 'Rathnayake',
                'email' => 'dilini.test@soulsync.com',
                'gender' => 'female',
                'date_of_birth' => '1994-09-12',
                'profile' => [
                    'current_city' => 'Negombo',
                    'current_country' => 'Sri Lanka',
                    'height_cm' => 162,
                    'occupation' => 'Accountant',
                    'religion' => 'Catholic',
                    'education_level' => 'bachelor',
                    'marital_status' => 'never_married',
                    'about_me' => 'Financial professional who enjoys music and cooking.',
                ]
            ],
        ];

        foreach ($testUsers as $index => $userData) {
            // Check if user already exists
            $existingUser = User::where('email', $userData['email'])->first();
            if ($existingUser) {
                $this->command->info("User {$userData['email']} already exists, skipping...");
                continue;
            }

            // Create user
            $user = User::create([
                'name' => $userData['first_name'] . ' ' . $userData['last_name'], // Required for backward compatibility
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'email' => $userData['email'],
                'password' => Hash::make('password123'),
                'gender' => $userData['gender'],
                'date_of_birth' => $userData['date_of_birth'],
                'status' => 'active',
                'profile_status' => 'approved',
                'email_verified_at' => now(),
                'last_active_at' => now()->subMinutes(rand(1, 60)), // Random activity in last hour
            ]);

            // Create profile
            UserProfile::create(array_merge(
                ['user_id' => $user->id],
                $userData['profile']
            ));

            $this->command->info("Created user: {$userData['first_name']} {$userData['last_name']}");
        }

        $this->command->info('LiveProfilesSeeder completed!');
    }
}
