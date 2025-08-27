<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Feature flags (public)
            [
                'category' => 'features',
                'key' => 'chat_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable chat functionality',
                'is_public' => true,
                'group' => 'communication',
            ],
            [
                'category' => 'features',
                'key' => 'video_calls_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable video call functionality',
                'is_public' => true,
                'group' => 'communication',
            ],
            [
                'category' => 'features',
                'key' => 'voice_messages_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable voice message functionality',
                'is_public' => true,
                'group' => 'communication',
            ],
            [
                'category' => 'features',
                'key' => 'horoscope_matching',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable horoscope-based matching',
                'is_public' => true,
                'group' => 'matching',
            ],
            [
                'category' => 'features',
                'key' => 'premium_features',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable premium subscription features',
                'is_public' => true,
                'group' => 'subscriptions',
            ],
            [
                'category' => 'features',
                'key' => 'social_login',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable social login (Google, Facebook, Apple)',
                'is_public' => true,
                'group' => 'authentication',
            ],
            [
                'category' => 'features',
                'key' => 'push_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable push notifications',
                'is_public' => true,
                'group' => 'notifications',
            ],
            [
                'category' => 'features',
                'key' => 'email_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable email notifications',
                'is_public' => true,
                'group' => 'notifications',
            ],
            [
                'category' => 'features',
                'key' => 'profile_verification',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable profile verification system',
                'is_public' => true,
                'group' => 'verification',
            ],
            [
                'category' => 'features',
                'key' => 'advanced_search',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable advanced search functionality',
                'is_public' => true,
                'group' => 'search',
            ],
            [
                'category' => 'features',
                'key' => 'ai_matching',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable AI-powered matching algorithm',
                'is_public' => true,
                'group' => 'matching',
            ],
            [
                'category' => 'features',
                'key' => 'analytics_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable analytics and insights',
                'is_public' => true,
                'group' => 'analytics',
            ],
            [
                'category' => 'features',
                'key' => 'content_moderation',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable content moderation system',
                'is_public' => true,
                'group' => 'moderation',
            ],
            [
                'category' => 'features',
                'key' => 'two_factor_auth',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable two-factor authentication',
                'is_public' => true,
                'group' => 'authentication',
            ],
            [
                'category' => 'features',
                'key' => 'privacy_mode',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable privacy mode features',
                'is_public' => true,
                'group' => 'privacy',
            ],

            // Payment settings (some public, some private)
            [
                'category' => 'payments',
                'key' => 'stripe_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable Stripe payment gateway',
                'is_public' => true,
                'group' => 'gateways',
            ],
            [
                'category' => 'payments',
                'key' => 'paypal_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable PayPal payment gateway',
                'is_public' => true,
                'group' => 'gateways',
            ],
            [
                'category' => 'payments',
                'key' => 'payhere_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable PayHere payment gateway',
                'is_public' => true,
                'group' => 'gateways',
            ],
            [
                'category' => 'payments',
                'key' => 'webxpay_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable WebXPay payment gateway',
                'is_public' => true,
                'group' => 'gateways',
            ],

            // General app settings (public)
            [
                'category' => 'general',
                'key' => 'app_maintenance_mode',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Enable maintenance mode',
                'is_public' => true,
                'group' => 'system',
            ],
            [
                'category' => 'general',
                'key' => 'registration_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Allow new user registrations',
                'is_public' => true,
                'group' => 'registration',
            ],
            [
                'category' => 'general',
                'key' => 'email_verification_required',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Require email verification for new accounts',
                'is_public' => true,
                'group' => 'registration',
            ],
            [
                'category' => 'general',
                'key' => 'min_age_requirement',
                'value' => '18',
                'type' => 'integer',
                'description' => 'Minimum age requirement for registration',
                'is_public' => true,
                'group' => 'registration',
            ],
            [
                'category' => 'general',
                'key' => 'max_age_requirement',
                'value' => '80',
                'type' => 'integer',
                'description' => 'Maximum age allowed for registration',
                'is_public' => true,
                'group' => 'registration',
            ],

            // UI/UX settings (public)
            [
                'category' => 'ui',
                'key' => 'dark_mode_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable dark mode option',
                'is_public' => true,
                'group' => 'theme',
            ],
            [
                'category' => 'ui',
                'key' => 'animation_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable UI animations',
                'is_public' => true,
                'group' => 'animations',
            ],
            [
                'category' => 'ui',
                'key' => 'sound_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable sound effects',
                'is_public' => true,
                'group' => 'audio',
            ],

            // Security settings (private)
            [
                'category' => 'security',
                'key' => 'session_timeout_minutes',
                'value' => '1440',
                'type' => 'integer',
                'description' => 'Session timeout in minutes (24 hours)',
                'is_public' => false,
                'group' => 'sessions',
            ],
            [
                'category' => 'security',
                'key' => 'max_login_attempts',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum login attempts before lockout',
                'is_public' => false,
                'group' => 'authentication',
            ],
            [
                'category' => 'security',
                'key' => 'lockout_duration_minutes',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Account lockout duration in minutes',
                'is_public' => false,
                'group' => 'authentication',
            ],

            // Matching algorithm settings (some public)
            [
                'category' => 'matching',
                'key' => 'compatibility_threshold',
                'value' => '60',
                'type' => 'integer',
                'description' => 'Minimum compatibility percentage for matches',
                'is_public' => false,
                'group' => 'algorithm',
            ],
            [
                'category' => 'matching',
                'key' => 'daily_match_limit',
                'value' => '20',
                'type' => 'integer',
                'description' => 'Maximum daily matches for free users',
                'is_public' => true,
                'group' => 'limits',
            ],
            [
                'category' => 'matching',
                'key' => 'premium_daily_match_limit',
                'value' => '100',
                'type' => 'integer',
                'description' => 'Maximum daily matches for premium users',
                'is_public' => true,
                'group' => 'limits',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('admin_settings')->updateOrInsert(
                ['category' => $setting['category'], 'key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}