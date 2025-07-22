<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Split name into first and last name
            $table->string('first_name')->after('name')->nullable();
            $table->string('last_name')->after('first_name')->nullable();
            
            // Basic matrimonial info
            $table->string('phone', 20)->after('email')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->date('date_of_birth')->after('password')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->after('date_of_birth')->nullable();
            $table->string('country_code', 3)->after('gender')->default('US');
            $table->string('language', 5)->after('country_code')->default('en');
            
            // Profile status
            $table->enum('status', ['active', 'inactive', 'suspended', 'banned', 'deleted'])
                  ->after('language')->default('active');
            $table->enum('profile_status', ['pending', 'approved', 'rejected', 'incomplete'])
                  ->after('status')->default('incomplete');
            
            // Registration tracking
            $table->ipAddress('registration_ip')->after('profile_status')->nullable();
            $table->enum('registration_method', ['email', 'phone', 'google', 'facebook', 'apple'])
                  ->after('registration_ip')->default('email');
            
            // Social login data
            $table->string('social_id')->after('registration_method')->nullable();
            $table->json('social_data')->after('social_id')->nullable();
            
            // Referral system
            $table->string('referral_code', 20)->after('social_data')->unique()->nullable();
            $table->foreignId('referred_by')->after('referral_code')->nullable()
                  ->constrained('users')->onDelete('set null');
            
            // Premium subscription
            $table->boolean('is_premium')->after('referred_by')->default(false);
            $table->timestamp('premium_expires_at')->after('is_premium')->nullable();
            
            // Activity tracking
            $table->timestamp('last_active_at')->after('premium_expires_at')->nullable();
            
            // Indexes for performance
            $table->index(['status', 'profile_status']);
            $table->index(['gender', 'status']);
            $table->index(['country_code', 'status']);
            $table->index(['is_premium', 'premium_expires_at']);
            $table->index('last_active_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn([
                'first_name', 'last_name', 'phone', 'phone_verified_at',
                'date_of_birth', 'gender', 'country_code', 'language',
                'status', 'profile_status', 'registration_ip', 'registration_method',
                'social_id', 'social_data', 'referral_code', 'referred_by',
                'is_premium', 'premium_expires_at', 'last_active_at'
            ]);
        });
    }
};
