<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PreferenceController extends Controller
{
    /**
     * Get user's partner preferences
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $preferences = $user->preferences;

        if (!$preferences) {
            // Return default preferences structure
            return response()->json([
                'success' => true,
                'data' => [
                    'preferences' => $this->getDefaultPreferences(),
                    'is_configured' => false,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'preferences' => $preferences,
                'is_configured' => true,
            ]
        ]);
    }

    /**
     * Update user's partner preferences
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            // Basic Preferences
            'min_age' => 'sometimes|integer|min:18|max:100',
            'max_age' => 'sometimes|integer|min:18|max:100|gte:min_age',
            'min_height' => 'sometimes|numeric|between:120,250',
            'max_height' => 'sometimes|numeric|between:120,250|gte:min_height',
            'marital_status' => 'sometimes|array',
            'marital_status.*' => 'in:never_married,divorced,widowed,separated',
            'education_levels' => 'sometimes|array',
            'education_levels.*' => 'string|max:255',
            'occupations' => 'sometimes|array',
            'occupations.*' => 'string|max:255',

            // Location Preferences
            'preferred_countries' => 'sometimes|array',
            'preferred_countries.*' => 'string|max:255',
            'preferred_states' => 'sometimes|array',
            'preferred_states.*' => 'string|max:255',
            'preferred_cities' => 'sometimes|array',
            'preferred_cities.*' => 'string|max:255',
            'max_distance_km' => 'sometimes|integer|min:1|max:10000',
            'willing_to_relocate' => 'sometimes|boolean',

            // Cultural Preferences
            'religions' => 'sometimes|array',
            'religions.*' => 'string|max:255',
            'castes' => 'sometimes|array',
            'castes.*' => 'string|max:255',
            'mother_tongues' => 'sometimes|array',
            'mother_tongues.*' => 'string|max:255',
            'caste_no_bar' => 'sometimes|boolean',
            'religion_no_bar' => 'sometimes|boolean',

            // Lifestyle Preferences
            'diet_preferences' => 'sometimes|array',
            'diet_preferences.*' => 'in:vegetarian,non_vegetarian,vegan,jain',
            'smoking_preferences' => 'sometimes|array',
            'smoking_preferences.*' => 'in:never,occasionally,regularly',
            'drinking_preferences' => 'sometimes|array',
            'drinking_preferences.*' => 'in:never,occasionally,socially,regularly',

            // Financial Preferences
            'min_income_usd' => 'sometimes|numeric|min:0',
            'max_income_usd' => 'sometimes|numeric|min:0|gte:min_income_usd',
            'income_no_bar' => 'sometimes|boolean',

            // Family Preferences
            'family_types' => 'sometimes|array',
            'family_types.*' => 'in:nuclear,joint',
            'family_status' => 'sometimes|array',
            'family_status.*' => 'in:middle_class,upper_middle_class,rich,affluent',
            'children_acceptable' => 'sometimes|boolean',
            'max_children_count' => 'sometimes|integer|min:0|max:10',

            // Physical Preferences
            'body_types' => 'sometimes|array',
            'body_types.*' => 'in:slim,average,athletic,heavy',
            'complexions' => 'sometimes|array',
            'complexions.*' => 'in:very_fair,fair,wheatish,dark',

            // Horoscope Preferences
            'horoscope_match_required' => 'sometimes|boolean',
            'manglik_acceptable' => 'sometimes|boolean',
            'zodiac_signs' => 'sometimes|array',
            'zodiac_signs.*' => 'string|max:255',

            // AI Matching Preferences
            'ai_matching_enabled' => 'sometimes|boolean',
            'ai_match_threshold' => 'sometimes|integer|min:1|max:100',
            'deal_breakers' => 'sometimes|array',
            'deal_breakers.*' => 'string|max:255',
            'nice_to_haves' => 'sometimes|array',
            'nice_to_haves.*' => 'string|max:255',

            // Privacy & Contact
            'show_profile_to_premium_only' => 'sometimes|boolean',
            'auto_accept_premium_requests' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validatedData = $validator->validated();

            // Create or update preferences
            $preferences = $user->preferences()->updateOrCreate(
                ['user_id' => $user->id],
                $validatedData
            );

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => [
                    'preferences' => $preferences,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset preferences to defaults
     */
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $defaultPreferences = $this->getDefaultPreferences();
            
            $preferences = $user->preferences()->updateOrCreate(
                ['user_id' => $user->id],
                $defaultPreferences
            );

            return response()->json([
                'success' => true,
                'message' => 'Preferences reset to defaults',
                'data' => [
                    'preferences' => $preferences,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get smart preferences based on user's profile
     */
    public function generateSmartPreferences(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        if (!$user->profile) {
            return response()->json([
                'success' => false,
                'message' => 'Complete your profile first to generate smart preferences'
            ], 400);
        }

        try {
            $smartPreferences = $this->calculateSmartPreferences($user);

            return response()->json([
                'success' => true,
                'message' => 'Smart preferences generated based on your profile',
                'data' => [
                    'suggested_preferences' => $smartPreferences,
                    'explanation' => $this->getPreferencesExplanation($user, $smartPreferences),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate smart preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply smart preferences
     */
    public function applySmartPreferences(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        if (!$user->profile) {
            return response()->json([
                'success' => false,
                'message' => 'Complete your profile first to apply smart preferences'
            ], 400);
        }

        try {
            $smartPreferences = $this->calculateSmartPreferences($user);
            
            $preferences = $user->preferences()->updateOrCreate(
                ['user_id' => $user->id],
                $smartPreferences
            );

            return response()->json([
                'success' => true,
                'message' => 'Smart preferences applied successfully',
                'data' => [
                    'preferences' => $preferences,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply smart preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get preference options for form building
     */
    public function getOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'marital_status_options' => [
                    'never_married' => 'Never Married',
                    'divorced' => 'Divorced',
                    'widowed' => 'Widowed',
                    'separated' => 'Separated'
                ],
                'diet_options' => [
                    'vegetarian' => 'Vegetarian',
                    'non_vegetarian' => 'Non-Vegetarian',
                    'vegan' => 'Vegan',
                    'jain' => 'Jain'
                ],
                'smoking_options' => [
                    'never' => 'Never',
                    'occasionally' => 'Occasionally',
                    'regularly' => 'Regularly'
                ],
                'drinking_options' => [
                    'never' => 'Never',
                    'occasionally' => 'Occasionally',
                    'socially' => 'Socially',
                    'regularly' => 'Regularly'
                ],
                'body_type_options' => [
                    'slim' => 'Slim',
                    'average' => 'Average',
                    'athletic' => 'Athletic',
                    'heavy' => 'Heavy'
                ],
                'complexion_options' => [
                    'very_fair' => 'Very Fair',
                    'fair' => 'Fair',
                    'wheatish' => 'Wheatish',
                    'dark' => 'Dark'
                ],
                'family_type_options' => [
                    'nuclear' => 'Nuclear',
                    'joint' => 'Joint'
                ],
                'family_status_options' => [
                    'middle_class' => 'Middle Class',
                    'upper_middle_class' => 'Upper Middle Class',
                    'rich' => 'Rich',
                    'affluent' => 'Affluent'
                ],
                'age_range' => ['min' => 18, 'max' => 80],
                'height_range' => ['min' => 120, 'max' => 250],
                'income_range' => ['min' => 0, 'max' => 1000000],
            ]
        ]);
    }

    /**
     * Get preference summary for matches
     */
    public function getSummary(Request $request): JsonResponse
    {
        $user = $request->user()->load('preferences');

        if (!$user->preferences) {
            return response()->json([
                'success' => false,
                'message' => 'No preferences set'
            ], 404);
        }

        $preferences = $user->preferences;
        $summary = [
            'age_range' => $preferences->min_age . '-' . $preferences->max_age . ' years',
            'height_range' => $preferences->min_height . '-' . $preferences->max_height . ' cm',
            'marital_status' => implode(', ', $preferences->marital_status ?? []),
            'location' => implode(', ', $preferences->preferred_countries ?? []),
            'religion' => $preferences->religion_no_bar ? 'Any' : implode(', ', $preferences->religions ?? []),
            'diet' => implode(', ', $preferences->diet_preferences ?? []),
            'education' => implode(', ', $preferences->education_levels ?? []),
            'income_important' => !$preferences->income_no_bar,
            'horoscope_important' => $preferences->horoscope_match_required ?? false,
            'deal_breakers_count' => count($preferences->deal_breakers ?? []),
            'flexibility_score' => $this->calculateFlexibilityScore($preferences),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'preferences' => $preferences,
            ]
        ]);
    }

    /**
     * Get default preferences
     */
    private function getDefaultPreferences(): array
    {
        return [
            'min_age' => 21,
            'max_age' => 35,
            'min_height' => 150,
            'max_height' => 180,
            'marital_status' => ['never_married'],
            'willing_to_relocate' => false,
            'caste_no_bar' => false,
            'religion_no_bar' => false,
            'children_acceptable' => true,
            'max_children_count' => 2,
            'income_no_bar' => true,
            'horoscope_match_required' => false,
            'manglik_acceptable' => true,
            'ai_matching_enabled' => true,
            'ai_match_threshold' => 70,
            'show_profile_to_premium_only' => false,
            'auto_accept_premium_requests' => false,
        ];
    }

    /**
     * Calculate smart preferences based on user profile
     */
    private function calculateSmartPreferences($user): array
    {
        $profile = $user->profile;
        $userAge = $user->age;
        $smartPreferences = $this->getDefaultPreferences();

        // Age preferences based on user's age and gender
        if ($user->gender === 'male') {
            $smartPreferences['min_age'] = max(18, $userAge - 8);
            $smartPreferences['max_age'] = min(80, $userAge + 3);
        } else {
            $smartPreferences['min_age'] = max(18, $userAge - 3);
            $smartPreferences['max_age'] = min(80, $userAge + 8);
        }

        // Height preferences based on user's height and gender
        if ($profile && $profile->height) {
            if ($user->gender === 'male') {
                $smartPreferences['min_height'] = max(120, $profile->height - 15);
                $smartPreferences['max_height'] = min(250, $profile->height + 5);
            } else {
                $smartPreferences['min_height'] = max(120, $profile->height - 5);
                $smartPreferences['max_height'] = min(250, $profile->height + 15);
            }
        }

        // Cultural preferences
        if ($profile) {
            if ($profile->religion) {
                $smartPreferences['religions'] = [$profile->religion];
                $smartPreferences['religion_no_bar'] = false;
            }

            if ($profile->mother_tongue) {
                $smartPreferences['mother_tongues'] = [$profile->mother_tongue];
            }

            if ($profile->diet) {
                $smartPreferences['diet_preferences'] = [$profile->diet];
            }

            // Location preferences
            if ($profile->country) {
                $smartPreferences['preferred_countries'] = [$profile->country];
            }
            if ($profile->state) {
                $smartPreferences['preferred_states'] = [$profile->state];
            }
        }

        // Income preferences based on user's income
        if ($profile && $profile->annual_income_usd) {
            $userIncome = $profile->annual_income_usd;
            $smartPreferences['min_income_usd'] = $userIncome * 0.5; // 50% of user's income
            $smartPreferences['income_no_bar'] = false;
        }

        // Education level preference
        if ($profile && $profile->education) {
            $smartPreferences['education_levels'] = [$profile->education];
        }

        return $smartPreferences;
    }

    /**
     * Get explanation for generated preferences
     */
    private function getPreferencesExplanation($user, $preferences): array
    {
        $explanations = [];

        $explanations[] = "Age range set based on your age ({$user->age}) and gender compatibility patterns.";
        
        if (isset($preferences['religions'])) {
            $explanations[] = "Religion preference set to match your religion for cultural compatibility.";
        }

        if (isset($preferences['min_height'], $preferences['max_height'])) {
            $explanations[] = "Height preferences set based on your height and gender-typical preferences.";
        }

        if (isset($preferences['min_income_usd'])) {
            $explanations[] = "Income preference set based on your income level for financial compatibility.";
        }

        if (isset($preferences['preferred_countries'])) {
            $explanations[] = "Location preference set to your country for easier meetings and compatibility.";
        }

        return $explanations;
    }

    /**
     * Calculate flexibility score (how flexible/strict the preferences are)
     */
    private function calculateFlexibilityScore($preferences): int
    {
        $score = 100; // Start with maximum flexibility

        // Reduce score for strict preferences
        if (!$preferences->religion_no_bar && !empty($preferences->religions)) {
            $score -= 15;
        }

        if (!$preferences->caste_no_bar && !empty($preferences->castes)) {
            $score -= 10;
        }

        if (!$preferences->income_no_bar) {
            $score -= 10;
        }

        if ($preferences->horoscope_match_required) {
            $score -= 15;
        }

        // Age range strictness
        $ageRange = $preferences->max_age - $preferences->min_age;
        if ($ageRange < 5) {
            $score -= 20;
        } elseif ($ageRange < 10) {
            $score -= 10;
        }

        // Height range strictness
        if ($preferences->max_height && $preferences->min_height) {
            $heightRange = $preferences->max_height - $preferences->min_height;
            if ($heightRange < 10) {
                $score -= 10;
            }
        }

        // Deal breakers reduce flexibility
        $dealBreakersCount = count($preferences->deal_breakers ?? []);
        $score -= $dealBreakersCount * 5;

        return max(0, min(100, $score));
    }
}
