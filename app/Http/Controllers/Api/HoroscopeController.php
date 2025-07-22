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
     * Calculate basic astrological factors (placeholder for external API integration)
     */
    private function calculateAstrologicalFactors($data): array
    {
        // This would typically integrate with an astrological calculation service
        // For now, we'll provide basic calculations
        
        $birthDate = Carbon::parse($data['birth_date']);
        
        return [
            'zodiac_sign' => $this->getZodiacSign($birthDate),
            'birth_day_of_week' => $birthDate->format('l'),
            'birth_lunar_month' => $this->calculateLunarMonth($birthDate),
            'birth_tithi' => $this->calculateTithi($birthDate),
            // More calculations would be added here
        ];
    }

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
        // This would implement a complex query to find compatible horoscopes
        // For now, return a simplified version
        return [];
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
