<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\UserPreference;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MatchController extends Controller
{
    /**
     * Get user's mutual matches
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
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
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $limit;

            // Get mutual matches
            $matchIds = UserMatch::where('user_id', $user->id)
                ->where('action', 'like')
                ->whereNotNull('matched_at')
                ->pluck('target_user_id');

            $matches = User::whereIn('id', $matchIds)
                ->with(['profile', 'profilePicture'])
                ->where('status', 'active')
                ->where('profile_status', 'approved')
                ->orderBy('last_active_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($matchUser) use ($user) {
                    $matchRecord = UserMatch::where('user_id', $user->id)
                        ->where('target_user_id', $matchUser->id)
                        ->first();

                    return [
                        'id' => $matchUser->id,
                        'first_name' => $matchUser->first_name,
                        'age' => $matchUser->age,
                        'location' => $matchUser->profile ? 
                            ($matchUser->profile->city . ', ' . $matchUser->profile->country) : null,
                        'occupation' => $matchUser->profile->occupation ?? null,
                        'profile_picture' => $matchUser->profilePicture ? 
                            Storage::url($matchUser->profilePicture->file_path) : null,
                        'compatibility_score' => $matchRecord->compatibility_score ?? 0,
                        'matched_at' => $matchRecord->matched_at,
                        'last_active' => $matchUser->last_active_at,
                    ];
                });

            $totalMatches = UserMatch::where('user_id', $user->id)
                ->where('action', 'like')
                ->whereNotNull('matched_at')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $matches,
                    'pagination' => [
                        'current_page' => $page,
                        'total_matches' => $totalMatches,
                        'has_more' => ($offset + $limit) < $totalMatches,
                    ]
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
     * Get daily match suggestions
     */
    public function dailyMatches(Request $request): JsonResponse
    {
        $user = $request->user()->load(['preferences', 'profile']);
        
        // Check if user has set preferences
        if (!$user->preferences) {
            return response()->json([
                'success' => false,
                'message' => 'Please set your partner preferences first',
                'redirect_to' => '/preferences'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:50',
            'refresh' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $limit = $request->get('limit', 10);
            $refresh = $request->get('refresh', false);

            // Get matches (either fresh or cached)
            $matches = $this->generateMatches($user, $limit, $refresh);

            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $matches,
                    'total_matches' => count($matches),
                    'daily_limit' => $user->is_premium_active ? 50 : 10,
                    'remaining_likes' => $this->getRemainingLikes($user),
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match suggestions based on AI
     */
    public function suggestions(Request $request): JsonResponse
    {
        $user = $request->user()->load(['preferences', 'profile']);

        $validator = Validator::make($request->all(), [
            'algorithm' => 'sometimes|in:ai,compatibility,recent,premium',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $algorithm = $request->get('algorithm', 'ai');
            $limit = $request->get('limit', 10);

            $suggestions = $this->generateSuggestions($user, $algorithm, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'algorithm_used' => $algorithm,
                    'total_suggestions' => count($suggestions),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Like a user profile
     */
    public function like(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'message' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is trying to like themselves
        if ($user->id === $targetUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot like your own profile'
            ], 400);
        }

        // Check daily like limit
        if (!$this->canLikeMore($user)) {
            $limit = $user->is_premium_active ? 100 : 20;
            return response()->json([
                'success' => false,
                'message' => "Daily like limit reached ({$limit} likes per day)",
                'upgrade_required' => !$user->is_premium_active
            ], 400);
        }

        try {
            // Check if match already exists
            $existingMatch = UserMatch::where(function ($query) use ($user, $targetUser) {
                $query->where('user_id', $user->id)->where('target_user_id', $targetUser->id);
            })->orWhere(function ($query) use ($user, $targetUser) {
                $query->where('user_id', $targetUser->id)->where('target_user_id', $user->id);
            })->first();

            if ($existingMatch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already interacted with this profile'
                ], 400);
            }

            // Create the like
            $match = UserMatch::create([
                'user_id' => $user->id,
                'target_user_id' => $targetUser->id,
                'action' => 'like',
                'message' => $request->get('message'),
                'compatibility_score' => $this->calculateCompatibilityScore($user, $targetUser),
                'matched_at' => null, // Will be set if it's a mutual like
            ]);

            // Check if target user has already liked this user (mutual like)
            $mutualLike = UserMatch::where('user_id', $targetUser->id)
                ->where('target_user_id', $user->id)
                ->where('action', 'like')
                ->first();

            $isMatch = false;
            if ($mutualLike) {
                // It's a match! Update both records
                $match->update(['matched_at' => now()]);
                $mutualLike->update(['matched_at' => now()]);
                $isMatch = true;

                // Create conversation for matched users
                $this->createConversation($user->id, $targetUser->id);

                // Send notification to target user about the match
                // Notification logic would go here
            }

            return response()->json([
                'success' => true,
                'message' => $isMatch ? 'It\'s a match! 🎉' : 'Profile liked successfully',
                'data' => [
                    'is_match' => $isMatch,
                    'match_id' => $match->id,
                    'can_chat' => $isMatch,
                    'remaining_likes' => $this->getRemainingLikes($user),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to like profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Super like a user profile (premium feature)
     */
    public function superLike(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();

        if (!$user->is_premium_active) {
            return response()->json([
                'success' => false,
                'message' => 'Super likes are available for premium members only',
                'upgrade_required' => true
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check daily super like limit (premium users get 5 per day)
            $todaySuperLikes = UserMatch::where('user_id', $user->id)
                ->where('action', 'super_like')
                ->whereDate('created_at', today())
                ->count();

            if ($todaySuperLikes >= 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily super like limit reached (5 super likes per day)'
                ], 400);
            }

            // Create the super like
            $match = UserMatch::create([
                'user_id' => $user->id,
                'target_user_id' => $targetUser->id,
                'action' => 'super_like',
                'message' => $request->get('message'),
                'compatibility_score' => $this->calculateCompatibilityScore($user, $targetUser),
            ]);

            // Send notification to target user about super like
            // This gives higher priority than regular likes

            return response()->json([
                'success' => true,
                'message' => 'Super like sent successfully! ⭐',
                'data' => [
                    'match_id' => $match->id,
                    'remaining_super_likes' => 5 - $todaySuperLikes - 1,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send super like',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dislike/pass a user profile
     */
    public function dislike(Request $request, User $targetUser): JsonResponse
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

        if ($user->id === $targetUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot dislike your own profile'
            ], 400);
        }

        try {
            // Check if already interacted
            $existingMatch = UserMatch::where('user_id', $user->id)
                ->where('target_user_id', $targetUser->id)
                ->first();

            if ($existingMatch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already interacted with this profile'
                ], 400);
            }

            // Create the dislike record
            UserMatch::create([
                'user_id' => $user->id,
                'target_user_id' => $targetUser->id,
                'action' => 'dislike',
                'reason' => $request->get('reason'),
                'compatibility_score' => $this->calculateCompatibilityScore($user, $targetUser),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile passed'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to pass profile',
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
            // Create or update the block record
            UserMatch::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'target_user_id' => $targetUser->id
                ],
                [
                    'action' => 'block',
                    'reason' => $request->get('reason'),
                    'matched_at' => null, // Remove match if it existed
                ]
            );

            // Remove any existing conversations
            Conversation::where(function ($query) use ($user, $targetUser) {
                $query->where('user1_id', $user->id)->where('user2_id', $targetUser->id);
            })->orWhere(function ($query) use ($user, $targetUser) {
                $query->where('user1_id', $targetUser->id)->where('user2_id', $user->id);
            })->delete();

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
     * Get users who liked me
     */
    public function whoLikedMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
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
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $limit;

            // Get users who liked this user but haven't been responded to
            $likerIds = UserMatch::where('target_user_id', $user->id)
                ->whereIn('action', ['like', 'super_like'])
                ->whereNull('matched_at') // Not mutual yet
                ->whereNotExists(function ($query) use ($user) {
                    $query->select(DB::raw(1))
                          ->from('user_matches as um2')
                          ->whereRaw('um2.user_id = ? AND um2.target_user_id = user_matches.user_id', [$user->id]);
                })
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->pluck('user_id');

            $likers = User::whereIn('id', $likerIds)
                ->with(['profile', 'profilePicture'])
                ->where('status', 'active')
                ->get()
                ->map(function ($liker) use ($user) {
                    $likeRecord = UserMatch::where('user_id', $liker->id)
                        ->where('target_user_id', $user->id)
                        ->first();

                    return [
                        'id' => $liker->id,
                        'first_name' => $liker->first_name,
                        'age' => $liker->age,
                        'location' => $liker->profile ? 
                            ($liker->profile->city . ', ' . $liker->profile->country) : null,
                        'occupation' => $liker->profile->occupation ?? null,
                        'profile_picture' => $liker->profilePicture ? 
                            Storage::url($liker->profilePicture->file_path) : null,
                        'is_super_like' => $likeRecord->action === 'super_like',
                        'message' => $likeRecord->message,
                        'liked_at' => $likeRecord->created_at,
                        'compatibility_score' => $likeRecord->compatibility_score,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'likers' => $likers,
                    'pagination' => [
                        'current_page' => $page,
                        'has_more' => count($likers) === $limit,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get likes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mutual matches
     */
    public function mutualMatches(Request $request): JsonResponse
    {
        // This is essentially the same as index() but with a different endpoint
        return $this->index($request);
    }

    /**
     * Generate matches based on preferences and AI
     */
    private function generateMatches(User $user, int $limit, bool $refresh = false): array
    {
        $preferences = $user->preferences;
        
        // Get users that have already been liked or passed
        $excludeUserIds = UserMatch::where('user_id', $user->id)
            ->pluck('target_user_id')
            ->toArray();
        
        // Add the current user to exclude list
        $excludeUserIds[] = $user->id;

        // Build query based on preferences
        $query = User::query()
            ->with(['profile', 'profilePicture', 'horoscope'])
            ->where('status', 'active')
            ->where('profile_status', 'approved')
            ->whereNotIn('id', $excludeUserIds);

        // Apply preference filters
        if ($preferences->min_age && $preferences->max_age) {
            $minBirthDate = now()->subYears($preferences->max_age);
            $maxBirthDate = now()->subYears($preferences->min_age);
            $query->whereBetween('date_of_birth', [$minBirthDate, $maxBirthDate]);
        }

        // Gender preference (opposite gender for matrimonial)
        if ($user->gender === 'male') {
            $query->where('gender', 'female');
        } elseif ($user->gender === 'female') {
            $query->where('gender', 'male');
        }

        // Apply location filters if specified
        if ($preferences->preferred_countries && count($preferences->preferred_countries) > 0) {
            $query->whereHas('profile', function ($q) use ($preferences) {
                $q->whereIn('country', $preferences->preferred_countries);
            });
        }

        // Apply religion filter if specified
        if ($preferences->religions && count($preferences->religions) > 0 && !$preferences->religion_no_bar) {
            $query->whereHas('profile', function ($q) use ($preferences) {
                $q->whereIn('religion', $preferences->religions);
            });
        }

        // Apply height filter if specified
        if ($preferences->min_height && $preferences->max_height) {
            $query->whereHas('profile', function ($q) use ($preferences) {
                $q->whereBetween('height', [$preferences->min_height, $preferences->max_height]);
            });
        }

        // Apply marital status filter
        if ($preferences->marital_status && count($preferences->marital_status) > 0) {
            $query->whereHas('profile', function ($q) use ($preferences) {
                $q->whereIn('marital_status', $preferences->marital_status);
            });
        }

        // Get potential matches
        $potentialMatches = $query->get();

        // Calculate compatibility scores and sort
        $scoredMatches = $potentialMatches->map(function ($potentialMatch) use ($user) {
            $score = $this->calculateCompatibilityScore($user, $potentialMatch);
            $potentialMatch->compatibility_score = $score;
            return $potentialMatch;
        })->sortByDesc('compatibility_score')->take($limit);

        // Format response
        return $scoredMatches->map(function ($match) {
            return [
                'id' => $match->id,
                'first_name' => $match->first_name,
                'age' => $match->age,
                'location' => $match->profile ? 
                    ($match->profile->city . ', ' . $match->profile->country) : null,
                'occupation' => $match->profile->occupation ?? null,
                'education' => $match->profile->education ?? null,
                'religion' => $match->profile->religion ?? null,
                'height' => $match->profile->height ?? null,
                'bio' => $match->profile ? substr($match->profile->bio, 0, 150) . '...' : null,
                'profile_picture' => $match->profilePicture ? 
                    Storage::url($match->profilePicture->file_path) : null,
                'compatibility_score' => $match->compatibility_score,
                'is_premium' => $match->is_premium_active,
                'last_active' => $match->last_active_at,
            ];
        })->values()->toArray();
    }

    /**
     * Generate suggestions based on different algorithms
     */
    private function generateSuggestions(User $user, string $algorithm, int $limit): array
    {
        switch ($algorithm) {
            case 'ai':
                return $this->generateMatches($user, $limit);
            case 'compatibility':
                return $this->generateCompatibilityBasedSuggestions($user, $limit);
            case 'recent':
                return $this->generateRecentJoinedSuggestions($user, $limit);
            case 'premium':
                return $this->generatePremiumSuggestions($user, $limit);
            default:
                return $this->generateMatches($user, $limit);
        }
    }

    /**
     * Generate compatibility-based suggestions
     */
    private function generateCompatibilityBasedSuggestions(User $user, int $limit): array
    {
        // Similar to generateMatches but focuses purely on compatibility score
        return $this->generateMatches($user, $limit);
    }

    /**
     * Generate recent joined suggestions
     */
    private function generateRecentJoinedSuggestions(User $user, int $limit): array
    {
        $excludeUserIds = UserMatch::where('user_id', $user->id)
            ->pluck('target_user_id')
            ->toArray();
        $excludeUserIds[] = $user->id;

        $oppositeGender = $user->gender === 'male' ? 'female' : 'male';

        $recentUsers = User::with(['profile', 'profilePicture'])
            ->where('gender', $oppositeGender)
            ->where('status', 'active')
            ->where('profile_status', 'approved')
            ->whereNotIn('id', $excludeUserIds)
            ->where('created_at', '>', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $recentUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'age' => $user->age,
                'location' => $user->profile ? 
                    ($user->profile->city . ', ' . $user->profile->country) : null,
                'occupation' => $user->profile->occupation ?? null,
                'profile_picture' => $user->profilePicture ? 
                    Storage::url($user->profilePicture->file_path) : null,
                'joined_at' => $user->created_at,
                'is_premium' => $user->is_premium_active,
            ];
        })->toArray();
    }

    /**
     * Generate premium user suggestions
     */
    private function generatePremiumSuggestions(User $user, int $limit): array
    {
        $excludeUserIds = UserMatch::where('user_id', $user->id)
            ->pluck('target_user_id')
            ->toArray();
        $excludeUserIds[] = $user->id;

        $oppositeGender = $user->gender === 'male' ? 'female' : 'male';

        $premiumUsers = User::with(['profile', 'profilePicture'])
            ->where('gender', $oppositeGender)
            ->where('status', 'active')
            ->where('profile_status', 'approved')
            ->where('is_premium', true)
            ->where('premium_expires_at', '>', now())
            ->whereNotIn('id', $excludeUserIds)
            ->orderBy('last_active_at', 'desc')
            ->limit($limit)
            ->get();

        return $premiumUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'age' => $user->age,
                'location' => $user->profile ? 
                    ($user->profile->city . ', ' . $user->profile->country) : null,
                'occupation' => $user->profile->occupation ?? null,
                'profile_picture' => $user->profilePicture ? 
                    Storage::url($user->profilePicture->file_path) : null,
                'is_premium' => true,
                'last_active' => $user->last_active_at,
            ];
        })->toArray();
    }

    /**
     * Calculate compatibility score between two users
     */
    private function calculateCompatibilityScore(User $user1, User $user2): int
    {
        $score = 0;
        $maxScore = 100;
        
        $user1Profile = $user1->profile;
        $user2Profile = $user2->profile;
        $preferences = $user1->preferences;

        if (!$user1Profile || !$user2Profile || !$preferences) {
            return 0;
        }

        // Age compatibility (20 points)
        $ageScore = $this->calculateAgeCompatibility($user1, $user2, $preferences);
        $score += $ageScore * 0.2;

        // Location compatibility (15 points)
        $locationScore = $this->calculateLocationCompatibility($user1Profile, $user2Profile, $preferences);
        $score += $locationScore * 0.15;

        // Cultural compatibility (25 points)
        $culturalScore = $this->calculateCulturalCompatibility($user1Profile, $user2Profile, $preferences);
        $score += $culturalScore * 0.25;

        // Lifestyle compatibility (15 points)
        $lifestyleScore = $this->calculateLifestyleCompatibility($user1Profile, $user2Profile, $preferences);
        $score += $lifestyleScore * 0.15;

        // Education/Career compatibility (10 points)
        $careerScore = $this->calculateCareerCompatibility($user1Profile, $user2Profile, $preferences);
        $score += $careerScore * 0.1;

        // Family compatibility (10 points)
        $familyScore = $this->calculateFamilyCompatibility($user1Profile, $user2Profile, $preferences);
        $score += $familyScore * 0.1;

        // Physical compatibility (5 points)
        $physicalScore = $this->calculatePhysicalCompatibility($user1Profile, $user2Profile, $preferences);
        $score += $physicalScore * 0.05;

        return min($maxScore, round($score));
    }

    /**
     * Individual compatibility calculations
     */
    private function calculateAgeCompatibility(User $user1, User $user2, UserPreference $preferences): int
    {
        $age2 = $user2->age;
        
        if ($age2 >= $preferences->min_age && $age2 <= $preferences->max_age) {
            return 100;
        }
        
        // Partial score if close to range
        $rangeMid = ($preferences->min_age + $preferences->max_age) / 2;
        $distance = abs($age2 - $rangeMid);
        return max(0, 100 - ($distance * 10));
    }

    private function calculateLocationCompatibility($profile1, $profile2, $preferences): int
    {
        if (!$preferences->preferred_countries || count($preferences->preferred_countries) === 0) {
            return 100; // No preference set
        }

        if (in_array($profile2->country, $preferences->preferred_countries)) {
            if ($profile1->city === $profile2->city) return 100;
            if ($profile1->state === $profile2->state) return 80;
            return 60;
        }

        return 20;
    }

    private function calculateCulturalCompatibility($profile1, $profile2, $preferences): int
    {
        $score = 0;
        $factors = 0;

        // Religion compatibility
        if ($preferences->religion_no_bar) {
            $score += 100;
        } elseif ($preferences->religions && in_array($profile2->religion, $preferences->religions)) {
            $score += 100;
        } else {
            $score += 30;
        }
        $factors++;

        // Mother tongue
        if ($preferences->mother_tongues && in_array($profile2->mother_tongue, $preferences->mother_tongues)) {
            $score += 80;
        } else {
            $score += 40;
        }
        $factors++;

        return $factors > 0 ? round($score / $factors) : 0;
    }

    private function calculateLifestyleCompatibility($profile1, $profile2, $preferences): int
    {
        $score = 70; // Base score

        if ($preferences->diet_preferences && in_array($profile2->diet, $preferences->diet_preferences)) {
            $score += 30;
        }

        return min(100, $score);
    }

    private function calculateCareerCompatibility($profile1, $profile2, $preferences): int
    {
        $score = 70; // Base score

        if ($preferences->education_levels && in_array($profile2->education, $preferences->education_levels)) {
            $score += 20;
        }

        if (!$preferences->income_no_bar && $preferences->min_income_usd) {
            if ($profile2->annual_income_usd >= $preferences->min_income_usd) {
                $score += 10;
            }
        }

        return min(100, $score);
    }

    private function calculateFamilyCompatibility($profile1, $profile2, $preferences): int
    {
        $score = 70;

        if ($preferences->family_types && in_array($profile2->family_type, $preferences->family_types)) {
            $score += 20;
        }

        if ($preferences->children_acceptable && $profile2->children_count <= ($preferences->max_children_count ?? 10)) {
            $score += 10;
        }

        return min(100, $score);
    }

    private function calculatePhysicalCompatibility($profile1, $profile2, $preferences): int
    {
        $score = 70;

        if ($preferences->body_types && in_array($profile2->body_type, $preferences->body_types)) {
            $score += 20;
        }

        if ($preferences->complexions && in_array($profile2->complexion, $preferences->complexions)) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Check if user can like more profiles
     */
    private function canLikeMore(User $user): bool
    {
        $limit = $user->is_premium_active ? 100 : 20;
        $todayLikes = UserMatch::where('user_id', $user->id)
            ->where('action', 'like')
            ->whereDate('created_at', today())
            ->count();

        return $todayLikes < $limit;
    }

    /**
     * Get remaining likes for today
     */
    private function getRemainingLikes(User $user): int
    {
        $limit = $user->is_premium_active ? 100 : 20;
        $todayLikes = UserMatch::where('user_id', $user->id)
            ->where('action', 'like')
            ->whereDate('created_at', today())
            ->count();

        return max(0, $limit - $todayLikes);
    }

    /**
     * Create conversation between matched users
     */
    private function createConversation(int $userId1, int $userId2): void
    {
        Conversation::firstOrCreate([
            'user1_id' => min($userId1, $userId2),
            'user2_id' => max($userId1, $userId2),
        ], [
            'status' => 'active',
        ]);
    }
}
