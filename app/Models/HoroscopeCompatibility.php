<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HoroscopeCompatibility extends Model
{
    use HasFactory;

    protected $fillable = [
        'user1_horoscope_id',
        'user2_horoscope_id',
        'overall_score',
        'zodiac_compatibility',
        'moon_sign_compatibility',
        'manglik_compatibility',
        'dosha_compatibility',
        'ashtakoot_score',
        'compatibility_grade',
        'detailed_analysis',
        'remedies_suggested',
        'is_verified',
        'verified_by',
        'verified_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'overall_score' => 'integer',
        'zodiac_compatibility' => 'integer',
        'moon_sign_compatibility' => 'integer',
        'manglik_compatibility' => 'integer',
        'dosha_compatibility' => 'integer',
        'ashtakoot_score' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'detailed_analysis' => 'array',
        'remedies_suggested' => 'array',
    ];

    protected $dates = [
        'verified_at',
    ];

    // Compatibility Grades
    const GRADE_EXCELLENT = 'Excellent';
    const GRADE_GOOD = 'Good';
    const GRADE_AVERAGE = 'Average';
    const GRADE_POOR = 'Poor';
    const GRADE_INCOMPATIBLE = 'Incompatible';

    // Relationships
    public function user1Horoscope(): BelongsTo
    {
        return $this->belongsTo(Horoscope::class, 'user1_horoscope_id');
    }

    public function user2Horoscope(): BelongsTo
    {
        return $this->belongsTo(Horoscope::class, 'user2_horoscope_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Methods
    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    public function getCompatibilityGrade(): string
    {
        if ($this->overall_score >= 80) {
            return self::GRADE_EXCELLENT;
        } elseif ($this->overall_score >= 60) {
            return self::GRADE_GOOD;
        } elseif ($this->overall_score >= 40) {
            return self::GRADE_AVERAGE;
        } elseif ($this->overall_score >= 20) {
            return self::GRADE_POOR;
        } else {
            return self::GRADE_INCOMPATIBLE;
        }
    }

    public function isCompatible(): bool
    {
        return $this->overall_score >= 60;
    }

    public function isExcellent(): bool
    {
        return $this->overall_score >= 80;
    }

    public function isGood(): bool
    {
        return $this->overall_score >= 60 && $this->overall_score < 80;
    }

    public function isAverage(): bool
    {
        return $this->overall_score >= 40 && $this->overall_score < 60;
    }

    public function isPoor(): bool
    {
        return $this->overall_score >= 20 && $this->overall_score < 40;
    }

    public function isIncompatible(): bool
    {
        return $this->overall_score < 20;
    }

    public function getDetailedAnalysis(): array
    {
        return $this->detailed_analysis ?? [];
    }

    public function getRemediesSuggested(): array
    {
        return $this->remedies_suggested ?? [];
    }

    public function verify(User $astrologer): bool
    {
        return $this->update([
            'is_verified' => true,
            'verified_by' => $astrologer->id,
            'verified_at' => now(),
        ]);
    }

    public function unverify(): bool
    {
        return $this->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);
    }

    public function getCompatibilitySummary(): array
    {
        return [
            'overall_score' => $this->overall_score,
            'grade' => $this->getCompatibilityGrade(),
            'is_compatible' => $this->isCompatible(),
            'zodiac_score' => $this->zodiac_compatibility,
            'moon_sign_score' => $this->moon_sign_compatibility,
            'manglik_score' => $this->manglik_compatibility,
            'dosha_score' => $this->dosha_compatibility,
            'ashtakoot_score' => $this->ashtakoot_score,
            'is_verified' => $this->is_verified,
            'verified_by' => $this->verifiedBy?->first_name . ' ' . $this->verifiedBy?->last_name,
            'verified_at' => $this->verified_at?->format('Y-m-d H:i:s'),
        ];
    }

    // Static methods
    public static function calculateCompatibility(Horoscope $horoscope1, Horoscope $horoscope2): self
    {
        $compatibility = $horoscope1->calculateCompatibility($horoscope2);

        return self::updateOrCreate(
            [
                'user1_horoscope_id' => $horoscope1->id,
                'user2_horoscope_id' => $horoscope2->id,
            ],
            [
                'overall_score' => $compatibility['score'],
                'zodiac_compatibility' => $compatibility['analysis']['zodiac']['score'] ?? 0,
                'moon_sign_compatibility' => $compatibility['analysis']['moon_sign']['score'] ?? 0,
                'manglik_compatibility' => $compatibility['analysis']['manglik']['score'] ?? 0,
                'dosha_compatibility' => $compatibility['analysis']['dosha']['score'] ?? 0,
                'ashtakoot_score' => $compatibility['analysis']['ashtakoot']['score'] ?? 0,
                'compatibility_grade' => $compatibility['grade'],
                'detailed_analysis' => $compatibility['analysis'],
                'remedies_suggested' => $compatibility['recommendations'],
            ]
        );
    }

    public static function getCompatibility(Horoscope $horoscope1, Horoscope $horoscope2): ?self
    {
        return self::where(function ($query) use ($horoscope1, $horoscope2) {
            $query->where('user1_horoscope_id', $horoscope1->id)
                  ->where('user2_horoscope_id', $horoscope2->id);
        })->orWhere(function ($query) use ($horoscope1, $horoscope2) {
            $query->where('user1_horoscope_id', $horoscope2->id)
                  ->where('user2_horoscope_id', $horoscope1->id);
        })->first();
    }

    public static function getOrCalculateCompatibility(Horoscope $horoscope1, Horoscope $horoscope2): self
    {
        $compatibility = self::getCompatibility($horoscope1, $horoscope2);
        
        if (!$compatibility) {
            $compatibility = self::calculateCompatibility($horoscope1, $horoscope2);
        }
        
        return $compatibility;
    }

    public static function getVerifiedCompatibilities(): array
    {
        return self::where('is_verified', true)
                  ->with(['user1Horoscope.user', 'user2Horoscope.user', 'verifiedBy'])
                  ->orderBy('overall_score', 'desc')
                  ->get()
                  ->toArray();
    }

    public static function getHighCompatibilityMatches(int $minScore = 80): array
    {
        return self::where('overall_score', '>=', $minScore)
                  ->with(['user1Horoscope.user', 'user2Horoscope.user'])
                  ->orderBy('overall_score', 'desc')
                  ->get()
                  ->toArray();
    }

    public static function getCompatibilityStats(): array
    {
        $total = self::count();
        $verified = self::where('is_verified', true)->count();
        $excellent = self::where('overall_score', '>=', 80)->count();
        $good = self::whereBetween('overall_score', [60, 79])->count();
        $average = self::whereBetween('overall_score', [40, 59])->count();
        $poor = self::whereBetween('overall_score', [20, 39])->count();
        $incompatible = self::where('overall_score', '<', 20)->count();

        return [
            'total_compatibilities' => $total,
            'verified_compatibilities' => $verified,
            'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0,
            'excellent_matches' => $excellent,
            'good_matches' => $good,
            'average_matches' => $average,
            'poor_matches' => $poor,
            'incompatible_matches' => $incompatible,
            'excellent_rate' => $total > 0 ? round(($excellent / $total) * 100, 2) : 0,
            'good_rate' => $total > 0 ? round(($good / $total) * 100, 2) : 0,
            'average_rate' => $total > 0 ? round(($average / $total) * 100, 2) : 0,
            'poor_rate' => $total > 0 ? round(($poor / $total) * 100, 2) : 0,
            'incompatible_rate' => $total > 0 ? round(($incompatible / $total) * 100, 2) : 0,
        ];
    }
} 