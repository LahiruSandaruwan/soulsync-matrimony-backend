<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserMatch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id', 'matched_user_id', 'match_type', 'status',
        'user_action', 'matched_user_action', 'user_action_at', 'matched_user_action_at',
        'compatibility_score', 'horoscope_score', 'preference_score', 'ai_score',
        'matching_factors', 'common_interests', 'compatibility_details',
        'can_communicate', 'communication_started_at', 'conversation_id',
        'is_premium_match', 'is_boosted', 'boost_expires_at',
        'profile_views', 'last_viewed_at', 'expires_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'user_action_at' => 'datetime',
            'matched_user_action_at' => 'datetime',
            'communication_started_at' => 'datetime',
            'boost_expires_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'expires_at' => 'datetime',
            'compatibility_score' => 'decimal:2',
            'horoscope_score' => 'decimal:2',
            'preference_score' => 'decimal:2',
            'ai_score' => 'decimal:2',
            'matching_factors' => 'array',
            'common_interests' => 'array',
            'compatibility_details' => 'array',
            'can_communicate' => 'boolean',
            'is_premium_match' => 'boolean',
            'is_boosted' => 'boolean',
        ];
    }

    // Relationships

    /**
     * Get the user who initiated the match.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the matched user.
     */
    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    /**
     * Get the conversation if it exists.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // Accessors

    /**
     * Check if this is a mutual match.
     */
    public function getIsMutualAttribute(): bool
    {
        return $this->status === 'mutual' || 
               ($this->user_action === 'liked' && $this->matched_user_action === 'liked') ||
               ($this->user_action === 'super_liked' && $this->matched_user_action === 'liked') ||
               ($this->user_action === 'liked' && $this->matched_user_action === 'super_liked');
    }

    /**
     * Check if match is still active/valid.
     */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['pending', 'liked', 'super_liked', 'mutual']) &&
               (!$this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Get the overall match quality rating.
     */
    public function getMatchQualityAttribute(): string
    {
        $score = $this->compatibility_score;
        
        if ($score >= 85) return 'excellent';
        if ($score >= 70) return 'very_good';
        if ($score >= 55) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }

    /**
     * Get days since match was created.
     */
    public function getDaysOldAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if match is expiring soon.
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->diffInDays(now()) <= 3;
    }

    // Business Logic Methods

    /**
     * Like a profile.
     */
    public function like(User $user, bool $isSuperLike = false): bool
    {
        $action = $isSuperLike ? 'super_liked' : 'liked';
        
        if ($user->id === $this->user_id) {
            $this->user_action = $action;
            $this->user_action_at = now();
        } elseif ($user->id === $this->matched_user_id) {
            $this->matched_user_action = $action;
            $this->matched_user_action_at = now();
        } else {
            return false;
        }

        // Check if it's now a mutual match
        if ($this->checkMutualMatch()) {
            \Log::info('Mutual match detected', [
                'match_id' => $this->id,
                'user_id' => $this->user_id,
                'matched_user_id' => $this->matched_user_id,
                'user_action' => $this->user_action,
                'matched_user_action' => $this->matched_user_action
            ]);
            
            $this->status = 'mutual';
            $this->can_communicate = true;
            $this->communication_started_at = now();
            
            // Create conversation
            $this->createConversation();
            
            // Send notifications
            $this->sendMutualMatchNotifications();
        } else {
            $this->status = $action;
        }

        return $this->save();
    }

    /**
     * Dislike a profile.
     */
    public function dislike(User $user): bool
    {
        if ($user->id === $this->user_id) {
            $this->user_action = 'disliked';
            $this->user_action_at = now();
        } elseif ($user->id === $this->matched_user_id) {
            $this->matched_user_action = 'disliked';
            $this->matched_user_action_at = now();
        } else {
            return false;
        }

        $this->status = 'disliked';
        $this->expires_at = now(); // Expire immediately
        
        return $this->save();
    }

    /**
     * Block a user.
     */
    public function block(User $user): bool
    {
        if ($user->id === $this->user_id) {
            $this->user_action = 'blocked';
            $this->user_action_at = now();
        } elseif ($user->id === $this->matched_user_id) {
            $this->matched_user_action = 'blocked';
            $this->matched_user_action_at = now();
        } else {
            return false;
        }

        $this->status = 'blocked';
        $this->can_communicate = false;
        
        // Block conversation if exists
        if ($this->conversation_id && $this->conversation) {
            $this->conversation->update([
                'status' => 'blocked',
                'blocked_by' => $user->id,
                'blocked_at' => now()
            ]);
        }
        
        return $this->save();
    }

    /**
     * Record a profile view.
     */
    public function recordView(): void
    {
        $this->increment('profile_views');
        $this->update(['last_viewed_at' => now()]);
    }

    /**
     * Boost this match (premium feature).
     */
    public function boost(int $hours = 24): bool
    {
        $this->is_boosted = true;
        $this->boost_expires_at = now()->addHours($hours);
        
        return $this->save();
    }

    /**
     * Check if both users have liked each other.
     */
    private function checkMutualMatch(): bool
    {
        $userLiked = in_array($this->user_action, ['liked', 'super_liked']);
        $matchedUserLiked = in_array($this->matched_user_action, ['liked', 'super_liked']);
        
        return $userLiked && $matchedUserLiked;
    }

    /**
     * Create a conversation for mutual match.
     */
    private function createConversation(): void
    {
        if (!$this->conversation_id) {
            try {
                $conversation = Conversation::createMatchConversation($this);
                $this->conversation_id = $conversation->id;
                $this->save();
            } catch (\Exception $e) {
                // Log the error for debugging
                \Log::error('Failed to create conversation for match: ' . $e->getMessage(), [
                    'match_id' => $this->id,
                    'user_id' => $this->user_id,
                    'matched_user_id' => $this->matched_user_id
                ]);
            }
        }
    }

    /**
     * Send notifications for mutual match.
     */
    private function sendMutualMatchNotifications(): void
    {
        // Create notifications for both users
        Notification::create([
            'user_id' => $this->user_id,
            'actor_id' => $this->matched_user_id,
            'type' => 'mutual_match',
            'category' => 'match',
            'title' => 'It\'s a Match!',
            'message' => 'You and ' . $this->matchedUser->first_name . ' have liked each other!',
            'data' => ['match_id' => $this->id, 'conversation_id' => $this->conversation_id],
            'priority' => 'high'
        ]);

        Notification::create([
            'user_id' => $this->matched_user_id,
            'actor_id' => $this->user_id,
            'type' => 'mutual_match',
            'category' => 'match',
            'title' => 'It\'s a Match!',
            'message' => 'You and ' . $this->user->first_name . ' have liked each other!',
            'data' => ['match_id' => $this->id, 'conversation_id' => $this->conversation_id],
            'priority' => 'high'
        ]);
    }

    /**
     * Calculate comprehensive compatibility score.
     */
    public function calculateCompatibilityScore(): float
    {
        $userProfile = $this->user->profile;
        $matchedProfile = $this->matchedUser->profile;
        
        if (!$userProfile || !$matchedProfile) {
            return 0.0;
        }

        // Base compatibility from profiles
        $profileScore = $userProfile->getCompatibilityScore($matchedProfile);
        
        // Preference matching score
        $preferenceScore = $this->calculatePreferenceScore();
        
        // Horoscope score if available
        $horoscopeScore = $this->calculateHoroscopeScore();
        
        // AI/ML score (placeholder for future ML implementation)
        $aiScore = $this->calculateAIScore();
        
        // Weight the scores
        $finalScore = ($profileScore * 0.4) + ($preferenceScore * 0.3) + 
                     ($horoscopeScore * 0.2) + ($aiScore * 0.1);
        
        // Update individual scores
        $this->preference_score = $preferenceScore;
        $this->horoscope_score = $horoscopeScore;
        $this->ai_score = $aiScore;
        
        return round($finalScore, 2);
    }

    /**
     * Calculate how well users match each other's preferences.
     */
    private function calculatePreferenceScore(): float
    {
        $userPrefs = $this->user->preferences;
        $matchedUserPrefs = $this->matchedUser->preferences;
        $userProfile = $this->user->profile;
        $matchedProfile = $this->matchedUser->profile;
        
        if (!$userPrefs || !$matchedUserPrefs || !$userProfile || !$matchedProfile) {
            return 50.0; // Default score if preferences not set
        }

        $score = 0;
        $maxScore = 100;

        // Age preference check
        $userAge = $this->user->age;
        $matchedAge = $this->matchedUser->age;
        
        if ($userAge >= $matchedUserPrefs->min_age && $userAge <= $matchedUserPrefs->max_age) {
            $score += 15;
        }
        if ($matchedAge >= $userPrefs->min_age && $matchedAge <= $userPrefs->max_age) {
            $score += 15;
        }

        // Height preference check
        if ($userPrefs->min_height_cm && $userPrefs->max_height_cm) {
            $matchedHeight = $matchedProfile->height_cm;
            if ($matchedHeight >= $userPrefs->min_height_cm && $matchedHeight <= $userPrefs->max_height_cm) {
                $score += 10;
            }
        }

        // Location preference check
        if ($userPrefs->preferred_countries && in_array($matchedProfile->current_country, $userPrefs->preferred_countries)) {
            $score += 10;
        }
        if ($matchedUserPrefs->preferred_countries && in_array($userProfile->current_country, $matchedUserPrefs->preferred_countries)) {
            $score += 10;
        }

        // Religion preference check
        if ($userPrefs->preferred_religions && in_array($matchedProfile->religion, $userPrefs->preferred_religions)) {
            $score += 10;
        }
        if ($matchedUserPrefs->preferred_religions && in_array($userProfile->religion, $matchedUserPrefs->preferred_religions)) {
            $score += 10;
        }

        // Education preference check
        if ($userPrefs->preferred_education_levels && in_array($matchedProfile->education_level, $userPrefs->preferred_education_levels)) {
            $score += 10;
        }
        if ($matchedUserPrefs->preferred_education_levels && in_array($userProfile->education_level, $matchedUserPrefs->preferred_education_levels)) {
            $score += 10;
        }

        // Income preference check
        if ($userPrefs->min_income_usd && $matchedProfile->annual_income_usd >= $userPrefs->min_income_usd) {
            $score += 5;
        }
        if ($matchedUserPrefs->min_income_usd && $userProfile->annual_income_usd >= $matchedUserPrefs->min_income_usd) {
            $score += 5;
        }

        return round($score, 2);
    }

    /**
     * Calculate horoscope compatibility score.
     */
    private function calculateHoroscopeScore(): ?float
    {
        $userHoroscope = $this->user->horoscope;
        $matchedHoroscope = $this->matchedUser->horoscope;
        
        if (!$userHoroscope || !$matchedHoroscope) {
            return null;
        }

        // Use Guna Milan score if available
        if ($userHoroscope->guna_milan_score) {
            return ($userHoroscope->guna_milan_score / 36) * 100;
        }

        // Basic compatibility check
        $score = 50; // Base score

        // Same zodiac sign
        if ($userHoroscope->zodiac_sign === $matchedHoroscope->zodiac_sign) {
            $score += 20;
        }

        // Compatible moon signs (simplified)
        $compatibleSigns = $this->getCompatibleMoonSigns($userHoroscope->moon_sign);
        if (in_array($matchedHoroscope->moon_sign, $compatibleSigns)) {
            $score += 15;
        }

        // Manglik compatibility
        if ($userHoroscope->manglik === $matchedHoroscope->manglik) {
            $score += 15;
        } elseif ($userHoroscope->manglik && !$matchedHoroscope->manglik) {
            $score -= 10;
        }

        return round(min(100, max(0, $score)), 2);
    }

    /**
     * Get compatible moon signs (simplified Vedic astrology).
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
     * Calculate AI/ML compatibility score (placeholder for future ML model).
     */
    private function calculateAIScore(): ?float
    {
        try {
            // Get user profiles
            $userProfile = $this->user->profile;
            $matchedProfile = $this->matchedUser->profile;
            
            if (!$userProfile || !$matchedProfile) {
                return 50.0; // Default score for incomplete profiles
            }
            
            // Calculate various compatibility factors
            $factors = [];
            
            // 1. Profile completeness factor (20%)
            $userCompleteness = $userProfile->profile_completion_percentage ?? 0;
            $matchedCompleteness = $matchedProfile->profile_completion_percentage ?? 0;
            $avgCompleteness = ($userCompleteness + $matchedCompleteness) / 2;
            $factors['completeness'] = $avgCompleteness * 0.2;
            
            // 2. Activity level factor (15%)
            $userActivity = $this->calculateActivityScore($this->user);
            $matchedActivity = $this->calculateActivityScore($this->matchedUser);
            $avgActivity = ($userActivity + $matchedActivity) / 2;
            $factors['activity'] = $avgActivity * 0.15;
            
            // 3. Communication pattern factor (15%)
            $communicationScore = $this->calculateCommunicationCompatibility();
            $factors['communication'] = $communicationScore * 0.15;
            
            // 4. Behavioral compatibility factor (20%)
            $behavioralScore = $this->calculateBehavioralCompatibility();
            $factors['behavioral'] = $behavioralScore * 0.2;
            
            // 5. Response rate factor (15%)
            $responseScore = $this->calculateResponseRateCompatibility();
            $factors['response'] = $responseScore * 0.15;
            
            // 6. Engagement factor (15%)
            $engagementScore = $this->calculateEngagementCompatibility();
            $factors['engagement'] = $engagementScore * 0.15;
            
            // Calculate weighted average
            $totalScore = array_sum($factors);
            
            // Apply machine learning adjustments (simplified)
            $mlAdjustment = $this->applyMLAdjustments($factors);
            $finalScore = $totalScore + $mlAdjustment;
            
            return round(min(100, max(0, $finalScore)), 2);
            
        } catch (\Exception $e) {
            \Log::error('AI compatibility calculation failed', [
                'user_id' => $this->user_id,
                'matched_user_id' => $this->matched_user_id,
                'error' => $e->getMessage()
            ]);
            
            return 50.0; // Fallback score
        }
    }
    
    /**
     * Calculate user activity score
     */
    private function calculateActivityScore($user): float
    {
        $score = 0;
        
        // Login frequency (last 30 days)
        $recentLogins = $user->loginHistory()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $score += min(30, $recentLogins * 2);
        
        // Profile updates (last 30 days)
        $recentUpdates = $user->profileUpdates()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $score += min(20, $recentUpdates * 4);
        
        // Message activity (last 30 days)
        $recentMessages = $user->sentMessages()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $score += min(25, $recentMessages * 0.5);
        
        // Photo uploads (last 30 days)
        $recentPhotos = $user->photos()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $score += min(25, $recentPhotos * 5);
        
        return min(100, $score);
    }
    
    /**
     * Calculate communication compatibility
     */
    private function calculateCommunicationCompatibility(): float
    {
        // Analyze communication patterns between users
        $conversations = \App\Models\Conversation::where(function($q) {
            $q->where('user1_id', $this->user_id)->where('user2_id', $this->matched_user_id)
              ->orWhere('user1_id', $this->matched_user_id)->where('user2_id', $this->user_id);
        })->get();
        
        if ($conversations->isEmpty()) {
            return 50.0; // Neutral score for no communication history
        }
        
        $score = 0;
        
        foreach ($conversations as $conversation) {
            $messages = $conversation->messages;
            
            // Response time analysis
            $avgResponseTime = $this->calculateAverageResponseTime($messages);
            if ($avgResponseTime < 3600) { // Less than 1 hour
                $score += 20;
            } elseif ($avgResponseTime < 86400) { // Less than 1 day
                $score += 15;
            } else {
                $score += 5;
            }
            
            // Message length analysis
            $avgMessageLength = $messages->avg('content_length') ?? 0;
            if ($avgMessageLength > 50) {
                $score += 15;
            } elseif ($avgMessageLength > 20) {
                $score += 10;
            } else {
                $score += 5;
            }
            
            // Conversation depth
            $messageCount = $messages->count();
            if ($messageCount > 50) {
                $score += 25;
            } elseif ($messageCount > 20) {
                $score += 20;
            } elseif ($messageCount > 10) {
                $score += 15;
            } else {
                $score += 10;
            }
        }
        
        return min(100, $score);
    }
    
    /**
     * Calculate behavioral compatibility
     */
    private function calculateBehavioralCompatibility(): float
    {
        $score = 0;
        
        // Analyze user behavior patterns
        $userBehavior = $this->analyzeUserBehavior($this->user);
        $matchedBehavior = $this->analyzeUserBehavior($this->matchedUser);
        
        // Compare behavior patterns
        $behavioralSimilarity = $this->calculateBehavioralSimilarity($userBehavior, $matchedBehavior);
        $score += $behavioralSimilarity * 0.6;
        
        // Activity level compatibility
        $activityCompatibility = $this->calculateActivityCompatibility($userBehavior, $matchedBehavior);
        $score += $activityCompatibility * 0.4;
        
        return min(100, $score);
    }
    
    /**
     * Calculate response rate compatibility
     */
    private function calculateResponseRateCompatibility(): float
    {
        $userResponseRate = $this->calculateResponseRate($this->user);
        $matchedResponseRate = $this->calculateResponseRate($this->matchedUser);
        
        // Higher compatibility for similar response rates
        $responseRateDiff = abs($userResponseRate - $matchedResponseRate);
        $compatibility = max(0, 100 - $responseRateDiff);
        
        return $compatibility;
    }
    
    /**
     * Calculate engagement compatibility
     */
    private function calculateEngagementCompatibility(): float
    {
        $userEngagement = $this->calculateEngagementScore($this->user);
        $matchedEngagement = $this->calculateEngagementScore($this->matchedUser);
        
        // Similar engagement levels indicate compatibility
        $engagementDiff = abs($userEngagement - $matchedEngagement);
        $compatibility = max(0, 100 - $engagementDiff);
        
        return $compatibility;
    }
    
    /**
     * Apply machine learning adjustments
     */
    private function applyMLAdjustments(array $factors): float
    {
        // This would integrate with a trained ML model
        // For now, use a simple heuristic based on factor correlations
        
        $adjustment = 0;
        
        // If all factors are high, boost the score
        $highFactors = array_filter($factors, fn($score) => $score > 15);
        if (count($highFactors) >= 4) {
            $adjustment += 5;
        }
        
        // If factors are very low, reduce the score
        $lowFactors = array_filter($factors, fn($score) => $score < 5);
        if (count($lowFactors) >= 3) {
            $adjustment -= 5;
        }
        
        return $adjustment;
    }

    // Scopes

    /**
     * Scope for mutual matches.
     */
    public function scopeMutual($query)
    {
        return $query->where('status', 'mutual');
    }

    /**
     * Scope for active matches.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'liked', 'super_liked', 'mutual'])
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for matches that can communicate.
     */
    public function scopeCanCommunicate($query)
    {
        return $query->where('can_communicate', true);
    }

    /**
     * Scope for high compatibility matches.
     */
    public function scopeHighCompatibility($query, float $minScore = 70.0)
    {
        return $query->where('compatibility_score', '>=', $minScore);
    }

    /**
     * Scope for premium matches.
     */
    public function scopePremium($query)
    {
        return $query->where('is_premium_match', true);
    }

    /**
     * Scope for boosted matches.
     */
    public function scopeBoosted($query)
    {
        return $query->where('is_boosted', true)
                    ->where('boost_expires_at', '>', now());
    }
}
