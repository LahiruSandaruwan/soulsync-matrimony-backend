<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MatchController extends Controller
{
    protected MatchingService $matchingService;

    public function __construct(MatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Get matches for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:daily,suggestions,mutual,premium',
            'limit' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $type = $request->get('type', 'suggestions');
            $limit = $request->get('limit', 10);

            $matches = match($type) {
                'daily' => $this->matchingService->generateDailyMatches($user, $limit),
                'mutual' => $this->matchingService->getMutualMatches($user),
                'premium' => $this->matchingService->getPremiumSuggestions($user, $limit),
                default => $this->matchingService->findMatches($user, $limit)
            };

            // Format matches for API response
            $formattedMatches = $matches->map(function ($match) use ($user) {
                if ($match instanceof UserMatch) {
                    // This is a mutual match
                    $targetUser = $match->user_id === $user->id ? $match->matchedUser : $match->user;
                    return $this->formatMatchUser($targetUser, $match);
                } else {
                    // This is a potential match
                    return $this->formatPotentialMatch($match);
                }
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $formattedMatches,
                    'total' => $formattedMatches->count(),
                    'type' => $type,
                    'has_more' => $formattedMatches->count() >= $limit,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily matches
     */
    public function dailyMatches(Request $request): JsonResponse
    {
        $user = $request->user();
        
        try {
            $matches = $this->matchingService->generateDailyMatches($user, 10);
            
            $formattedMatches = $matches->map(function ($match) {
                return $this->formatPotentialMatch($match);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'daily_matches' => $formattedMatches,
                    'date' => now()->format('Y-m-d'),
                    'refresh_time' => now()->addDay()->startOfDay()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get daily matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match suggestions
     */
    public function suggestions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:50',
            'min_score' => 'sometimes|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $limit = $request->get('limit', 20);
            $minScore = $request->get('min_score', 50);
            
            $matches = $this->matchingService->findMatches($user, $limit);
            
            // Filter by minimum score if specified
            $filteredMatches = $matches->filter(function ($match) use ($minScore) {
                return $match->compatibility_score >= $minScore;
            });

            $formattedMatches = $filteredMatches->map(function ($match) {
                return $this->formatPotentialMatch($match);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $formattedMatches,
                    'total' => $formattedMatches->count(),
                    'min_score' => $minScore,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Like a user
     */
    public function like(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();

        // Validate user can like
        if ($user->id === $targetUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot like yourself'
            ], 400);
        }

        if ($targetUser->status !== 'active' || $targetUser->profile_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'User profile not available'
            ], 404);
        }

        try {
            $result = $this->matchingService->processLike($user, $targetUser, false);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'is_match' => $result['is_match'],
                    'match_id' => $result['match_id'] ?? null,
                    'conversation_id' => $result['conversation_id'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process like',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Super like a user
     */
    public function superLike(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();

        // Check if user has super likes remaining
        if (!$this->canSuperLike($user)) {
            return response()->json([
                'success' => false,
                'message' => 'No super likes remaining. Upgrade to premium for more super likes.',
                'upgrade_required' => true
            ], 403);
        }

        try {
            $result = $this->matchingService->processLike($user, $targetUser, true);

            // Decrement super likes count
            $user->decrement('super_likes_count');

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'is_match' => $result['is_match'],
                    'match_id' => $result['match_id'] ?? null,
                    'conversation_id' => $result['conversation_id'] ?? null,
                    'super_likes_remaining' => $this->getSuperLikesRemaining($user),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process super like',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dislike/pass a user
     */
    public function dislike(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();

        try {
            // Find or create match record
            $match = UserMatch::firstOrCreate([
                'user_id' => $user->id,
                'matched_user_id' => $targetUser->id,
            ], [
                'match_type' => 'user_action',
                'status' => 'pending',
            ]);

            $match->dislike($user);

            return response()->json([
                'success' => true,
                'message' => 'User passed'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process dislike',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Block a user
     */
    public function block(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find or create match record
            $match = UserMatch::firstOrCreate([
                'user_id' => $user->id,
                'matched_user_id' => $targetUser->id,
            ], [
                'match_type' => 'user_action',
                'status' => 'pending',
            ]);

            $match->block($user);

            return response()->json([
                'success' => true,
                'message' => 'User blocked successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to block user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users who liked me (premium feature)
     */
    public function whoLikedMe(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->is_premium) {
            return response()->json([
                'success' => false,
                'message' => 'Premium subscription required to see who liked you',
                'upgrade_required' => true
            ], 403);
        }

        try {
            $matches = $this->matchingService->getWhoLikedMe($user);

            $formattedMatches = $matches->map(function ($match) {
                return $this->formatMatchUser($match->user, $match);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'who_liked_me' => $formattedMatches,
                    'total' => $formattedMatches->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get who liked you',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mutual matches
     */
    public function mutualMatches(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $matches = $this->matchingService->getMutualMatches($user);

            $formattedMatches = $matches->map(function ($match) use ($user) {
                $targetUser = $match->user_id === $user->id ? $match->matchedUser : $match->user;
                return $this->formatMatchUser($targetUser, $match);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'mutual_matches' => $formattedMatches,
                    'total' => $formattedMatches->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get mutual matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $stats = $this->matchingService->getMatchStatistics($user);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format potential match for API response
     */
    private function formatPotentialMatch(User $user): array
    {
        $profile = $user->profile;
        $photos = $user->photos()->where('status', 'approved')->orderBy('is_profile_picture', 'desc')->get();

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'age' => $user->age,
            'location' => $profile?->full_location,
            'occupation' => $profile?->occupation,
            'education' => $profile?->education_level,
            'religion' => $profile?->religion,
            'height' => $profile?->height_feet,
            'photos' => $photos->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'url' => asset('storage/' . $photo->file_path),
                    'is_profile' => $photo->is_profile_picture,
                ];
            }),
            'compatibility_score' => $user->compatibility_score ?? 0,
            'match_factors' => $user->matching_factors ?? [],
            'is_premium' => $user->is_premium,
            'last_active' => $user->last_active_at?->diffForHumans(),
            'profile_completion' => $user->profile_completion_percentage,
        ];
    }

    /**
     * Format match user for API response
     */
    private function formatMatchUser(User $user, UserMatch $match): array
    {
        $basicInfo = $this->formatPotentialMatch($user);
        
        return array_merge($basicInfo, [
            'match_id' => $match->id,
            'match_status' => $match->status,
            'can_communicate' => $match->can_communicate,
            'conversation_id' => $match->conversation_id,
            'matched_at' => $match->created_at->toISOString(),
            'is_mutual' => $match->is_mutual,
        ]);
    }

    /**
     * Check if user can super like
     */
    private function canSuperLike(User $user): bool
    {
        if ($user->is_premium) {
            return true; // Premium users have unlimited super likes
        }

        // Free users get limited super likes (e.g., 1 per day)
        $dailySuperLikes = UserMatch::where('user_id', $user->id)
            ->where('user_action', 'super_liked')
            ->whereDate('user_action_at', today())
            ->count();

        return $dailySuperLikes < 1;
    }

    /**
     * Get remaining super likes for user
     */
    private function getSuperLikesRemaining(User $user): int|string
    {
        if ($user->is_premium) {
            return 'unlimited';
        }

        $dailySuperLikes = UserMatch::where('user_id', $user->id)
            ->where('user_action', 'super_liked')
            ->whereDate('user_action_at', today())
            ->count();

        return max(0, 1 - $dailySuperLikes);
    }
}
