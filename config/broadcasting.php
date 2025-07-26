<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null", "websockets"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'websockets'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusherapp.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'websockets' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('WEBSOCKET_HOST', '127.0.0.1'),
                'port' => env('WEBSOCKET_PORT', 6001),
                'scheme' => env('WEBSOCKET_SCHEME', 'http'),
                'encrypted' => false,
                'useTLS' => false,
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Channels
    |--------------------------------------------------------------------------
    |
    | Here you may define the broadcast channels that will be used by your
    | application. These channels are used to broadcast events to other
    | systems or over websockets.
    |
    */

    'channels' => [
        // User-specific channels
        'user.{id}' => [
            'driver' => 'pusher',
            'auth' => true,
            'presence' => false,
        ],

        // Chat channels
        'chat.{conversation_id}' => [
            'driver' => 'pusher',
            'auth' => true,
            'presence' => true,
        ],

        // Match notifications
        'matches.{user_id}' => [
            'driver' => 'pusher',
            'auth' => true,
            'presence' => false,
        ],

        // Profile view notifications
        'profile-views.{user_id}' => [
            'driver' => 'pusher',
            'auth' => true,
            'presence' => false,
        ],

        // System notifications
        'notifications.{user_id}' => [
            'driver' => 'pusher',
            'auth' => true,
            'presence' => false,
        ],

        // Admin channels
        'admin.{channel}' => [
            'driver' => 'pusher',
            'auth' => true,
            'presence' => false,
            'middleware' => ['auth:sanctum', 'role:admin'],
        ],

        // Public channels (for announcements, etc.)
        'public.{channel}' => [
            'driver' => 'pusher',
            'auth' => false,
            'presence' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Events
    |--------------------------------------------------------------------------
    |
    | Here you may define the broadcast events that will be used by your
    | application. These events are used to broadcast data to other
    | systems or over websockets.
    |
    */

    'events' => [
        // Chat events
        'chat.message.sent' => \App\Events\MessageSent::class,
        'chat.message.read' => \App\Events\MessageRead::class,
        'chat.message.typing' => \App\Events\UserTyping::class,
        'chat.conversation.created' => \App\Events\ConversationCreated::class,
        'chat.conversation.updated' => \App\Events\ConversationUpdated::class,

        // Match events
        'match.created' => \App\Events\MatchFound::class,
        'match.liked' => \App\Events\UserLiked::class,
        'match.super_liked' => \App\Events\UserSuperLiked::class,
        'match.disliked' => \App\Events\UserDisliked::class,

        // Profile events
        'profile.viewed' => \App\Events\ProfileViewed::class,
        'profile.updated' => \App\Events\ProfileUpdated::class,
        'profile.photo.uploaded' => \App\Events\PhotoUploaded::class,

        // Notification events
        'notification.sent' => \App\Events\NotificationSent::class,
        'notification.read' => \App\Events\NotificationRead::class,

        // User events
        'user.online' => \App\Events\UserOnline::class,
        'user.offline' => \App\Events\UserOffline::class,
        'user.status.changed' => \App\Events\UserStatusChanged::class,

        // Subscription events
        'subscription.created' => \App\Events\SubscriptionCreated::class,
        'subscription.updated' => \App\Events\SubscriptionUpdated::class,
        'subscription.cancelled' => \App\Events\SubscriptionCancelled::class,
        'subscription.expired' => \App\Events\SubscriptionExpired::class,

        // Payment events
        'payment.successful' => \App\Events\PaymentSuccessful::class,
        'payment.failed' => \App\Events\PaymentFailed::class,
        'payment.refunded' => \App\Events\PaymentRefunded::class,

        // System events
        'system.maintenance' => \App\Events\SystemMaintenance::class,
        'system.announcement' => \App\Events\SystemAnnouncement::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may define the middleware that will be applied to broadcast
    | routes and channels. These middleware are used to authenticate and
    | authorize users for broadcasting.
    |
    */

    'middleware' => [
        'auth' => \App\Http\Middleware\BroadcastAuth::class,
        'admin' => \App\Http\Middleware\AdminAuth::class,
        'premium' => \App\Http\Middleware\PremiumAuth::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define additional configuration options for broadcasting.
    |
    */

    'config' => [
        // Rate limiting for broadcasting
        'rate_limit' => [
            'enabled' => env('BROADCAST_RATE_LIMIT_ENABLED', true),
            'max_attempts' => env('BROADCAST_RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_minutes' => env('BROADCAST_RATE_LIMIT_DECAY_MINUTES', 1),
        ],

        // Message queue configuration
        'queue' => [
            'enabled' => env('BROADCAST_QUEUE_ENABLED', true),
            'connection' => env('BROADCAST_QUEUE_CONNECTION', 'redis'),
            'queue' => env('BROADCAST_QUEUE_NAME', 'broadcasting'),
        ],

        // Retry configuration
        'retry' => [
            'enabled' => env('BROADCAST_RETRY_ENABLED', true),
            'max_attempts' => env('BROADCAST_RETRY_MAX_ATTEMPTS', 3),
            'delay' => env('BROADCAST_RETRY_DELAY', 1000),
        ],

        // Logging configuration
        'logging' => [
            'enabled' => env('BROADCAST_LOGGING_ENABLED', false),
            'level' => env('BROADCAST_LOGGING_LEVEL', 'info'),
        ],

        // Security configuration
        'security' => [
            'encrypt_payloads' => env('BROADCAST_ENCRYPT_PAYLOADS', true),
            'verify_signatures' => env('BROADCAST_VERIFY_SIGNATURES', true),
            'allowed_origins' => explode(',', env('BROADCAST_ALLOWED_ORIGINS', '*')),
        ],
    ],

]; 