<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentController extends Controller
{
    /**
     * Get all interests with statistics
     */
    public function interests(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 50);
            $search = $request->get('search');
            $category = $request->get('category');
            $isActive = $request->get('is_active');
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');

            $query = Interest::withCount('users');

            // Apply filters
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($category) {
                $query->where('category', $category);
            }

            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }

            // Apply sorting
            $allowedSortFields = ['name', 'category', 'user_count', 'popularity_score', 'created_at'];
            if (in_array($sortBy, $allowedSortFields)) {
                if ($sortBy === 'user_count') {
                    $query->orderBy('users_count', $sortOrder);
                } else {
                    $query->orderBy($sortBy, $sortOrder);
                }
            }

            $interests = $query->paginate($perPage);

            // Format response
            $interests->getCollection()->transform(function ($interest) {
                return [
                    'id' => $interest->id,
                    'name' => $interest->name,
                    'slug' => $interest->slug,
                    'description' => $interest->description,
                    'category' => $interest->category,
                    'icon' => $interest->icon,
                    'user_count' => $interest->users_count,
                    'popularity_score' => $interest->popularity_score,
                    'is_trending' => $interest->is_trending,
                    'is_active' => $interest->is_active,
                    'matching_weight' => $interest->matching_weight,
                    'created_at' => $interest->created_at,
                    'updated_at' => $interest->updated_at,
                ];
            });

            // Get summary statistics
            $summary = [
                'total_interests' => Interest::count(),
                'active_interests' => Interest::where('is_active', true)->count(),
                'trending_interests' => Interest::where('is_trending', true)->count(),
                'by_category' => Interest::selectRaw('category, count(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray(),
                'top_interests' => Interest::withCount('users')
                    ->orderBy('users_count', 'desc')
                    ->limit(5)
                    ->get(['id', 'name', 'users_count'])
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $interests,
                'summary' => $summary,
                'filters' => [
                    'categories' => Interest::distinct()->pluck('category')->filter()->values(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin interests list error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get interests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new interest
     */
    public function createInterest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:interests,name',
            'description' => 'sometimes|string|max:500',
            'category' => 'required|string|max:50',
            'icon' => 'sometimes|string|max:50',
            'matching_weight' => 'sometimes|numeric|min:0|max:10',
            'is_active' => 'sometimes|boolean',
            'localization' => 'sometimes|array',
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
                'slug' => Str::slug($request->name),
                'description' => $request->get('description'),
                'category' => $request->category,
                'icon' => $request->get('icon'),
                'matching_weight' => $request->get('matching_weight', 1.0),
                'is_active' => $request->get('is_active', true),
                'localization' => $request->get('localization') ? json_encode($request->localization) : null,
                'user_count' => 0,
                'popularity_score' => 0,
                'is_trending' => false,
            ]);

            Log::info('Interest created by admin', [
                'interest_id' => $interest->id,
                'name' => $interest->name,
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interest created successfully',
                'data' => $interest
            ]);

        } catch (\Exception $e) {
            Log::error('Admin create interest error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an interest
     */
    public function updateInterest(Request $request, Interest $interest): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100|unique:interests,name,' . $interest->id,
            'description' => 'sometimes|string|max:500',
            'category' => 'sometimes|string|max:50',
            'icon' => 'sometimes|string|max:50',
            'matching_weight' => 'sometimes|numeric|min:0|max:10',
            'is_active' => 'sometimes|boolean',
            'is_trending' => 'sometimes|boolean',
            'localization' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
                $updateData['slug'] = Str::slug($request->name);
            }

            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }

            if ($request->has('category')) {
                $updateData['category'] = $request->category;
            }

            if ($request->has('icon')) {
                $updateData['icon'] = $request->icon;
            }

            if ($request->has('matching_weight')) {
                $updateData['matching_weight'] = $request->matching_weight;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }

            if ($request->has('is_trending')) {
                $updateData['is_trending'] = $request->is_trending;
            }

            if ($request->has('localization')) {
                $updateData['localization'] = json_encode($request->localization);
            }

            $interest->update($updateData);

            Log::info('Interest updated by admin', [
                'interest_id' => $interest->id,
                'updated_fields' => array_keys($updateData),
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interest updated successfully',
                'data' => $interest->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Admin update interest error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an interest
     */
    public function deleteInterest(Request $request, Interest $interest): JsonResponse
    {
        try {
            // Check if interest is being used by users
            $userCount = $interest->users()->count();
            
            if ($userCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete interest '{$interest->name}' as it is being used by {$userCount} users",
                    'data' => ['user_count' => $userCount]
                ], 400);
            }

            $interestName = $interest->name;
            $interest->delete();

            Log::warning('Interest deleted by admin', [
                'interest_name' => $interestName,
                'deleted_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Interest '{$interestName}' deleted successfully"
            ]);

        } catch (\Exception $e) {
            Log::error('Admin delete interest error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update interests
     */
    public function bulkUpdateInterests(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'interest_ids' => 'required|array|min:1',
            'interest_ids.*' => 'required|integer|exists:interests,id',
            'action' => 'required|in:activate,deactivate,set_trending,unset_trending,update_category,update_weight',
            'value' => 'sometimes|string|max:100', // For category updates
            'weight' => 'sometimes|numeric|min:0|max:10', // For weight updates
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
            $action = $request->action;

            $updateData = [];

            switch ($action) {
                case 'activate':
                    $updateData['is_active'] = true;
                    break;
                case 'deactivate':
                    $updateData['is_active'] = false;
                    break;
                case 'set_trending':
                    $updateData['is_trending'] = true;
                    break;
                case 'unset_trending':
                    $updateData['is_trending'] = false;
                    break;
                case 'update_category':
                    if (!$request->has('value')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Category value is required for this action'
                        ], 400);
                    }
                    $updateData['category'] = $request->value;
                    break;
                case 'update_weight':
                    if (!$request->has('weight')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Weight value is required for this action'
                        ], 400);
                    }
                    $updateData['matching_weight'] = $request->weight;
                    break;
            }

            $updatedCount = Interest::whereIn('id', $interestIds)->update($updateData);

            Log::info('Bulk interests updated', [
                'interest_ids' => $interestIds,
                'action' => $action,
                'updated_count' => $updatedCount,
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} interests updated successfully",
                'data' => [
                    'updated_count' => $updatedCount,
                    'action' => $action,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin bulk update interests error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update interests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh interest statistics (user counts, popularity scores)
     */
    public function refreshInterestStats(Request $request): JsonResponse
    {
        try {
            DB::transaction(function () {
                // Update user counts for all interests
                $interests = Interest::all();
                
                foreach ($interests as $interest) {
                    $userCount = $interest->users()->count();
                    
                    // Calculate popularity score based on user count and recent activity
                    $recentUsers = $interest->users()
                        ->wherePivot('created_at', '>=', now()->subMonth())
                        ->count();
                    
                    $popularityScore = ($userCount * 0.7) + ($recentUsers * 0.3);
                    
                    $interest->update([
                        'user_count' => $userCount,
                        'popularity_score' => round($popularityScore, 2),
                    ]);
                }

                // Update trending interests (top 10 by popularity score)
                Interest::query()->update(['is_trending' => false]);
                
                Interest::orderBy('popularity_score', 'desc')
                    ->limit(10)
                    ->update(['is_trending' => true]);
            });

            Log::info('Interest statistics refreshed', [
                'refreshed_by' => $request->user()->id,
                'refreshed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interest statistics refreshed successfully',
                'data' => [
                    'refreshed_at' => now(),
                    'trending_count' => Interest::where('is_trending', true)->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin refresh interest stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh interest statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import interests from CSV
     */
    public function importInterests(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'overwrite_existing' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('csv_file');
            $overwriteExisting = $request->get('overwrite_existing', false);
            
            $csvData = array_map('str_getcsv', file($file->getPathname()));
            $header = array_shift($csvData); // Remove header row

            $imported = 0;
            $skipped = 0;
            $errors = [];

            DB::transaction(function () use ($csvData, $header, $overwriteExisting, &$imported, &$skipped, &$errors) {
                foreach ($csvData as $index => $row) {
                    $rowData = array_combine($header, $row);
                    
                    // Validate required fields
                    if (empty($rowData['name']) || empty($rowData['category'])) {
                        $errors[] = "Row " . ($index + 2) . ": Missing required fields (name, category)";
                        continue;
                    }

                    // Check if interest already exists
                    $existingInterest = Interest::where('name', $rowData['name'])->first();
                    
                    if ($existingInterest && !$overwriteExisting) {
                        $skipped++;
                        continue;
                    }

                    $interestData = [
                        'name' => $rowData['name'],
                        'slug' => Str::slug($rowData['name']),
                        'description' => $rowData['description'] ?? null,
                        'category' => $rowData['category'],
                        'icon' => $rowData['icon'] ?? null,
                        'matching_weight' => isset($rowData['matching_weight']) ? (float)$rowData['matching_weight'] : 1.0,
                        'is_active' => isset($rowData['is_active']) ? filter_var($rowData['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
                        'user_count' => 0,
                        'popularity_score' => 0,
                        'is_trending' => false,
                    ];

                    if ($existingInterest && $overwriteExisting) {
                        $existingInterest->update($interestData);
                    } else {
                        Interest::create($interestData);
                    }

                    $imported++;
                }
            });

            Log::info('Interests imported from CSV', [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors_count' => count($errors),
                'imported_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$imported} interests",
                'data' => [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin import interests error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to import interests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export interests to CSV
     */
    public function exportInterests(Request $request): JsonResponse
    {
        try {
            $interests = Interest::withCount('users')
                ->orderBy('category')
                ->orderBy('name')
                ->get();

            $csvData = [];
            $csvData[] = [
                'id', 'name', 'slug', 'description', 'category', 'icon',
                'user_count', 'popularity_score', 'matching_weight', 
                'is_trending', 'is_active', 'created_at', 'updated_at'
            ];

            foreach ($interests as $interest) {
                $csvData[] = [
                    $interest->id,
                    $interest->name,
                    $interest->slug,
                    $interest->description,
                    $interest->category,
                    $interest->icon,
                    $interest->users_count,
                    $interest->popularity_score,
                    $interest->matching_weight,
                    $interest->is_trending ? 'true' : 'false',
                    $interest->is_active ? 'true' : 'false',
                    $interest->created_at,
                    $interest->updated_at,
                ];
            }

            $fileName = 'interests_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $filePath = storage_path('app/temp/' . $fileName);

            // Ensure temp directory exists
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            $file = fopen($filePath, 'w');
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);

            Log::info('Interests exported to CSV', [
                'file_name' => $fileName,
                'interests_count' => count($interests),
                'exported_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interests exported successfully',
                'data' => [
                    'file_name' => $fileName,
                    'file_path' => 'temp/' . $fileName,
                    'interests_count' => count($interests),
                    'download_url' => url('api/admin/content/download/' . $fileName),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin export interests error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export interests',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 