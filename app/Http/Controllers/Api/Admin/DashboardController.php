<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\Report;
use App\Models\UserPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get admin dashboard overview
     */
    public function index(): JsonResponse
    {
        try {
            // For now, just return a basic response to test the endpoint
            return response()->json([
                'success' => true,
                'message' => 'Admin dashboard accessed successfully',
                'data' => [
                    'total_users' => 0,
                    'active_users' => 0,
                    'premium_users' => 0,
                    'total_matches' => 0,
                    'total_conversations' => 0,
                    'total_messages' => 0,
                    'pending_reports' => 0,
                    'pending_photos' => 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accessing dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            // For now, just return a basic response to test the endpoint
            return response()->json([
                'success' => true,
                'message' => 'Admin stats accessed successfully',
                'data' => [
                    'overview' => [
                        'total_users' => 0,
                        'active_users' => 0,
                        'premium_users' => 0,
                        'total_matches' => 0,
                        'total_messages' => 0,
                        'total_revenue' => 0,
                    ],
                    'daily_stats' => [],
                    'user_distribution' => [],
                    'revenue_breakdown' => [],
                    'top_metrics' => [],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accessing stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    private function getUserStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $premiumUsers = User::where('is_premium', true)->count();
        $newUsersToday = User::whereDate('created_at', today())->count();
        $newUsersThisWeek = User::whereBetween('created_at', [
            now()->startOfWeek(), now()->endOfWeek()
        ])->count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)->count();

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'inactive' => $totalUsers - $activeUsers,
            'premium' => $premiumUsers,
            'premium_percentage' => $totalUsers > 0 ? round(($premiumUsers / $totalUsers) * 100, 2) : 0,
            'new_today' => $newUsersToday,
            'new_this_week' => $newUsersThisWeek,
            'new_this_month' => $newUsersThisMonth,
            'gender_distribution' => User::selectRaw('gender, count(*) as count')
                ->groupBy('gender')
                ->pluck('count', 'gender')
                ->toArray(),
            'country_distribution' => User::selectRaw('country_code, count(*) as count')
                ->groupBy('country_code')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'country_code')
                ->toArray(),
        ];
    }

    /**
     * Get activity statistics
     */
    private function getActivityStats(): array
    {
        $totalMatches = UserMatch::count();
        $successfulMatches = UserMatch::whereNotNull('matched_at')->count();
        $totalMessages = Message::count();
        $dailyActiveUsers = User::where('last_active_at', '>=', now()->subDay())->count();
        $weeklyActiveUsers = User::where('last_active_at', '>=', now()->subWeek())->count();

        return [
            'total_interactions' => $totalMatches,
            'successful_matches' => $successfulMatches,
            'match_success_rate' => $totalMatches > 0 ? round(($successfulMatches / $totalMatches) * 100, 2) : 0,
            'total_messages' => $totalMessages,
            'avg_messages_per_match' => $successfulMatches > 0 ? round($totalMessages / $successfulMatches, 2) : 0,
            'daily_active_users' => $dailyActiveUsers,
            'weekly_active_users' => $weeklyActiveUsers,
            'user_engagement' => [
                'daily' => User::count() > 0 ? round(($dailyActiveUsers / User::count()) * 100, 2) : 0,
                'weekly' => User::count() > 0 ? round(($weeklyActiveUsers / User::count()) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get revenue statistics
     */
    private function getRevenueStats(): array
    {
        $totalRevenue = Subscription::where('status', 'active')->sum('amount_usd');
        $monthlyRevenue = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->sum('amount_usd');
        $avgRevenuePerUser = User::where('is_premium', true)->count() > 0 ? 
            $totalRevenue / User::where('is_premium', true)->count() : 0;

        $planDistribution = Subscription::where('status', 'active')
            ->selectRaw('plan_type, count(*) as count, sum(amount_usd) as revenue')
            ->groupBy('plan_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->plan_type => [
                    'count' => $item->count,
                    'revenue' => $item->revenue
                ]];
            })->toArray();

        return [
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'avg_revenue_per_user' => round($avgRevenuePerUser, 2),
            'plan_distribution' => $planDistribution,
            'conversion_rate' => User::count() > 0 ? 
                round((User::where('is_premium', true)->count() / User::count()) * 100, 2) : 0,
        ];
    }

    /**
     * Get moderation statistics
     */
    private function getModerationStats(): array
    {
        $pendingReports = Report::where('status', 'pending')->count();
        $pendingPhotos = UserPhoto::where('status', 'pending')->count();
        $pendingProfiles = User::where('profile_status', 'pending_approval')->count();

        return [
            'pending_reports' => $pendingReports,
            'pending_photos' => $pendingPhotos,
            'pending_profiles' => $pendingProfiles,
            'total_pending' => $pendingReports + $pendingPhotos + $pendingProfiles,
            'recent_reports' => Report::where('created_at', '>=', now()->subWeek())
                ->selectRaw('reason, count(*) as count')
                ->groupBy('reason')
                ->pluck('count', 'reason')
                ->toArray(),
        ];
    }

    /**
     * Get growth statistics
     */
    private function getGrowthStats(): array
    {
        $lastMonthUsers = User::whereMonth('created_at', now()->subMonth()->month)->count();
        $thisMonthUsers = User::whereMonth('created_at', now()->month)->count();
        $userGrowthRate = $lastMonthUsers > 0 ? 
            round((($thisMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100, 2) : 0;

        $lastMonthRevenue = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount_usd');
        $thisMonthRevenue = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->sum('amount_usd');
        $revenueGrowthRate = $lastMonthRevenue > 0 ? 
            round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 2) : 0;

        return [
            'user_growth_rate' => $userGrowthRate,
            'revenue_growth_rate' => $revenueGrowthRate,
            'monthly_users' => [
                'last_month' => $lastMonthUsers,
                'this_month' => $thisMonthUsers,
            ],
            'monthly_revenue' => [
                'last_month' => $lastMonthRevenue,
                'this_month' => $thisMonthRevenue,
            ],
        ];
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(): array
    {
        return [
            'recent_users' => User::latest()
                ->limit(5)
                ->get(['id', 'first_name', 'last_name', 'created_at'])
                ->toArray(),
            'recent_matches' => UserMatch::with(['user:id,first_name', 'targetUser:id,first_name'])
                ->whereNotNull('matched_at')
                ->latest('matched_at')
                ->limit(5)
                ->get()
                ->map(function ($match) {
                    return [
                        'user1' => $match->user->first_name,
                        'user2' => $match->targetUser->first_name,
                        'matched_at' => $match->matched_at,
                    ];
                })
                ->toArray(),
            'recent_reports' => Report::with(['reporter:id,first_name', 'reportedUser:id,first_name'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($report) {
                    return [
                        'reporter' => $report->reporter->first_name,
                        'reported_user' => $report->reportedUser->first_name,
                        'reason' => $report->reason,
                        'created_at' => $report->created_at,
                    ];
                })
                ->toArray(),
        ];
    }

    /**
     * Get daily statistics for charts
     */
    private function getDailyStats($startDate): array
    {
        $dailyUsers = User::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $dailyMatches = UserMatch::where('matched_at', '>=', $startDate)
            ->whereNotNull('matched_at')
            ->selectRaw('DATE(matched_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $dailyRevenue = Subscription::where('created_at', '>=', $startDate)
            ->where('status', 'active')
            ->selectRaw('DATE(created_at) as date, sum(amount_usd) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('revenue', 'date')
            ->toArray();

        return [
            'users' => $dailyUsers,
            'matches' => $dailyMatches,
            'revenue' => $dailyRevenue,
        ];
    }

    /**
     * Get user distribution by various factors
     */
    private function getUserDistribution(): array
    {
        return [
            'by_age' => User::selectRaw('
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 25 THEN "18-25"
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 26 AND 35 THEN "26-35"
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 36 AND 45 THEN "36-45"
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 45 THEN "45+"
                    ELSE "Unknown"
                END as age_group,
                count(*) as count
            ')
                ->groupBy('age_group')
                ->pluck('count', 'age_group')
                ->toArray(),
            'by_status' => User::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_registration_method' => User::selectRaw('registration_method, count(*) as count')
                ->groupBy('registration_method')
                ->pluck('count', 'registration_method')
                ->toArray(),
        ];
    }

    /**
     * Get revenue breakdown
     */
    private function getRevenueBreakdown(): array
    {
        return [
            'by_plan' => Subscription::where('status', 'active')
                ->selectRaw('plan_type, sum(amount_usd) as revenue, count(*) as count')
                ->groupBy('plan_type')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->plan_type => [
                        'revenue' => $item->revenue,
                        'count' => $item->count
                    ]];
                })
                ->toArray(),
            'by_payment_method' => Subscription::where('status', 'active')
                ->selectRaw('payment_method, sum(amount_usd) as revenue, count(*) as count')
                ->groupBy('payment_method')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->payment_method => [
                        'revenue' => $item->revenue,
                        'count' => $item->count
                    ]];
                })
                ->toArray(),
        ];
    }

    /**
     * Get top metrics
     */
    private function getTopMetrics(): array
    {
        return [
            'most_active_users' => User::withCount(['sentMessages'])
                ->orderBy('sent_messages_count', 'desc')
                ->limit(10)
                ->get(['id', 'first_name', 'last_name', 'sent_messages_count'])
                ->toArray(),
            'top_countries' => User::selectRaw('country_code, count(*) as user_count')
                ->groupBy('country_code')
                ->orderBy('user_count', 'desc')
                ->limit(10)
                ->get()
                ->toArray(),
            'successful_matchers' => User::withCount(['matches' => function ($query) {
                $query->whereNotNull('matched_at');
            }])
                ->orderBy('matches_count', 'desc')
                ->limit(10)
                ->get(['id', 'first_name', 'last_name', 'matches_count'])
                ->toArray(),
        ];
    }
}
