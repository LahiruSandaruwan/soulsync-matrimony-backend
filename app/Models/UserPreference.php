<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPreference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        // Basic preferences
        'min_age', 'max_age', 'min_height_cm', 'max_height_cm', 'preferred_genders',
        
        // Location preferences
        'preferred_countries', 'preferred_states', 'preferred_cities', 
        'max_distance_km', 'willing_to_relocate',
        
        // Cultural & Religious preferences
        'preferred_religions', 'preferred_castes', 'preferred_mother_tongues', 'preferred_religiousness',
        
        // Education & Career preferences
        'preferred_education_levels', 'preferred_occupations', 'min_income_usd', 'max_income_usd', 'preferred_working_status',
        
        // Physical preferences
        'preferred_body_types', 'preferred_complexions', 'preferred_blood_groups', 'accept_physically_challenged',
        
        // Lifestyle preferences
        'preferred_diets', 'preferred_smoking_habits', 'preferred_drinking_habits',
        
        // Matrimonial preferences
        'preferred_marital_status', 'accept_with_children', 'max_children_count',
        
        // Family preferences
        'preferred_family_types', 'preferred_family_status',
        
        // Horoscope preferences
        'require_horoscope_match', 'min_horoscope_score', 'preferred_zodiac_signs', 'preferred_stars',
        
        // Matching behavior
        'auto_accept_matches', 'show_me_on_search', 'hide_profile_from_premium', 'preferred_distance_km',
        
        // Premium preferences
        'show_only_verified_profiles', 'show_only_premium_profiles', 'priority_to_recent_profiles',
        
        // Notification preferences
        'email_new_matches', 'email_profile_views', 'email_messages',
        'push_new_matches', 'push_profile_views', 'push_messages'
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'preferred_genders' => 'array',
            'preferred_countries' => 'array',
            'preferred_states' => 'array',
            'preferred_cities' => 'array',
            'preferred_religions' => 'array',
            'preferred_castes' => 'array',
            'preferred_mother_tongues' => 'array',
            'preferred_religiousness' => 'array',
            'preferred_education_levels' => 'array',
            'preferred_occupations' => 'array',
            'preferred_working_status' => 'array',
            'preferred_body_types' => 'array',
            'preferred_complexions' => 'array',
            'preferred_blood_groups' => 'array',
            'preferred_diets' => 'array',
            'preferred_smoking_habits' => 'array',
            'preferred_drinking_habits' => 'array',
            'preferred_marital_status' => 'array',
            'preferred_family_types' => 'array',
            'preferred_family_status' => 'array',
            'preferred_zodiac_signs' => 'array',
            'preferred_stars' => 'array',
            'min_income_usd' => 'decimal:2',
            'max_income_usd' => 'decimal:2',
            'min_horoscope_score' => 'decimal:1',
            'willing_to_relocate' => 'boolean',
            'accept_physically_challenged' => 'boolean',
            'accept_with_children' => 'boolean',
            'require_horoscope_match' => 'boolean',
            'auto_accept_matches' => 'boolean',
            'show_me_on_search' => 'boolean',
            'hide_profile_from_premium' => 'boolean',
            'show_only_verified_profiles' => 'boolean',
            'show_only_premium_profiles' => 'boolean',
            'priority_to_recent_profiles' => 'boolean',
            'email_new_matches' => 'boolean',
            'email_profile_views' => 'boolean',
            'email_messages' => 'boolean',
            'push_new_matches' => 'boolean',
            'push_profile_views' => 'boolean',
            'push_messages' => 'boolean',
        ];
    }

    // Relationships

    /**
     * Get the user that owns the preferences.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Business Logic

    /**
     * Check if a user profile matches these preferences.
     */
    public function matchesProfile(UserProfile $profile): bool
    {
        $user = $profile->user;
        
        // Age check
        if ($user->age < $this->min_age || $user->age > $this->max_age) {
            return false;
        }
        
        // Gender check
        if ($this->preferred_genders && !in_array($user->gender, $this->preferred_genders)) {
            return false;
        }
        
        // Height check
        if ($this->min_height_cm && $profile->height_cm < $this->min_height_cm) {
            return false;
        }
        if ($this->max_height_cm && $profile->height_cm > $this->max_height_cm) {
            return false;
        }
        
        // Location check
        if ($this->preferred_countries && !in_array($profile->current_country, $this->preferred_countries)) {
            return false;
        }
        
        // Religion check
        if ($this->preferred_religions && !in_array($profile->religion, $this->preferred_religions)) {
            return false;
        }
        
        // Education check
        if ($this->preferred_education_levels && !in_array($profile->education_level, $this->preferred_education_levels)) {
            return false;
        }
        
        // Income check
        if ($this->min_income_usd && $profile->annual_income_usd < $this->min_income_usd) {
            return false;
        }
        if ($this->max_income_usd && $profile->annual_income_usd > $this->max_income_usd) {
            return false;
        }
        
        // Marital status check
        if ($this->preferred_marital_status && !in_array($profile->marital_status, $this->preferred_marital_status)) {
            return false;
        }
        
        // Children check
        if (!$this->accept_with_children && $profile->have_children) {
            return false;
        }
        if ($this->max_children_count && $profile->children_count > $this->max_children_count) {
            return false;
        }
        
        // Diet check
        if ($this->preferred_diets && !in_array($profile->diet, $this->preferred_diets)) {
            return false;
        }
        
        // Smoking/drinking check
        if ($this->preferred_smoking_habits && !in_array($profile->smoking, $this->preferred_smoking_habits)) {
            return false;
        }
        if ($this->preferred_drinking_habits && !in_array($profile->drinking, $this->preferred_drinking_habits)) {
            return false;
        }
        
        // Physical challenges check
        if (!$this->accept_physically_challenged && $profile->physically_challenged) {
            return false;
        }
        
        return true;
    }

    /**
     * Get a compatibility score (0-100) for how well a profile matches preferences.
     */
    public function getMatchScore(UserProfile $profile): float
    {
        $score = 0;
        $maxScore = 0;
        
        // Age matching (weight: 15)
        $maxScore += 15;
        $userAge = $profile->user->age;
        if ($userAge >= $this->min_age && $userAge <= $this->max_age) {
            $ageRange = $this->max_age - $this->min_age;
            $ageCenter = ($this->min_age + $this->max_age) / 2;
            $ageDistance = abs($userAge - $ageCenter);
            $ageScore = max(0, 15 - ($ageDistance / $ageRange * 15));
            $score += $ageScore;
        }
        
        // Location matching (weight: 10)
        $maxScore += 10;
        if (!$this->preferred_countries || in_array($profile->current_country, $this->preferred_countries)) {
            $score += 10;
        }
        
        // Religion matching (weight: 15)
        $maxScore += 15;
        if (!$this->preferred_religions || in_array($profile->religion, $this->preferred_religions)) {
            $score += 15;
        }
        
        // Education matching (weight: 10)
        $maxScore += 10;
        if (!$this->preferred_education_levels || in_array($profile->education_level, $this->preferred_education_levels)) {
            $score += 10;
        }
        
        // Lifestyle matching (weight: 20)
        $maxScore += 20;
        $lifestyleScore = 0;
        if (!$this->preferred_diets || in_array($profile->diet, $this->preferred_diets)) {
            $lifestyleScore += 7;
        }
        if (!$this->preferred_smoking_habits || in_array($profile->smoking, $this->preferred_smoking_habits)) {
            $lifestyleScore += 7;
        }
        if (!$this->preferred_drinking_habits || in_array($profile->drinking, $this->preferred_drinking_habits)) {
            $lifestyleScore += 6;
        }
        $score += $lifestyleScore;
        
        // Marital status matching (weight: 10)
        $maxScore += 10;
        if (!$this->preferred_marital_status || in_array($profile->marital_status, $this->preferred_marital_status)) {
            $score += 10;
        }
        
        // Children compatibility (weight: 10)
        $maxScore += 10;
        if ($this->accept_with_children || !$profile->have_children) {
            if (!$this->max_children_count || $profile->children_count <= $this->max_children_count) {
                $score += 10;
            }
        }
        
        // Height matching (weight: 5)
        $maxScore += 5;
        if ((!$this->min_height_cm || $profile->height_cm >= $this->min_height_cm) &&
            (!$this->max_height_cm || $profile->height_cm <= $this->max_height_cm)) {
            $score += 5;
        }
        
        // Income matching (weight: 5)
        $maxScore += 5;
        if ((!$this->min_income_usd || $profile->annual_income_usd >= $this->min_income_usd) &&
            (!$this->max_income_usd || $profile->annual_income_usd <= $this->max_income_usd)) {
            $score += 5;
        }
        
        return $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0;
    }

    /**
     * Get deal breakers - preferences that are absolute requirements.
     */
    public function getDealBreakers(): array
    {
        $dealBreakers = [];
        
        if (!$this->accept_with_children) {
            $dealBreakers[] = 'no_children';
        }
        
        if (!$this->accept_physically_challenged) {
            $dealBreakers[] = 'no_physical_challenges';
        }
        
        if ($this->require_horoscope_match) {
            $dealBreakers[] = 'horoscope_required';
        }
        
        if ($this->show_only_verified_profiles) {
            $dealBreakers[] = 'verified_only';
        }
        
        return $dealBreakers;
    }

    /**
     * Check if preferences are sufficiently filled for matching.
     */
    public function areComplete(): bool
    {
        // Check essential preferences
        return $this->min_age && $this->max_age && 
               !empty($this->preferred_genders) &&
               !empty($this->preferred_countries);
    }

    /**
     * Get preference summary for display.
     */
    public function getSummary(): array
    {
        return [
            'age_range' => $this->min_age . '-' . $this->max_age,
            'preferred_genders' => $this->preferred_genders,
            'preferred_countries' => $this->preferred_countries,
            'preferred_religions' => $this->preferred_religions,
            'accepts_children' => $this->accept_with_children,
            'max_distance' => $this->max_distance_km . 'km',
            'deal_breakers' => $this->getDealBreakers(),
        ];
    }
}
