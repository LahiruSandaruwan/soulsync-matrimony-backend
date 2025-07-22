<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        // Physical attributes
        'height_cm', 'weight_kg', 'body_type', 'complexion', 'blood_group',
        'physically_challenged', 'physical_challenge_details',
        
        // Location
        'current_city', 'current_state', 'current_country',
        'hometown_city', 'hometown_state', 'hometown_country',
        'latitude', 'longitude',
        
        // Education & Career
        'education_level', 'education_field', 'college_university',
        'occupation', 'company', 'job_title', 'annual_income_usd', 'working_status',
        
        // Cultural & Religious
        'religion', 'caste', 'sub_caste', 'mother_tongue', 'languages_known', 'religiousness',
        
        // Family
        'family_type', 'family_status', 'father_occupation', 'mother_occupation',
        'brothers_count', 'sisters_count', 'brothers_married', 'sisters_married', 'family_details',
        
        // Lifestyle
        'diet', 'smoking', 'drinking', 'hobbies', 'about_me', 'looking_for',
        
        // Matrimonial specific
        'marital_status', 'have_children', 'children_count', 'children_living_status',
        'willing_to_relocate', 'preferred_locations',
        
        // Verification
        'profile_verified', 'income_verified', 'education_verified',
        'verified_at', 'verified_by',
        
        // Completion tracking
        'profile_completion_percentage', 'last_updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'languages_known' => 'array',
            'hobbies' => 'array',
            'preferred_locations' => 'array',
            'physically_challenged' => 'boolean',
            'have_children' => 'boolean',
            'willing_to_relocate' => 'boolean',
            'profile_verified' => 'boolean',
            'income_verified' => 'boolean',
            'education_verified' => 'boolean',
            'verified_at' => 'datetime',
            'last_updated_at' => 'datetime',
            'annual_income_usd' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'annual_income_usd', // Hide income unless premium or matched
    ];

    // Relationships

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who verified this profile.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Accessors

    /**
     * Get the formatted height in feet and inches.
     */
    public function getHeightFeetAttribute(): string
    {
        if (!$this->height_cm) return 'Not specified';
        
        $totalInches = $this->height_cm / 2.54;
        $feet = floor($totalInches / 12);
        $inches = round($totalInches % 12);
        
        return $feet . "'" . $inches . '"';
    }

    /**
     * Get BMI calculation.
     */
    public function getBmiAttribute(): ?float
    {
        if (!$this->height_cm || !$this->weight_kg) return null;
        
        $heightM = $this->height_cm / 100;
        return round($this->weight_kg / ($heightM * $heightM), 1);
    }

    /**
     * Get BMI category.
     */
    public function getBmiCategoryAttribute(): ?string
    {
        $bmi = $this->getBmiAttribute();
        if (!$bmi) return null;
        
        if ($bmi < 18.5) return 'Underweight';
        if ($bmi < 25) return 'Normal';
        if ($bmi < 30) return 'Overweight';
        return 'Obese';
    }

    /**
     * Get full location string.
     */
    public function getFullLocationAttribute(): string
    {
        $parts = array_filter([
            $this->current_city,
            $this->current_state,
            $this->current_country
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Get age from user's date of birth.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->user?->age;
    }

    /**
     * Get verification status.
     */
    public function getVerificationScoreAttribute(): int
    {
        $score = 0;
        if ($this->profile_verified) $score += 25;
        if ($this->income_verified) $score += 25;
        if ($this->education_verified) $score += 25;
        if ($this->user?->photo_verified) $score += 25;
        
        return $score;
    }

    /**
     * Check if profile is complete enough for matching.
     */
    public function getIsMatchReadyAttribute(): bool
    {
        return $this->profile_completion_percentage >= 70 && 
               $this->user?->profile_status === 'approved';
    }

    // Mutators

    /**
     * Set languages known array.
     */
    public function setLanguagesKnownAttribute($value)
    {
        $this->attributes['languages_known'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Set hobbies array.
     */
    public function setHobbiesAttribute($value)
    {
        $this->attributes['hobbies'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Automatically update profile completion when saved.
     */
    protected static function booted()
    {
        static::saving(function ($profile) {
            $profile->profile_completion_percentage = $profile->calculateCompletionPercentage();
            $profile->last_updated_at = now();
        });
    }

    // Business Logic Methods

    /**
     * Calculate profile completion percentage.
     */
    public function calculateCompletionPercentage(): int
    {
        $requiredFields = [
            'height_cm', 'body_type', 'current_city', 'current_country',
            'education_level', 'occupation', 'religion', 'mother_tongue',
            'family_type', 'diet', 'marital_status', 'about_me'
        ];
        
        $optionalFields = [
            'weight_kg', 'complexion', 'blood_group', 'education_field',
            'company', 'annual_income_usd', 'caste', 'smoking', 'drinking',
            'hobbies', 'looking_for', 'family_details'
        ];
        
        $completed = 0;
        $total = count($requiredFields) + count($optionalFields);
        
        // Required fields worth more
        foreach ($requiredFields as $field) {
            if (!empty($this->$field)) {
                $completed += 1.5; // Weight required fields more
            }
        }
        
        // Optional fields
        foreach ($optionalFields as $field) {
            if (!empty($this->$field)) {
                $completed += 1;
            }
        }
        
        // Bonus for verification
        if ($this->profile_verified) $completed += 2;
        if ($this->user?->photos()->where('status', 'approved')->count() >= 3) $completed += 2;
        
        $maxScore = (count($requiredFields) * 1.5) + count($optionalFields) + 4; // 4 for bonuses
        
        return min(100, round(($completed / $maxScore) * 100));
    }

    /**
     * Check compatibility with another profile.
     */
    public function getCompatibilityScore(UserProfile $otherProfile): float
    {
        $score = 0;
        $maxScore = 100;
        
        // Location compatibility (20 points)
        if ($this->current_country === $otherProfile->current_country) {
            $score += 10;
            if ($this->current_state === $otherProfile->current_state) {
                $score += 5;
                if ($this->current_city === $otherProfile->current_city) {
                    $score += 5;
                }
            }
        }
        
        // Religious compatibility (15 points)
        if ($this->religion === $otherProfile->religion) {
            $score += 10;
            if ($this->caste === $otherProfile->caste) {
                $score += 5;
            }
        }
        
        // Education compatibility (15 points)
        $educationLevels = ['high_school' => 1, 'bachelor' => 2, 'master' => 3, 'phd' => 4];
        $thisLevel = $educationLevels[$this->education_level] ?? 0;
        $otherLevel = $educationLevels[$otherProfile->education_level] ?? 0;
        $levelDiff = abs($thisLevel - $otherLevel);
        
        if ($levelDiff === 0) $score += 15;
        elseif ($levelDiff === 1) $score += 10;
        elseif ($levelDiff === 2) $score += 5;
        
        // Lifestyle compatibility (20 points)
        if ($this->diet === $otherProfile->diet) $score += 7;
        if ($this->smoking === $otherProfile->smoking) $score += 7;
        if ($this->drinking === $otherProfile->drinking) $score += 6;
        
        // Family compatibility (15 points)
        if ($this->family_type === $otherProfile->family_type) $score += 8;
        if ($this->marital_status === $otherProfile->marital_status) $score += 7;
        
        // Language compatibility (10 points)
        $thisLanguages = $this->languages_known ?? [];
        $otherLanguages = $otherProfile->languages_known ?? [];
        $commonLanguages = array_intersect($thisLanguages, $otherLanguages);
        $score += min(10, count($commonLanguages) * 3);
        
        // Interest compatibility (5 points) - basic calculation
        $thisHobbies = $this->hobbies ?? [];
        $otherHobbies = $otherProfile->hobbies ?? [];
        $commonHobbies = array_intersect($thisHobbies, $otherHobbies);
        $score += min(5, count($commonHobbies) * 1);
        
        return round($score, 2);
    }

    /**
     * Get income in local currency.
     */
    public function getIncomeInCurrency(string $currency = 'USD'): ?float
    {
        if (!$this->annual_income_usd || $currency === 'USD') {
            return $this->annual_income_usd;
        }
        
        // Get exchange rate (this would use the exchange rate service)
        $exchangeRate = $this->getExchangeRate('USD', $currency);
        return $exchangeRate ? $this->annual_income_usd * $exchangeRate : null;
    }

    /**
     * Get exchange rate helper (placeholder).
     */
    private function getExchangeRate(string $from, string $to): ?float
    {
        // This would be implemented using the ExchangeRate model
        return $to === 'LKR' ? 300.0 : 1.0; // Placeholder
    }

    // Scopes

    /**
     * Scope for verified profiles.
     */
    public function scopeVerified($query)
    {
        return $query->where('profile_verified', true);
    }

    /**
     * Scope for complete profiles.
     */
    public function scopeComplete($query, int $minPercentage = 70)
    {
        return $query->where('profile_completion_percentage', '>=', $minPercentage);
    }

    /**
     * Scope for profiles in a specific location.
     */
    public function scopeInLocation($query, string $country, string $state = null, string $city = null)
    {
        $query = $query->where('current_country', $country);
        
        if ($state) {
            $query = $query->where('current_state', $state);
        }
        
        if ($city) {
            $query = $query->where('current_city', $city);
        }
        
        return $query;
    }

    /**
     * Scope for profiles by religion.
     */
    public function scopeByReligion($query, $religion)
    {
        return $query->where('religion', $religion);
    }

    /**
     * Scope for profiles by marital status.
     */
    public function scopeByMaritalStatus($query, $status)
    {
        return $query->where('marital_status', $status);
    }
}
