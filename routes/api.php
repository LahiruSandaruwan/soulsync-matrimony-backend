<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InterestController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\VoiceController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\HoroscopeController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\BrowseController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\InsightsController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\PhotoController as AdminPhotoController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ContentController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\VideoCallController;
use App\Models\User;
use App\Http\Controllers\Api\EmailVerificationController;

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

// Explicit model binding for user
Route::model('user', User::class);

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
        Route::get('interests', [InterestController::class, 'index']);
        Route::get('countries', [LocationController::class, 'countries']);
        Route::get('states/{country}', [LocationController::class, 'states']);
        Route::get('cities/{state}', [LocationController::class, 'cities']);
        Route::get('subscription-plans', [SubscriptionController::class, 'plans']);
    });
    
    // Public subscription plans route
    Route::get('subscription/plans', [SubscriptionController::class, 'plans']);
    
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

// Email verification routes
Route::prefix('email')->group(function () {
    Route::get('verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify');
    Route::get('verify/{id}/status', [EmailVerificationController::class, 'status']);
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
        Route::delete('delete-account', [AuthController::class, 'deleteAccount']);
    });
    
    // Profile management
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('complete', [ProfileController::class, 'complete']);
        Route::get('completion-status', [ProfileController::class, 'completionStatus']);
        
        // Photo management
        Route::prefix('photos')->group(function () {
            Route::get('/', [PhotoController::class, 'index']);
            Route::post('/', [PhotoController::class, 'store']);
            Route::put('{photo}', [PhotoController::class, 'update']);
            Route::delete('{photo}', [PhotoController::class, 'destroy']);
            Route::post('{photo}/set-profile', [PhotoController::class, 'setAsProfile']);
            Route::post('{photo}/toggle-private', [PhotoController::class, 'togglePrivate']);
        });
        
        // Voice intro
        Route::prefix('voice')->group(function () {
            Route::post('/', [VoiceController::class, 'store']);
            Route::get('/', [VoiceController::class, 'show']);
            Route::delete('/', [VoiceController::class, 'destroy']);
            Route::get('stream', [VoiceController::class, 'stream']);
            Route::put('settings', [VoiceController::class, 'updateSettings']);
        });
    });
    
    // Preferences
    Route::prefix('preferences')->group(function () {
        Route::get('/', [PreferenceController::class, 'show']);
        Route::put('/', [PreferenceController::class, 'update']);
    });
    
    // Horoscope
    Route::prefix('horoscope')->group(function () {
        Route::get('/', [HoroscopeController::class, 'show']);
        Route::post('/', [HoroscopeController::class, 'store']);
        Route::put('/', [HoroscopeController::class, 'update']);
        Route::post('compatibility/{user}', [HoroscopeController::class, 'checkCompatibility']);
    });
    
    // Matching and search
    Route::prefix('matches')->group(function () {
        Route::get('/', [MatchController::class, 'index']);
        Route::get('daily', [MatchController::class, 'dailyMatches']);
        Route::get('suggestions', [MatchController::class, 'suggestions']);
        Route::get('interaction-status/{user}', [MatchController::class, 'interactionStatus']);
        Route::post('{user}/like', [MatchController::class, 'like']);
        Route::post('{user}/super-like', [MatchController::class, 'superLike']);
        Route::post('{user}/dislike', [MatchController::class, 'dislike']);
        Route::post('{user}/block', [MatchController::class, 'block']);
        Route::post('{match}/boost', [MatchController::class, 'boost']);
        Route::get('liked-me', [MatchController::class, 'whoLikedMe']);
        Route::get('mutual', [MatchController::class, 'mutualMatches']);
    });
    
    // Search and browse
    Route::prefix('search')->group(function () {
        Route::post('/', [SearchController::class, 'search']);
        Route::post('advanced', [SearchController::class, 'advancedSearch']);
        Route::get('filters', [SearchController::class, 'getFilters']);
        Route::get('interests', [SearchController::class, 'searchByInterests']);
        Route::get('suggestions', [SearchController::class, 'getSearchSuggestions']);
        Route::get('recent', [SearchController::class, 'getRecentSearches']);
        Route::post('save', [SearchController::class, 'saveSearch']);
        Route::get('saved', [SearchController::class, 'savedSearches']);
        Route::delete('saved/{search}', [SearchController::class, 'deleteSavedSearch']);
        Route::get('stats', [SearchController::class, 'getSearchStats']);
    });
    
    // Browse profiles
    Route::prefix('browse')->group(function () {
        Route::get('/', [BrowseController::class, 'index']);
        Route::get('premium', [BrowseController::class, 'premiumProfiles']);
        Route::get('recent', [BrowseController::class, 'recentlyJoined']);
        Route::get('verified', [BrowseController::class, 'verifiedProfiles']);
    });
    
    // User profiles (viewing others)
    Route::prefix('users')->group(function () {
        Route::get('{user}', [UserController::class, 'show']);
        Route::post('{user}/view', [UserController::class, 'recordView']);
        Route::post('{user}/interest', [UserController::class, 'expressInterest']);
        Route::post('{user}/report', [UserController::class, 'report']);
        Route::get('{user}/photos', [UserController::class, 'photos']);
        Route::post('{user}/request-photo-access', [UserController::class, 'requestPhotoAccess']);
        Route::get('{user}/voice', [VoiceController::class, 'getUserVoice']);
        Route::get('{user}/voice/stream', [VoiceController::class, 'streamUserVoice']);
    });
    
    // Messaging and chat
    Route::prefix('chat')->group(function () {
        Route::post('start-conversation', [ChatController::class, 'startConversation']);
        Route::get('conversations', [ChatController::class, 'conversations']);
        Route::get('conversations/{conversation}', [ChatController::class, 'show']);
        Route::post('conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
        Route::put('messages/{message}', [ChatController::class, 'updateMessage']);
        Route::delete('messages/{message}', [ChatController::class, 'deleteMessage']);
        Route::post('messages/{message}/read', [ChatController::class, 'markAsRead']);
        Route::post('conversations/{conversation}/block', [ChatController::class, 'blockConversation']);
        Route::delete('conversations/{conversation}', [ChatController::class, 'deleteConversation']);
    });
    
    // Video calls
    Route::prefix('video-calls')->group(function () {
        Route::get('/', [VideoCallController::class, 'index']);
        Route::post('initiate', [VideoCallController::class, 'initiate']);
        Route::get('{videoCall}', [VideoCallController::class, 'show']);
        Route::post('{videoCall}/accept', [VideoCallController::class, 'accept']);
        Route::post('{videoCall}/reject', [VideoCallController::class, 'reject']);
        Route::post('{videoCall}/end', [VideoCallController::class, 'end']);
    });
    
    // Subscriptions and payments
    Route::prefix('subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'current']);
        Route::get('plans', [SubscriptionController::class, 'plans']);
        Route::get('status', [SubscriptionController::class, 'current']);
        Route::get('features', [SubscriptionController::class, 'features']);
        Route::post('subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('cancel', [SubscriptionController::class, 'cancel']);
        Route::post('reactivate', [SubscriptionController::class, 'reactivate']);
        Route::post('upgrade', [SubscriptionController::class, 'upgrade']);
        Route::post('downgrade', [SubscriptionController::class, 'downgrade']);
        Route::post('start-trial', [SubscriptionController::class, 'startTrial']);
        Route::post('process-renewals', [SubscriptionController::class, 'processRenewals']);
        Route::get('history', [SubscriptionController::class, 'history']);
        Route::post('payment/verify', [SubscriptionController::class, 'verifyPayment']);
        Route::post('payment/refund', [SubscriptionController::class, 'refund']);
    });

    // Payment methods
    Route::prefix('payment')->group(function () {
        Route::get('methods', [SubscriptionController::class, 'paymentMethods']);
    });
    
    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('batch/read', [NotificationController::class, 'markBatchAsRead']);
        Route::post('cleanup', [NotificationController::class, 'cleanup']);
        Route::post('push-subscription', [NotificationController::class, 'subscribeToPush']);
        Route::delete('push-subscription', [NotificationController::class, 'unsubscribeFromPush']);
        Route::get('{notification}', [NotificationController::class, 'show']);
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('{notification}', [NotificationController::class, 'destroy']);
    });
    
    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'show']);
        Route::put('/', [SettingsController::class, 'update']);
        Route::put('privacy', [SettingsController::class, 'updatePrivacy']);
        Route::put('notifications', [SettingsController::class, 'updateNotifications']);
        Route::post('deactivate', [SettingsController::class, 'deactivateAccount']);
        Route::post('delete', [SettingsController::class, 'deleteAccount']);
        Route::get('stats', [SettingsController::class, 'getAccountStats']);
        Route::post('export-data', [SettingsController::class, 'exportData']);
    });

    // Two-Factor Authentication
    Route::prefix('2fa')->group(function () {
        Route::get('status', [TwoFactorController::class, 'status']);
        Route::post('setup', [TwoFactorController::class, 'setup']);
        Route::post('verify-setup', [TwoFactorController::class, 'verifySetup']);
        Route::post('disable', [TwoFactorController::class, 'disable']);
        Route::post('recovery-codes', [TwoFactorController::class, 'generateRecoveryCodes']);
        Route::post('send-code', [TwoFactorController::class, 'sendCode']);
    });
    
    // Interests
    Route::prefix('interests')->group(function () {
        Route::get('/', [InterestController::class, 'index']);
        Route::post('/', [InterestController::class, 'updateUserInterests']);
    });
    
    // Analytics and insights (for premium users)
    Route::prefix('insights')->middleware('premium')->group(function () {
        Route::get('profile-views', [InsightsController::class, 'profileViews']);
        Route::get('match-analytics', [InsightsController::class, 'matchAnalytics']);
        Route::get('compatibility-reports', [InsightsController::class, 'compatibilityReports']);
        Route::get('profile-optimization', [InsightsController::class, 'profileOptimization']);
    });

    // Email verification (authenticated)
    Route::prefix('email')->group(function () {
        Route::post('resend', [EmailVerificationController::class, 'resend']);
        Route::get('check', [EmailVerificationController::class, 'check']);
    });
});

// Admin routes (role-based access)
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('stats', [DashboardController::class, 'stats']);
    
    // User management
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('analytics', [AdminUserController::class, 'analytics']);
        Route::post('export', [AdminUserController::class, 'export']);
        Route::post('bulk-action', [AdminUserController::class, 'bulkAction']);
        Route::get('{user}', [AdminUserController::class, 'show']);
        Route::put('{user}/status', [AdminUserController::class, 'updateStatus']);
        Route::put('{user}/profile-status', [AdminUserController::class, 'updateProfileStatus']);
        Route::post('{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('{user}/ban', [AdminUserController::class, 'ban']);
        Route::post('{user}/unban', [AdminUserController::class, 'unban']);
        Route::delete('{user}', [AdminUserController::class, 'destroy']);
    });
    
    // Photo moderation
    Route::prefix('photos')->group(function () {
        Route::get('pending', [AdminPhotoController::class, 'pending']);
        Route::post('{photo}/approve', [AdminPhotoController::class, 'approve']);
        Route::post('{photo}/reject', [AdminPhotoController::class, 'reject']);
    });
    
    // Reports management
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('{report}', [ReportController::class, 'show']);
        Route::put('{report}/status', [ReportController::class, 'updateStatus']);
        Route::post('{report}/action', [ReportController::class, 'takeAction']);
    });
    
    // Content management
    Route::prefix('content')->group(function () {
        Route::get('interests', [ContentController::class, 'interests']);
        Route::post('interests', [ContentController::class, 'createInterest']);
        Route::put('interests/{interest}', [ContentController::class, 'updateInterest']);
        Route::delete('interests/{interest}', [ContentController::class, 'deleteInterest']);
    });
    
    // System settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [AdminSettingsController::class, 'index']);
        Route::put('/', [AdminSettingsController::class, 'update']);
    });
    
    // System health
    Route::get('system/health', [AdminSettingsController::class, 'systemHealth']);
    
    // Revenue analytics
    Route::get('revenue/analytics', [AdminSettingsController::class, 'revenueAnalytics']);
});

// Webhook routes (no authentication required)
Route::prefix('webhooks')->group(function () {
    Route::post('stripe', [WebhookController::class, 'stripe']);
    Route::post('paypal', [WebhookController::class, 'paypal']);
    Route::post('payhere', [WebhookController::class, 'payhere']);
    Route::post('webxpay', [WebhookController::class, 'webxpay']);
    Route::post('test', [WebhookController::class, 'test']);
    Route::get('health', [WebhookController::class, 'health']);
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'error' => 'The requested endpoint does not exist'
    ], 404);
}); 