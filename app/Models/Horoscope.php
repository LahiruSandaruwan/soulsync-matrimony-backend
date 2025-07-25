<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Horoscope extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'birth_date',
        'birth_time',
        'birth_place',
        'birth_latitude',
        'birth_longitude',
        'zodiac_sign',
        'moon_sign',
        'ascendant',
        'sun_sign',
        'birth_day_of_week',
        'birth_lunar_month',
        'birth_tithi',
        'birth_nakshatra',
        'birth_yoga',
        'birth_karana',
        'manglik_status',
        'kuja_dosha',
        'shani_dosha',
        'rahu_dosha',
        'ketu_dosha',
        'guru_dosha',
        'budh_dosha',
        'shukra_dosha',
        'ashtakoot_score',
        'mangalik_compatibility',
        'nadi_compatibility',
        'gana_compatibility',
        'bhakoot_compatibility',
        'varna_compatibility',
        'vashya_compatibility',
        'tara_compatibility',
        'yoni_compatibility',
        'graha_maitri_compatibility',
        'overall_compatibility_score',
        'compatibility_grade',
        'detailed_analysis',
        'remedies_suggested',
        'is_verified',
        'verified_at',
        'verified_by',
        'astrologer_notes',
        'metadata',
        'calculation_method',
        'last_calculated_at'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'birth_time' => 'datetime',
        'birth_latitude' => 'decimal:6',
        'birth_longitude' => 'decimal:6',
        'kuja_dosha' => 'boolean',
        'shani_dosha' => 'boolean',
        'rahu_dosha' => 'boolean',
        'ketu_dosha' => 'boolean',
        'guru_dosha' => 'boolean',
        'budh_dosha' => 'boolean',
        'shukra_dosha' => 'boolean',
        'ashtakoot_score' => 'integer',
        'overall_compatibility_score' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'last_calculated_at' => 'datetime',
        'metadata' => 'array',
        'detailed_analysis' => 'array',
        'remedies_suggested' => 'array',
        'astrologer_notes' => 'array',
    ];

    protected $dates = [
        'birth_date',
        'birth_time',
        'verified_at',
        'last_calculated_at',
    ];

    // Zodiac Signs
    const ZODIAC_ARIES = 'Aries';
    const ZODIAC_TAURUS = 'Taurus';
    const ZODIAC_GEMINI = 'Gemini';
    const ZODIAC_CANCER = 'Cancer';
    const ZODIAC_LEO = 'Leo';
    const ZODIAC_VIRGO = 'Virgo';
    const ZODIAC_LIBRA = 'Libra';
    const ZODIAC_SCORPIO = 'Scorpio';
    const ZODIAC_SAGITTARIUS = 'Sagittarius';
    const ZODIAC_CAPRICORN = 'Capricorn';
    const ZODIAC_AQUARIUS = 'Aquarius';
    const ZODIAC_PISCES = 'Pisces';

    // Moon Signs (Nakshatras)
    const MOON_ASHWINI = 'Ashwini';
    const MOON_BHARANI = 'Bharani';
    const MOON_KRITTIKA = 'Krittika';
    const MOON_ROHINI = 'Rohini';
    const MOON_MRIGASHIRA = 'Mrigashira';
    const MOON_ARDRA = 'Ardra';
    const MOON_PUNARVASU = 'Punarvasu';
    const MOON_PUSHYA = 'Pushya';
    const MOON_ASHLESHA = 'Ashlesha';
    const MOON_MAGHA = 'Magha';
    const MOON_PURVA_PHALGUNI = 'Purva Phalguni';
    const MOON_UTTARA_PHALGUNI = 'Uttara Phalguni';
    const MOON_HASTA = 'Hasta';
    const MOON_CHITRA = 'Chitra';
    const MOON_SWATI = 'Swati';
    const MOON_VISHAKHA = 'Vishakha';
    const MOON_ANURADHA = 'Anuradha';
    const MOON_JYESHTHA = 'Jyeshtha';
    const MOON_MULA = 'Mula';
    const MOON_PURVA_ASHADHA = 'Purva Ashadha';
    const MOON_UTTARA_ASHADHA = 'Uttara Ashadha';
    const MOON_SHRAVANA = 'Shravana';
    const MOON_DHANISHTA = 'Dhanishta';
    const MOON_SHATABHISHA = 'Shatabhisha';
    const MOON_PURVA_BHADRAPADA = 'Purva Bhadrapada';
    const MOON_UTTARA_BHADRAPADA = 'Uttara Bhadrapada';
    const MOON_REVATI = 'Revati';

    // Compatibility Grades
    const GRADE_EXCELLENT = 'Excellent';
    const GRADE_GOOD = 'Good';
    const GRADE_AVERAGE = 'Average';
    const GRADE_POOR = 'Poor';
    const GRADE_INCOMPATIBLE = 'Incompatible';

    // Calculation Methods
    const METHOD_VEDIC = 'vedic';
    const METHOD_WESTERN = 'western';
    const METHOD_CHINESE = 'chinese';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function compatibilityReports(): HasMany
    {
        return $this->hasMany(HoroscopeCompatibility::class);
    }

    // Scopes
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->where('is_verified', false);
    }

    public function scopeByZodiacSign(Builder $query, string $sign): Builder
    {
        return $query->where('zodiac_sign', $sign);
    }

    public function scopeByMoonSign(Builder $query, string $sign): Builder
    {
        return $query->where('moon_sign', $sign);
    }

    public function scopeManglik(Builder $query): Builder
    {
        return $query->where('manglik_status', true);
    }

    public function scopeNonManglik(Builder $query): Builder
    {
        return $query->where('manglik_status', false);
    }

    public function scopeWithDosha(Builder $query, string $dosha): Builder
    {
        return $query->where($dosha . '_dosha', true);
    }

    public function scopeWithoutDosha(Builder $query, string $dosha): Builder
    {
        return $query->where($dosha . '_dosha', false);
    }

    public function scopeByCompatibilityScore(Builder $query, int $minScore): Builder
    {
        return $query->where('overall_compatibility_score', '>=', $minScore);
    }

    public function scopeByGrade(Builder $query, string $grade): Builder
    {
        return $query->where('compatibility_grade', $grade);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Methods
    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    public function isManglik(): bool
    {
        return $this->manglik_status;
    }

    public function hasDosha(string $dosha): bool
    {
        return $this->{$dosha . '_dosha'} ?? false;
    }

    public function getDoshas(): array
    {
        $doshas = [];
        
        if ($this->kuja_dosha) $doshas[] = 'Kuja';
        if ($this->shani_dosha) $doshas[] = 'Shani';
        if ($this->rahu_dosha) $doshas[] = 'Rahu';
        if ($this->ketu_dosha) $doshas[] = 'Ketu';
        if ($this->guru_dosha) $doshas[] = 'Guru';
        if ($this->budh_dosha) $doshas[] = 'Budh';
        if ($this->shukra_dosha) $doshas[] = 'Shukra';
        
        return $doshas;
    }

    public function getCompatibilityGrade(): string
    {
        if ($this->overall_compatibility_score >= 80) {
            return self::GRADE_EXCELLENT;
        } elseif ($this->overall_compatibility_score >= 60) {
            return self::GRADE_GOOD;
        } elseif ($this->overall_compatibility_score >= 40) {
            return self::GRADE_AVERAGE;
        } elseif ($this->overall_compatibility_score >= 20) {
            return self::GRADE_POOR;
        } else {
            return self::GRADE_INCOMPATIBLE;
        }
    }

    public function getZodiacElement(): string
    {
        return match($this->zodiac_sign) {
            self::ZODIAC_ARIES, self::ZODIAC_LEO, self::ZODIAC_SAGITTARIUS => 'Fire',
            self::ZODIAC_TAURUS, self::ZODIAC_VIRGO, self::ZODIAC_CAPRICORN => 'Earth',
            self::ZODIAC_GEMINI, self::ZODIAC_LIBRA, self::ZODIAC_AQUARIUS => 'Air',
            self::ZODIAC_CANCER, self::ZODIAC_SCORPIO, self::ZODIAC_PISCES => 'Water',
            default => 'Unknown'
        };
    }

    public function getZodiacQuality(): string
    {
        return match($this->zodiac_sign) {
            self::ZODIAC_ARIES, self::ZODIAC_CANCER, self::ZODIAC_LIBRA, self::ZODIAC_CAPRICORN => 'Cardinal',
            self::ZODIAC_TAURUS, self::ZODIAC_LEO, self::ZODIAC_SCORPIO, self::ZODIAC_AQUARIUS => 'Fixed',
            self::ZODIAC_GEMINI, self::ZODIAC_VIRGO, self::ZODIAC_SAGITTARIUS, self::ZODIAC_PISCES => 'Mutable',
            default => 'Unknown'
        };
    }

    public function getCompatibleSigns(): array
    {
        return match($this->zodiac_sign) {
            self::ZODIAC_ARIES => [self::ZODIAC_GEMINI, self::ZODIAC_LEO, self::ZODIAC_SAGITTARIUS, self::ZODIAC_AQUARIUS],
            self::ZODIAC_TAURUS => [self::ZODIAC_CANCER, self::ZODIAC_VIRGO, self::ZODIAC_CAPRICORN, self::ZODIAC_PISCES],
            self::ZODIAC_GEMINI => [self::ZODIAC_ARIES, self::ZODIAC_LEO, self::ZODIAC_LIBRA, self::ZODIAC_AQUARIUS],
            self::ZODIAC_CANCER => [self::ZODIAC_TAURUS, self::ZODIAC_VIRGO, self::ZODIAC_SCORPIO, self::ZODIAC_PISCES],
            self::ZODIAC_LEO => [self::ZODIAC_ARIES, self::ZODIAC_GEMINI, self::ZODIAC_LIBRA, self::ZODIAC_SAGITTARIUS],
            self::ZODIAC_VIRGO => [self::ZODIAC_TAURUS, self::ZODIAC_CANCER, self::ZODIAC_SCORPIO, self::ZODIAC_CAPRICORN],
            self::ZODIAC_LIBRA => [self::ZODIAC_GEMINI, self::ZODIAC_LEO, self::ZODIAC_SAGITTARIUS, self::ZODIAC_AQUARIUS],
            self::ZODIAC_SCORPIO => [self::ZODIAC_CANCER, self::ZODIAC_VIRGO, self::ZODIAC_CAPRICORN, self::ZODIAC_PISCES],
            self::ZODIAC_SAGITTARIUS => [self::ZODIAC_ARIES, self::ZODIAC_LEO, self::ZODIAC_LIBRA, self::ZODIAC_AQUARIUS],
            self::ZODIAC_CAPRICORN => [self::ZODIAC_TAURUS, self::ZODIAC_VIRGO, self::ZODIAC_SCORPIO, self::ZODIAC_PISCES],
            self::ZODIAC_AQUARIUS => [self::ZODIAC_ARIES, self::ZODIAC_GEMINI, self::ZODIAC_LIBRA, self::ZODIAC_SAGITTARIUS],
            self::ZODIAC_PISCES => [self::ZODIAC_TAURUS, self::ZODIAC_CANCER, self::ZODIAC_SCORPIO, self::ZODIAC_CAPRICORN],
            default => []
        };
    }

    public function getIncompatibleSigns(): array
    {
        return match($this->zodiac_sign) {
            self::ZODIAC_ARIES => [self::ZODIAC_CANCER, self::ZODIAC_CAPRICORN],
            self::ZODIAC_TAURUS => [self::ZODIAC_LEO, self::ZODIAC_AQUARIUS],
            self::ZODIAC_GEMINI => [self::ZODIAC_VIRGO, self::ZODIAC_PISCES],
            self::ZODIAC_CANCER => [self::ZODIAC_ARIES, self::ZODIAC_LIBRA],
            self::ZODIAC_LEO => [self::ZODIAC_TAURUS, self::ZODIAC_SCORPIO],
            self::ZODIAC_VIRGO => [self::ZODIAC_GEMINI, self::ZODIAC_SAGITTARIUS],
            self::ZODIAC_LIBRA => [self::ZODIAC_CANCER, self::ZODIAC_CAPRICORN],
            self::ZODIAC_SCORPIO => [self::ZODIAC_LEO, self::ZODIAC_AQUARIUS],
            self::ZODIAC_SAGITTARIUS => [self::ZODIAC_VIRGO, self::ZODIAC_PISCES],
            self::ZODIAC_CAPRICORN => [self::ZODIAC_ARIES, self::ZODIAC_LIBRA],
            self::ZODIAC_AQUARIUS => [self::ZODIAC_TAURUS, self::ZODIAC_SCORPIO],
            self::ZODIAC_PISCES => [self::ZODIAC_GEMINI, self::ZODIAC_SAGITTARIUS],
            default => []
        };
    }

    public function calculateCompatibility(Horoscope $other): array
    {
        $score = 0;
        $analysis = [];

        // Zodiac compatibility (25 points)
        $zodiacScore = $this->calculateZodiacCompatibility($other);
        $score += $zodiacScore;
        $analysis['zodiac'] = [
            'score' => $zodiacScore,
            'compatible' => in_array($other->zodiac_sign, $this->getCompatibleSigns()),
            'incompatible' => in_array($other->zodiac_sign, $this->getIncompatibleSigns()),
        ];

        // Moon sign compatibility (20 points)
        $moonScore = $this->calculateMoonSignCompatibility($other);
        $score += $moonScore;
        $analysis['moon_sign'] = [
            'score' => $moonScore,
            'compatible' => $this->areMoonSignsCompatible($other->moon_sign),
        ];

        // Manglik compatibility (20 points)
        $manglikScore = $this->calculateManglikCompatibility($other);
        $score += $manglikScore;
        $analysis['manglik'] = [
            'score' => $manglikScore,
            'compatible' => $this->areManglikCompatible($other),
        ];

        // Dosha compatibility (15 points)
        $doshaScore = $this->calculateDoshaCompatibility($other);
        $score += $doshaScore;
        $analysis['dosha'] = [
            'score' => $doshaScore,
            'compatible' => $this->areDoshasCompatible($other),
        ];

        // Ashtakoot compatibility (20 points)
        $ashtakootScore = $this->calculateAshtakootCompatibility($other);
        $score += $ashtakootScore;
        $analysis['ashtakoot'] = [
            'score' => $ashtakootScore,
            'total_possible' => 36,
        ];

        $grade = $this->getCompatibilityGradeFromScore($score);

        return [
            'score' => $score,
            'grade' => $grade,
            'analysis' => $analysis,
            'compatible' => $score >= 60,
            'recommendations' => $this->generateCompatibilityRecommendations($analysis),
        ];
    }

    private function calculateZodiacCompatibility(Horoscope $other): int
    {
        if (in_array($other->zodiac_sign, $this->getCompatibleSigns())) {
            return 25;
        } elseif (in_array($other->zodiac_sign, $this->getIncompatibleSigns())) {
            return 5;
        } else {
            return 15; // Neutral
        }
    }

    private function calculateMoonSignCompatibility(Horoscope $other): int
    {
        // Simplified moon sign compatibility
        $compatibleNakshatras = $this->getCompatibleNakshatras();
        
        if (in_array($other->birth_nakshatra, $compatibleNakshatras)) {
            return 20;
        } else {
            return 10;
        }
    }

    private function calculateManglikCompatibility(Horoscope $other): int
    {
        if ($this->isManglik() && $other->isManglik()) {
            return 20; // Both manglik - compatible
        } elseif (!$this->isManglik() && !$other->isManglik()) {
            return 20; // Both non-manglik - compatible
        } else {
            return 5; // One manglik, one not - less compatible
        }
    }

    private function calculateDoshaCompatibility(Horoscope $other): int
    {
        $thisDoshas = $this->getDoshas();
        $otherDoshas = $other->getDoshas();
        
        $commonDoshas = array_intersect($thisDoshas, $otherDoshas);
        
        if (empty($commonDoshas)) {
            return 15; // No common doshas - good
        } else {
            return 5; // Common doshas - less compatible
        }
    }

    private function calculateAshtakootCompatibility(Horoscope $other): int
    {
        // Simplified Ashtakoot calculation
        $score = 0;
        
        // Varna compatibility (1 point)
        if ($this->varna_compatibility) $score += 1;
        
        // Vashya compatibility (2 points)
        if ($this->vashya_compatibility) $score += 2;
        
        // Tara compatibility (3 points)
        if ($this->tara_compatibility) $score += 3;
        
        // Yoni compatibility (4 points)
        if ($this->yoni_compatibility) $score += 4;
        
        // Graha Maitri compatibility (5 points)
        if ($this->graha_maitri_compatibility) $score += 5;
        
        // Gana compatibility (6 points)
        if ($this->gana_compatibility) $score += 6;
        
        // Bhakoot compatibility (7 points)
        if ($this->bhakoot_compatibility) $score += 7;
        
        // Nadi compatibility (8 points)
        if ($this->nadi_compatibility) $score += 8;
        
        // Convert to 20-point scale
        return round(($score / 36) * 20);
    }

    private function getCompatibleNakshatras(): array
    {
        // Simplified nakshatra compatibility
        return [
            self::MOON_ASHWINI, self::MOON_BHARANI, self::MOON_KRITTIKA,
            self::MOON_ROHINI, self::MOON_MRIGASHIRA, self::MOON_ARDRA,
            self::MOON_PUNARVASU, self::MOON_PUSHYA, self::MOON_ASHLESHA,
        ];
    }

    private function areMoonSignsCompatible(string $otherMoonSign): bool
    {
        return in_array($otherMoonSign, $this->getCompatibleNakshatras());
    }

    private function areManglikCompatible(Horoscope $other): bool
    {
        return ($this->isManglik() && $other->isManglik()) || 
               (!$this->isManglik() && !$other->isManglik());
    }

    private function areDoshasCompatible(Horoscope $other): bool
    {
        $thisDoshas = $this->getDoshas();
        $otherDoshas = $other->getDoshas();
        
        return empty(array_intersect($thisDoshas, $otherDoshas));
    }

    private function getCompatibilityGradeFromScore(int $score): string
    {
        if ($score >= 80) return self::GRADE_EXCELLENT;
        if ($score >= 60) return self::GRADE_GOOD;
        if ($score >= 40) return self::GRADE_AVERAGE;
        if ($score >= 20) return self::GRADE_POOR;
        return self::GRADE_INCOMPATIBLE;
    }

    private function generateCompatibilityRecommendations(array $analysis): array
    {
        $recommendations = [];

        if ($analysis['zodiac']['score'] < 15) {
            $recommendations[] = 'Consider zodiac compatibility for better harmony';
        }

        if ($analysis['manglik']['score'] < 15) {
            $recommendations[] = 'Manglik compatibility should be addressed through remedies';
        }

        if ($analysis['dosha']['score'] < 10) {
            $recommendations[] = 'Dosha remedies may be beneficial for compatibility';
        }

        if ($analysis['ashtakoot']['score'] < 15) {
            $recommendations[] = 'Ashtakoot compatibility can be improved with specific remedies';
        }

        return $recommendations;
    }

    public function verify(User $astrologer): bool
    {
        return $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $astrologer->id,
        ]);
    }

    public function addAstrologerNote(string $note, User $astrologer): bool
    {
        $notes = $this->astrologer_notes ?? [];
        $notes[] = [
            'note' => $note,
            'astrologer_id' => $astrologer->id,
            'astrologer_name' => $astrologer->first_name . ' ' . $astrologer->last_name,
            'created_at' => now()->toISOString(),
        ];

        return $this->update(['astrologer_notes' => $notes]);
    }

    public function getDailyHoroscope(): array
    {
        // Simplified daily horoscope
        return [
            'general' => 'Today brings positive energy for relationships and personal growth.',
            'love' => 'Venus favors romantic connections and harmony in relationships.',
            'career' => 'Professional opportunities may arise. Stay focused on your goals.',
            'health' => 'Good health prospects. Maintain balance in daily routine.',
            'lucky_numbers' => [7, 14, 21, 28],
            'lucky_colors' => ['Blue', 'Gold', 'White'],
            'compatibility_today' => [
                'most_compatible' => ['Leo', 'Sagittarius'],
                'least_compatible' => ['Virgo'],
            ],
        ];
    }

    // Static methods
    public static function calculateZodiacSign(Carbon $birthDate): string
    {
        $month = $birthDate->month;
        $day = $birthDate->day;

        if (($month == 3 && $day >= 21) || ($month == 4 && $day <= 19)) {
            return self::ZODIAC_ARIES;
        } elseif (($month == 4 && $day >= 20) || ($month == 5 && $day <= 20)) {
            return self::ZODIAC_TAURUS;
        } elseif (($month == 5 && $day >= 21) || ($month == 6 && $day <= 20)) {
            return self::ZODIAC_GEMINI;
        } elseif (($month == 6 && $day >= 21) || ($month == 7 && $day <= 22)) {
            return self::ZODIAC_CANCER;
        } elseif (($month == 7 && $day >= 23) || ($month == 8 && $day <= 22)) {
            return self::ZODIAC_LEO;
        } elseif (($month == 8 && $day >= 23) || ($month == 9 && $day <= 22)) {
            return self::ZODIAC_VIRGO;
        } elseif (($month == 9 && $day >= 23) || ($month == 10 && $day <= 22)) {
            return self::ZODIAC_LIBRA;
        } elseif (($month == 10 && $day >= 23) || ($month == 11 && $day <= 21)) {
            return self::ZODIAC_SCORPIO;
        } elseif (($month == 11 && $day >= 22) || ($month == 12 && $day <= 21)) {
            return self::ZODIAC_SAGITTARIUS;
        } elseif (($month == 12 && $day >= 22) || ($month == 1 && $day <= 19)) {
            return self::ZODIAC_CAPRICORN;
        } elseif (($month == 1 && $day >= 20) || ($month == 2 && $day <= 18)) {
            return self::ZODIAC_AQUARIUS;
        } else {
            return self::ZODIAC_PISCES;
        }
    }
}
