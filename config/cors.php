<?php

/**
 * Build the allowed origins list based on environment.
 * In production, only allow production domains.
 * In development, allow localhost URLs.
 */
$allowedOrigins = array_filter([
    env('FRONTEND_URL'),
    env('ADMIN_URL'),
]);

// Only allow localhost in non-production environments
if (env('APP_ENV') !== 'production') {
    $allowedOrigins = array_merge($allowedOrigins, [
        'http://localhost:4200',
        'http://localhost:3000',
        'http://localhost:4201',
        'http://127.0.0.1:4200',
        'http://127.0.0.1:3000',
    ]);
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-Socket-Id',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 86400, // 24 hours

    'supports_credentials' => true,

];
