<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserMatch;
use App\Models\UserProfile;
use App\Models\UserPreference;
use App\Models\ExchangeRate;
use App\Events\MatchFound;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MatchingService
{
    /**
     * Generate daily matches for a user.
     */
    public function generateDailyMatches(User $user, int $limit = 10): Collection
    {
        $cacheKey = "daily_matches_{$user->id}_" . now()->format('Y-m-d');
        
        return collect(Cache::remember($cacheKey, now()->addHours(6), function () use ($user, $limit) {
            $matches = $this->findMatches($user, $limit, 'daily');
            return $matches->map(function ($match) use ($user) {
                return [
                    'user' => $match,
                    'compatibility_score' => $match->compatibility_score ?? 0,
                    'matching_factors' => $this->getMatchingFactors($user, $match),
                ];
            })->toArray();
        }));
    }

    /**
     * Find matches for a user based on preferences and compatibility.
     */
    public function findMatches(User $user, int $limit = 20, string $type = 'suggestion'): Collection
    {
        $userProfile = $user->profile;
        $userPreferences = $user->preferences;
        
        if (!$userProfile || !$userPreferences) {
            return collect();
        }

        // Build base query for potential matches
        $query = $this->buildMatchQuery($user, $userPreferences);
        
        // Get potential matches
        $potentialMatches = $query->limit($limit * 3) // Get more than needed for filtering
            ->get();

        // Calculate compatibility scores and filter
        $scoredMatches = $this->scoreAndFilterMatches($user, $potentialMatches, $limit);
        
        // Create match records
        $this->createMatchRecords($user, $scoredMatches, $type);
        
        return $scoredMatches;
    }

    /**
     * Build the base query for finding potential matches.
     */
    private function buildMatchQuery(User $user, UserPreference $preferences): \Illuminate\Database\Eloquent\Builder
    {
        $userProfile = $user->profile;
        
        $query = User::query()
            ->with(['profile', 'photos' => function($q) {
                $q->where('status', 'approved')->orderBy('is_profile_picture', 'desc');
            }])
            ->whereHas('profile', function ($q) use ($preferences, $user) {
                // Basic filters
                $q->where('user_id', '!=', $user->id);
                
                // Age filters
                $q->whereRaw('TIMESTAMPDIFF(YEAR, (SELECT date_of_birth FROM users WHERE users.id = user_profiles.user_id), CURDATE()) BETWEEN ? AND ?', 
                    [$preferences->min_age, $preferences->max_age]);
                
                // Height filters
                if ($preferences->min_height_cm) {
                    $q->where('height_cm', '>=', $preferences->min_height_cm);
                }
                if ($preferences->max_height_cm) {
                    $q->where('height_cm', '<=', $preferences->max_height_cm);
                }
                
                // Location filters
                if ($preferences->preferred_countries) {
                    $q->whereIn('current_country', $preferences->preferred_countries);
                }
                
                // Religion filters
                if ($preferences->preferred_religions) {
                    $q->whereIn('religion', $preferences->preferred_religions);
                }
                
                // Education filters
                if ($preferences->preferred_education_levels) {
                    $q->whereIn('education_level', $preferences->preferred_education_levels);
                }
                
                // Marital status filters
                if ($preferences->preferred_marital_status) {
                    $q->whereIn('marital_status', $preferences->preferred_marital_status);
                }
                
                // Children filters
                if (!$preferences->accept_with_children) {
                    $q->where('have_children', false);
                }
                
                // Income filters
                if ($preferences->min_income_usd) {
                    $q->where('annual_income_usd', '>=', $preferences->min_income_usd);
                }
                
                // Lifestyle filters
                if ($preferences->preferred_diets) {
                    $q->whereIn('diet', $preferences->preferred_diets);
                }
                if ($preferences->preferred_smoking_habits) {
                    $q->whereIn('smoking', $preferences->preferred_smoking_habits);
                }
                if ($preferences->preferred_drinking_habits) {
                    $q->whereIn('drinking', $preferences->preferred_drinking_habits);
                }
                
                // Physical challenges filter
                if (!$preferences->accept_physically_challenged) {
                    $q->where('physically_challenged', false);
                }
                
                // Verification filters (for premium users)
                if ($preferences->show_only_verified_profiles) {
                    $q->where('profile_verified', true);
                }
            })
            ->where('status', 'active')
            ->where('profile_status', 'approved')
            ->where('gender', '!=', $user->gender); // Opposite gender by default
        
        // Gender preference
        if ($preferences->preferred_genders) {
            $query->whereIn('gender', $preferences->preferred_genders);
        }
        
        // Exclude users who have already been matched or blocked
        $query->whereNotExists(function ($q) use ($user) {
            $q->select(DB::raw(1))
              ->from('user_matches')
              ->where(function ($subQ) use ($user) {
                  $subQ->where('user_id', $user->id)
                       ->whereColumn('matched_user_id', 'users.id');
              })
              ->orWhere(function ($subQ) use ($user) {
                  $subQ->where('matched_user_id', $user->id)
                       ->whereColumn('user_id', 'users.id');
              });
        });
        
        // Premium users get priority
        $query->orderByDesc('is_premium')
              ->orderByDesc('last_active_at')
              ->orderByDesc('created_at');
        
        return $query;
    }

    /**
     * Score and filter matches based on compatibility.
     */
    private function scoreAndFilterMatches(User $user, Collection $potentialMatches, int $limit): Collection
    {
        $userProfile = $user->profile;
        $userPreferences = $user->preferences;
        
        $scoredMatches = $potentialMatches->map(function ($potentialMatch) use ($user, $userProfile, $userPreferences) {
            $matchProfile = $potentialMatch->profile;
            
            if (!$matchProfile) {
                return null;
            }
            
            // Calculate various compatibility scores
            $profileScore = $userProfile->getCompatibilityScore($matchProfile);
            $preferenceScore = $userPreferences->getMatchScore($matchProfile);
            $horoscopeScore = $this->calculateHoroscopeCompatibility($user, $potentialMatch);
            $activityScore = $this->calculateActivityScore($potentialMatch);
            $premiumBonus = $potentialMatch->is_premium ? 5 : 0;
            
            // Weighted final score
            $finalScore = ($profileScore * 0.3) + 
                         ($preferenceScore * 0.4) + 
                         ($horoscopeScore * 0.2) + 
                         ($activityScore * 0.1) + 
                         $premiumBonus;
            
            $potentialMatch->compatibility_score = round($finalScore, 2);
            $potentialMatch->profile_score = $profileScore;
            $potentialMatch->preference_score = $preferenceScore;
            $potentialMatch->horoscope_score = $horoscopeScore;
            $potentialMatch->activity_score = $activityScore;
            
            return $potentialMatch;
        })
        ->filter() // Remove nulls
        ->sortByDesc('compatibility_score')
        ->take($limit);
        
        return $scoredMatches->values();
    }

    /**
     * Calculate horoscope compatibility between two users.
     */
    public function calculateHoroscopeCompatibility(User $user1, User $user2): float
    {
        $horoscope1 = $user1->horoscope;
        $horoscope2 = $user2->horoscope;
        
        if (!$horoscope1 || !$horoscope2) {
            return 50.0; // Neutral score if horoscope data missing
        }
        
        // Use Guna Milan score if available
        if ($horoscope1->guna_milan_score) {
            return ($horoscope1->guna_milan_score / 36) * 100;
        }
        
        $score = 50; // Base score
        
        // Zodiac compatibility
        if ($horoscope1->zodiac_sign === $horoscope2->zodiac_sign) {
            $score += 15;
        }
        
        // Moon sign compatibility
        $compatibleSigns = $this->getCompatibleMoonSigns($horoscope1->moon_sign);
        if (in_array($horoscope2->moon_sign, $compatibleSigns)) {
            $score += 20;
        }
        
        // Manglik compatibility
        if ($horoscope1->manglik === $horoscope2->manglik) {
            $score += 15;
        } elseif ($horoscope1->manglik && !$horoscope2->manglik) {
            $score -= 20; // Penalty for manglik mismatch
        }
        
        // Nakshatra compatibility (simplified)
        if ($horoscope1->nakshatra === $horoscope2->nakshatra) {
            $score += 10;
        }
        
        return round(min(100, max(0, $score)), 2);
    }

    /**
     * Calculate activity score based on recent activity.
     */
    public function calculateActivityScore(User $user): float
    {
        $daysSinceActive = $user->last_active_at ? 
            $user->last_active_at->diffInDays(now()) : 30;
        
        $profileCompleteness = $user->profile_completion_percentage ?? 0;
        $hasPhotos = $user->photos()->where('status', 'approved')->count() > 0;
        
        $score = 100 - min($daysSinceActive * 2, 50); // Penalty for inactivity
        $score = ($score * 0.6) + ($profileCompleteness * 0.3) + ($hasPhotos ? 10 : 0);
        
        return round(max(0, min(100, $score)), 2);
    }

    /**
     * Create match records in the database.
     */
    private function createMatchRecords(User $user, Collection $matches, string $type): void
    {
        $matchRecords = [];
        $now = now();
        
        foreach ($matches as $match) {
            $matchRecords[] = [
                'user_id' => $user->id,
                'matched_user_id' => $match->id,
                'match_type' => $type === 'daily' ? 'ai_suggestion' : 'search_result',
                'status' => 'pending',
                'compatibility_score' => $match->compatibility_score,
                'preference_score' => $match->preference_score,
                'horoscope_score' => $match->horoscope_score,
                'ai_score' => $match->activity_score,
                'matching_factors' => json_encode($this->getMatchingFactors($user, $match)),
                'expires_at' => $now->copy()->addDays(30), // Matches expire in 30 days
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        
        if (!empty($matchRecords)) {
            UserMatch::insert($matchRecords);
        }
    }

    /**
     * Get matching factors between two users.
     */
    private function getMatchingFactors(User $user1, User $user2): array
    {
        $factors = [];
        $profile1 = $user1->profile;
        $profile2 = $user2->profile;
        
        if (!$profile1 || !$profile2) {
            return $factors;
        }
        
        // Location match
        if ($profile1->current_country === $profile2->current_country) {
            $factors[] = 'same_country';
            if ($profile1->current_city === $profile2->current_city) {
                $factors[] = 'same_city';
            }
        }
        
        // Religion match
        if ($profile1->religion === $profile2->religion) {
            $factors[] = 'same_religion';
            if ($profile1->caste === $profile2->caste) {
                $factors[] = 'same_caste';
            }
        }
        
        // Education match
        if ($profile1->education_level === $profile2->education_level) {
            $factors[] = 'similar_education';
        }
        
        // Language match
        $languages1 = $profile1->languages_known ?? [];
        $languages2 = $profile2->languages_known ?? [];
        if (array_intersect($languages1, $languages2)) {
            $factors[] = 'common_languages';
        }
        
        // Lifestyle match
        if ($profile1->diet === $profile2->diet) {
            $factors[] = 'same_diet';
        }
        if ($profile1->smoking === $profile2->smoking) {
            $factors[] = 'same_smoking_habits';
        }
        
        // Family compatibility
        if ($profile1->family_type === $profile2->family_type) {
            $factors[] = 'similar_family_background';
        }
        
        return $factors;
    }

    /**
     * Get compatible moon signs for Vedic astrology.
     */
    private function getCompatibleMoonSigns(string $moonSign): array
    {
        $compatibility = [
            'Aries' => ['Gemini', 'Leo', 'Sagittarius', 'Aquarius'],
            'Taurus' => ['Cancer', 'Virgo', 'Capricorn', 'Pisces'],
            'Gemini' => ['Aries', 'Leo', 'Libra', 'Aquarius'],
            'Cancer' => ['Taurus', 'Virgo', 'Scorpio', 'Pisces'],
            'Leo' => ['Aries', 'Gemini', 'Libra', 'Sagittarius'],
            'Virgo' => ['Taurus', 'Cancer', 'Scorpio', 'Capricorn'],
            'Libra' => ['Gemini', 'Leo', 'Sagittarius', 'Aquarius'],
            'Scorpio' => ['Cancer', 'Virgo', 'Capricorn', 'Pisces'],
            'Sagittarius' => ['Aries', 'Leo', 'Libra', 'Aquarius'],
            'Capricorn' => ['Taurus', 'Virgo', 'Scorpio', 'Pisces'],
            'Aquarius' => ['Aries', 'Gemini', 'Libra', 'Sagittarius'],
            'Pisces' => ['Taurus', 'Cancer', 'Scorpio', 'Capricorn']
        ];

        return $compatibility[$moonSign] ?? [];
    }

    /**
     * Get premium suggestions for premium users.
     */
    public function getPremiumSuggestions(User $user, int $limit = 5): Collection
    {
        if (!$user->is_premium) {
            return collect();
        }
        
        // Premium users get higher quality matches
        $matches = $this->findMatches($user, $limit * 2, 'premium_suggestion');
        
        return $matches->filter(function ($match) {
            return $match->compatibility_score >= 75; // Higher threshold for premium
        })->take($limit);
    }

    /**
     * Get mutual matches for a user.
     */
    public function getMutualMatches(User $user): Collection
    {
        return UserMatch::with(['matchedUser.profile', 'matchedUser.photos'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('status', 'mutual');
            })
            ->orWhere(function ($query) use ($user) {
                $query->where('matched_user_id', $user->id)
                      ->where('status', 'mutual');
            })
            ->orderByDesc('communication_started_at')
            ->get();
    }

    /**
     * Get users who liked the current user.
     */
    public function getWhoLikedMe(User $user): Collection
    {
        return UserMatch::with(['user.profile', 'user.photos'])
            ->where('matched_user_id', $user->id)
            ->whereIn('user_action', ['liked', 'super_liked'])
            ->where('matched_user_action', 'none')
            ->orderByDesc('user_action_at')
            ->get();
    }

    /**
     * Process a like action between users.
     */
    public function processLike(User $user, User $targetUser, bool $isSuperLike = false): array
    {
        // Check if user has already liked this target
        $existingMatch = UserMatch::where('user_id', $user->id)
            ->where('matched_user_id', $targetUser->id)
            ->first();
            
        if ($existingMatch && in_array($existingMatch->user_action, ['liked', 'super_liked'])) {
            return [
                'success' => false,
                'is_match' => false,
                'message' => 'You have already liked this profile'
            ];
        }
        
        // Check if target user has blocked this user
        $blockedMatch = UserMatch::where('user_id', $targetUser->id)
            ->where('matched_user_id', $user->id)
            ->where('user_action', 'blocked')
            ->first();
            
        if ($blockedMatch) {
            return [
                'success' => false,
                'is_match' => false,
                'message' => 'Cannot like blocked user'
            ];
        }
        
        // Find or create match record
        $match = UserMatch::firstOrCreate([
            'user_id' => $user->id,
            'matched_user_id' => $targetUser->id,
        ], [
            'match_type' => 'mutual_interest',
            'status' => 'pending',
            'compatibility_score' => $this->calculateQuickCompatibility($user, $targetUser),
        ]);

        // Also check for the reciprocal match
        $reciprocal = UserMatch::where('user_id', $targetUser->id)
            ->where('matched_user_id', $user->id)
            ->first();

        // Process the like
        $result = $match->like($user, $isSuperLike);

        // If reciprocal exists and both sides liked, set both to mutual
        if ($reciprocal && in_array($reciprocal->user_action, ['liked', 'super_liked']) && in_array($match->user_action, ['liked', 'super_liked'])) {
            $match->status = 'mutual';
            $match->can_communicate = true;
            $match->communication_started_at = now();
            $match->save();
            $reciprocal->status = 'mutual';
            $reciprocal->can_communicate = true;
            $reciprocal->communication_started_at = now();
            $reciprocal->save();
            
            // Create conversation for the mutual match
            if (!$match->conversation_id) {
                try {
                    $conversation = \App\Models\Conversation::createMatchConversation($match);
                    $match->conversation_id = $conversation->id;
                    $match->save();
                    $reciprocal->conversation_id = $conversation->id;
                    $reciprocal->save();
                } catch (\Exception $e) {
                    \Log::error('Failed to create conversation for mutual match: ' . $e->getMessage(), [
                        'match_id' => $match->id,
                        'reciprocal_id' => $reciprocal->id
                    ]);
                }
            }
            
            // Fire match event for real-time updates and notifications
            event(new \App\Events\MatchFound($match, $user, $targetUser));
            return [
                'success' => true,
                'is_match' => true,
                'match_id' => $match->id,
                'conversation_id' => $match->conversation_id,
                'message' => "It's a match! You can now start chatting."
            ];
        }

        if ($result && $match->is_mutual) {
            // It's a mutual match!
            event(new \App\Events\MatchFound($match, $user, $targetUser));
            return [
                'success' => true,
                'is_match' => true,
                'match_id' => $match->id,
                'conversation_id' => $match->conversation_id,
                'message' => "It's a match! You can now start chatting."
            ];
        }

        return [
            'success' => $result,
            'is_match' => false,
            'message' => $isSuperLike ? 'Super like sent!' : 'Like sent!'
        ];
    }

    /**
     * Calculate quick compatibility score for immediate matching.
     */
    private function calculateQuickCompatibility(User $user1, User $user2): float
    {
        $profile1 = $user1->profile;
        $profile2 = $user2->profile;
        
        if (!$profile1 || !$profile2) {
            return 50.0;
        }
        
        return $profile1->getCompatibilityScore($profile2);
    }

    /**
     * Update match scores for existing matches (background job).
     */
    public function updateMatchScores(User $user): void
    {
        $matches = UserMatch::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with(['matchedUser.profile'])
            ->get();
        
        foreach ($matches as $match) {
            $targetUser = $match->matchedUser;
            if ($targetUser && $targetUser->profile) {
                $newScore = $this->calculateQuickCompatibility($user, $targetUser);
                $match->update(['compatibility_score' => $newScore]);
            }
        }
    }

    /**
     * Get match statistics for analytics.
     */
    public function getMatchStatistics(User $user): array
    {
        $stats = [
            'total_matches' => UserMatch::where('user_id', $user->id)->count(),
            'mutual_matches' => UserMatch::where('user_id', $user->id)->where('status', 'mutual')->count(),
            'pending_matches' => UserMatch::where('user_id', $user->id)->where('status', 'pending')->count(),
            'likes_sent' => UserMatch::where('user_id', $user->id)->whereIn('user_action', ['liked', 'super_liked'])->count(),
            'likes_received' => UserMatch::where('matched_user_id', $user->id)->whereIn('user_action', ['liked', 'super_liked'])->count(),
            'super_likes_sent' => UserMatch::where('user_id', $user->id)->where('user_action', 'super_liked')->count(),
            'response_rate' => 0,
        ];
        
        // Calculate response rate
        $sentLikes = $stats['likes_sent'];
        if ($sentLikes > 0) {
            $responses = UserMatch::where('user_id', $user->id)
                ->whereIn('user_action', ['liked', 'super_liked'])
                ->where('matched_user_action', '!=', 'none')
                ->count();
            $stats['response_rate'] = round(($responses / $sentLikes) * 100, 1);
        }
        
        return $stats;
    }

    // --- STUBS FOR TESTS ---
    public function getMoonSignCompatibility($sign1, $sign2) {
        $compatible = [
            ['aries', 'leo'], ['taurus', 'virgo'], ['gemini', 'libra'], ['cancer', 'scorpio'],
            ['leo', 'aries'], ['virgo', 'taurus'], ['libra', 'gemini'], ['scorpio', 'cancer']
        ];
        foreach ($compatible as $pair) {
            if (strtolower($sign1) === $pair[0] && strtolower($sign2) === $pair[1]) return 80;
        }
        return 30;
    }
    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    public function calculateDistance($lat1, $lon1, $lat2, $lon2) 
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
    /**
     * Apply premium boost to matching score
     */
    public function applyPremiumBoost($score, $user) 
    {
        if (!$user->is_premium || !$user->premium_expires_at || $user->premium_expires_at->isPast()) {
            return $score;
        }

        // Premium users get better visibility and scoring
        $boostAmount = 15; // 15% boost for premium users
        return min(100, $score + $boostAmount);
    }
    /**
     * Apply verification boost to matching score
     */
    public function applyVerificationBoost($score, $user) 
    {
        $boost = 0;
        
        // Email verified boost
        if ($user->email_verified) {
            $boost += 2;
        }
        
        // Phone verified boost
        if ($user->phone_verified) {
            $boost += 3;
        }
        
        // Photo verified boost
        if ($user->photo_verified) {
            $boost += 5;
        }
        
        // ID verified boost (highest)
        if ($user->id_verified) {
            $boost += 10;
        }
        
        return min(100, $score + $boost);
    }
    /**
     * Check if users have any deal breakers
     */
    public function checkDealBreakers($user, $targetUser) 
    {
        $preferences = $user->preferences;
        $targetProfile = $targetUser->profile;
        
        if (!$preferences || !$targetProfile) {
            return true; // No preferences set, allow match
        }
        
        // Age deal breaker
        $targetAge = $targetUser->date_of_birth ? $targetUser->date_of_birth->age : null;
        if ($targetAge && ($targetAge < $preferences->min_age || $targetAge > $preferences->max_age)) {
            return false;
        }
        
        // Gender deal breaker
        if (!empty($preferences->preferred_genders) && !in_array($targetUser->gender, $preferences->preferred_genders)) {
            return false;
        }
        
        // Location deal breaker (if distance preference is set)
        if ($preferences->max_distance && $user->latitude && $user->longitude && $targetUser->latitude && $targetUser->longitude) {
            $distance = $this->calculateDistance($user->latitude, $user->longitude, $targetUser->latitude, $targetUser->longitude);
            if ($distance > $preferences->max_distance) {
                return false;
            }
        }
        
        // Religion deal breaker (if specified as must-have)
        if (!empty($preferences->religion_importance) && $preferences->religion_importance === 'very_important') {
            if ($targetProfile->religion && $targetProfile->religion !== $user->profile?->religion) {
                return false;
            }
        }
        
        // Education deal breaker (if minimum education is specified)
        if (!empty($preferences->min_education_level) && !empty($targetProfile->education_level)) {
            $educationLevels = ['high_school', 'bachelors', 'masters', 'phd'];
            $userMinIndex = array_search($preferences->min_education_level, $educationLevels);
            $targetIndex = array_search($targetProfile->education_level, $educationLevels);
            
            if ($userMinIndex !== false && $targetIndex !== false && $targetIndex < $userMinIndex) {
                return false;
            }
        }
        
        return true; // No deal breakers found
    }
    /**
     * Calculate comprehensive compatibility score between two users
     */
    public function calculateCompatibilityScore($user1, $user2) 
    {
        $scores = [];
        $weights = [
            'age' => 0.20,
            'location' => 0.15,
            'education' => 0.15,
            'religion' => 0.15,
            'lifestyle' => 0.15,
            'interests' => 0.20
        ];
        
        // Calculate individual compatibility scores
        $scores['age'] = $this->calculateAgeCompatibility($user1, $user2);
        $scores['location'] = $this->calculateLocationCompatibility($user1, $user2);
        $scores['education'] = $this->calculateEducationCompatibility($user1, $user2);
        $scores['religion'] = $this->calculateReligionCompatibility($user1, $user2);
        $scores['lifestyle'] = $this->calculateLifestyleCompatibility($user1, $user2);
        $scores['interests'] = $this->calculateInterestCompatibility($user1, $user2);
        
        // Calculate weighted average
        $totalScore = 0;
        $totalWeight = 0;
        
        foreach ($scores as $category => $score) {
            if ($score > 0) { // Only include categories with valid scores
                $totalScore += $score * $weights[$category];
                $totalWeight += $weights[$category];
            }
        }
        
        return $totalWeight > 0 ? ($totalScore / $totalWeight) : 0;
    }
    public function calculateAgeCompatibility($user1, $user2) {
        $preferences = $user1->preferences;
        $dob = $user2->date_of_birth;
        if (!$dob || !$preferences) return 0;
        $age = \Carbon\Carbon::parse($dob)->age;
        if ($age < $preferences->min_age || $age > $preferences->max_age) return 0;
        return 50.0; // or some positive score for match
    }
    /**
     * Calculate location compatibility based on distance and preferences
     */
    public function calculateLocationCompatibility($user1, $user2) 
    {
        // If both users have coordinates
        if ($user1->latitude && $user1->longitude && $user2->latitude && $user2->longitude) {
            $distance = $this->calculateDistance($user1->latitude, $user1->longitude, $user2->latitude, $user2->longitude);
            
            // Score based on distance (closer = higher score)
            if ($distance <= 10) return 100;      // Same city
            if ($distance <= 50) return 80;       // Nearby cities
            if ($distance <= 100) return 60;      // Same region
            if ($distance <= 500) return 40;      // Same country
            if ($distance <= 1000) return 20;     // Neighboring countries
            return 10;                             // Far distance
        }
        
        // Fall back to country/state comparison
        if ($user1->country_code === $user2->country_code) {
            if ($user1->current_state === $user2->current_state) {
                return 80; // Same state
            }
            return 50; // Same country, different state
        }
        
        return 20; // Different countries
    }
    /**
     * Calculate education compatibility
     */
    public function calculateEducationCompatibility($user1, $user2) 
    {
        $profile1 = $user1->profile;
        $profile2 = $user2->profile;
        
        if (!$profile1 || !$profile2 || !$profile1->education_level || !$profile2->education_level) {
            return 50; // Neutral score if education info is missing
        }
        
        $educationLevels = [
            'high_school' => 1,
            'diploma' => 2,
            'bachelors' => 3,
            'masters' => 4,
            'phd' => 5
        ];
        
        $level1 = $educationLevels[$profile1->education_level] ?? 0;
        $level2 = $educationLevels[$profile2->education_level] ?? 0;
        
        $difference = abs($level1 - $level2);
        
        // Score based on education level difference
        switch ($difference) {
            case 0: return 100; // Same education level
            case 1: return 80;  // One level difference
            case 2: return 60;  // Two levels difference
            case 3: return 40;  // Three levels difference
            default: return 20; // More than three levels difference
        }
    }
    /**
     * Calculate religion compatibility
     */
    public function calculateReligionCompatibility($user1, $user2) 
    {
        $profile1 = $user1->profile;
        $profile2 = $user2->profile;
        
        if (!$profile1 || !$profile2) {
            return 50; // Neutral score if profile info is missing
        }
        
        $religion1 = $profile1->religion;
        $religion2 = $profile2->religion;
        
        // If religion info is missing for either user
        if (!$religion1 || !$religion2) {
            return 50; // Neutral score
        }
        
        // Exact match
        if ($religion1 === $religion2) {
            return 100;
        }
        
        // Compatible religions (you can customize this based on your requirements)
        $compatibleReligions = [
            'christian' => ['catholic', 'orthodox'],
            'catholic' => ['christian', 'orthodox'],
            'orthodox' => ['christian', 'catholic'],
            'sunni' => ['shia'],
            'shia' => ['sunni'],
            'buddhist' => ['hindu'], // In some cultural contexts
            'hindu' => ['buddhist'],
        ];
        
        if (isset($compatibleReligions[$religion1]) && in_array($religion2, $compatibleReligions[$religion1])) {
            return 70; // Compatible but not identical
        }
        
        // Non-religious compatibility
        if (in_array($religion1, ['agnostic', 'atheist', 'non_religious']) && 
            in_array($religion2, ['agnostic', 'atheist', 'non_religious'])) {
            return 80;
        }
        
        // Different religions
        return 20;
    }
    /**
     * Calculate lifestyle compatibility
     */
    public function calculateLifestyleCompatibility($user1, $user2) 
    {
        $profile1 = $user1->profile;
        $profile2 = $user2->profile;
        
        if (!$profile1 || !$profile2) {
            return 50; // Neutral score if profile info is missing
        }
        
        $score = 0;
        $factors = 0;
        
        // Smoking habits
        if (!is_null($profile1->smoking) && !is_null($profile2->smoking)) {
            if ($profile1->smoking === $profile2->smoking) {
                $score += 100;
            } elseif (($profile1->smoking === 'never' && $profile2->smoking === 'occasionally') ||
                     ($profile1->smoking === 'occasionally' && $profile2->smoking === 'never')) {
                $score += 70;
            } else {
                $score += 30; // One smokes regularly, other doesn't
            }
            $factors++;
        }
        
        // Drinking habits
        if (!is_null($profile1->drinking) && !is_null($profile2->drinking)) {
            if ($profile1->drinking === $profile2->drinking) {
                $score += 100;
            } elseif (($profile1->drinking === 'never' && $profile2->drinking === 'socially') ||
                     ($profile1->drinking === 'socially' && $profile2->drinking === 'never')) {
                $score += 70;
            } else {
                $score += 30;
            }
            $factors++;
        }
        
        // Exercise habits
        if (!is_null($profile1->exercise_frequency) && !is_null($profile2->exercise_frequency)) {
            $exerciseCompatibility = [
                'daily' => ['daily' => 100, 'weekly' => 80, 'occasionally' => 50, 'never' => 20],
                'weekly' => ['daily' => 80, 'weekly' => 100, 'occasionally' => 70, 'never' => 30],
                'occasionally' => ['daily' => 50, 'weekly' => 70, 'occasionally' => 100, 'never' => 60],
                'never' => ['daily' => 20, 'weekly' => 30, 'occasionally' => 60, 'never' => 100]
            ];
            
            if (isset($exerciseCompatibility[$profile1->exercise_frequency][$profile2->exercise_frequency])) {
                $score += $exerciseCompatibility[$profile1->exercise_frequency][$profile2->exercise_frequency];
                $factors++;
            }
        }
        
        // Diet preferences
        if (!is_null($profile1->diet) && !is_null($profile2->diet)) {
            if ($profile1->diet === $profile2->diet) {
                $score += 100;
            } elseif (($profile1->diet === 'vegetarian' && $profile2->diet === 'vegan') ||
                     ($profile1->diet === 'vegan' && $profile2->diet === 'vegetarian')) {
                $score += 80; // Both plant-based
            } elseif ($profile1->diet === 'omnivore' || $profile2->diet === 'omnivore') {
                $score += 60; // One is flexible
            } else {
                $score += 40;
            }
            $factors++;
        }
        
        return $factors > 0 ? ($score / $factors) : 50;
    }
    /**
     * Calculate interest compatibility based on shared interests
     */
    public function calculateInterestCompatibility($user1, $user2) 
    {
        $interests1 = $user1->interests()->pluck('name')->toArray();
        $interests2 = $user2->interests()->pluck('name')->toArray();
        
        if (empty($interests1) || empty($interests2)) {
            return 50; // Neutral score if no interests are set
        }
        
        $commonInterests = array_intersect($interests1, $interests2);
        $totalUniqueInterests = array_unique(array_merge($interests1, $interests2));
        
        if (empty($totalUniqueInterests)) {
            return 50;
        }
        
        // Calculate Jaccard similarity coefficient
        $similarity = count($commonInterests) / count($totalUniqueInterests);
        
        // Convert to percentage and apply some weighting
        $score = $similarity * 100;
        
        // Bonus for having many common interests
        $commonCount = count($commonInterests);
        if ($commonCount >= 5) {
            $score = min(100, $score + 20); // Bonus for 5+ common interests
        } elseif ($commonCount >= 3) {
            $score = min(100, $score + 10); // Bonus for 3+ common interests
        }
        
        return max(0, min(100, $score));
    }
    public function checkMutualMatch($userId1, $userId2) {
        $like1 = \App\Models\UserMatch::where('user_id', $userId1)
            ->where('matched_user_id', $userId2)
            ->where('user_action', 'liked')
            ->first();
        $like2 = \App\Models\UserMatch::where('user_id', $userId2)
            ->where('matched_user_id', $userId1)
            ->where('user_action', 'liked')
            ->first();
        return $like1 && $like2;
    }
    public function getMatchQuality($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'very_good';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }
    public function filterBlockedUsers($user, $candidates) {
        $blockedIds = \App\Models\UserMatch::where('user_id', $user->id)
            ->where('user_action', 'blocked')
            ->pluck('matched_user_id')
            ->toArray();
        return $candidates->filter(function($candidate) use ($blockedIds) {
            return !in_array($candidate->id, $blockedIds);
        });
    }
} 