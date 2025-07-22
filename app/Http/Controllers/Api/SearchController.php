<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Advanced search with comprehensive filters
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            // Basic filters
            'min_age' => 'sometimes|integer|min:18|max:100',
            'max_age' => 'sometimes|integer|min:18|max:100|gte:min_age',
            'min_height' => 'sometimes|numeric|between:120,250',
            'max_height' => 'sometimes|numeric|between:120,250|gte:min_height',
            'gender' => 'sometimes|in:male,female,other',

            // Location filters
            'country' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'max_distance_km' => 'sometimes|integer|min:1|max:10000',

            // Cultural filters
            'religion' => 'sometimes|string|max:255',
            'caste' => 'sometimes|string|max:255',
            'mother_tongue' => 'sometimes|string|max:255',

            // Professional filters
            'education' => 'sometimes|string|max:255',
            'occupation' => 'sometimes|string|max:255',
            'min_income_usd' => 'sometimes|numeric|min:0',
            'max_income_usd' => 'sometimes|numeric|min:0|gte:min_income_usd',

            // Lifestyle filters
            'marital_status' => 'sometimes|in:never_married,divorced,widowed,separated',
            'diet' => 'sometimes|in:vegetarian,non_vegetarian,vegan,jain',
            'smoking' => 'sometimes|in:never,occasionally,regularly',
            'drinking' => 'sometimes|in:never,occasionally,socially,regularly',

            // Physical filters
            'body_type' => 'sometimes|in:slim,average,athletic,heavy',
            'complexion' => 'sometimes|in:very_fair,fair,wheatish,dark',

            // Family filters
            'family_type' => 'sometimes|in:nuclear,joint',
            'family_status' => 'sometimes|in:middle_class,upper_middle_class,rich,affluent',
            'children_acceptable' => 'sometimes|boolean',
            'max_children_count' => 'sometimes|integer|min:0|max:10',

            // Additional filters
            'has_photo' => 'sometimes|boolean',
            'photo_verified' => 'sometimes|boolean',
            'premium_only' => 'sometimes|boolean',
            'online_status' => 'sometimes|in:online,recently_active,any',
            'verified_profiles' => 'sometimes|boolean',

            // Search query
            'keyword' => 'sometimes|string|max:255',

            // Pagination and sorting
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|in:relevance,last_active,newest,age,compatibility',
            'sort_order' => 'sometimes|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filters = $validator->validated();
            $results = $this->performSearch($user, $filters);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick search with common filters
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        // This is essentially the same as search but with a different endpoint name
        return $this->search($request);
    }

    /**
     * Get search filters and options
     */
    public function getFilters(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'age_range' => ['min' => 18, 'max' => 80],
                'height_range' => ['min' => 120, 'max' => 250],
                'income_range' => ['min' => 0, 'max' => 1000000],
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
                'sort_options' => [
                    'relevance' => 'Most Relevant',
                    'last_active' => 'Recently Active',
                    'newest' => 'Newest Members',
                    'age' => 'Age',
                    'compatibility' => 'Best Match'
                ],
                'popular_searches' => $this->getPopularSearches(),
            ]
        ]);
    }

    /**
     * Save a search for later
     */
    public function saveSearch(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'filters' => 'required|array',
            'is_alert' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create saved search
            $savedSearch = \App\Models\SavedSearch::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'filters' => $request->filters,
                'is_alert_enabled' => $request->get('is_alert', false),
                'alert_frequency' => $request->get('alert_frequency', 24),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Search saved successfully',
                'data' => [
                    'search_id' => $savedSearch->id,
                    'name' => $savedSearch->name,
                    'filters' => $savedSearch->filters,
                    'is_alert_enabled' => $savedSearch->is_alert_enabled,
                    'created_at' => $savedSearch->created_at->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save search',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get saved searches
     */
    public function savedSearches(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $savedSearches = \App\Models\SavedSearch::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderBy('last_executed', 'desc')
                ->get();

            $recentSearches = \App\Models\SavedSearch::getUserSearchHistory($user, 5);

            return response()->json([
                'success' => true,
                'data' => [
                    'saved_searches' => $savedSearches->map(function ($search) {
                        return [
                            'id' => $search->id,
                            'name' => $search->name,
                            'filters' => $search->filters,
                            'result_count' => $search->result_count,
                            'is_alert_enabled' => $search->is_alert_enabled,
                            'last_executed' => $search->last_executed?->toISOString(),
                            'created_at' => $search->created_at->toISOString(),
                        ];
                    }),
                    'recent_searches' => $recentSearches,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get saved searches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse profiles by category
     */
    public function browse(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'mode' => 'sometimes|in:latest,popular,premium,nearby,compatible,verified',
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mode = $request->get('mode', 'latest');
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);

            $results = $this->browseProfiles($user, $mode, $limit, $page);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Browse failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get search suggestions for autocomplete
     */
    public function getSearchSuggestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:50',
            'type' => 'sometimes|in:location,occupation,education,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->get('query');
            $type = $request->get('type', 'all');

            $suggestions = $this->generateSearchSuggestions($query, $type);

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'query' => $query,
                    'type' => $type,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Core search functionality
     */
    private function performSearch(User $currentUser, array $filters): array
    {
        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        // Build base query
        $query = User::query()
            ->with(['profile', 'profilePicture', 'horoscope'])
            ->where('status', 'active')
            ->where('profile_status', 'approved')
            ->where('id', '!=', $currentUser->id);

        // Exclude already matched/blocked users
        $excludeUserIds = UserMatch::where('user_id', $currentUser->id)
            ->pluck('target_user_id')
            ->toArray();
        
        if (!empty($excludeUserIds)) {
            $query->whereNotIn('id', $excludeUserIds);
        }

        // Apply filters
        $this->applyBasicFilters($query, $filters);
        $this->applyLocationFilters($query, $filters);
        $this->applyCulturalFilters($query, $filters);
        $this->applyProfessionalFilters($query, $filters);
        $this->applyLifestyleFilters($query, $filters);
        $this->applyPhysicalFilters($query, $filters);
        $this->applyFamilyFilters($query, $filters);
        $this->applyAdditionalFilters($query, $filters);
        $this->applyKeywordSearch($query, $filters);

        // Get total count before pagination
        $totalCount = $query->count();

        // Apply sorting
        $this->applySorting($query, $filters, $currentUser);

        // Apply pagination
        $results = $query->offset($offset)->limit($limit)->get();

        // Add compatibility scores if sorting by compatibility
        if (($filters['sort_by'] ?? '') === 'compatibility') {
            $results = $this->addCompatibilityScores($results, $currentUser);
        }

        // Format results
        $formattedResults = $results->map(function ($user) use ($currentUser) {
            return $this->formatSearchResult($user, $currentUser);
        });

        return [
            'results' => $formattedResults,
            'pagination' => [
                'current_page' => $page,
                'total_results' => $totalCount,
                'per_page' => $limit,
                'total_pages' => ceil($totalCount / $limit),
                'has_more' => ($offset + $limit) < $totalCount,
            ],
            'filters_applied' => $this->getAppliedFiltersCount($filters),
            'search_id' => uniqid(), // For analytics
        ];
    }

    /**
     * Apply basic filters (age, height, gender)
     */
    private function applyBasicFilters($query, $filters): void
    {
        if (isset($filters['min_age']) && isset($filters['max_age'])) {
            $minBirthDate = now()->subYears($filters['max_age']);
            $maxBirthDate = now()->subYears($filters['min_age']);
            $query->whereBetween('date_of_birth', [$minBirthDate, $maxBirthDate]);
        }

        if (isset($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['min_height']) || isset($filters['max_height'])) {
            $query->whereHas('profile', function ($q) use ($filters) {
                if (isset($filters['min_height'])) {
                    $q->where('height', '>=', $filters['min_height']);
                }
                if (isset($filters['max_height'])) {
                    $q->where('height', '<=', $filters['max_height']);
                }
            });
        }
    }

    /**
     * Apply location filters
     */
    private function applyLocationFilters($query, $filters): void
    {
        if (isset($filters['country']) || isset($filters['state']) || isset($filters['city'])) {
            $query->whereHas('profile', function ($q) use ($filters) {
                if (isset($filters['country'])) {
                    $q->where('country', 'like', '%' . $filters['country'] . '%');
                }
                if (isset($filters['state'])) {
                    $q->where('state', 'like', '%' . $filters['state'] . '%');
                }
                if (isset($filters['city'])) {
                    $q->where('city', 'like', '%' . $filters['city'] . '%');
                }
            });
        }
    }

    /**
     * Apply cultural filters
     */
    private function applyCulturalFilters($query, $filters): void
    {
        if (isset($filters['religion']) || isset($filters['caste']) || isset($filters['mother_tongue'])) {
            $query->whereHas('profile', function ($q) use ($filters) {
                if (isset($filters['religion'])) {
                    $q->where('religion', 'like', '%' . $filters['religion'] . '%');
                }
                if (isset($filters['caste'])) {
                    $q->where('caste', 'like', '%' . $filters['caste'] . '%');
                }
                if (isset($filters['mother_tongue'])) {
                    $q->where('mother_tongue', 'like', '%' . $filters['mother_tongue'] . '%');
                }
            });
        }
    }

    /**
     * Apply professional filters
     */
    private function applyProfessionalFilters($query, $filters): void
    {
        if (isset($filters['education']) || isset($filters['occupation']) || 
            isset($filters['min_income_usd']) || isset($filters['max_income_usd'])) {
            
            $query->whereHas('profile', function ($q) use ($filters) {
                if (isset($filters['education'])) {
                    $q->where('education', 'like', '%' . $filters['education'] . '%');
                }
                if (isset($filters['occupation'])) {
                    $q->where('occupation', 'like', '%' . $filters['occupation'] . '%');
                }
                if (isset($filters['min_income_usd'])) {
                    $q->where('annual_income_usd', '>=', $filters['min_income_usd']);
                }
                if (isset($filters['max_income_usd'])) {
                    $q->where('annual_income_usd', '<=', $filters['max_income_usd']);
                }
            });
        }
    }

    /**
     * Apply lifestyle filters
     */
    private function applyLifestyleFilters($query, $filters): void
    {
        if (isset($filters['marital_status']) || isset($filters['diet']) || 
            isset($filters['smoking']) || isset($filters['drinking'])) {
            
            $query->whereHas('profile', function ($q) use ($filters) {
                if (isset($filters['marital_status'])) {
                    $q->where('marital_status', $filters['marital_status']);
                }
                if (isset($filters['diet'])) {
                    $q->where('diet', $filters['diet']);
                }
                if (isset($filters['smoking'])) {
                    $q->where('smoking', $filters['smoking']);
                }
                if (isset($filters['drinking'])) {
                    $q->where('drinking', $filters['drinking']);
                }
            });
        }
    }

    /**
     * Apply physical filters
     */
    private function applyPhysicalFilters($query, $filters): void
    {
        if (isset($filters['body_type']) || isset($filters['complexion'])) {
            $query->whereHas('profile', function ($q) use ($filters) {
                if (isset($filters['body_type'])) {
                    $q->where('body_type', $filters['body_type']);
                }
                if (isset($filters['complexion'])) {
                    $q->where('complexion', $filters['complexion']);
                }
            });
        }
    }

    /**
     * Apply family filters
     */
    private function applyFamilyFilters($query, $filters): void
    {
        if (isset($filters['family_type']) || isset($filters['family_status']) || 
            isset($filters['children_acceptable']) || isset($filters['max_children_count'])) {
            
            $query->whereHas('profile', function ($q) use ($filters) {
                if (isset($filters['family_type'])) {
                    $q->where('family_type', $filters['family_type']);
                }
                if (isset($filters['family_status'])) {
                    $q->where('family_status', $filters['family_status']);
                }
                if (isset($filters['max_children_count'])) {
                    $q->where('children_count', '<=', $filters['max_children_count']);
                }
            });
        }
    }

    /**
     * Apply additional filters
     */
    private function applyAdditionalFilters($query, $filters): void
    {
        if (isset($filters['has_photo']) && $filters['has_photo']) {
            $query->whereHas('profilePicture');
        }

        if (isset($filters['premium_only']) && $filters['premium_only']) {
            $query->where('is_premium', true)
                  ->where('premium_expires_at', '>', now());
        }

        if (isset($filters['verified_profiles']) && $filters['verified_profiles']) {
            $query->where('profile_status', 'approved');
        }

        if (isset($filters['online_status'])) {
            switch ($filters['online_status']) {
                case 'online':
                    $query->where('last_active_at', '>', now()->subMinutes(15));
                    break;
                case 'recently_active':
                    $query->where('last_active_at', '>', now()->subDays(7));
                    break;
            }
        }
    }

    /**
     * Apply keyword search
     */
    private function applyKeywordSearch($query, $filters): void
    {
        if (isset($filters['keyword']) && !empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            
            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', '%' . $keyword . '%')
                  ->orWhere('last_name', 'like', '%' . $keyword . '%')
                  ->orWhereHas('profile', function ($profileQuery) use ($keyword) {
                      $profileQuery->where('bio', 'like', '%' . $keyword . '%')
                                  ->orWhere('occupation', 'like', '%' . $keyword . '%')
                                  ->orWhere('education', 'like', '%' . $keyword . '%')
                                  ->orWhere('city', 'like', '%' . $keyword . '%')
                                  ->orWhere('company', 'like', '%' . $keyword . '%');
                  });
            });
        }
    }

    /**
     * Apply sorting
     */
    private function applySorting($query, $filters, $currentUser): void
    {
        $sortBy = $filters['sort_by'] ?? 'last_active';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'last_active':
                $query->orderBy('last_active_at', $sortOrder);
                break;
            case 'newest':
                $query->orderBy('created_at', $sortOrder);
                break;
            case 'age':
                $query->orderBy('date_of_birth', $sortOrder === 'asc' ? 'desc' : 'asc');
                break;
            case 'compatibility':
                // Compatibility sorting will be handled after getting results
                $query->orderBy('last_active_at', 'desc');
                break;
            default:
                $query->orderBy('last_active_at', 'desc');
        }
    }

    /**
     * Add compatibility scores to results
     */
    private function addCompatibilityScores($users, $currentUser): mixed
    {
        // This would use the same compatibility calculation from MatchController
        return $users;
    }

    /**
     * Format search result
     */
    private function formatSearchResult($user, $currentUser): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'age' => $user->age,
            'location' => $user->profile ? 
                ($user->profile->city . ', ' . $user->profile->country) : null,
            'occupation' => $user->profile->occupation ?? null,
            'education' => $user->profile->education ?? null,
            'religion' => $user->profile->religion ?? null,
            'height' => $user->profile->height ?? null,
            'marital_status' => $user->profile->marital_status ?? null,
            'bio_snippet' => $user->profile ? substr($user->profile->bio, 0, 100) . '...' : null,
            'profile_picture' => $user->profilePicture ? 
                Storage::url($user->profilePicture->file_path) : null,
            'is_premium' => $user->is_premium_active,
            'is_verified' => $user->profile_status === 'approved',
            'last_active' => $user->last_active_at,
            'joined_at' => $user->created_at,
        ];
    }

    /**
     * Browse profiles by different modes
     */
    private function browseProfiles($currentUser, $mode, $limit, $page): array
    {
        $filters = ['page' => $page, 'limit' => $limit];

        switch ($mode) {
            case 'latest':
                $filters['sort_by'] = 'newest';
                break;
            case 'premium':
                $filters['premium_only'] = true;
                $filters['sort_by'] = 'last_active';
                break;
            case 'verified':
                $filters['verified_profiles'] = true;
                $filters['sort_by'] = 'last_active';
                break;
            case 'nearby':
                if ($currentUser->profile && $currentUser->profile->country) {
                    $filters['country'] = $currentUser->profile->country;
                }
                $filters['sort_by'] = 'last_active';
                break;
            case 'compatible':
                $filters['sort_by'] = 'compatibility';
                break;
            default:
                $filters['sort_by'] = 'last_active';
        }

        return $this->performSearch($currentUser, $filters);
    }

    /**
     * Generate search suggestions
     */
    private function generateSearchSuggestions($query, $type): array
    {
        $suggestions = [];

        if ($type === 'location' || $type === 'all') {
            // Get location suggestions from profiles
            $locations = DB::table('user_profiles')
                ->select('city', 'state', 'country')
                ->where(function ($q) use ($query) {
                    $q->where('city', 'like', '%' . $query . '%')
                      ->orWhere('state', 'like', '%' . $query . '%')
                      ->orWhere('country', 'like', '%' . $query . '%');
                })
                ->distinct()
                ->limit(5)
                ->get();

            foreach ($locations as $location) {
                if (stripos($location->city, $query) !== false) {
                    $suggestions[] = ['type' => 'city', 'value' => $location->city];
                }
                if (stripos($location->state, $query) !== false) {
                    $suggestions[] = ['type' => 'state', 'value' => $location->state];
                }
                if (stripos($location->country, $query) !== false) {
                    $suggestions[] = ['type' => 'country', 'value' => $location->country];
                }
            }
        }

        if ($type === 'occupation' || $type === 'all') {
            $occupations = DB::table('user_profiles')
                ->select('occupation')
                ->where('occupation', 'like', '%' . $query . '%')
                ->whereNotNull('occupation')
                ->distinct()
                ->limit(5)
                ->pluck('occupation');

            foreach ($occupations as $occupation) {
                $suggestions[] = ['type' => 'occupation', 'value' => $occupation];
            }
        }

        if ($type === 'education' || $type === 'all') {
            $educations = DB::table('user_profiles')
                ->select('education')
                ->where('education', 'like', '%' . $query . '%')
                ->whereNotNull('education')
                ->distinct()
                ->limit(5)
                ->pluck('education');

            foreach ($educations as $education) {
                $suggestions[] = ['type' => 'education', 'value' => $education];
            }
        }

        return array_unique($suggestions, SORT_REGULAR);
    }

    /**
     * Get popular searches
     */
    private function getPopularSearches(): array
    {
        return [
            ['label' => 'Never Married', 'filters' => ['marital_status' => 'never_married']],
            ['label' => 'Age 25-30', 'filters' => ['min_age' => 25, 'max_age' => 30]],
            ['label' => 'Premium Members', 'filters' => ['premium_only' => true]],
            ['label' => 'With Photos', 'filters' => ['has_photo' => true]],
            ['label' => 'Recently Active', 'filters' => ['online_status' => 'recently_active']],
        ];
    }

    /**
     * Count applied filters
     */
    private function getAppliedFiltersCount($filters): int
    {
        $excludeKeys = ['page', 'limit', 'sort_by', 'sort_order'];
        return count(array_diff_key($filters, array_flip($excludeKeys)));
    }
}
