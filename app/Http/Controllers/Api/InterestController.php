<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class InterestController extends Controller
{
    /**
     * Get all available interests
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'sometimes|string|in:sports,music,travel,food,books,movies,fitness,technology,art,outdoor',
            'search' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = Interest::where('is_active', true)->orderBy('name');

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $interests = $query->get();

            // Group by category
            $groupedInterests = $interests->groupBy('category')->map(function ($categoryInterests) {
                return $categoryInterests->map(function ($interest) {
                    return [
                        'id' => $interest->id,
                        'name' => $interest->name,
                        'icon' => $interest->icon,
                        'color' => $interest->color,
                    ];
                });
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'interests' => $groupedInterests,
                    'categories' => $interests->pluck('category')->unique()->values(),
                    'total_count' => $interests->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get interests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's interests
     */
    public function getUserInterests(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $userInterests = $user->interests()->get()->map(function ($interest) {
                return [
                    'id' => $interest->id,
                    'name' => $interest->name,
                    'category' => $interest->category,
                    'icon' => $interest->icon,
                    'color' => $interest->color,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'user_interests' => $userInterests,
                    'count' => $userInterests->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user interests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user's interests
     */
    public function updateUserInterests(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'interest_ids' => 'required|array|max:20',
            'interest_ids.*' => 'integer|exists:interests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $interestIds = $request->interest_ids;

            // Validate that interests exist and are active
            $validInterests = Interest::whereIn('id', $interestIds)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            if (count($validInterests) !== count($interestIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some interests are invalid or inactive'
                ], 400);
            }

            // Update user interests
            $user->interests()->sync($interestIds);

            // Get updated interests
            $updatedInterests = $user->interests()->get()->map(function ($interest) {
                return [
                    'id' => $interest->id,
                    'name' => $interest->name,
                    'category' => $interest->category,
                    'icon' => $interest->icon,
                    'color' => $interest->color,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Interests updated successfully',
                'data' => [
                    'user_interests' => $updatedInterests,
                    'count' => $updatedInterests->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update interests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add interest to user
     */
    public function addUserInterest(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'interest_id' => 'required|integer|exists:interests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $interestId = $request->interest_id;

            // Check if interest is active
            $interest = Interest::where('id', $interestId)
                ->where('is_active', true)
                ->first();

            if (!$interest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interest not found or inactive'
                ], 404);
            }

            // Check if user already has this interest
            if ($user->interests()->where('interest_id', $interestId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interest already added'
                ], 400);
            }

            // Check limit (max 20 interests)
            if ($user->interests()->count() >= 20) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum 20 interests allowed'
                ], 400);
            }

            // Add interest
            $user->interests()->attach($interestId);

            return response()->json([
                'success' => true,
                'message' => 'Interest added successfully',
                'data' => [
                    'interest' => [
                        'id' => $interest->id,
                        'name' => $interest->name,
                        'category' => $interest->category,
                        'icon' => $interest->icon,
                        'color' => $interest->color,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove interest from user
     */
    public function removeUserInterest(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'interest_id' => 'required|integer|exists:interests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $interestId = $request->interest_id;

            // Check if user has this interest
            if (!$user->interests()->where('interest_id', $interestId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interest not found in user\'s interests'
                ], 404);
            }

            // Remove interest
            $user->interests()->detach($interestId);

            return response()->json([
                'success' => true,
                'message' => 'Interest removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get interest suggestions based on user profile
     */
    public function getSuggestions(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Get interests user doesn't have
            $userInterestIds = $user->interests()->pluck('interest_id')->toArray();
            
            $suggestions = Interest::where('is_active', true)
                ->whereNotIn('id', $userInterestIds)
                ->orderBy('popularity', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($interest) {
                    return [
                        'id' => $interest->id,
                        'name' => $interest->name,
                        'category' => $interest->category,
                        'icon' => $interest->icon,
                        'color' => $interest->color,
                        'popularity' => $interest->popularity,
                    ];
                });

            // Group by category
            $groupedSuggestions = $suggestions->groupBy('category');

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $groupedSuggestions,
                    'total_suggestions' => $suggestions->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get interest suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular interests
     */
    public function getPopular(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:50',
            'category' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $limit = $request->get('limit', 20);
            $category = $request->get('category');

            $query = Interest::where('is_active', true)
                ->orderBy('popularity', 'desc');

            if ($category) {
                $query->where('category', $category);
            }

            $popularInterests = $query->limit($limit)->get()->map(function ($interest) {
                return [
                    'id' => $interest->id,
                    'name' => $interest->name,
                    'category' => $interest->category,
                    'icon' => $interest->icon,
                    'color' => $interest->color,
                    'popularity' => $interest->popularity,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'popular_interests' => $popularInterests,
                    'count' => $popularInterests->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get popular interests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new interest (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:interests,name',
            'category' => 'required|string|in:sports,music,travel,food,books,movies,fitness,technology,art,outdoor,other',
            'icon' => 'sometimes|string|max:255',
            'color' => 'sometimes|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'description' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $interest = Interest::create([
                'name' => $request->name,
                'category' => $request->category,
                'icon' => $request->get('icon', '🔗'),
                'color' => $request->get('color', '#3B82F6'),
                'description' => $request->get('description'),
                'is_active' => true,
                'popularity' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interest created successfully',
                'data' => [
                    'interest' => [
                        'id' => $interest->id,
                        'name' => $interest->name,
                        'category' => $interest->category,
                        'icon' => $interest->icon,
                        'color' => $interest->color,
                        'description' => $interest->description,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update interest (Admin only)
     */
    public function update(Request $request, Interest $interest): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:interests,name,' . $interest->id,
            'category' => 'sometimes|string|in:sports,music,travel,food,books,movies,fitness,technology,art,outdoor,other',
            'icon' => 'sometimes|string|max:255',
            'color' => 'sometimes|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'description' => 'sometimes|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $interest->update($request->only([
                'name', 'category', 'icon', 'color', 'description', 'is_active'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Interest updated successfully',
                'data' => [
                    'interest' => [
                        'id' => $interest->id,
                        'name' => $interest->name,
                        'category' => $interest->category,
                        'icon' => $interest->icon,
                        'color' => $interest->color,
                        'description' => $interest->description,
                        'is_active' => $interest->is_active,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete interest (Admin only)
     */
    public function destroy(Interest $interest): JsonResponse
    {
        try {
            // Soft delete by deactivating instead of hard delete to preserve relationships
            $interest->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Interest deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
