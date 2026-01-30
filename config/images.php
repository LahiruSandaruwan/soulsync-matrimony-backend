<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Optimization Settings
    |--------------------------------------------------------------------------
    */

    // Quality settings (1-100)
    'quality' => [
        'jpeg' => env('IMAGE_JPEG_QUALITY', 85),
        'webp' => env('IMAGE_WEBP_QUALITY', 80),
        'png' => env('IMAGE_PNG_QUALITY', 9), // 0-9 for PNG
    ],

    // Maximum dimensions for uploads
    'max_dimensions' => [
        'width' => env('IMAGE_MAX_WIDTH', 2000),
        'height' => env('IMAGE_MAX_HEIGHT', 2000),
    ],

    // Maximum file size in bytes (5MB default)
    'max_file_size' => env('IMAGE_MAX_SIZE', 5 * 1024 * 1024),

    // Allowed MIME types
    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],

    // Image size variants
    'sizes' => [
        'thumbnail' => ['width' => 150, 'height' => 150],
        'small' => ['width' => 300, 'height' => 300],
        'medium' => ['width' => 600, 'height' => 600],
        'large' => ['width' => 1200, 'height' => 1200],
        'profile' => ['width' => 400, 'height' => 400],
        'gallery' => ['width' => 800, 'height' => 800],
    ],

    // Profile photo settings
    'profile' => [
        'variants' => ['thumbnail', 'profile', 'medium'],
        'generate_webp' => true,
    ],

    // Gallery photo settings
    'gallery' => [
        'variants' => ['thumbnail', 'medium', 'large'],
        'generate_webp' => true,
    ],

    // WebP generation
    'webp' => [
        'enabled' => env('IMAGE_WEBP_ENABLED', true),
        'fallback' => true, // Serve JPEG if WebP not available
    ],

    // Storage settings
    'storage' => [
        'disk' => env('IMAGE_STORAGE_DISK', 'public'),
        'directory' => 'photos',
    ],

    // CDN settings (optional)
    'cdn' => [
        'enabled' => env('CDN_ENABLED', false),
        'url' => env('CDN_URL'),
    ],
];
