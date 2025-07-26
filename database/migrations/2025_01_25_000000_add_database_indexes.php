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
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index(['email', 'email_verified_at'], 'users_email_verified_index');
            $table->index(['is_premium', 'premium_expires_at'], 'users_premium_status_index');
            $table->index(['is_verified', 'verification_status'], 'users_verification_index');
            $table->index(['date_of_birth', 'gender'], 'users_birth_gender_index');
            $table->index(['location_city', 'location_country'], 'users_location_index');
            $table->index(['last_active_at'], 'users_last_active_index');
            $table->index(['created_at'], 'users_created_at_index');
            $table->index(['stripe_customer_id'], 'users_stripe_customer_index');
        });

        // User profiles table indexes
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->index(['user_id'], 'user_profiles_user_id_index');
            $table->index(['marital_status', 'religion'], 'user_profiles_marital_religion_index');
            $table->index(['education_level', 'occupation'], 'user_profiles_education_occupation_index');
            $table->index(['annual_income'], 'user_profiles_income_index');
            $table->index(['height', 'weight'], 'user_profiles_physical_index');
        });

        // User preferences table indexes
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->index(['user_id'], 'user_preferences_user_id_index');
            $table->index(['preferred_age_min', 'preferred_age_max'], 'user_preferences_age_index');
            $table->index(['preferred_height_min', 'preferred_height_max'], 'user_preferences_height_index');
            $table->index(['preferred_marital_status'], 'user_preferences_marital_index');
            $table->index(['preferred_religion'], 'user_preferences_religion_index');
            $table->index(['preferred_location_city', 'preferred_location_country'], 'user_preferences_location_index');
        });

        // Matches table indexes
        Schema::table('matches', function (Blueprint $table) {
            $table->index(['user_id', 'matched_user_id'], 'matches_users_index');
            $table->index(['status'], 'matches_status_index');
            $table->index(['created_at'], 'matches_created_at_index');
            $table->index(['compatibility_score'], 'matches_compatibility_index');
        });

        // Conversations table indexes
        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['user_id', 'matched_user_id'], 'conversations_users_index');
            $table->index(['last_message_at'], 'conversations_last_message_index');
            $table->index(['created_at'], 'conversations_created_at_index');
        });

        // Messages table indexes
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id'], 'messages_conversation_index');
            $table->index(['sender_id'], 'messages_sender_index');
            $table->index(['created_at'], 'messages_created_at_index');
            $table->index(['is_read'], 'messages_read_index');
            $table->index(['message_type'], 'messages_type_index');
        });

        // Notifications table indexes
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id'], 'notifications_user_index');
            $table->index(['type'], 'notifications_type_index');
            $table->index(['is_read'], 'notifications_read_index');
            $table->index(['created_at'], 'notifications_created_at_index');
        });

        // Subscriptions table indexes
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id'], 'subscriptions_user_index');
            $table->index(['status'], 'subscriptions_status_index');
            $table->index(['plan_id'], 'subscriptions_plan_index');
            $table->index(['stripe_subscription_id'], 'subscriptions_stripe_index');
            $table->index(['paypal_subscription_id'], 'subscriptions_paypal_index');
            $table->index(['current_period_end'], 'subscriptions_period_end_index');
        });

        // User photos table indexes
        Schema::table('user_photos', function (Blueprint $table) {
            $table->index(['user_id'], 'user_photos_user_index');
            $table->index(['is_primary'], 'user_photos_primary_index');
            $table->index(['is_approved'], 'user_photos_approved_index');
            $table->index(['created_at'], 'user_photos_created_at_index');
        });

        // Profile views table indexes
        Schema::table('profile_views', function (Blueprint $table) {
            $table->index(['viewed_user_id'], 'profile_views_viewed_index');
            $table->index(['viewer_user_id'], 'profile_views_viewer_index');
            $table->index(['created_at'], 'profile_views_created_at_index');
        });

        // Reports table indexes
        Schema::table('reports', function (Blueprint $table) {
            $table->index(['reporter_id'], 'reports_reporter_index');
            $table->index(['reported_user_id'], 'reports_reported_index');
            $table->index(['status'], 'reports_status_index');
            $table->index(['type'], 'reports_type_index');
            $table->index(['created_at'], 'reports_created_at_index');
        });

        // User interests table indexes
        Schema::table('user_interests', function (Blueprint $table) {
            $table->index(['user_id'], 'user_interests_user_index');
            $table->index(['interest_id'], 'user_interests_interest_index');
        });

        // Horoscopes table indexes
        Schema::table('horoscopes', function (Blueprint $table) {
            $table->index(['zodiac_sign'], 'horoscopes_sign_index');
            $table->index(['date'], 'horoscopes_date_index');
        });

        // Horoscope compatibility table indexes
        Schema::table('horoscope_compatibilities', function (Blueprint $table) {
            $table->index(['sign1', 'sign2'], 'horoscope_compat_signs_index');
            $table->index(['compatibility_score'], 'horoscope_compat_score_index');
        });

        // Saved searches table indexes
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->index(['user_id'], 'saved_searches_user_index');
            $table->index(['is_active'], 'saved_searches_active_index');
            $table->index(['created_at'], 'saved_searches_created_at_index');
        });

        // Search alerts table indexes
        Schema::table('search_alerts', function (Blueprint $table) {
            $table->index(['user_id'], 'search_alerts_user_index');
            $table->index(['is_active'], 'search_alerts_active_index');
            $table->index(['last_sent_at'], 'search_alerts_last_sent_index');
        });

        // Exchange rates table indexes
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->index(['from_currency', 'to_currency'], 'exchange_rates_currencies_index');
            $table->index(['date'], 'exchange_rates_date_index');
        });

        // Coupons table indexes
        Schema::table('coupons', function (Blueprint $table) {
            $table->index(['code'], 'coupons_code_index');
            $table->index(['is_active'], 'coupons_active_index');
            $table->index(['expires_at'], 'coupons_expires_index');
        });

        // Coupon usage table indexes
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->index(['user_id'], 'coupon_usages_user_index');
            $table->index(['coupon_id'], 'coupon_usages_coupon_index');
            $table->index(['used_at'], 'coupon_usages_used_at_index');
        });

        // Two factor auth table indexes
        Schema::table('two_factor_auth', function (Blueprint $table) {
            $table->index(['user_id'], 'two_factor_user_index');
            $table->index(['is_enabled'], 'two_factor_enabled_index');
        });

        // Two factor codes table indexes
        Schema::table('two_factor_codes', function (Blueprint $table) {
            $table->index(['user_id'], 'two_factor_codes_user_index');
            $table->index(['code'], 'two_factor_codes_code_index');
            $table->index(['expires_at'], 'two_factor_codes_expires_index');
        });

        // User warnings table indexes
        Schema::table('user_warnings', function (Blueprint $table) {
            $table->index(['user_id'], 'user_warnings_user_index');
            $table->index(['warning_template_id'], 'user_warnings_template_index');
            $table->index(['is_active'], 'user_warnings_active_index');
            $table->index(['created_at'], 'user_warnings_created_at_index');
        });

        // Deleted accounts table indexes
        Schema::table('deleted_accounts', function (Blueprint $table) {
            $table->index(['user_id'], 'deleted_accounts_user_index');
            $table->index(['deleted_at'], 'deleted_accounts_deleted_at_index');
        });

        // Password changes table indexes
        Schema::table('password_changes', function (Blueprint $table) {
            $table->index(['user_id'], 'password_changes_user_index');
            $table->index(['changed_at'], 'password_changes_changed_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all indexes in reverse order
        Schema::table('password_changes', function (Blueprint $table) {
            $table->dropIndex('password_changes_user_index');
            $table->dropIndex('password_changes_changed_at_index');
        });

        Schema::table('deleted_accounts', function (Blueprint $table) {
            $table->dropIndex('deleted_accounts_user_index');
            $table->dropIndex('deleted_accounts_deleted_at_index');
        });

        Schema::table('user_warnings', function (Blueprint $table) {
            $table->dropIndex('user_warnings_user_index');
            $table->dropIndex('user_warnings_template_index');
            $table->dropIndex('user_warnings_active_index');
            $table->dropIndex('user_warnings_created_at_index');
        });

        Schema::table('two_factor_codes', function (Blueprint $table) {
            $table->dropIndex('two_factor_codes_user_index');
            $table->dropIndex('two_factor_codes_code_index');
            $table->dropIndex('two_factor_codes_expires_index');
        });

        Schema::table('two_factor_auth', function (Blueprint $table) {
            $table->dropIndex('two_factor_user_index');
            $table->dropIndex('two_factor_enabled_index');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropIndex('coupon_usages_user_index');
            $table->dropIndex('coupon_usages_coupon_index');
            $table->dropIndex('coupon_usages_used_at_index');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex('coupons_code_index');
            $table->dropIndex('coupons_active_index');
            $table->dropIndex('coupons_expires_index');
        });

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropIndex('exchange_rates_currencies_index');
            $table->dropIndex('exchange_rates_date_index');
        });

        Schema::table('search_alerts', function (Blueprint $table) {
            $table->dropIndex('search_alerts_user_index');
            $table->dropIndex('search_alerts_active_index');
            $table->dropIndex('search_alerts_last_sent_index');
        });

        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropIndex('saved_searches_user_index');
            $table->dropIndex('saved_searches_active_index');
            $table->dropIndex('saved_searches_created_at_index');
        });

        Schema::table('horoscope_compatibilities', function (Blueprint $table) {
            $table->dropIndex('horoscope_compat_signs_index');
            $table->dropIndex('horoscope_compat_score_index');
        });

        Schema::table('horoscopes', function (Blueprint $table) {
            $table->dropIndex('horoscopes_sign_index');
            $table->dropIndex('horoscopes_date_index');
        });

        Schema::table('user_interests', function (Blueprint $table) {
            $table->dropIndex('user_interests_user_index');
            $table->dropIndex('user_interests_interest_index');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_reporter_index');
            $table->dropIndex('reports_reported_index');
            $table->dropIndex('reports_status_index');
            $table->dropIndex('reports_type_index');
            $table->dropIndex('reports_created_at_index');
        });

        Schema::table('profile_views', function (Blueprint $table) {
            $table->dropIndex('profile_views_viewed_index');
            $table->dropIndex('profile_views_viewer_index');
            $table->dropIndex('profile_views_created_at_index');
        });

        Schema::table('user_photos', function (Blueprint $table) {
            $table->dropIndex('user_photos_user_index');
            $table->dropIndex('user_photos_primary_index');
            $table->dropIndex('user_photos_approved_index');
            $table->dropIndex('user_photos_created_at_index');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_user_index');
            $table->dropIndex('subscriptions_status_index');
            $table->dropIndex('subscriptions_plan_index');
            $table->dropIndex('subscriptions_stripe_index');
            $table->dropIndex('subscriptions_paypal_index');
            $table->dropIndex('subscriptions_period_end_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_index');
            $table->dropIndex('notifications_type_index');
            $table->dropIndex('notifications_read_index');
            $table->dropIndex('notifications_created_at_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_index');
            $table->dropIndex('messages_sender_index');
            $table->dropIndex('messages_created_at_index');
            $table->dropIndex('messages_read_index');
            $table->dropIndex('messages_type_index');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_users_index');
            $table->dropIndex('conversations_last_message_index');
            $table->dropIndex('conversations_created_at_index');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_users_index');
            $table->dropIndex('matches_status_index');
            $table->dropIndex('matches_created_at_index');
            $table->dropIndex('matches_compatibility_index');
        });

        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropIndex('user_preferences_user_index');
            $table->dropIndex('user_preferences_age_index');
            $table->dropIndex('user_preferences_height_index');
            $table->dropIndex('user_preferences_marital_index');
            $table->dropIndex('user_preferences_religion_index');
            $table->dropIndex('user_preferences_location_index');
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex('user_profiles_user_id_index');
            $table->dropIndex('user_profiles_marital_religion_index');
            $table->dropIndex('user_profiles_education_occupation_index');
            $table->dropIndex('user_profiles_income_index');
            $table->dropIndex('user_profiles_physical_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_verified_index');
            $table->dropIndex('users_premium_status_index');
            $table->dropIndex('users_verification_index');
            $table->dropIndex('users_birth_gender_index');
            $table->dropIndex('users_location_index');
            $table->dropIndex('users_last_active_index');
            $table->dropIndex('users_created_at_index');
            $table->dropIndex('users_stripe_customer_index');
        });
    }
}; 