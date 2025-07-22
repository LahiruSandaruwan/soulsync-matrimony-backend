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
            // Additional profile completion tracking
            $table->integer('profile_completion_percentage')->after('last_active_at')->default(0);
            $table->json('completed_sections')->after('profile_completion_percentage')->nullable(); // Array of completed profile sections
            
            // Location details (more specific than country_code)
            $table->string('current_city')->after('country_code')->nullable();
            $table->string('current_state')->after('current_city')->nullable();
            $table->decimal('latitude', 10, 8)->after('current_state')->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Communication preferences
            $table->boolean('email_notifications')->after('longitude')->default(true);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('push_notifications')->default(true);
            
            // Privacy settings
            $table->enum('profile_visibility', ['public', 'members_only', 'premium_only', 'private'])->default('members_only');
            $table->boolean('hide_last_seen')->default(false);
            $table->boolean('incognito_mode')->default(false); // Premium feature
            
            // Verification status
            $table->boolean('email_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->boolean('photo_verified')->default(false);
            $table->boolean('id_verified')->default(false);
            $table->json('verification_documents')->nullable(); // Uploaded document references
            
            // Account security
            $table->string('two_factor_secret')->nullable(); // For 2FA
            $table->boolean('two_factor_enabled')->default(false);
            $table->json('recovery_codes')->nullable(); // 2FA recovery codes
            $table->timestamp('password_changed_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            
            // Device tracking
            $table->json('device_tokens')->nullable(); // Push notification tokens
            $table->string('last_login_ip')->nullable();
            $table->string('last_device')->nullable(); // Device type/browser
            $table->timestamp('last_password_reset')->nullable();
            
            // Behavioral analytics
            $table->integer('login_count')->default(0);
            $table->integer('profile_views_received')->default(0);
            $table->integer('profile_views_given')->default(0);
            $table->integer('likes_received')->default(0);
            $table->integer('likes_given')->default(0);
            $table->timestamp('first_login_at')->nullable();
            
            // Premium tracking
            $table->json('premium_features_used')->nullable(); // Track which premium features are used
            $table->integer('super_likes_count')->default(0);
            $table->integer('boosts_used')->default(0);
            $table->timestamp('last_boost_at')->nullable();
            
            // Matching preferences (basic)
            $table->integer('preferred_min_age')->default(18);
            $table->integer('preferred_max_age')->default(50);
            $table->integer('preferred_distance_km')->default(50);
            
            // Add indexes for new fields
            $table->index(['profile_completion_percentage']);
            $table->index(['current_city', 'current_state']);
            $table->index(['profile_visibility']);
            $table->index(['email_verified', 'phone_verified']);
            $table->index(['two_factor_enabled']);
            $table->index(['failed_login_attempts', 'locked_until']);
            $table->index(['profile_views_received']);
            $table->index(['last_boost_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_completion_percentage', 'completed_sections', 'current_city', 'current_state',
                'latitude', 'longitude', 'email_notifications', 'sms_notifications', 'push_notifications',
                'profile_visibility', 'hide_last_seen', 'incognito_mode', 'email_verified', 'phone_verified',
                'photo_verified', 'id_verified', 'verification_documents', 'two_factor_secret',
                'two_factor_enabled', 'recovery_codes', 'password_changed_at', 'failed_login_attempts',
                'locked_until', 'device_tokens', 'last_login_ip', 'last_device', 'last_password_reset',
                'login_count', 'profile_views_received', 'profile_views_given', 'likes_received',
                'likes_given', 'first_login_at', 'premium_features_used', 'super_likes_count',
                'boosts_used', 'last_boost_at', 'preferred_min_age', 'preferred_max_age',
                'preferred_distance_km'
            ]);
        });
    }
};
