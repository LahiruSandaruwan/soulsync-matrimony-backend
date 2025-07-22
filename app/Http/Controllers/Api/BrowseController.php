<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BrowseController extends Controller
{
    protected $matchingService;

    public function __construct(MatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Browse all profiles with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
            'age_min' => 'sometimes|integer|min:18|max:100',
            'age_max' => 'sometimes|integer|min:18|max:100',
            'gender' => 'sometimes|in:male,female,other',
            'location' => 'sometimes|string|max:100',
            'religion' => 'sometimes|string|max:50',
            'education' => 'sometimes|string|max:100',
            'profession' => 'sometimes|string|max:100',
            'income_min' => 'sometimes|integer|min:0',
            'income_max' => 'sometimes|integer|min:0',
            'height_min' => 'sometimes|integer|min:120',
            'height_max' => 'sometimes|integer|min:120',
            'marital_status' => 'sometimes|in:never_married,divorced,widowed,separated',
            'sort_by' => 'sometimes|in:newest,active,compatibility,distance',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $offset = ($page - 1) * $limit;

            // Build base query
            $query = User::with(['profile', 'photos' => function($q) {
                $q->where('is_profile_picture', true)->orWhere('order', 1);
            }])
            ->whereHas('profile')
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->where('is_profile_complete', true)
            ->whereNotIn('id', function($q) use ($user) {
                $q->select('target_user_id')
                  ->from('user_matches')
                  ->where('user_id', $user->id)
                  ->where('action', 'block');
            });

            // Apply filters
            $this->applyFilters($query, $request);

            // Apply sorting
            $this->applySorting($query, $request->get('sort_by', 'newest'));

            // Get total count for pagination
            $totalCount = $query->count();

            // Get profiles
            $profiles = $query->offset($offset)->limit($limit)->get();

            // Format profiles
            $formattedProfiles = $profiles->map(function ($profile) use ($user) {
                return $this->formatProfileForBrowse($profile, $user);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'profiles' => $formattedProfiles,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit),
                        'has_more' => ($page * $limit) < $totalCount,
                    ],
                    'filters_applied' => $this->getAppliedFilters($request),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to browse profiles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse premium profiles
     */
    public function premiumProfiles(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
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
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $offset = ($page - 1) * $limit;

            // Get premium users
            $query = User::with(['profile', 'photos' => function($q) {
                $q->where('is_profile_picture', true)->orWhere('order', 1);
            }])
            ->whereHas('profile')
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->where('is_profile_complete', true)
            ->where('is_premium_active', true)
            ->whereNotIn('id', function($q) use ($user) {
                $q->select('target_user_id')
                  ->from('user_matches')
                  ->where('user_id', $user->id)
                  ->where('action', 'block');
            })
            ->orderBy('premium_expires_at', 'desc')
            ->orderBy('last_seen_at', 'desc');

            $totalCount = $query->count();
            $profiles = $query->offset($offset)->limit($limit)->get();

            $formattedProfiles = $profiles->map(function ($profile) use ($user) {
                $formatted = $this->formatProfileForBrowse($profile, $user);
                $formatted['is_premium'] = true;
                $formatted['premium_badge'] = '👑';
                return $formatted;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'profiles' => $formattedProfiles,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit),
                        'has_more' => ($page * $limit) < $totalCount,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get premium profiles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse recently joined profiles
     */
    public function recentlyJoined(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
            'days' => 'sometimes|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $days = $request->get('days', 7);
            $offset = ($page - 1) * $limit;

            // Get recently joined users
            $query = User::with(['profile', 'photos' => function($q) {
                $q->where('is_profile_picture', true)->orWhere('order', 1);
            }])
            ->whereHas('profile')
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->where('is_profile_complete', true)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotIn('id', function($q) use ($user) {
                $q->select('target_user_id')
                  ->from('user_matches')
                  ->where('user_id', $user->id)
                  ->where('action', 'block');
            })
            ->orderBy('created_at', 'desc');

            $totalCount = $query->count();
            $profiles = $query->offset($offset)->limit($limit)->get();

            $formattedProfiles = $profiles->map(function ($profile) use ($user) {
                $formatted = $this->formatProfileForBrowse($profile, $user);
                $formatted['is_new'] = true;
                $formatted['joined_days_ago'] = $profile->created_at->diffInDays(now());
                $formatted['new_badge'] = '✨';
                return $formatted;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'profiles' => $formattedProfiles,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit),
                        'has_more' => ($page * $limit) < $totalCount,
                    ],
                    'filter_days' => $days,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recently joined profiles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse verified profiles
     */
    public function verifiedProfiles(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
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
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $offset = ($page - 1) * $limit;

            // Get verified users (users with verified email and phone)
            $query = User::with(['profile', 'photos' => function($q) {
                $q->where('is_profile_picture', true)->orWhere('order', 1);
            }])
            ->whereHas('profile')
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->where('is_profile_complete', true)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('phone_verified_at')
            ->whereNotIn('id', function($q) use ($user) {
                $q->select('target_user_id')
                  ->from('user_matches')
                  ->where('user_id', $user->id)
                  ->where('action', 'block');
            })
            ->orderBy('email_verified_at', 'desc')
            ->orderBy('phone_verified_at', 'desc');

            $totalCount = $query->count();
            $profiles = $query->offset($offset)->limit($limit)->get();

            $formattedProfiles = $profiles->map(function ($profile) use ($user) {
                $formatted = $this->formatProfileForBrowse($profile, $user);
                $formatted['is_verified'] = true;
                $formatted['verification_badge'] = '✓';
                $formatted['verified_email'] = !is_null($profile->email_verified_at);
                $formatted['verified_phone'] = !is_null($profile->phone_verified_at);
                return $formatted;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'profiles' => $formattedProfiles,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit),
                        'has_more' => ($page * $limit) < $totalCount,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get verified profiles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters($query, Request $request): void
    {
        // Age filter
        if ($request->has('age_min') || $request->has('age_max')) {
            $query->whereHas('profile', function($q) use ($request) {
                if ($request->has('age_min')) {
                    $q->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?', [$request->age_min]);
                }
                if ($request->has('age_max')) {
                    $q->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?', [$request->age_max]);
                }
            });
        }

        // Gender filter
        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }

        // Location filter
        if ($request->has('location')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where(function($subQ) use ($request) {
                    $subQ->where('city', 'like', '%' . $request->location . '%')
                         ->orWhere('state', 'like', '%' . $request->location . '%')
                         ->orWhere('country', 'like', '%' . $request->location . '%');
                });
            });
        }

        // Religion filter
        if ($request->has('religion')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('religion', $request->religion);
            });
        }

        // Education filter
        if ($request->has('education')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('education_level', 'like', '%' . $request->education . '%');
            });
        }

        // Profession filter
        if ($request->has('profession')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('profession', 'like', '%' . $request->profession . '%');
            });
        }

        // Income filter
        if ($request->has('income_min') || $request->has('income_max')) {
            $query->whereHas('profile', function($q) use ($request) {
                if ($request->has('income_min')) {
                    $q->where('annual_income', '>=', $request->income_min);
                }
                if ($request->has('income_max')) {
                    $q->where('annual_income', '<=', $request->income_max);
                }
            });
        }

        // Height filter
        if ($request->has('height_min') || $request->has('height_max')) {
            $query->whereHas('profile', function($q) use ($request) {
                if ($request->has('height_min')) {
                    $q->where('height_cm', '>=', $request->height_min);
                }
                if ($request->has('height_max')) {
                    $q->where('height_cm', '<=', $request->height_max);
                }
            });
        }

        // Marital status filter
        if ($request->has('marital_status')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('marital_status', $request->marital_status);
            });
        }
    }

    /**
     * Apply sorting to the query
     */
    private function applySorting($query, string $sortBy): void
    {
        switch ($sortBy) {
            case 'active':
                $query->orderBy('last_seen_at', 'desc');
                break;
            case 'compatibility':
                // For now, order by profile completion and premium status
                $query->orderBy('is_premium_active', 'desc')
                      ->orderBy('profile_completion_percentage', 'desc');
                break;
            case 'distance':
                // For now, order by country/state (would need GPS coordinates for real distance)
                $query->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
                      ->orderBy('user_profiles.country')
                      ->orderBy('user_profiles.state')
                      ->orderBy('user_profiles.city');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
    }

    /**
     * Format profile for browse response
     */
    private function formatProfileForBrowse($user, $currentUser): array
    {
        $profile = $user->profile;
        $photo = $user->photos->first();

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'age' => $profile ? $profile->age : null,
            'city' => $profile ? $profile->city : null,
            'state' => $profile ? $profile->state : null,
            'country' => $profile ? $profile->country : null,
            'profession' => $profile ? $profile->profession : null,
            'education' => $profile ? $profile->education_level : null,
            'religion' => $profile ? $profile->religion : null,
            'height' => $profile ? $profile->height_cm : null,
            'marital_status' => $profile ? $profile->marital_status : null,
            'bio' => $profile ? substr($profile->bio, 0, 150) . '...' : null,
            'photo' => $photo ? [
                'id' => $photo->id,
                'url' => $photo->file_path,
                'is_private' => $photo->is_private,
            ] : null,
            'is_online' => $user->last_seen_at && $user->last_seen_at->gt(now()->subMinutes(15)),
            'last_seen' => $user->last_seen_at ? $user->last_seen_at->diffForHumans() : null,
            'is_premium' => $user->is_premium_active,
            'profile_completion' => $user->profile_completion_percentage,
            'distance' => null, // Would calculate based on GPS coordinates
            'compatibility_score' => null, // Would calculate using MatchingService
        ];
    }

    /**
     * Get applied filters summary
     */
    private function getAppliedFilters(Request $request): array
    {
        $filters = [];
        
        if ($request->has('age_min') || $request->has('age_max')) {
            $filters['age'] = [
                'min' => $request->get('age_min'),
                'max' => $request->get('age_max'),
            ];
        }

        if ($request->has('gender')) {
            $filters['gender'] = $request->gender;
        }

        if ($request->has('location')) {
            $filters['location'] = $request->location;
        }

        if ($request->has('religion')) {
            $filters['religion'] = $request->religion;
        }

        if ($request->has('education')) {
            $filters['education'] = $request->education;
        }

        if ($request->has('profession')) {
            $filters['profession'] = $request->profession;
        }

        if ($request->has('income_min') || $request->has('income_max')) {
            $filters['income'] = [
                'min' => $request->get('income_min'),
                'max' => $request->get('income_max'),
            ];
        }

        if ($request->has('height_min') || $request->has('height_max')) {
            $filters['height'] = [
                'min' => $request->get('height_min'),
                'max' => $request->get('height_max'),
            ];
        }

        if ($request->has('marital_status')) {
            $filters['marital_status'] = $request->marital_status;
        }

        return $filters;
    }
} 