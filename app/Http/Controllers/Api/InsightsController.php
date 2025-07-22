<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\UserProfile;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InsightsController extends Controller
{
    /**
     * Get profile views analytics
     */
    public function profileViews(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'period' => 'sometimes|in:7d,30d,90d,1y',
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $period = $request->get('period', '30d');
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 50);
            $offset = ($page - 1) * $limit;

            // Calculate date range
            $dateRange = $this->getDateRange($period);

            // Get profile views data (simulated for now)
            $profileViews = $this->getProfileViewsData($user, $dateRange, $offset, $limit);
            
            // Get summary statistics
            $totalViews = $this->getTotalProfileViews($user, $dateRange);
            $uniqueViews = $this->getUniqueProfileViews($user, $dateRange);
            $averageDaily = $this->getAverageDailyViews($user, $dateRange);
            
            // Get chart data for the period
            $chartData = $this->getProfileViewsChart($user, $dateRange);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_views' => $totalViews,
                        'unique_viewers' => $uniqueViews,
                        'average_daily' => $averageDaily,
                        'period' => $period,
                        'date_range' => [
                            'from' => $dateRange['from']->toDateString(),
                            'to' => $dateRange['to']->toDateString(),
                        ],
                    ],
                    'chart_data' => $chartData,
                    'recent_viewers' => $profileViews,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalViews,
                        'total_pages' => ceil($totalViews / $limit),
                        'has_more' => ($page * $limit) < $totalViews,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile views analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match analytics
     */
    public function matchAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'period' => 'sometimes|in:7d,30d,90d,1y',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $period = $request->get('period', '30d');
            $dateRange = $this->getDateRange($period);

            // Get match statistics
            $matchStats = [
                'total_likes_sent' => UserMatch::where('user_id', $user->id)
                    ->where('action', 'like')
                    ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                    ->count(),
                
                'total_likes_received' => UserMatch::where('target_user_id', $user->id)
                    ->where('action', 'like')
                    ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                    ->count(),
                
                'super_likes_sent' => UserMatch::where('user_id', $user->id)
                    ->where('action', 'super_like')
                    ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                    ->count(),
                
                'super_likes_received' => UserMatch::where('target_user_id', $user->id)
                    ->where('action', 'super_like')
                    ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                    ->count(),
                
                'mutual_matches' => $this->getMutualMatches($user, $dateRange),
                
                'response_rate' => $this->getResponseRate($user, $dateRange),
                
                'match_success_rate' => $this->getMatchSuccessRate($user, $dateRange),
            ];

            // Get chart data
            $chartData = $this->getMatchAnalyticsChart($user, $dateRange);

            // Get demographics of people who liked the user
            $demographics = $this->getLikerDemographics($user, $dateRange);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $matchStats,
                    'chart_data' => $chartData,
                    'demographics' => $demographics,
                    'period' => $period,
                    'date_range' => [
                        'from' => $dateRange['from']->toDateString(),
                        'to' => $dateRange['to']->toDateString(),
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get match analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get compatibility reports
     */
    public function compatibilityReports(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
            'min_score' => 'sometimes|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $minScore = $request->get('min_score', 70);
            $offset = ($page - 1) * $limit;

            // Get high compatibility matches
            $compatibilityReports = $this->getCompatibilityReports($user, $minScore, $offset, $limit);

            // Get compatibility distribution
            $distribution = $this->getCompatibilityDistribution($user);

            // Get average compatibility score
            $averageScore = $this->getAverageCompatibilityScore($user);

            // Get top compatibility factors
            $topFactors = $this->getTopCompatibilityFactors($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'average_compatibility' => $averageScore,
                        'high_compatibility_matches' => count($compatibilityReports),
                        'total_analyzed' => $this->getTotalAnalyzedProfiles($user),
                    ],
                    'compatibility_reports' => $compatibilityReports,
                    'distribution' => $distribution,
                    'top_factors' => $topFactors,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'has_more' => count($compatibilityReports) === $limit,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get compatibility reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get profile optimization suggestions
     */
    public function profileOptimization(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $profile = $user->profile;
            $suggestions = [];

            // Analyze profile completeness
            $completionScore = $user->profile_completion_percentage ?? 0;
            
            if ($completionScore < 90) {
                $suggestions[] = [
                    'type' => 'completion',
                    'priority' => 'high',
                    'title' => 'Complete Your Profile',
                    'description' => 'Profiles with 90%+ completion get 3x more views',
                    'current_score' => $completionScore,
                    'target_score' => 90,
                ];
            }

            // Check for profile photo
            $hasProfilePhoto = $user->photos()->where('is_profile_picture', true)->exists();
            if (!$hasProfilePhoto) {
                $suggestions[] = [
                    'type' => 'photo',
                    'priority' => 'critical',
                    'title' => 'Add Profile Photo',
                    'description' => 'Profiles with photos get 10x more likes',
                    'action' => 'upload_profile_photo',
                ];
            }

            // Check for bio
            if (!$profile || !$profile->bio || strlen($profile->bio) < 50) {
                $suggestions[] = [
                    'type' => 'bio',
                    'priority' => 'high',
                    'title' => 'Write a Compelling Bio',
                    'description' => 'A good bio increases your match rate by 40%',
                    'current_length' => $profile ? strlen($profile->bio ?? '') : 0,
                    'recommended_length' => '100-300 characters',
                ];
            }

            // Check for interests
            $interestCount = $user->interests()->count();
            if ($interestCount < 3) {
                $suggestions[] = [
                    'type' => 'interests',
                    'priority' => 'medium',
                    'title' => 'Add More Interests',
                    'description' => 'Users with 5+ interests get better matches',
                    'current_count' => $interestCount,
                    'recommended_count' => 5,
                ];
            }

            // Activity suggestion
            $lastActivity = $user->last_seen_at;
            if (!$lastActivity || $lastActivity->lt(now()->subDays(7))) {
                $suggestions[] = [
                    'type' => 'activity',
                    'priority' => 'medium',
                    'title' => 'Stay Active',
                    'description' => 'Active users appear higher in search results',
                    'last_activity' => $lastActivity ? $lastActivity->diffForHumans() : 'Never',
                ];
            }

            // Performance metrics
            $metrics = [
                'profile_views_last_week' => rand(5, 50), // Simulated data
                'likes_received_last_week' => rand(2, 20),
                'matches_last_week' => rand(1, 10),
                'response_rate' => rand(30, 90) . '%',
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'optimization_score' => min(100, $completionScore + count($suggestions) * 5),
                    'suggestions' => $suggestions,
                    'performance_metrics' => $metrics,
                    'trends' => [
                        'profile_views' => 'up',
                        'likes_received' => 'stable',
                        'matches' => 'up',
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile optimization suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get date range based on period
     */
    private function getDateRange(string $period): array
    {
        $to = Carbon::now();
        
        switch ($period) {
            case '7d':
                $from = $to->copy()->subDays(7);
                break;
            case '30d':
                $from = $to->copy()->subDays(30);
                break;
            case '90d':
                $from = $to->copy()->subDays(90);
                break;
            case '1y':
                $from = $to->copy()->subYear();
                break;
            default:
                $from = $to->copy()->subDays(30);
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Get profile views data (simulated)
     */
    private function getProfileViewsData($user, $dateRange, $offset, $limit): array
    {
        // This would typically query a profile_views table
        // For now, returning simulated data
        $viewers = User::where('id', '!=', $user->id)
            ->inRandomOrder()
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $viewers->map(function ($viewer) use ($dateRange) {
            return [
                'viewer_id' => $viewer->id,
                'viewer_name' => $viewer->first_name,
                'viewer_age' => rand(25, 45),
                'viewer_location' => 'City, Country',
                'viewed_at' => $dateRange['from']->addDays(rand(0, 30))->toISOString(),
                'is_premium' => $viewer->is_premium_active,
            ];
        })->toArray();
    }

    /**
     * Get total profile views (simulated)
     */
    private function getTotalProfileViews($user, $dateRange): int
    {
        return rand(50, 500);
    }

    /**
     * Get unique profile views (simulated)
     */
    private function getUniqueProfileViews($user, $dateRange): int
    {
        return rand(30, 300);
    }

    /**
     * Get average daily views (simulated)
     */
    private function getAverageDailyViews($user, $dateRange): float
    {
        $days = $dateRange['from']->diffInDays($dateRange['to']);
        return round(rand(2, 20) + (rand(0, 99) / 100), 1);
    }

    /**
     * Get profile views chart data (simulated)
     */
    private function getProfileViewsChart($user, $dateRange): array
    {
        $days = $dateRange['from']->diffInDays($dateRange['to']);
        $data = [];
        
        for ($i = 0; $i < min($days, 30); $i++) {
            $date = $dateRange['from']->copy()->addDays($i);
            $data[] = [
                'date' => $date->toDateString(),
                'views' => rand(1, 25),
                'unique_views' => rand(1, 20),
            ];
        }

        return $data;
    }

    /**
     * Get mutual matches count
     */
    private function getMutualMatches($user, $dateRange): int
    {
        return UserMatch::where('user_id', $user->id)
            ->where('action', 'like')
            ->whereExists(function ($query) use ($user) {
                $query->select(DB::raw(1))
                    ->from('user_matches as um2')
                    ->whereRaw('um2.user_id = user_matches.target_user_id')
                    ->whereRaw('um2.target_user_id = user_matches.user_id')
                    ->where('um2.action', 'like');
            })
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();
    }

    /**
     * Get response rate (simulated)
     */
    private function getResponseRate($user, $dateRange): string
    {
        return rand(30, 85) . '%';
    }

    /**
     * Get match success rate (simulated)
     */
    private function getMatchSuccessRate($user, $dateRange): string
    {
        return rand(10, 40) . '%';
    }

    /**
     * Get match analytics chart data (simulated)
     */
    private function getMatchAnalyticsChart($user, $dateRange): array
    {
        $days = $dateRange['from']->diffInDays($dateRange['to']);
        $data = [];
        
        for ($i = 0; $i < min($days, 30); $i++) {
            $date = $dateRange['from']->copy()->addDays($i);
            $data[] = [
                'date' => $date->toDateString(),
                'likes_sent' => rand(0, 10),
                'likes_received' => rand(0, 15),
                'matches' => rand(0, 3),
            ];
        }

        return $data;
    }

    /**
     * Get liker demographics (simulated)
     */
    private function getLikerDemographics($user, $dateRange): array
    {
        return [
            'age_groups' => [
                '20-25' => rand(10, 30),
                '26-30' => rand(20, 40),
                '31-35' => rand(15, 35),
                '36-40' => rand(10, 25),
                '41+' => rand(5, 15),
            ],
            'locations' => [
                'Same City' => rand(30, 60),
                'Same State' => rand(20, 40),
                'Same Country' => rand(10, 30),
                'International' => rand(5, 20),
            ],
            'education' => [
                'Bachelor\'s Degree' => rand(30, 50),
                'Master\'s Degree' => rand(20, 40),
                'PhD' => rand(5, 15),
                'High School' => rand(10, 25),
            ],
        ];
    }

    /**
     * Get compatibility reports (simulated)
     */
    private function getCompatibilityReports($user, $minScore, $offset, $limit): array
    {
        $users = User::where('id', '!=', $user->id)
            ->with(['profile'])
            ->inRandomOrder()
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $users->map(function ($otherUser) use ($minScore) {
            $score = rand($minScore, 100);
            return [
                'user_id' => $otherUser->id,
                'first_name' => $otherUser->first_name,
                'compatibility_score' => $score,
                'match_factors' => [
                    'interests' => rand(70, 100),
                    'lifestyle' => rand(60, 95),
                    'values' => rand(65, 90),
                    'location' => rand(50, 100),
                    'education' => rand(70, 95),
                ],
                'report_generated_at' => now()->subDays(rand(1, 30))->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Get compatibility distribution (simulated)
     */
    private function getCompatibilityDistribution($user): array
    {
        return [
            '90-100%' => rand(5, 15),
            '80-89%' => rand(15, 30),
            '70-79%' => rand(25, 45),
            '60-69%' => rand(20, 40),
            '50-59%' => rand(15, 30),
            'Below 50%' => rand(10, 25),
        ];
    }

    /**
     * Get average compatibility score (simulated)
     */
    private function getAverageCompatibilityScore($user): int
    {
        return rand(65, 85);
    }

    /**
     * Get top compatibility factors (simulated)
     */
    private function getTopCompatibilityFactors($user): array
    {
        return [
            ['factor' => 'Shared Interests', 'importance' => 85],
            ['factor' => 'Lifestyle Compatibility', 'importance' => 78],
            ['factor' => 'Educational Background', 'importance' => 72],
            ['factor' => 'Location Proximity', 'importance' => 68],
            ['factor' => 'Career Goals', 'importance' => 65],
        ];
    }

    /**
     * Get total analyzed profiles (simulated)
     */
    private function getTotalAnalyzedProfiles($user): int
    {
        return rand(100, 1000);
    }
} 