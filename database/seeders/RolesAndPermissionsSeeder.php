<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view profile',
            'edit profile',
            'delete profile',
            'upload photos',
            'view photos',
            'delete photos',
            
            // Matching permissions
            'view matches',
            'like profiles',
            'super like profiles',
            'view who liked me',
            'advanced search',
            
            // Communication permissions
            'send messages',
            'receive messages',
            'create conversations',
            'block users',
            'report users',
            
            // Subscription permissions
            'view subscription plans',
            'purchase subscriptions',
            'cancel subscriptions',
            
            // Horoscope permissions
            'view horoscope',
            'edit horoscope',
            'view compatibility',
            'horoscope matching',
            
            // Premium features
            'unlimited likes',
            'see who viewed profile',
            'access private photos',
            'priority customer support',
            'advanced filters',
            'voice intro',
            'read receipts',
            
            // Admin permissions
            'admin dashboard',
            'manage users',
            'moderate photos',
            'moderate profiles',
            'view reports',
            'manage reports',
            'manage subscriptions',
            'view analytics',
            'manage content',
            'system settings',
            'ban users',
            'suspend users',
            'delete users',
            'approve profiles',
            'reject profiles',
            'approve photos',
            'reject photos',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin role
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin role
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo([
            'admin dashboard',
            'manage users',
            'moderate photos',
            'moderate profiles',
            'view reports',
            'manage reports',
            'manage subscriptions',
            'view analytics',
            'manage content',
            'ban users',
            'suspend users',
            'approve profiles',
            'reject profiles',
            'approve photos',
            'reject photos',
        ]);

        // Moderator role
        $moderator = Role::create(['name' => 'moderator']);
        $moderator->givePermissionTo([
            'admin dashboard',
            'moderate photos',
            'moderate profiles',
            'view reports',
            'manage reports',
            'view analytics',
            'approve profiles',
            'reject profiles',
            'approve photos',
            'reject photos',
        ]);

        // Premium User role
        $premiumUser = Role::create(['name' => 'premium-user']);
        $premiumUser->givePermissionTo([
            // Basic user permissions
            'view profile',
            'edit profile',
            'upload photos',
            'view photos',
            'view matches',
            'like profiles',
            'super like profiles',
            'send messages',
            'receive messages',
            'create conversations',
            'block users',
            'report users',
            'view subscription plans',
            'cancel subscriptions',
            'view horoscope',
            'edit horoscope',
            'view compatibility',
            
            // Premium features
            'unlimited likes',
            'see who viewed profile',
            'view who liked me',
            'access private photos',
            'priority customer support',
            'advanced filters',
            'advanced search',
            'voice intro',
            'read receipts',
            'horoscope matching',
        ]);

        // Basic User role
        $user = Role::create(['name' => 'user']);
        $user->givePermissionTo([
            'view profile',
            'edit profile',
            'upload photos',
            'view photos',
            'view matches',
            'like profiles',
            'super like profiles',
            'send messages',
            'receive messages',
            'create conversations',
            'block users',
            'report users',
            'view subscription plans',
            'purchase subscriptions',
            'cancel subscriptions',
            'view horoscope',
            'edit horoscope',
            'view compatibility',
        ]);

        // Suspended User role (limited permissions)
        $suspendedUser = Role::create(['name' => 'suspended']);
        $suspendedUser->givePermissionTo([
            'view profile',
        ]);

        $this->command->info('Roles and permissions created successfully!');
    }
}
