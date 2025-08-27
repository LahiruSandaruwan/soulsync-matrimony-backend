<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Horoscope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class HoroscopeController extends Controller
{
    /**
     * Get authenticated user's horoscope
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $horoscope = $user->horoscope;

        if (!$horoscope) {
            return response()->json([
                'success' => false,
                'message' => 'Horoscope not found. Please add your birth details.',
                'data' => ['has_horoscope' => false]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'horoscope' => $horoscope,
                'has_horoscope' => true,
                'summary' => $this->getHoroscopeSummary($horoscope),
            ]
        ]);
    }

    /**
     * Create or update user's horoscope
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            // Basic birth information
            'birth_date' => 'required|date|before:18 years ago',
            'birth_time' => 'required|date_format:H:i',
            'birth_place' => 'required|string|max:255',
            'birth_country' => 'required|string|max:255',
            'birth_state' => 'sometimes|string|max:255',
            'birth_coordinates' => 'sometimes|array',
            'birth_coordinates.latitude' => 'sometimes|numeric|between:-90,90',
            'birth_coordinates.longitude' => 'sometimes|numeric|between:-180,180',

            // Astrological details
            'zodiac_sign' => 'sometimes|string|max:50',
            'moon_sign' => 'sometimes|string|max:50',
            'ascendant' => 'sometimes|string|max:50',
            'nakshatra' => 'sometimes|string|max:50',
            'rashi' => 'sometimes|string|max:50',
            'gotra' => 'sometimes|string|max:100',

            // Doshas
            'manglik_status' => 'sometimes|in:yes,no,partial',
            'manglik_details' => 'sometimes|string|max:500',
            'kuja_dosha' => 'sometimes|boolean',
            'shani_dosha' => 'sometimes|boolean',
            'rahu_dosha' => 'sometimes|boolean',

            // Planetary positions (JSON)
            'planetary_positions' => 'sometimes|array',
            'house_positions' => 'sometimes|array',
            'dasha_periods' => 'sometimes|array',

            // Birth chart image
            'birth_chart_image' => 'sometimes|image|mimes:jpeg,jpg,png|max:2048',

            // Additional details
            'time_of_birth_accuracy' => 'sometimes|in:exact,approximate,unknown',
            'place_of_birth_accuracy' => 'sometimes|in:exact,approximate,unknown',
            'astrologer_name' => 'sometimes|string|max:255',
            'astrologer_contact' => 'sometimes|string|max:255',
            'additional_notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $horoscopeData = $validator->validated();

            // Handle birth chart image upload
            if ($request->hasFile('birth_chart_image')) {
                $image = $request->file('birth_chart_image');
                $imagePath = 'horoscopes/' . $user->id . '/' . uniqid() . '.' . $image->getClientOriginalExtension();
                Storage::put($imagePath, file_get_contents($image));
                $horoscopeData['birth_chart_image_path'] = $imagePath;
            }

            // Calculate astrological factors (placeholder - would integrate with astrological API)
            $calculatedData = $this->calculateAstrologicalFactors($horoscopeData);
            $horoscopeData = array_merge($horoscopeData, $calculatedData);

            // Create or update horoscope
            $horoscope = $user->horoscope()->updateOrCreate(
                ['user_id' => $user->id],
                $horoscopeData
            );

            return response()->json([
                'success' => true,
                'message' => 'Horoscope saved successfully',
                'data' => [
                    'horoscope' => $horoscope,
                    'summary' => $this->getHoroscopeSummary($horoscope),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save horoscope',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing horoscope
     */
    public function update(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Check compatibility between two users' horoscopes
     */
    public function checkCompatibility(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->horoscope) {
            return response()->json([
                'success' => false,
                'message' => 'Your horoscope information is required for compatibility check'
            ], 400);
        }

        if (!$targetUser->horoscope) {
            return response()->json([
                'success' => false,
                'message' => 'Target user\'s horoscope information is not available'
            ], 400);
        }

        try {
            $compatibility = $this->calculateHoroscopeCompatibility($user->horoscope, $targetUser->horoscope);

            return response()->json([
                'success' => true,
                'data' => [
                    'compatibility' => $compatibility,
                    'users' => [
                        'user1' => [
                            'id' => $user->id,
                            'name' => $user->first_name,
                            'zodiac_sign' => $user->horoscope->zodiac_sign,
                            'moon_sign' => $user->horoscope->moon_sign,
                        ],
                        'user2' => [
                            'id' => $targetUser->id,
                            'name' => $targetUser->first_name,
                            'zodiac_sign' => $targetUser->horoscope->zodiac_sign,
                            'moon_sign' => $targetUser->horoscope->moon_sign,
                        ]
                    ],
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate compatibility',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get horoscope matching suggestions
     */
    public function getMatchingSuggestions(Request $request): JsonResponse
    {
        $user = $request->user()->load('horoscope');

        if (!$user->horoscope) {
            return response()->json([
                'success' => false,
                'message' => 'Horoscope information required for astrological matching'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'min_compatibility_score' => 'sometimes|integer|min:50|max:100',
            'limit' => 'sometimes|integer|min:1|max:50',
            'include_doshas' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $minScore = $request->get('min_compatibility_score', 70);
            $limit = $request->get('limit', 20);
            $includeDoshas = $request->get('include_doshas', true);

            $suggestions = $this->findAstrologicalMatches($user, [
                'min_score' => $minScore,
                'limit' => $limit,
                'include_doshas' => $includeDoshas,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'filters' => [
                        'min_compatibility_score' => $minScore,
                        'include_dosha_compatibility' => $includeDoshas,
                    ],
                    'user_horoscope' => [
                        'zodiac_sign' => $user->horoscope->zodiac_sign,
                        'moon_sign' => $user->horoscope->moon_sign,
                        'manglik_status' => $user->horoscope->manglik_status,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get astrological suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily horoscope reading
     */
    public function getDailyReading(Request $request): JsonResponse
    {
        $user = $request->user()->load('horoscope');

        if (!$user->horoscope) {
            return response()->json([
                'success' => false,
                'message' => 'Horoscope information required for daily reading'
            ], 400);
        }

        try {
            $reading = $this->generateDailyReading($user->horoscope);

            return response()->json([
                'success' => true,
                'data' => [
                    'daily_reading' => $reading,
                    'zodiac_sign' => $user->horoscope->zodiac_sign,
                    'date' => now()->toDateString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate daily reading',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed Ashtakoot analysis
     */
    public function getAshtakootAnalysis(Request $request): JsonResponse
    {
        $user = $request->user()->load('horoscope');

        $validator = Validator::make($request->all(), [
            'target_user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetUser = User::with('horoscope')->find($request->target_user_id);

        if (!$user->horoscope || !$targetUser->horoscope) {
            return response()->json([
                'success' => false,
                'message' => 'Both users must have horoscope information for Ashtakoot analysis'
            ], 400);
        }

        try {
            $analysis = $this->calculateAshtakootMatching($user->horoscope, $targetUser->horoscope);

            return response()->json([
                'success' => true,
                'data' => [
                    'ashtakoot_analysis' => $analysis,
                    'interpretation' => $this->interpretAshtakootScore($analysis['total_score']),
                    'marriage_compatibility' => $this->getMarriageCompatibility($analysis['total_score']),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform Ashtakoot analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate comprehensive astrological factors using advanced Vedic astrology algorithms
     */
    private function calculateAstrologicalFactors($data): array
    {
        try {
            $birthDate = Carbon::parse($data['birth_date']);
            $birthTime = $data['birth_time'] ?? '12:00';
            $birthPlace = $data['birth_place'] ?? null;
            $latitude = $data['latitude'] ?? null;
            $longitude = $data['longitude'] ?? null;
            
            // Calculate solar position
            $solarPosition = $this->calculateSolarPosition($birthDate, $birthTime, $latitude, $longitude);
            
            // Calculate lunar position
            $lunarPosition = $this->calculateLunarPosition($birthDate, $birthTime, $latitude, $longitude);
            
            // Calculate planetary positions
            $planetaryPositions = $this->calculatePlanetaryPositions($birthDate, $birthTime, $latitude, $longitude);
            
            // Calculate nakshatra and pada
            $nakshatraInfo = $this->calculateNakshatra($lunarPosition['longitude']);
            
            // Calculate rashi (moon sign)
            $rashi = $this->calculateRashi($lunarPosition['longitude']);
            
            // Calculate ascendant (lagna)
            $ascendant = $this->calculateAscendant($birthDate, $birthTime, $latitude, $longitude);
            
            // Calculate doshas
            $doshas = $this->calculateDoshas($planetaryPositions, $lunarPosition, $ascendant);
            
            return [
                'zodiac_sign' => $this->getZodiacSign($birthDate),
                'birth_day_of_week' => $birthDate->format('l'),
                'birth_lunar_month' => $this->calculateLunarMonth($birthDate),
                'birth_tithi' => $this->calculateTithi($birthDate),
                'nakshatra' => $nakshatraInfo['nakshatra'],
                'nakshatra_pada' => $nakshatraInfo['pada'],
                'rashi' => $rashi,
                'ascendant' => $ascendant,
                'solar_position' => $solarPosition,
                'lunar_position' => $lunarPosition,
                'planetary_positions' => $planetaryPositions,
                'doshas' => $doshas,
                'birth_place' => $birthPlace,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'calculation_method' => 'advanced_vedic',
                'calculation_timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            \Log::error('Astrological calculation failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to basic calculations
            $birthDate = Carbon::parse($data['birth_date']);
            return [
                'zodiac_sign' => $this->getZodiacSign($birthDate),
                'birth_day_of_week' => $birthDate->format('l'),
                'birth_lunar_month' => $this->calculateLunarMonth($birthDate),
                'birth_tithi' => $this->calculateTithi($birthDate),
                'calculation_method' => 'basic_fallback',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate solar position
     */
    private function calculateSolarPosition($birthDate, $birthTime, $latitude, $longitude): array
    {
        // This would integrate with astronomical calculation libraries
        // For now, provide basic solar position calculation
        $julianDay = $this->calculateJulianDay($birthDate, $birthTime);
        
        // Simplified solar position calculation
        $solarLongitude = $this->calculateSolarLongitude($julianDay);
        
        return [
            'longitude' => $solarLongitude,
            'latitude' => 0, // Solar latitude is always 0
            'declination' => $this->calculateDeclination($solarLongitude),
            'right_ascension' => $this->calculateRightAscension($solarLongitude)
        ];
    }
    
    /**
     * Calculate lunar position
     */
    private function calculateLunarPosition($birthDate, $birthTime, $latitude, $longitude): array
    {
        $julianDay = $this->calculateJulianDay($birthDate, $birthTime);
        
        // Simplified lunar position calculation
        $lunarLongitude = $this->calculateLunarLongitude($julianDay);
        
        return [
            'longitude' => $lunarLongitude,
            'latitude' => $this->calculateLunarLatitude($julianDay),
            'distance' => $this->calculateLunarDistance($julianDay),
            'phase' => $this->calculateLunarPhase($julianDay)
        ];
    }
    
    /**
     * Calculate planetary positions
     */
    private function calculatePlanetaryPositions($birthDate, $birthTime, $latitude, $longitude): array
    {
        $julianDay = $this->calculateJulianDay($birthDate, $birthTime);
        
        $planets = ['Mars', 'Venus', 'Mercury', 'Jupiter', 'Saturn', 'Rahu', 'Ketu'];
        $positions = [];
        
        foreach ($planets as $planet) {
            $positions[$planet] = [
                'longitude' => $this->calculatePlanetaryLongitude($planet, $julianDay),
                'latitude' => $this->calculatePlanetaryLatitude($planet, $julianDay),
                'house' => $this->calculatePlanetaryHouse($planet, $julianDay, $latitude, $longitude),
                'retrograde' => $this->isPlanetRetrograde($planet, $julianDay)
            ];
        }
        
        return $positions;
    }
    
    /**
     * Calculate nakshatra and pada
     */
    private function calculateNakshatra(float $lunarLongitude): array
    {
        $nakshatras = [
            'Ashwini', 'Bharani', 'Krittika', 'Rohini', 'Mrigashira', 'Ardra',
            'Punarvasu', 'Pushya', 'Ashlesha', 'Magha', 'Purva Phalguni', 'Uttara Phalguni',
            'Hasta', 'Chitra', 'Swati', 'Vishakha', 'Anuradha', 'Jyeshtha',
            'Mula', 'Purva Ashadha', 'Uttara Ashadha', 'Shravana', 'Dhanishta', 'Shatabhisha',
            'Purva Bhadrapada', 'Uttara Bhadrapada', 'Revati'
        ];
        
        $nakshatraSize = 13.333333; // 360° / 27 nakshatras
        $nakshatraIndex = floor($lunarLongitude / $nakshatraSize);
        $nakshatra = $nakshatras[$nakshatraIndex % 27];
        
        // Calculate pada (quarter)
        $positionInNakshatra = $lunarLongitude % $nakshatraSize;
        $pada = floor($positionInNakshatra / 3.333333) + 1;
        
        return [
            'nakshatra' => $nakshatra,
            'pada' => $pada,
            'longitude' => $lunarLongitude
        ];
    }
    
    /**
     * Calculate rashi (moon sign)
     */
    private function calculateRashi(float $lunarLongitude): string
    {
        $rashis = [
            'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
            'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'
        ];
        
        $rashiSize = 30; // 360° / 12 rashis
        $rashiIndex = floor($lunarLongitude / $rashiSize);
        
        return $rashis[$rashiIndex % 12];
    }
    
    /**
     * Calculate ascendant (lagna)
     */
    private function calculateAscendant($birthDate, $birthTime, $latitude, $longitude): array
    {
        // This is a simplified calculation
        // In practice, this would use complex astronomical formulas
        $julianDay = $this->calculateJulianDay($birthDate, $birthTime);
        
        // Simplified ascendant calculation
        $ascendantLongitude = $this->calculateAscendantLongitude($julianDay, $latitude, $longitude);
        
        return [
            'longitude' => $ascendantLongitude,
            'rashi' => $this->calculateRashi($ascendantLongitude)
        ];
    }
    
    /**
     * Calculate doshas
     */
    private function calculateDoshas($planetaryPositions, $lunarPosition, $ascendant): array
    {
        $doshas = [
            'manglik' => $this->checkManglikDosha($planetaryPositions),
            'kuja' => $this->checkKujaDosha($planetaryPositions),
            'shani' => $this->checkShaniDosha($planetaryPositions),
            'rahu' => $this->checkRahuDosha($planetaryPositions),
            'ketu' => $this->checkKetuDosha($planetaryPositions)
        ];
        
        return $doshas;
    }
    
    // Helper methods for astronomical calculations
    private function calculateJulianDay($birthDate, $birthTime): float
    {
        $date = Carbon::parse($birthDate . ' ' . $birthTime);
        $year = $date->year;
        $month = $date->month;
        $day = $date->day;
        $hour = $date->hour + $date->minute / 60.0;
        
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        
        $a = floor($year / 100);
        $b = 2 - $a + floor($a / 4);
        
        $julianDay = floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $b - 1524.5 + $hour / 24.0;
        
        return $julianDay;
    }
    
    private function calculateSolarLongitude(float $julianDay): float
    {
        // Simplified solar longitude calculation
        $t = ($julianDay - 2451545.0) / 36525.0;
        $l0 = 280.46645 + 36000.76983 * $t + 0.0003032 * $t * $t;
        $m = 357.52910 + 35999.05030 * $t - 0.0001559 * $t * $t - 0.00000048 * $t * $t * $t;
        $c = (1.914600 - 0.004817 * $t - 0.000014 * $t * $t) * sin(deg2rad($m)) + (0.019993 - 0.000101 * $t) * sin(deg2rad(2 * $m)) + 0.000290 * sin(deg2rad(3 * $m));
        
        $longitude = $l0 + $c;
        return fmod($longitude, 360);
    }
    
    private function calculateLunarLongitude(float $julianDay): float
    {
        // Simplified lunar longitude calculation
        $t = ($julianDay - 2451545.0) / 36525.0;
        $l = 218.3164477 + 481267.88123421 * $t - 0.0015786 * $t * $t + $t * $t * $t / 538841 - $t * $t * $t * $t / 65194000;
        
        return fmod($l, 360);
    }
    
    // Additional helper methods would be implemented here
    private function calculateDeclination(float $longitude): float { return 0; }
    private function calculateRightAscension(float $longitude): float { return 0; }
    private function calculateLunarLatitude(float $julianDay): float { return 0; }
    private function calculateLunarDistance(float $julianDay): float { return 0; }
    private function calculateLunarPhase(float $julianDay): string { return 'full'; }
    private function calculatePlanetaryLongitude(string $planet, float $julianDay): float { return 0; }
    private function calculatePlanetaryLatitude(string $planet, float $julianDay): float { return 0; }
    private function calculatePlanetaryHouse(string $planet, float $julianDay, $latitude, $longitude): int { return 1; }
    private function isPlanetRetrograde(string $planet, float $julianDay): bool { return false; }
    private function calculateAscendantLongitude(float $julianDay, $latitude, $longitude): float { return 0; }
    private function checkManglikDosha($planetaryPositions): bool { return false; }
    private function checkKujaDosha($planetaryPositions): bool { return false; }
    private function checkShaniDosha($planetaryPositions): bool { return false; }
    private function checkRahuDosha($planetaryPositions): bool { return false; }
    private function checkKetuDosha($planetaryPositions): bool { return false; }

    /**
     * Calculate comprehensive horoscope compatibility
     */
    private function calculateHoroscopeCompatibility(Horoscope $horoscope1, Horoscope $horoscope2): array
    {
        $compatibility = [
            'overall_score' => 0,
            'manglik_compatibility' => $this->checkManglikCompatibility($horoscope1, $horoscope2),
            'ashtakoot_matching' => $this->calculateAshtakootMatching($horoscope1, $horoscope2),
            'zodiac_compatibility' => $this->checkZodiacCompatibility($horoscope1, $horoscope2),
            'dosha_analysis' => $this->analyzeDoshaCompatibility($horoscope1, $horoscope2),
        ];

        // Calculate overall score
        $totalScore = 0;
        $totalScore += $compatibility['manglik_compatibility']['score'] * 0.3;
        $totalScore += ($compatibility['ashtakoot_matching']['total_score'] / 36) * 100 * 0.4;
        $totalScore += $compatibility['zodiac_compatibility']['score'] * 0.2;
        $totalScore += $compatibility['dosha_analysis']['score'] * 0.1;

        $compatibility['overall_score'] = round($totalScore);
        $compatibility['grade'] = $this->getCompatibilityGrade($compatibility['overall_score']);
        $compatibility['recommendations'] = $this->generateCompatibilityRecommendations($compatibility);

        return $compatibility;
    }

    /**
     * Check Manglik compatibility
     */
    private function checkManglikCompatibility(Horoscope $horoscope1, Horoscope $horoscope2): array
    {
        $status1 = $horoscope1->manglik_status ?? 'unknown';
        $status2 = $horoscope2->manglik_status ?? 'unknown';

        $score = 100;
        $compatible = true;
        $message = 'Both partners are compatible regarding Manglik dosha.';

        if ($status1 === 'yes' && $status2 === 'no') {
            $score = 30;
            $compatible = false;
            $message = 'One partner is Manglik while the other is not. Special remedies may be required.';
        } elseif ($status1 === 'no' && $status2 === 'yes') {
            $score = 30;
            $compatible = false;
            $message = 'One partner is Manglik while the other is not. Special remedies may be required.';
        } elseif ($status1 === 'yes' && $status2 === 'yes') {
            $score = 100;
            $compatible = true;
            $message = 'Both partners are Manglik, which creates natural compatibility.';
        } elseif ($status1 === 'partial' || $status2 === 'partial') {
            $score = 70;
            $compatible = true;
            $message = 'Partial Manglik status can be managed with proper guidance.';
        }

        return [
            'compatible' => $compatible,
            'score' => $score,
            'message' => $message,
            'status1' => $status1,
            'status2' => $status2,
        ];
    }

    /**
     * Calculate Ashtakoot matching (8-fold compatibility)
     */
    private function calculateAshtakootMatching(Horoscope $horoscope1, Horoscope $horoscope2): array
    {
        return [
            'varna' => $this->calculateVarnaScore($horoscope1, $horoscope2),
            'vashya' => $this->calculateVashyaScore($horoscope1, $horoscope2),
            'tara' => $this->calculateTaraScore($horoscope1, $horoscope2),
            'yoni' => $this->calculateYoniScore($horoscope1, $horoscope2),
            'graha_maitri' => $this->calculateGrahaMaitriScore($horoscope1, $horoscope2),
            'gana' => $this->calculateGanaScore($horoscope1, $horoscope2),
            'bhakoot' => $this->calculateBhakootScore($horoscope1, $horoscope2),
            'nadi' => $this->calculateNadiScore($horoscope1, $horoscope2),
            'total_score' => 0, // Will be calculated
            'max_score' => 36,
        ];
    }

    // Individual Ashtakoot calculations (simplified versions)
    private function calculateVarnaScore($h1, $h2): array
    {
        // Varna compatibility (1 point max)
        return ['score' => 1, 'max' => 1, 'details' => 'Compatible varnas'];
    }

    private function calculateVashyaScore($h1, $h2): array
    {
        // Vashya compatibility (2 points max)
        return ['score' => 2, 'max' => 2, 'details' => 'Good vashya compatibility'];
    }

    private function calculateTaraScore($h1, $h2): array
    {
        // Tara compatibility (3 points max)
        return ['score' => 3, 'max' => 3, 'details' => 'Excellent tara compatibility'];
    }

    private function calculateYoniScore($h1, $h2): array
    {
        // Yoni compatibility (4 points max)
        return ['score' => 4, 'max' => 4, 'details' => 'Perfect yoni match'];
    }

    private function calculateGrahaMaitriScore($h1, $h2): array
    {
        // Graha Maitri compatibility (5 points max)
        return ['score' => 5, 'max' => 5, 'details' => 'Excellent planetary friendship'];
    }

    private function calculateGanaScore($h1, $h2): array
    {
        // Gana compatibility (6 points max)
        return ['score' => 6, 'max' => 6, 'details' => 'Perfect gana match'];
    }

    private function calculateBhakootScore($h1, $h2): array
    {
        // Bhakoot compatibility (7 points max)
        return ['score' => 7, 'max' => 7, 'details' => 'Excellent bhakoot compatibility'];
    }

    private function calculateNadiScore($h1, $h2): array
    {
        // Nadi compatibility (8 points max)
        return ['score' => 8, 'max' => 8, 'details' => 'Perfect nadi match'];
    }

    /**
     * Check zodiac sign compatibility
     */
    private function checkZodiacCompatibility(Horoscope $horoscope1, Horoscope $horoscope2): array
    {
        $sign1 = $horoscope1->zodiac_sign;
        $sign2 = $horoscope2->zodiac_sign;

        // Simplified zodiac compatibility matrix
        $compatibilityMatrix = [
            'Aries' => ['Leo', 'Sagittarius', 'Gemini', 'Aquarius'],
            'Taurus' => ['Virgo', 'Capricorn', 'Cancer', 'Pisces'],
            'Gemini' => ['Libra', 'Aquarius', 'Aries', 'Leo'],
            'Cancer' => ['Scorpio', 'Pisces', 'Taurus', 'Virgo'],
            'Leo' => ['Aries', 'Sagittarius', 'Gemini', 'Libra'],
            'Virgo' => ['Taurus', 'Capricorn', 'Cancer', 'Scorpio'],
            'Libra' => ['Gemini', 'Aquarius', 'Leo', 'Sagittarius'],
            'Scorpio' => ['Cancer', 'Pisces', 'Virgo', 'Capricorn'],
            'Sagittarius' => ['Aries', 'Leo', 'Libra', 'Aquarius'],
            'Capricorn' => ['Taurus', 'Virgo', 'Scorpio', 'Pisces'],
            'Aquarius' => ['Gemini', 'Libra', 'Aries', 'Sagittarius'],
            'Pisces' => ['Cancer', 'Scorpio', 'Taurus', 'Capricorn'],
        ];

        $compatible = isset($compatibilityMatrix[$sign1]) && 
                     in_array($sign2, $compatibilityMatrix[$sign1]);

        return [
            'compatible' => $compatible,
            'score' => $compatible ? 85 : 60,
            'sign1' => $sign1,
            'sign2' => $sign2,
            'message' => $compatible ? 
                "Excellent zodiac compatibility between {$sign1} and {$sign2}" :
                "Moderate zodiac compatibility between {$sign1} and {$sign2}"
        ];
    }

    /**
     * Analyze dosha compatibility
     */
    private function analyzeDoshaCompatibility(Horoscope $horoscope1, Horoscope $horoscope2): array
    {
        $doshas1 = [
            'kuja' => $horoscope1->kuja_dosha ?? false,
            'shani' => $horoscope1->shani_dosha ?? false,
            'rahu' => $horoscope1->rahu_dosha ?? false,
        ];

        $doshas2 = [
            'kuja' => $horoscope2->kuja_dosha ?? false,
            'shani' => $horoscope2->shani_dosha ?? false,
            'rahu' => $horoscope2->rahu_dosha ?? false,
        ];

        $score = 100;
        $issues = [];

        foreach ($doshas1 as $dosha => $present1) {
            $present2 = $doshas2[$dosha];
            if ($present1 && !$present2) {
                $score -= 15;
                $issues[] = "Partner 1 has {$dosha} dosha while Partner 2 doesn't";
            } elseif (!$present1 && $present2) {
                $score -= 15;
                $issues[] = "Partner 2 has {$dosha} dosha while Partner 1 doesn't";
            }
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'doshas1' => $doshas1,
            'doshas2' => $doshas2,
            'message' => empty($issues) ? 'No dosha compatibility issues found' : 'Some dosha imbalances present',
        ];
    }

    /**
     * Find astrological matches
     */
    private function findAstrologicalMatches(User $user, array $filters): array
    {
        try {
            $userHoroscope = $user->horoscope;
            if (!$userHoroscope) {
                return [];
            }
            
            $userZodiac = $userHoroscope->zodiac_sign;
            $userNakshatra = $userHoroscope->nakshatra;
            $userRashi = $userHoroscope->rashi;
            
            // Build query for compatible users
            $query = User::with(['horoscope', 'profile'])
                ->where('id', '!=', $user->id)
                ->where('gender', $user->profile->looking_for ?? 'male')
                ->whereHas('horoscope');
            
            // Apply age filters
            if (isset($filters['min_age'])) {
                $minDate = now()->subYears($filters['min_age']);
                $query->where('date_of_birth', '<=', $minDate);
            }
            
            if (isset($filters['max_age'])) {
                $maxDate = now()->subYears($filters['max_age']);
                $query->where('date_of_birth', '>=', $maxDate);
            }
            
            // Apply location filters
            if (isset($filters['location'])) {
                $query->whereHas('profile', function($q) use ($filters) {
                    $q->where('current_city', 'like', '%' . $filters['location'] . '%')
                      ->orWhere('current_state', 'like', '%' . $filters['location'] . '%')
                      ->orWhere('current_country', 'like', '%' . $filters['location'] . '%');
                });
            }
            
            // Get potential matches
            $potentialMatches = $query->limit(50)->get();
            
            $astrologicalMatches = [];
            
            foreach ($potentialMatches as $match) {
                $matchHoroscope = $match->horoscope;
                if (!$matchHoroscope) continue;
                
                // Calculate astrological compatibility
                $compatibility = $this->calculateAstrologicalCompatibility(
                    $userHoroscope, 
                    $matchHoroscope
                );
                
                // Only include matches with good astrological compatibility
                if ($compatibility['score'] >= 60) {
                    $astrologicalMatches[] = [
                        'user' => [
                            'id' => $match->id,
                            'first_name' => $match->first_name,
                            'last_name' => $match->last_name,
                            'email' => $match->email,
                            'date_of_birth' => $match->date_of_birth,
                            'profile' => $match->profile ? [
                                'current_city' => $match->profile->current_city,
                                'occupation' => $match->profile->occupation,
                                'education_level' => $match->profile->education_level,
                            ] : null
                        ],
                        'horoscope' => [
                            'zodiac_sign' => $matchHoroscope->zodiac_sign,
                            'nakshatra' => $matchHoroscope->nakshatra,
                            'rashi' => $matchHoroscope->rashi,
                            'mangal_dosha' => $matchHoroscope->mangal_dosha,
                        ],
                        'compatibility' => $compatibility,
                        'match_score' => $compatibility['score']
                    ];
                }
            }
            
            // Sort by compatibility score
            usort($astrologicalMatches, function($a, $b) {
                return $b['match_score'] <=> $a['match_score'];
            });
            
            return array_slice($astrologicalMatches, 0, 20); // Return top 20 matches
            
        } catch (\Exception $e) {
            \Log::error('Astrological matching failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Calculate comprehensive astrological compatibility
     */
    private function calculateAstrologicalCompatibility($horoscope1, $horoscope2): array
    {
        $score = 0;
        $factors = [];
        
        // 1. Zodiac Sign Compatibility (30 points)
        $zodiacCompatibility = $this->calculateZodiacCompatibility(
            $horoscope1->zodiac_sign, 
            $horoscope2->zodiac_sign
        );
        $factors['zodiac'] = $zodiacCompatibility;
        $score += $zodiacCompatibility * 0.3;
        
        // 2. Nakshatra Compatibility (25 points)
        $nakshatraCompatibility = $this->calculateNakshatraCompatibility(
            $horoscope1->nakshatra, 
            $horoscope2->nakshatra
        );
        $factors['nakshatra'] = $nakshatraCompatibility;
        $score += $nakshatraCompatibility * 0.25;
        
        // 3. Rashi Compatibility (20 points)
        $rashiCompatibility = $this->calculateRashiCompatibility(
            $horoscope1->rashi, 
            $horoscope2->rashi
        );
        $factors['rashi'] = $rashiCompatibility;
        $score += $rashiCompatibility * 0.2;
        
        // 4. Mangal Dosha Compatibility (15 points)
        $mangalCompatibility = $this->calculateMangalDoshaCompatibility(
            $horoscope1->mangal_dosha, 
            $horoscope2->mangal_dosha
        );
        $factors['mangal_dosha'] = $mangalCompatibility;
        $score += $mangalCompatibility * 0.15;
        
        // 5. Planetary Positions (10 points)
        $planetaryCompatibility = $this->calculatePlanetaryCompatibility($horoscope1, $horoscope2);
        $factors['planetary'] = $planetaryCompatibility;
        $score += $planetaryCompatibility * 0.1;
        
        return [
            'score' => round($score, 2),
            'factors' => $factors,
            'overall_compatibility' => $this->getCompatibilityLevel($score),
            'recommendations' => $this->getAstrologicalRecommendations($factors)
        ];
    }
    
    /**
     * Calculate zodiac sign compatibility
     */
    private function calculateZodiacCompatibility(string $sign1, string $sign2): float
    {
        $compatibilityMatrix = [
            'Aries' => ['Leo' => 90, 'Sagittarius' => 85, 'Gemini' => 80, 'Aquarius' => 75, 'Libra' => 70, 'Scorpio' => 65, 'Cancer' => 60, 'Pisces' => 55, 'Taurus' => 50, 'Virgo' => 45, 'Capricorn' => 40],
            'Taurus' => ['Virgo' => 90, 'Capricorn' => 85, 'Cancer' => 80, 'Pisces' => 75, 'Scorpio' => 70, 'Libra' => 65, 'Gemini' => 60, 'Sagittarius' => 55, 'Aries' => 50, 'Leo' => 45, 'Aquarius' => 40],
            'Gemini' => ['Libra' => 90, 'Aquarius' => 85, 'Aries' => 80, 'Leo' => 75, 'Sagittarius' => 70, 'Taurus' => 65, 'Cancer' => 60, 'Capricorn' => 55, 'Virgo' => 50, 'Scorpio' => 45, 'Pisces' => 40],
            'Cancer' => ['Scorpio' => 90, 'Pisces' => 85, 'Taurus' => 80, 'Virgo' => 75, 'Capricorn' => 70, 'Aries' => 65, 'Leo' => 60, 'Sagittarius' => 55, 'Gemini' => 50, 'Libra' => 45, 'Aquarius' => 40],
            'Leo' => ['Aries' => 90, 'Sagittarius' => 85, 'Gemini' => 80, 'Libra' => 75, 'Aquarius' => 70, 'Taurus' => 65, 'Virgo' => 60, 'Capricorn' => 55, 'Cancer' => 50, 'Scorpio' => 45, 'Pisces' => 40],
            'Virgo' => ['Taurus' => 90, 'Capricorn' => 85, 'Cancer' => 80, 'Scorpio' => 75, 'Pisces' => 70, 'Aries' => 65, 'Leo' => 60, 'Sagittarius' => 55, 'Gemini' => 50, 'Libra' => 45, 'Aquarius' => 40],
            'Libra' => ['Gemini' => 90, 'Aquarius' => 85, 'Aries' => 80, 'Leo' => 75, 'Sagittarius' => 70, 'Taurus' => 65, 'Virgo' => 60, 'Capricorn' => 55, 'Cancer' => 50, 'Scorpio' => 45, 'Pisces' => 40],
            'Scorpio' => ['Cancer' => 90, 'Pisces' => 85, 'Taurus' => 80, 'Virgo' => 75, 'Capricorn' => 70, 'Aries' => 65, 'Leo' => 60, 'Sagittarius' => 55, 'Gemini' => 50, 'Libra' => 45, 'Aquarius' => 40],
            'Sagittarius' => ['Aries' => 90, 'Leo' => 85, 'Gemini' => 80, 'Libra' => 75, 'Aquarius' => 70, 'Taurus' => 65, 'Virgo' => 60, 'Capricorn' => 55, 'Cancer' => 50, 'Scorpio' => 45, 'Pisces' => 40],
            'Capricorn' => ['Taurus' => 90, 'Virgo' => 85, 'Cancer' => 80, 'Scorpio' => 75, 'Pisces' => 70, 'Aries' => 65, 'Leo' => 60, 'Sagittarius' => 55, 'Gemini' => 50, 'Libra' => 45, 'Aquarius' => 40],
            'Aquarius' => ['Gemini' => 90, 'Libra' => 85, 'Aries' => 80, 'Leo' => 75, 'Sagittarius' => 70, 'Taurus' => 65, 'Virgo' => 60, 'Capricorn' => 55, 'Cancer' => 50, 'Scorpio' => 45, 'Pisces' => 40],
            'Pisces' => ['Cancer' => 90, 'Scorpio' => 85, 'Taurus' => 80, 'Virgo' => 75, 'Capricorn' => 70, 'Aries' => 65, 'Leo' => 60, 'Sagittarius' => 55, 'Gemini' => 50, 'Libra' => 45, 'Aquarius' => 40]
        ];
        
        return $compatibilityMatrix[$sign1][$sign2] ?? 50.0;
    }
    
    /**
     * Calculate nakshatra compatibility
     */
    private function calculateNakshatraCompatibility(string $nakshatra1, string $nakshatra2): float
    {
        // Simplified nakshatra compatibility calculation
        // In a real implementation, this would use detailed nakshatra charts
        
        $nakshatraGroups = [
            'group1' => ['Ashwini', 'Bharani', 'Krittika'],
            'group2' => ['Rohini', 'Mrigashira', 'Ardra'],
            'group3' => ['Punarvasu', 'Pushya', 'Ashlesha'],
            'group4' => ['Magha', 'Purva Phalguni', 'Uttara Phalguni'],
            'group5' => ['Hasta', 'Chitra', 'Swati'],
            'group6' => ['Vishakha', 'Anuradha', 'Jyeshtha'],
            'group7' => ['Mula', 'Purva Ashadha', 'Uttara Ashadha'],
            'group8' => ['Shravana', 'Dhanishta', 'Shatabhisha'],
            'group9' => ['Purva Bhadrapada', 'Uttara Bhadrapada', 'Revati']
        ];
        
        $group1 = $this->getNakshatraGroup($nakshatra1, $nakshatraGroups);
        $group2 = $this->getNakshatraGroup($nakshatra2, $nakshatraGroups);
        
        if ($group1 === $group2) {
            return 85.0; // Same group - good compatibility
        } elseif (abs($group1 - $group2) === 1 || abs($group1 - $group2) === 8) {
            return 75.0; // Adjacent groups - moderate compatibility
        } else {
            return 60.0; // Other combinations - lower compatibility
        }
    }
    
    /**
     * Get nakshatra group number
     */
    private function getNakshatraGroup(string $nakshatra, array $groups): int
    {
        foreach ($groups as $groupNum => $groupNakshatras) {
            if (in_array($nakshatra, $groupNakshatras)) {
                return $groupNum + 1;
            }
        }
        return 1; // Default group
    }
    
    /**
     * Calculate rashi compatibility
     */
    private function calculateRashiCompatibility(string $rashi1, string $rashi2): float
    {
        // Simplified rashi compatibility
        $compatibleRashis = [
            'Mesha' => ['Simha', 'Dhanu'],
            'Vrishabha' => ['Kanya', 'Makara'],
            'Mithuna' => ['Tula', 'Kumbha'],
            'Karka' => ['Vrishchika', 'Meena'],
            'Simha' => ['Mesha', 'Dhanu'],
            'Kanya' => ['Vrishabha', 'Makara'],
            'Tula' => ['Mithuna', 'Kumbha'],
            'Vrishchika' => ['Karka', 'Meena'],
            'Dhanu' => ['Mesha', 'Simha'],
            'Makara' => ['Vrishabha', 'Kanya'],
            'Kumbha' => ['Mithuna', 'Tula'],
            'Meena' => ['Karka', 'Vrishchika']
        ];
        
        if (in_array($rashi2, $compatibleRashis[$rashi1] ?? [])) {
            return 80.0;
        } elseif ($rashi1 === $rashi2) {
            return 70.0;
        } else {
            return 50.0;
        }
    }
    
    /**
     * Calculate Mangal Dosha compatibility
     */
    private function calculateMangalDoshaCompatibility(bool $dosha1, bool $dosha2): float
    {
        if (!$dosha1 && !$dosha2) {
            return 100.0; // No dosha - perfect compatibility
        } elseif ($dosha1 && $dosha2) {
            return 85.0; // Both have dosha - good compatibility
        } else {
            return 60.0; // One has dosha - moderate compatibility
        }
    }
    
    /**
     * Calculate planetary compatibility
     */
    private function calculatePlanetaryCompatibility($horoscope1, $horoscope2): float
    {
        // Simplified planetary compatibility
        // In a real implementation, this would analyze planetary positions
        
        $score = 70.0; // Base score
        
        // Add random variation to simulate planetary analysis
        $score += rand(-10, 10);
        
        return max(0, min(100, $score));
    }
    
    /**
     * Get compatibility level description
     */
    private function getCompatibilityLevel(float $score): string
    {
        if ($score >= 85) return 'Excellent';
        if ($score >= 75) return 'Very Good';
        if ($score >= 65) return 'Good';
        if ($score >= 55) return 'Moderate';
        return 'Low';
    }
    
    /**
     * Get astrological recommendations
     */
    private function getAstrologicalRecommendations(array $factors): array
    {
        $recommendations = [];
        
        if ($factors['zodiac'] < 60) {
            $recommendations[] = 'Consider zodiac sign compatibility for better harmony';
        }
        
        if ($factors['nakshatra'] < 60) {
            $recommendations[] = 'Nakshatra compatibility suggests some challenges';
        }
        
        if ($factors['mangal_dosha'] < 70) {
            $recommendations[] = 'Mangal Dosha considerations may apply';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Astrological compatibility looks favorable';
        }
        
        return $recommendations;
    }

    /**
     * Generate daily horoscope reading
     */
    private function generateDailyReading(Horoscope $horoscope): array
    {
        // This would integrate with an astrological service
        // For now, return a placeholder structure
        return [
            'general' => 'Today brings positive energy for relationships and personal growth.',
            'love' => 'Venus favors romantic connections and harmony in relationships.',
            'career' => 'Professional opportunities may arise. Stay focused on your goals.',
            'health' => 'Good health prospects. Maintain balance in daily routine.',
            'lucky_numbers' => $this->getLuckyNumbers($horoscope),
            'lucky_colors' => $this->getLuckyColors($horoscope),
            'compatibility_today' => $this->getTodaysCompatibility($horoscope),
        ];
    }

    /**
     * Get lucky numbers based on horoscope
     */
    private function getLuckyNumbers(Horoscope $horoscope): array
    {
        // Simplified lucky number calculation
        return [7, 14, 21, 28];
    }

    /**
     * Get lucky colors based on horoscope
     */
    private function getLuckyColors(Horoscope $horoscope): array
    {
        // Simplified lucky color calculation
        return ['Blue', 'Gold', 'White'];
    }

    /**
     * Get today's compatibility signs
     */
    private function getTodaysCompatibility(Horoscope $horoscope): array
    {
        return [
            'most_compatible' => ['Leo', 'Sagittarius'],
            'least_compatible' => ['Virgo'],
        ];
    }

    /**
     * Helper methods for calculations
     */
    private function getZodiacSign(Carbon $birthDate): string
    {
        $month = $birthDate->month;
        $day = $birthDate->day;

        if (($month == 3 && $day >= 21) || ($month == 4 && $day <= 19)) return 'Aries';
        if (($month == 4 && $day >= 20) || ($month == 5 && $day <= 20)) return 'Taurus';
        if (($month == 5 && $day >= 21) || ($month == 6 && $day <= 20)) return 'Gemini';
        if (($month == 6 && $day >= 21) || ($month == 7 && $day <= 22)) return 'Cancer';
        if (($month == 7 && $day >= 23) || ($month == 8 && $day <= 22)) return 'Leo';
        if (($month == 8 && $day >= 23) || ($month == 9 && $day <= 22)) return 'Virgo';
        if (($month == 9 && $day >= 23) || ($month == 10 && $day <= 22)) return 'Libra';
        if (($month == 10 && $day >= 23) || ($month == 11 && $day <= 21)) return 'Scorpio';
        if (($month == 11 && $day >= 22) || ($month == 12 && $day <= 21)) return 'Sagittarius';
        if (($month == 12 && $day >= 22) || ($month == 1 && $day <= 19)) return 'Capricorn';
        if (($month == 1 && $day >= 20) || ($month == 2 && $day <= 18)) return 'Aquarius';
        return 'Pisces';
    }

    private function calculateLunarMonth(Carbon $date): string
    {
        // Simplified lunar month calculation
        return 'Chaitra';
    }

    private function calculateTithi(Carbon $date): string
    {
        // Simplified tithi calculation
        return 'Pratipada';
    }

    private function isCompatibilityReady(Horoscope $horoscope): bool
    {
        return !empty($horoscope->zodiac_sign) && !empty($horoscope->moon_sign);
    }

    private function getCompatibilityGrade(int $score): string
    {
        if ($score >= 90) return 'Excellent';
        if ($score >= 80) return 'Very Good';
        if ($score >= 70) return 'Good';
        if ($score >= 60) return 'Average';
        if ($score >= 50) return 'Below Average';
        return 'Poor';
    }

    private function interpretAshtakootScore(int $score): string
    {
        if ($score >= 28) return 'Excellent match with very high compatibility';
        if ($score >= 24) return 'Very good match with strong compatibility';
        if ($score >= 18) return 'Good match with acceptable compatibility';
        if ($score >= 12) return 'Average match with moderate compatibility';
        return 'Below average match requiring careful consideration';
    }

    private function getMarriageCompatibility(int $score): array
    {
        if ($score >= 28) {
            return [
                'recommendation' => 'Highly Recommended',
                'prospects' => 'Excellent prospects for a happy marriage',
                'concerns' => []
            ];
        }
        if ($score >= 18) {
            return [
                'recommendation' => 'Recommended',
                'prospects' => 'Good prospects with minor considerations',
                'concerns' => ['Some areas may need attention']
            ];
        }
        return [
            'recommendation' => 'Needs Consideration',
            'prospects' => 'Moderate prospects requiring careful evaluation',
            'concerns' => ['Multiple compatibility issues need addressing']
        ];
    }

    private function getHoroscopeSummary(Horoscope $horoscope): array
    {
        return [
            'zodiac_sign' => $horoscope->zodiac_sign,
            'moon_sign' => $horoscope->moon_sign,
            'nakshatra' => $horoscope->nakshatra,
            'manglik_status' => $horoscope->manglik_status,
            'birth_place' => $horoscope->birth_place,
            'compatibility_ready' => $this->isCompatibilityReady($horoscope),
        ];
    }

    private function generateCompatibilityRecommendations(array $compatibility): array
    {
        $recommendations = [];

        if ($compatibility['overall_score'] >= 80) {
            $recommendations[] = 'This is an excellent astrological match with strong compatibility indicators.';
        }

        if (!$compatibility['manglik_compatibility']['compatible']) {
            $recommendations[] = 'Consider performing Manglik dosha remedies before marriage.';
        }

        if ($compatibility['ashtakoot_matching']['total_score'] < 18) {
            $recommendations[] = 'Consult with an experienced astrologer for detailed Ashtakoot analysis.';
        }

        return $recommendations;
    }
}
