<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    
    // Authentication routes with rate limiting
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('auth.rate.limit:register');
        Route::post('login', [AuthController::class, 'login'])->middleware('auth.rate.limit:login');
        Route::post('social-login', [AuthController::class, 'socialLogin'])->middleware('auth.rate.limit:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('auth.rate.limit:forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('auth.rate.limit:reset-password');
    });
    
    // Public data routes (for browsing without login)
    Route::prefix('public')->group(function () {
        Route::get('interests', 'Api\InterestController@index');
        Route::get('countries', 'Api\LocationController@countries');
        Route::get('states/{country}', 'Api\LocationController@states');
        Route::get('cities/{state}', 'Api\LocationController@cities');
        Route::get('subscription-plans', 'Api\SubscriptionController@plans');
    });
    
    // Health check
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'SoulSync API is running',
            'version' => '1.0.0',
            'timestamp' => now()
        ]);
    });
});

// Protected routes (authentication required)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
    });
    
    // Profile management
    Route::prefix('profile')->group(function () {
        Route::get('/', 'Api\ProfileController@show');
        Route::put('/', 'Api\ProfileController@update');
        Route::post('complete', 'Api\ProfileController@complete');
        Route::get('completion-status', 'Api\ProfileController@completionStatus');
        
        // Photo management
        Route::prefix('photos')->group(function () {
            Route::get('/', 'Api\PhotoController@index');
            Route::post('/', 'Api\PhotoController@store');
            Route::put('{photo}', 'Api\PhotoController@update');
            Route::delete('{photo}', 'Api\PhotoController@destroy');
            Route::post('{photo}/set-profile', 'Api\PhotoController@setAsProfile');
            Route::post('{photo}/toggle-private', 'Api\PhotoController@togglePrivate');
        });
        
        // Voice intro
        Route::prefix('voice')->group(function () {
            Route::post('/', 'Api\VoiceController@store');
            Route::get('/', 'Api\VoiceController@show');
            Route::delete('/', 'Api\VoiceController@destroy');
            Route::get('stream', 'Api\VoiceController@stream');
            Route::put('settings', 'Api\VoiceController@updateSettings');
        });
    });
    
    // Preferences
    Route::prefix('preferences')->group(function () {
        Route::get('/', 'Api\PreferenceController@show');
        Route::put('/', 'Api\PreferenceController@update');
    });
    
    // Horoscope
    Route::prefix('horoscope')->group(function () {
        Route::get('/', 'Api\HoroscopeController@show');
        Route::post('/', 'Api\HoroscopeController@store');
        Route::put('/', 'Api\HoroscopeController@update');
        Route::post('compatibility/{user}', 'Api\HoroscopeController@checkCompatibility');
    });
    
    // Matching and search
    Route::prefix('matches')->group(function () {
        Route::get('/', 'Api\MatchController@index');
        Route::get('daily', 'Api\MatchController@dailyMatches');
        Route::get('suggestions', 'Api\MatchController@suggestions');
        Route::post('{user}/like', 'Api\MatchController@like');
        Route::post('{user}/super-like', 'Api\MatchController@superLike');
        Route::post('{user}/dislike', 'Api\MatchController@dislike');
        Route::post('{user}/block', 'Api\MatchController@block');
        Route::get('liked-me', 'Api\MatchController@whoLikedMe');
        Route::get('mutual', 'Api\MatchController@mutualMatches');
    });
    
    // Search and browse
    Route::prefix('search')->group(function () {
        Route::post('/', 'Api\SearchController@search');
        Route::post('advanced', 'Api\SearchController@advancedSearch');
        Route::get('filters', 'Api\SearchController@getFilters');
        Route::post('save-search', 'Api\SearchController@saveSearch');
        Route::get('saved-searches', 'Api\SearchController@savedSearches');
    });
    
    // Browse profiles
    Route::prefix('browse')->group(function () {
        Route::get('/', 'Api\BrowseController@index');
        Route::get('premium', 'Api\BrowseController@premiumProfiles');
        Route::get('recent', 'Api\BrowseController@recentlyJoined');
        Route::get('verified', 'Api\BrowseController@verifiedProfiles');
    });
    
    // User profiles (viewing others)
    Route::prefix('users')->group(function () {
        Route::get('{user}', 'Api\UserController@show');
        Route::post('{user}/view', 'Api\UserController@recordView');
        Route::post('{user}/interest', 'Api\UserController@expressInterest');
        Route::post('{user}/report', 'Api\UserController@report');
        Route::get('{user}/photos', 'Api\UserController@photos');
        Route::post('{user}/request-photo-access', 'Api\UserController@requestPhotoAccess');
        Route::get('{user}/voice', 'Api\VoiceController@getUserVoice');
        Route::get('{user}/voice/stream', 'Api\VoiceController@streamUserVoice');
    });
    
    // Messaging and chat
    Route::prefix('chat')->group(function () {
        Route::get('conversations', 'Api\ChatController@conversations');
        Route::get('conversations/{conversation}', 'Api\ChatController@show');
        Route::post('conversations/{conversation}/messages', 'Api\ChatController@sendMessage');
        Route::put('messages/{message}', 'Api\ChatController@updateMessage');
        Route::delete('messages/{message}', 'Api\ChatController@deleteMessage');
        Route::post('messages/{message}/read', 'Api\ChatController@markAsRead');
        Route::post('conversations/{conversation}/block', 'Api\ChatController@blockConversation');
        Route::delete('conversations/{conversation}', 'Api\ChatController@deleteConversation');
    });
    
    // Subscriptions and payments
    Route::prefix('subscription')->group(function () {
        Route::get('/', 'Api\SubscriptionController@current');
        Route::get('plans', 'Api\SubscriptionController@plans');
        Route::post('subscribe', 'Api\SubscriptionController@subscribe');
        Route::post('cancel', 'Api\SubscriptionController@cancel');
        Route::get('history', 'Api\SubscriptionController@history');
        Route::post('payment/verify', 'Api\SubscriptionController@verifyPayment');
    });
    
    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', 'Api\NotificationController@index');
        Route::post('{notification}/read', 'Api\NotificationController@markAsRead');
        Route::post('read-all', 'Api\NotificationController@markAllAsRead');
        Route::delete('{notification}', 'Api\NotificationController@destroy');
        Route::get('unread-count', 'Api\NotificationController@unreadCount');
    });
    
    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', 'Api\SettingsController@show');
        Route::put('/', 'Api\SettingsController@update');
        Route::put('privacy', 'Api\SettingsController@updatePrivacy');
        Route::put('notifications', 'Api\SettingsController@updateNotifications');
        Route::post('deactivate', 'Api\SettingsController@deactivateAccount');
        Route::post('delete', 'Api\SettingsController@deleteAccount');
        Route::get('stats', 'Api\SettingsController@getAccountStats');
        Route::post('export-data', 'Api\SettingsController@exportData');
    });

    // Two-Factor Authentication
    Route::prefix('2fa')->group(function () {
        Route::get('status', 'Api\TwoFactorController@status');
        Route::post('setup', 'Api\TwoFactorController@setup');
        Route::post('verify-setup', 'Api\TwoFactorController@verifySetup');
        Route::post('disable', 'Api\TwoFactorController@disable');
        Route::post('recovery-codes', 'Api\TwoFactorController@generateRecoveryCodes');
        Route::post('send-code', 'Api\TwoFactorController@sendCode');
    });
    
    // Interests
    Route::prefix('interests')->group(function () {
        Route::get('/', 'Api\InterestController@index');
        Route::post('/', 'Api\InterestController@updateUserInterests');
    });
    
    // Analytics and insights (for premium users)
    Route::prefix('insights')->middleware('premium')->group(function () {
        Route::get('profile-views', 'Api\InsightsController@profileViews');
        Route::get('match-analytics', 'Api\InsightsController@matchAnalytics');
        Route::get('compatibility-reports', 'Api\InsightsController@compatibilityReports');
        Route::get('profile-optimization', 'Api\InsightsController@profileOptimization');
    });
});

// Admin routes (role-based access)
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'role:admin|moderator'])->group(function () {
    
    // Dashboard
    Route::get('dashboard', 'Api\Admin\DashboardController@index');
    Route::get('stats', 'Api\Admin\DashboardController@stats');
    
    // User management
    Route::prefix('users')->group(function () {
        Route::get('/', 'Api\Admin\UserController@index');
        Route::get('{user}', 'Api\Admin\UserController@show');
        Route::put('{user}/status', 'Api\Admin\UserController@updateStatus');
        Route::put('{user}/profile-status', 'Api\Admin\UserController@updateProfileStatus');
        Route::post('{user}/suspend', 'Api\Admin\UserController@suspend');
        Route::post('{user}/ban', 'Api\Admin\UserController@ban');
        Route::post('{user}/unban', 'Api\Admin\UserController@unban');
        Route::delete('{user}', 'Api\Admin\UserController@destroy');
    });
    
    // Photo moderation
    Route::prefix('photos')->group(function () {
        Route::get('pending', 'Api\Admin\PhotoController@pending');
        Route::post('{photo}/approve', 'Api\Admin\PhotoController@approve');
        Route::post('{photo}/reject', 'Api\Admin\PhotoController@reject');
    });
    
    // Reports management
    Route::prefix('reports')->group(function () {
        Route::get('/', 'Api\Admin\ReportController@index');
        Route::get('{report}', 'Api\Admin\ReportController@show');
        Route::put('{report}/status', 'Api\Admin\ReportController@updateStatus');
        Route::post('{report}/action', 'Api\Admin\ReportController@takeAction');
    });
    
    // Content management
    Route::prefix('content')->group(function () {
        Route::get('interests', 'Api\Admin\ContentController@interests');
        Route::post('interests', 'Api\Admin\ContentController@createInterest');
        Route::put('interests/{interest}', 'Api\Admin\ContentController@updateInterest');
        Route::delete('interests/{interest}', 'Api\Admin\ContentController@deleteInterest');
    });
    
    // System settings
    Route::prefix('settings')->group(function () {
        Route::get('/', 'Api\Admin\SettingsController@index');
        Route::put('/', 'Api\Admin\SettingsController@update');
    });
});

// Webhook routes (for payment gateways)
Route::prefix('webhooks')->group(function () {
    Route::post('stripe', 'Api\WebhookController@stripe');
    Route::post('payhere', 'Api\WebhookController@payhere');
    Route::post('webxpay', 'Api\WebhookController@webxpay');
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'error' => 'The requested endpoint does not exist'
    ], 404);
}); 