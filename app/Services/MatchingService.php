<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserMatch;
use App\Models\UserProfile;
use App\Models\UserPreference;
use App\Models\ExchangeRate;
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
        
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($user, $limit) {
            return $this->findMatches($user, $limit, 'daily');
        });
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
    private function calculateHoroscopeCompatibility(User $user1, User $user2): float
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
    private function calculateActivityScore(User $user): float
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
        // Find or create match record
        $match = UserMatch::firstOrCreate([
            'user_id' => $user->id,
            'matched_user_id' => $targetUser->id,
        ], [
            'match_type' => 'user_action',
            'status' => 'pending',
            'compatibility_score' => $this->calculateQuickCompatibility($user, $targetUser),
        ]);
        
        // Process the like
        $result = $match->like($user, $isSuperLike);
        
        if ($result && $match->is_mutual) {
            // It's a mutual match!
            return [
                'success' => true,
                'is_match' => true,
                'match_id' => $match->id,
                'conversation_id' => $match->conversation_id,
                'message' => 'It\'s a match! You can now start chatting.'
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
} 