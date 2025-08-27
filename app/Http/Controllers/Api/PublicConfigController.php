<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicConfigController extends Controller
{
    /**
     * Get public configuration for frontend applications
     * This endpoint provides client-side configuration without requiring authentication
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Cache the config for 1 hour to improve performance
            $config = Cache::remember('public_config', 3600, function () {
                return $this->buildPublicConfig();
            });

            return response()->json([
                'success' => true,
                'data' => $config,
                'cached_at' => now()->toISOString(),
                'ttl' => 3600
            ]);

        } catch (\Exception $e) {
            Log::error('Public config fetch error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load configuration',
                'error' => config('app.debug') ? $e->getMessage() : 'Configuration unavailable'
            ], 500);
        }
    }

    /**
     * Build the public configuration array
     */
    private function buildPublicConfig(): array
    {
        return [
            'app' => $this->getAppConfig(),
            'payments' => $this->getPaymentConfig(),
            'features' => $this->getFeatureFlags(),
            'social' => $this->getSocialConfig(),
            'location' => $this->getLocationConfig(),
            'subscription' => $this->getSubscriptionConfig(),
            'limits' => $this->getLimitsConfig(),
            'ui' => $this->getUIConfig(),
        ];
    }

    /**
     * Get basic app configuration
     */
    private function getAppConfig(): array
    {
        return [
            'name' => config('app.name', 'SoulSync'),
            'version' => '1.0.0',
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug', false),
            'url' => config('app.url'),
            'api_version' => 'v1',
            'timezone' => config('app.timezone', 'UTC'),
            'locale' => config('app.locale', 'en'),
            'supported_locales' => ['en', 'si', 'ta'],
        ];
    }

    /**
     * Get payment gateway public configuration
     */
    private function getPaymentConfig(): array
    {
        return [
            'stripe' => [
                'publishable_key' => config('services.stripe.key'), // Only publishable key, never secret
                'enabled' => !empty(config('services.stripe.key')),
                'currency' => config('services.stripe.currency', 'usd'),
                'webhook_endpoint' => url('/api/webhooks/stripe'),
            ],
            'paypal' => [
                'client_id' => config('services.paypal.client_id'), // Only client ID, never secret
                'enabled' => !empty(config('services.paypal.client_id')),
                'currency' => config('services.paypal.currency', 'usd'),
                'environment' => config('services.paypal.environment', 'sandbox'),
                'webhook_endpoint' => url('/api/webhooks/paypal'),
            ],
            'payhere' => [
                'merchant_id' => config('services.payhere.merchant_id'), // Only merchant ID
                'enabled' => !empty(config('services.payhere.merchant_id')),
                'currency' => config('services.payhere.currency', 'lkr'),
                'webhook_endpoint' => url('/api/webhooks/payhere'),
            ],
            'webxpay' => [
                'merchant_id' => config('services.webxpay.merchant_id'), // Only merchant ID
                'enabled' => !empty(config('services.webxpay.merchant_id')),
                'currency' => config('services.webxpay.currency', 'lkr'),
                'webhook_endpoint' => url('/api/webhooks/webxpay'),
            ],
            'supported_currencies' => [
                'USD', 'LKR', 'INR', 'GBP', 'EUR', 'AUD', 'CAD', 'SGD', 'AED', 'SAR'
            ],
            'default_currency' => config('app.default_currency', 'USD'),
        ];
    }

    /**
     * Get feature flags from admin settings
     */
    private function getFeatureFlags(): array
    {
        try {
            // Get feature flags from database (admin settings)
            $features = DB::table('admin_settings')
                ->where('category', 'features')
                ->where('is_public', true)
                ->pluck('value', 'key')
                ->toArray();

            // Merge with default feature flags
            return array_merge([
                'chat_enabled' => true,
                'video_calls_enabled' => true,
                'voice_messages_enabled' => true,
                'horoscope_matching' => true,
                'premium_features' => true,
                'social_login' => true,
                'push_notifications' => true,
                'email_notifications' => true,
                'profile_verification' => true,
                'advanced_search' => true,
                'ai_matching' => true,
                'analytics_enabled' => true,
                'content_moderation' => true,
                'two_factor_auth' => true,
                'privacy_mode' => true,
            ], $features);

        } catch (\Exception $e) {
            Log::warning('Failed to load feature flags from database: ' . $e->getMessage());
            
            // Return default features if database fails
            return [
                'chat_enabled' => true,
                'video_calls_enabled' => true,
                'voice_messages_enabled' => true,
                'horoscope_matching' => true,
                'premium_features' => true,
                'social_login' => true,
                'push_notifications' => true,
                'email_notifications' => true,
                'profile_verification' => true,
                'advanced_search' => true,
                'ai_matching' => true,
                'analytics_enabled' => true,
                'content_moderation' => true,
                'two_factor_auth' => true,
                'privacy_mode' => true,
            ];
        }
    }

    /**
     * Get social login configuration
     */
    private function getSocialConfig(): array
    {
        return [
            'google' => [
                'client_id' => config('services.google.client_id'), // Only client ID
                'enabled' => !empty(config('services.google.client_id')),
            ],
            'facebook' => [
                'app_id' => config('services.facebook.client_id'), // Only app ID
                'enabled' => !empty(config('services.facebook.client_id')),
            ],
            'apple' => [
                'client_id' => config('services.apple.client_id'), // Only client ID
                'enabled' => !empty(config('services.apple.client_id')),
            ],
        ];
    }

    /**
     * Get location configuration
     */
    private function getLocationConfig(): array
    {
        return [
            'default_country' => config('app.default_country', 'LK'),
            'supported_countries' => [
                'LK' => 'Sri Lanka',
                'IN' => 'India',
                'GB' => 'United Kingdom',
                'US' => 'United States',
                'AU' => 'Australia',
                'CA' => 'Canada',
                'SG' => 'Singapore',
                'AE' => 'United Arab Emirates',
                'SA' => 'Saudi Arabia',
                'QA' => 'Qatar',
                'KW' => 'Kuwait',
                'BH' => 'Bahrain',
                'OM' => 'Oman',
            ],
            'currency_mapping' => [
                'LK' => 'LKR',
                'IN' => 'INR',
                'GB' => 'GBP',
                'US' => 'USD',
                'AU' => 'AUD',
                'CA' => 'CAD',
                'SG' => 'SGD',
                'AE' => 'AED',
                'SA' => 'SAR',
                'QA' => 'QAR',
                'KW' => 'KWD',
                'BH' => 'BHD',
                'OM' => 'OMR',
            ],
        ];
    }

    /**
     * Get subscription configuration
     */
    private function getSubscriptionConfig(): array
    {
        return [
            'trial_period_days' => 7,
            'plans' => [
                'free' => [
                    'name' => 'Free',
                    'features' => ['Basic profile', 'Limited matches', 'Basic chat'],
                ],
                'basic' => [
                    'name' => 'Basic',
                    'price_usd' => 4.99,
                    'price_lkr' => 1500,
                    'features' => ['Extended profile', 'More matches', 'Voice messages'],
                ],
                'premium' => [
                    'name' => 'Premium',
                    'price_usd' => 9.99,
                    'price_lkr' => 3000,
                    'features' => ['All features', 'Unlimited matches', 'Priority support'],
                ],
                'platinum' => [
                    'name' => 'Platinum',
                    'price_usd' => 19.99,
                    'price_lkr' => 6000,
                    'features' => ['VIP features', 'Personal matchmaker', '24/7 support'],
                ],
            ],
        ];
    }

    /**
     * Get application limits configuration
     */
    private function getLimitsConfig(): array
    {
        return [
            'profile' => [
                'max_photos' => 10,
                'max_bio_length' => 1000,
                'max_interests' => 15,
                'photo_size_mb' => 5,
                'voice_intro_seconds' => 60,
            ],
            'matching' => [
                'daily_likes_free' => 10,
                'daily_likes_premium' => 100,
                'daily_super_likes_free' => 1,
                'daily_super_likes_premium' => 5,
                'search_results_per_page' => 20,
                'max_search_filters' => 10,
            ],
            'chat' => [
                'max_message_length' => 2000,
                'max_voice_message_seconds' => 120,
                'max_file_size_mb' => 10,
                'typing_indicator_timeout' => 5,
            ],
            'api' => [
                'rate_limit_per_minute' => 60,
                'rate_limit_auth_per_minute' => 5,
                'max_page_size' => 100,
            ],
        ];
    }

    /**
     * Get UI configuration
     */
    private function getUIConfig(): array
    {
        return [
            'theme' => [
                'primary_color' => '#ec4899', // Soft pink
                'secondary_color' => '#f43f5e', // Rose gold
                'accent_color' => '#a855f7', // Lavender
                'neutral_color' => '#e5d9cc', // Beige
                'success_color' => '#10b981',
                'warning_color' => '#f59e0b',
                'error_color' => '#ef4444',
            ],
            'fonts' => [
                'heading' => 'Playfair Display',
                'body' => 'Inter',
            ],
            'layout' => [
                'max_width' => '1200px',
                'sidebar_width' => '280px',
                'header_height' => '64px',
                'footer_height' => '200px',
            ],
            'animations' => [
                'enabled' => true,
                'duration_fast' => '150ms',
                'duration_normal' => '300ms',
                'duration_slow' => '500ms',
            ],
        ];
    }

    /**
     * Clear the public config cache
     * This can be called when admin updates settings
     */
    public function clearCache(): JsonResponse
    {
        try {
            Cache::forget('public_config');
            
            return response()->json([
                'success' => true,
                'message' => 'Public config cache cleared successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear public config cache: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache'
            ], 500);
        }
    }
}
