<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProfileView extends Model
{
    use HasFactory;

    protected $fillable = [
        'viewer_id',
        'viewed_user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'referrer',
        'is_anonymous',
        'duration_seconds',
        'sections_viewed',
        'profile_contacted',
        'contacted_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'profile_contacted' => 'boolean',
        'contacted_at' => 'datetime',
        'sections_viewed' => 'array',
    ];

    /**
     * Get the viewer user
     */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    /**
     * Get the viewed user
     */
    public function viewedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewed_user_id');
    }

    /**
     * Record a profile view
     */
    public static function recordView(
        User $viewedUser,
        ?User $viewer = null,
        array $metadata = []
    ): ?self {
        // Don't record self-views
        if ($viewer && $viewer->id === $viewedUser->id) {
            return null;
        }

        // Check for duplicate views in the last hour
        $recentView = static::where('viewed_user_id', $viewedUser->id)
            ->where('viewer_id', $viewer?->id)
            ->where('ip_address', request()->ip())
            ->where('created_at', '>=', now()->subHour())
            ->first();

        if ($recentView) {
            // Update the existing view with new metadata
            $recentView->update([
                'duration_seconds' => $metadata['duration_seconds'] ?? null,
                'sections_viewed' => $metadata['sections_viewed'] ?? null,
            ]);
            return $recentView;
        }

        $view = static::create([
            'viewer_id' => $viewer?->id,
            'viewed_user_id' => $viewedUser->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'device_type' => static::detectDeviceType(request()->userAgent()),
            'browser' => static::detectBrowser(request()->userAgent()),
            'platform' => static::detectPlatform(request()->userAgent()),
            'referrer' => request()->header('referer'),
            'is_anonymous' => $viewer === null,
            'duration_seconds' => $metadata['duration_seconds'] ?? null,
            'sections_viewed' => $metadata['sections_viewed'] ?? null,
        ]);

        // Update user's view counters
        static::updateViewCounters($viewedUser, $viewer);

        // Send notification to viewed user if they're premium
        if ($viewedUser->is_premium_active && $viewer) {
            static::sendViewNotification($viewedUser, $viewer);
        }

        return $view;
    }

    /**
     * Update user view counters
     */
    private static function updateViewCounters(User $viewedUser, ?User $viewer): void
    {
        DB::transaction(function () use ($viewedUser, $viewer) {
            // Increment total views
            $viewedUser->increment('total_profile_views');
            $viewedUser->update(['last_profile_view_at' => now()]);

            // Increment unique views if it's a new viewer
            if ($viewer) {
                $existingView = static::where('viewed_user_id', $viewedUser->id)
                    ->where('viewer_id', $viewer->id)
                    ->where('created_at', '<', now()->subHour())
                    ->exists();

                if (!$existingView) {
                    $viewedUser->increment('unique_profile_views');
                }
            } else {
                // For anonymous views, check by IP
                $existingIPView = static::where('viewed_user_id', $viewedUser->id)
                    ->where('ip_address', request()->ip())
                    ->where('is_anonymous', true)
                    ->where('created_at', '<', now()->subDay())
                    ->exists();

                if (!$existingIPView) {
                    $viewedUser->increment('unique_profile_views');
                }
            }
        });
    }

    /**
     * Send view notification
     */
    private static function sendViewNotification(User $viewedUser, User $viewer): void
    {
        try {
            $pushService = app(\App\Services\PushNotificationService::class);
            $pushService->sendProfileViewNotification($viewedUser, $viewer);
        } catch (\Exception $e) {
            \Log::error('Failed to send profile view notification', [
                'viewed_user_id' => $viewedUser->id,
                'viewer_id' => $viewer->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mark that viewer contacted the viewed user
     */
    public function markAsContacted(): void
    {
        $this->update([
            'profile_contacted' => true,
            'contacted_at' => now(),
        ]);
    }

    /**
     * Get profile views for a user
     */
    public static function getViewsFor(User $user, int $limit = 50, int $offset = 0): array
    {
        $views = static::where('viewed_user_id', $user->id)
            ->with('viewer:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'views' => $views->map(function ($view) {
                return [
                    'id' => $view->id,
                    'viewer' => $view->viewer ? [
                        'id' => $view->viewer->id,
                        'name' => $view->viewer->first_name . ' ' . $view->viewer->last_name,
                        'profile_picture' => $view->viewer->profilePicture?->file_path,
                    ] : null,
                    'is_anonymous' => $view->is_anonymous,
                    'device_type' => $view->device_type,
                    'viewed_at' => $view->created_at->toISOString(),
                    'duration_seconds' => $view->duration_seconds,
                    'sections_viewed' => $view->sections_viewed,
                    'profile_contacted' => $view->profile_contacted,
                ];
            }),
            'total_views' => $user->total_profile_views,
            'unique_views' => $user->unique_profile_views,
        ];
    }

    /**
     * Get view analytics for a user
     */
    public static function getAnalyticsFor(User $user, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $views = static::where('viewed_user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->get();

        $dailyViews = $views->groupBy(function ($view) {
            return $view->created_at->format('Y-m-d');
        })->map->count();

        return [
            'period_views' => $views->count(),
            'unique_viewers' => $views->whereNotNull('viewer_id')->unique('viewer_id')->count(),
            'anonymous_views' => $views->where('is_anonymous', true)->count(),
            'average_duration' => $views->whereNotNull('duration_seconds')->avg('duration_seconds'),
            'device_breakdown' => $views->groupBy('device_type')->map->count(),
            'browser_breakdown' => $views->groupBy('browser')->map->count(),
            'daily_views' => $dailyViews,
            'conversion_rate' => $views->where('profile_contacted', true)->count() / max($views->count(), 1) * 100,
            'most_viewed_sections' => static::getMostViewedSections($views),
        ];
    }

    /**
     * Get most viewed sections
     */
    private static function getMostViewedSections($views): array
    {
        $allSections = [];
        
        foreach ($views as $view) {
            if ($view->sections_viewed) {
                foreach ($view->sections_viewed as $section) {
                    $allSections[] = $section;
                }
            }
        }

        return array_count_values($allSections);
    }

    /**
     * Detect device type from user agent
     */
    private static function detectDeviceType(string $userAgent): string
    {
        if (preg_match('/mobile|android|iphone|ipad/i', $userAgent)) {
            return 'mobile';
        } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * Detect browser from user agent
     */
    private static function detectBrowser(string $userAgent): string
    {
        if (preg_match('/chrome/i', $userAgent)) return 'Chrome';
        if (preg_match('/firefox/i', $userAgent)) return 'Firefox';
        if (preg_match('/safari/i', $userAgent)) return 'Safari';
        if (preg_match('/edge/i', $userAgent)) return 'Edge';
        if (preg_match('/opera/i', $userAgent)) return 'Opera';
        return 'Other';
    }

    /**
     * Detect platform from user agent
     */
    private static function detectPlatform(string $userAgent): string
    {
        if (preg_match('/windows/i', $userAgent)) return 'Windows';
        if (preg_match('/macintosh|mac os/i', $userAgent)) return 'Mac';
        if (preg_match('/linux/i', $userAgent)) return 'Linux';
        if (preg_match('/android/i', $userAgent)) return 'Android';
        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) return 'iOS';
        return 'Other';
    }

    /**
     * Clean up old views
     */
    public static function cleanupOld(int $monthsToKeep = 6): int
    {
        return static::where('created_at', '<', now()->subMonths($monthsToKeep))->delete();
    }

    /**
     * Get top viewed profiles
     */
    public static function getTopViewedProfiles(int $days = 30, int $limit = 10): array
    {
        return static::where('created_at', '>=', now()->subDays($days))
            ->select('viewed_user_id', DB::raw('COUNT(*) as view_count'))
            ->groupBy('viewed_user_id')
            ->orderByDesc('view_count')
            ->with('viewedUser:id,first_name,last_name')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => $item->viewedUser,
                    'view_count' => $item->view_count,
                ];
            })->toArray();
    }
} 