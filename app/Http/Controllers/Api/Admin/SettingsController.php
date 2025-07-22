<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /**
     * Get all system settings
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $settings = [
                'general' => $this->getGeneralSettings(),
                'matching' => $this->getMatchingSettings(),
                'subscription' => $this->getSubscriptionSettings(),
                'notification' => $this->getNotificationSettings(),
                'security' => $this->getSecuritySettings(),
                'content' => $this->getContentSettings(),
                'payment' => $this->getPaymentSettings(),
                'email' => $this->getEmailSettings(),
                'social' => $this->getSocialSettings(),
                'features' => $this->getFeatureSettings(),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Admin settings fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update system settings
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|in:general,matching,subscription,notification,security,content,payment,email,social,features',
            'settings' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category = $request->category;
            $settings = $request->settings;

            // Validate settings based on category
            $validationResult = $this->validateCategorySettings($category, $settings);
            if (!$validationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Settings validation failed',
                    'errors' => $validationResult['errors']
                ], 422);
            }

            // Update settings in cache/database
            $this->updateCategorySettings($category, $settings);

            Log::info('System settings updated', [
                'category' => $category,
                'updated_by' => $request->user()->id,
                'settings_count' => count($settings),
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($category) . ' settings updated successfully',
                'data' => [
                    'category' => $category,
                    'updated_settings' => array_keys($settings),
                    'updated_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin settings update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get general platform settings
     */
    private function getGeneralSettings(): array
    {
        return [
            'app_name' => config('app.name', 'SoulSync'),
            'app_tagline' => Cache::get('settings.general.app_tagline', 'Find Your Perfect Match'),
            'app_description' => Cache::get('settings.general.app_description', 'A global matrimonial platform'),
            'default_timezone' => config('app.timezone', 'UTC'),
            'default_language' => Cache::get('settings.general.default_language', 'en'),
            'supported_languages' => Cache::get('settings.general.supported_languages', ['en', 'si', 'ta']),
            'maintenance_mode' => Cache::get('settings.general.maintenance_mode', false),
            'registration_enabled' => Cache::get('settings.general.registration_enabled', true),
            'guest_browsing_enabled' => Cache::get('settings.general.guest_browsing_enabled', false),
            'contact_email' => Cache::get('settings.general.contact_email', 'support@soulsync.com'),
            'privacy_policy_url' => Cache::get('settings.general.privacy_policy_url'),
            'terms_of_service_url' => Cache::get('settings.general.terms_of_service_url'),
        ];
    }

    /**
     * Get matching algorithm settings
     */
    private function getMatchingSettings(): array
    {
        return [
            'max_daily_matches' => Cache::get('settings.matching.max_daily_matches', 10),
            'match_radius_km' => Cache::get('settings.matching.match_radius_km', 50),
            'min_compatibility_score' => Cache::get('settings.matching.min_compatibility_score', 60),
            'age_preference_weight' => Cache::get('settings.matching.age_preference_weight', 0.2),
            'location_preference_weight' => Cache::get('settings.matching.location_preference_weight', 0.15),
            'education_preference_weight' => Cache::get('settings.matching.education_preference_weight', 0.15),
            'religion_preference_weight' => Cache::get('settings.matching.religion_preference_weight', 0.2),
            'horoscope_preference_weight' => Cache::get('settings.matching.horoscope_preference_weight', 0.1),
            'interests_preference_weight' => Cache::get('settings.matching.interests_preference_weight', 0.1),
            'lifestyle_preference_weight' => Cache::get('settings.matching.lifestyle_preference_weight', 0.1),
            'enable_horoscope_matching' => Cache::get('settings.matching.enable_horoscope_matching', true),
            'enable_ai_scoring' => Cache::get('settings.matching.enable_ai_scoring', false),
            'refresh_matches_daily' => Cache::get('settings.matching.refresh_matches_daily', true),
        ];
    }

    /**
     * Get subscription settings
     */
    private function getSubscriptionSettings(): array
    {
        return [
            'free_trial_days' => Cache::get('settings.subscription.free_trial_days', 7),
            'basic_plan_price_usd' => Cache::get('settings.subscription.basic_plan_price_usd', 4.99),
            'premium_plan_price_usd' => Cache::get('settings.subscription.premium_plan_price_usd', 9.99),
            'platinum_plan_price_usd' => Cache::get('settings.subscription.platinum_plan_price_usd', 19.99),
            'enable_discounts' => Cache::get('settings.subscription.enable_discounts', true),
            'multi_month_discount_3' => Cache::get('settings.subscription.multi_month_discount_3', 10),
            'multi_month_discount_6' => Cache::get('settings.subscription.multi_month_discount_6', 15),
            'multi_month_discount_12' => Cache::get('settings.subscription.multi_month_discount_12', 20),
            'auto_renewal_default' => Cache::get('settings.subscription.auto_renewal_default', true),
            'grace_period_days' => Cache::get('settings.subscription.grace_period_days', 3),
            'refund_policy_days' => Cache::get('settings.subscription.refund_policy_days', 7),
        ];
    }

    /**
     * Get notification settings
     */
    private function getNotificationSettings(): array
    {
        return [
            'enable_email_notifications' => Cache::get('settings.notification.enable_email_notifications', true),
            'enable_push_notifications' => Cache::get('settings.notification.enable_push_notifications', true),
            'enable_sms_notifications' => Cache::get('settings.notification.enable_sms_notifications', false),
            'new_match_notification' => Cache::get('settings.notification.new_match_notification', true),
            'new_message_notification' => Cache::get('settings.notification.new_message_notification', true),
            'profile_view_notification' => Cache::get('settings.notification.profile_view_notification', true),
            'subscription_expiry_days' => Cache::get('settings.notification.subscription_expiry_days', [7, 3, 1]),
            'inactive_user_reminder_days' => Cache::get('settings.notification.inactive_user_reminder_days', 7),
            'notification_batch_size' => Cache::get('settings.notification.notification_batch_size', 100),
            'notification_rate_limit' => Cache::get('settings.notification.notification_rate_limit', 10),
        ];
    }

    /**
     * Get security settings
     */
    private function getSecuritySettings(): array
    {
        return [
            'password_min_length' => Cache::get('settings.security.password_min_length', 8),
            'password_require_uppercase' => Cache::get('settings.security.password_require_uppercase', true),
            'password_require_lowercase' => Cache::get('settings.security.password_require_lowercase', true),
            'password_require_numbers' => Cache::get('settings.security.password_require_numbers', true),
            'password_require_symbols' => Cache::get('settings.security.password_require_symbols', false),
            'max_login_attempts' => Cache::get('settings.security.max_login_attempts', 5),
            'lockout_duration_minutes' => Cache::get('settings.security.lockout_duration_minutes', 15),
            'session_timeout_minutes' => Cache::get('settings.security.session_timeout_minutes', 1440),
            'enable_two_factor_auth' => Cache::get('settings.security.enable_two_factor_auth', false),
            'require_email_verification' => Cache::get('settings.security.require_email_verification', true),
            'require_phone_verification' => Cache::get('settings.security.require_phone_verification', false),
            'enable_ip_logging' => Cache::get('settings.security.enable_ip_logging', true),
            'suspicious_activity_threshold' => Cache::get('settings.security.suspicious_activity_threshold', 10),
        ];
    }

    /**
     * Get content moderation settings
     */
    private function getContentSettings(): array
    {
        return [
            'auto_approve_profiles' => Cache::get('settings.content.auto_approve_profiles', false),
            'auto_approve_photos' => Cache::get('settings.content.auto_approve_photos', false),
            'enable_ai_content_moderation' => Cache::get('settings.content.enable_ai_content_moderation', false),
            'max_photos_per_user' => Cache::get('settings.content.max_photos_per_user', 10),
            'max_photo_size_mb' => Cache::get('settings.content.max_photo_size_mb', 5),
            'allowed_photo_formats' => Cache::get('settings.content.allowed_photo_formats', ['jpg', 'jpeg', 'png']),
            'watermark_photos' => Cache::get('settings.content.watermark_photos', false),
            'blur_private_photos' => Cache::get('settings.content.blur_private_photos', true),
            'profile_completion_required' => Cache::get('settings.content.profile_completion_required', 80),
            'enable_voice_intro' => Cache::get('settings.content.enable_voice_intro', true),
            'max_voice_intro_seconds' => Cache::get('settings.content.max_voice_intro_seconds', 60),
        ];
    }

    /**
     * Get payment gateway settings
     */
    private function getPaymentSettings(): array
    {
        return [
            'default_currency' => Cache::get('settings.payment.default_currency', 'USD'),
            'enable_stripe' => Cache::get('settings.payment.enable_stripe', true),
            'enable_paypal' => Cache::get('settings.payment.enable_paypal', true),
            'enable_payhere' => Cache::get('settings.payment.enable_payhere', true),
            'enable_webxpay' => Cache::get('settings.payment.enable_webxpay', true),
            'payment_retry_attempts' => Cache::get('settings.payment.payment_retry_attempts', 3),
            'payment_timeout_minutes' => Cache::get('settings.payment.payment_timeout_minutes', 15),
            'webhook_timeout_seconds' => Cache::get('settings.payment.webhook_timeout_seconds', 30),
            'enable_payment_logging' => Cache::get('settings.payment.enable_payment_logging', true),
            'auto_refund_failed_payments' => Cache::get('settings.payment.auto_refund_failed_payments', false),
        ];
    }

    /**
     * Get email settings
     */
    private function getEmailSettings(): array
    {
        return [
            'from_name' => Cache::get('settings.email.from_name', 'SoulSync'),
            'from_email' => Cache::get('settings.email.from_email', 'noreply@soulsync.com'),
            'support_email' => Cache::get('settings.email.support_email', 'support@soulsync.com'),
            'welcome_email_enabled' => Cache::get('settings.email.welcome_email_enabled', true),
            'verification_email_enabled' => Cache::get('settings.email.verification_email_enabled', true),
            'password_reset_email_enabled' => Cache::get('settings.email.password_reset_email_enabled', true),
            'match_notification_email_enabled' => Cache::get('settings.email.match_notification_email_enabled', true),
            'weekly_digest_enabled' => Cache::get('settings.email.weekly_digest_enabled', true),
            'marketing_emails_enabled' => Cache::get('settings.email.marketing_emails_enabled', false),
            'email_batch_size' => Cache::get('settings.email.email_batch_size', 50),
            'email_rate_limit_per_hour' => Cache::get('settings.email.email_rate_limit_per_hour', 100),
        ];
    }

    /**
     * Get social login settings
     */
    private function getSocialSettings(): array
    {
        return [
            'enable_google_login' => Cache::get('settings.social.enable_google_login', true),
            'enable_facebook_login' => Cache::get('settings.social.enable_facebook_login', true),
            'enable_apple_login' => Cache::get('settings.social.enable_apple_login', true),
            'social_profile_sync' => Cache::get('settings.social.social_profile_sync', true),
            'auto_import_social_photos' => Cache::get('settings.social.auto_import_social_photos', false),
            'social_friend_suggestions' => Cache::get('settings.social.social_friend_suggestions', false),
            'require_manual_approval_social' => Cache::get('settings.social.require_manual_approval_social', false),
        ];
    }

    /**
     * Get feature flags
     */
    private function getFeatureSettings(): array
    {
        return [
            'enable_video_calls' => Cache::get('settings.features.enable_video_calls', false),
            'enable_voice_calls' => Cache::get('settings.features.enable_voice_calls', false),
            'enable_gift_sending' => Cache::get('settings.features.enable_gift_sending', false),
            'enable_astrology_reports' => Cache::get('settings.features.enable_astrology_reports', true),
            'enable_personality_tests' => Cache::get('settings.features.enable_personality_tests', false),
            'enable_compatibility_games' => Cache::get('settings.features.enable_compatibility_games', false),
            'enable_location_sharing' => Cache::get('settings.features.enable_location_sharing', true),
            'enable_story_features' => Cache::get('settings.features.enable_story_features', false),
            'enable_live_streaming' => Cache::get('settings.features.enable_live_streaming', false),
            'enable_group_chats' => Cache::get('settings.features.enable_group_chats', false),
            'enable_referral_program' => Cache::get('settings.features.enable_referral_program', true),
            'enable_ai_chat_assistant' => Cache::get('settings.features.enable_ai_chat_assistant', false),
        ];
    }

    /**
     * Validate settings for a specific category
     */
    private function validateCategorySettings(string $category, array $settings): array
    {
        $errors = [];
        $validationRules = $this->getValidationRules($category);

        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, $validationRules)) {
                $errors[$key] = "Unknown setting: {$key}";
                continue;
            }

            $rules = $validationRules[$key];
            $validator = Validator::make([$key => $value], [$key => $rules]);

            if ($validator->fails()) {
                $errors[$key] = $validator->errors()->first($key);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get validation rules for each category
     */
    private function getValidationRules(string $category): array
    {
        $rules = [
            'general' => [
                'app_name' => 'string|max:100',
                'app_tagline' => 'string|max:200',
                'app_description' => 'string|max:500',
                'default_timezone' => 'string|max:50',
                'default_language' => 'string|in:en,si,ta',
                'supported_languages' => 'array',
                'maintenance_mode' => 'boolean',
                'registration_enabled' => 'boolean',
                'guest_browsing_enabled' => 'boolean',
                'contact_email' => 'email',
                'privacy_policy_url' => 'url',
                'terms_of_service_url' => 'url',
            ],
            'matching' => [
                'max_daily_matches' => 'integer|min:1|max:100',
                'match_radius_km' => 'integer|min:1|max:1000',
                'min_compatibility_score' => 'integer|min:0|max:100',
                'age_preference_weight' => 'numeric|min:0|max:1',
                'location_preference_weight' => 'numeric|min:0|max:1',
                'education_preference_weight' => 'numeric|min:0|max:1',
                'religion_preference_weight' => 'numeric|min:0|max:1',
                'horoscope_preference_weight' => 'numeric|min:0|max:1',
                'interests_preference_weight' => 'numeric|min:0|max:1',
                'lifestyle_preference_weight' => 'numeric|min:0|max:1',
                'enable_horoscope_matching' => 'boolean',
                'enable_ai_scoring' => 'boolean',
                'refresh_matches_daily' => 'boolean',
            ],
            'subscription' => [
                'free_trial_days' => 'integer|min:0|max:365',
                'basic_plan_price_usd' => 'numeric|min:0',
                'premium_plan_price_usd' => 'numeric|min:0',
                'platinum_plan_price_usd' => 'numeric|min:0',
                'enable_discounts' => 'boolean',
                'multi_month_discount_3' => 'integer|min:0|max:100',
                'multi_month_discount_6' => 'integer|min:0|max:100',
                'multi_month_discount_12' => 'integer|min:0|max:100',
                'auto_renewal_default' => 'boolean',
                'grace_period_days' => 'integer|min:0|max:30',
                'refund_policy_days' => 'integer|min:0|max:30',
            ],
            // Add more validation rules for other categories...
        ];

        return $rules[$category] ?? [];
    }

    /**
     * Update settings for a specific category
     */
    private function updateCategorySettings(string $category, array $settings): void
    {
        foreach ($settings as $key => $value) {
            $cacheKey = "settings.{$category}.{$key}";
            Cache::forever($cacheKey, $value);
        }

        // Clear related caches
        Cache::forget('app_settings_' . $category);
        Cache::tags(['settings', $category])->flush();
    }

    /**
     * Reset settings to default values
     */
    public function resetToDefaults(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|in:general,matching,subscription,notification,security,content,payment,email,social,features',
            'confirm' => 'required|string|in:RESET_TO_DEFAULTS',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category = $request->category;

            // Clear all cache entries for this category
            $pattern = "settings.{$category}.*";
            Cache::forget($pattern);

            Log::warning('Settings reset to defaults', [
                'category' => $category,
                'reset_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($category) . ' settings reset to defaults successfully',
                'data' => [
                    'category' => $category,
                    'reset_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin reset settings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function systemHealth(Request $request): JsonResponse
    {
        try {
            $health = [
                'database' => $this->checkDatabaseHealth(),
                'cache' => $this->checkCacheHealth(),
                'storage' => $this->checkStorageHealth(),
                'email' => $this->checkEmailHealth(),
                'queue' => $this->checkQueueHealth(),
                'memory' => $this->getMemoryUsage(),
                'disk_space' => $this->getDiskSpace(),
            ];

            $overallStatus = collect($health)->every(function ($check) {
                return $check['status'] === 'healthy';
            }) ? 'healthy' : 'issues_detected';

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_status' => $overallStatus,
                    'checks' => $health,
                    'checked_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('System health check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check system health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connection' => 'active',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache system
     */
    private function checkCacheHealth(): array
    {
        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'test_value', 60);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'status' => $value === 'test_value' ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage system
     */
    private function checkStorageHealth(): array
    {
        try {
            $diskSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usedPercentage = round((($totalSpace - $diskSpace) / $totalSpace) * 100, 2);

            return [
                'status' => $usedPercentage < 90 ? 'healthy' : 'warning',
                'free_space_gb' => round($diskSpace / 1024 / 1024 / 1024, 2),
                'used_percentage' => $usedPercentage,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check email system
     */
    private function checkEmailHealth(): array
    {
        try {
            return [
                'status' => 'healthy',
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue system
     */
    private function checkQueueHealth(): array
    {
        try {
            return [
                'status' => 'healthy',
                'driver' => config('queue.default'),
                'connection' => 'active',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage(): array
    {
        return [
            'status' => 'healthy',
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Get disk space information
     */
    private function getDiskSpace(): array
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $used = $total - $free;
        $usedPercentage = round(($used / $total) * 100, 2);

        return [
            'status' => $usedPercentage < 85 ? 'healthy' : 'warning',
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_percentage' => $usedPercentage,
        ];
    }
} 