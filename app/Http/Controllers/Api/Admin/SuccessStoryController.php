<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuccessStory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SuccessStoryController extends Controller
{
    /**
     * Get all success stories with filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SuccessStory::with([
                'submittedBy:id,first_name,last_name,email',
                'coupleUser2:id,first_name,last_name',
                'approvedBy:id,first_name,last_name',
                'photos' => fn($q) => $q->where('is_cover_photo', true)->limit(1)
            ]);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by featured
            if ($request->has('featured')) {
                $query->where('featured', $request->boolean('featured'));
            }

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhereHas('submittedBy', function ($q) use ($search) {
                          $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = min($request->get('per_page', 20), 100);
            $stories = $query->paginate($perPage);

            // Get stats
            $stats = $this->getStats();

            return response()->json([
                'success' => true,
                'data' => $stories->getCollection()->map(fn($story) => $this->formatStoryForAdmin($story)),
                'meta' => [
                    'current_page' => $stories->currentPage(),
                    'last_page' => $stories->lastPage(),
                    'per_page' => $stories->perPage(),
                    'total' => $stories->total(),
                ],
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending stories (approval queue).
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->get('per_page', 20), 100);

            $stories = SuccessStory::with([
                'submittedBy:id,first_name,last_name,email',
                'coupleUser2:id,first_name,last_name',
                'photos'
            ])
                ->pending()
                ->orderBy('created_at', 'asc') // Oldest first
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $stories->getCollection()->map(fn($story) => $this->formatStoryForAdmin($story, true)),
                'meta' => [
                    'current_page' => $stories->currentPage(),
                    'last_page' => $stories->lastPage(),
                    'per_page' => $stories->perPage(),
                    'total' => $stories->total(),
                ],
                'pending_count' => SuccessStory::pending()->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pending stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single story details.
     */
    public function show(SuccessStory $successStory): JsonResponse
    {
        try {
            $successStory->load([
                'submittedBy:id,first_name,last_name,email,created_at',
                'coupleUser2:id,first_name,last_name,email',
                'approvedBy:id,first_name,last_name',
                'photos'
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatStoryForAdmin($successStory, true)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a success story.
     */
    public function approve(Request $request, SuccessStory $successStory): JsonResponse
    {
        if ($successStory->status !== SuccessStory::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending stories can be approved'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'feature' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $successStory->approve($request->user()->id, $request->notes);

            // Optionally feature the story
            if ($request->get('feature', false)) {
                $successStory->setFeatured();
            }

            DB::commit();

            // TODO: Send notification to user about approval

            return response()->json([
                'success' => true,
                'message' => 'Story approved successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a success story.
     */
    public function reject(Request $request, SuccessStory $successStory): JsonResponse
    {
        if ($successStory->status !== SuccessStory::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending stories can be rejected'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $successStory->reject($request->reason, $request->notes);

            // TODO: Send notification to user about rejection

            return response()->json([
                'success' => true,
                'message' => 'Story rejected'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve stories.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'story_ids' => 'required|array|min:1',
            'story_ids.*' => 'integer|exists:success_stories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $adminId = $request->user()->id;
            $now = now();

            $count = SuccessStory::whereIn('id', $request->story_ids)
                ->where('status', SuccessStory::STATUS_PENDING)
                ->update([
                    'status' => SuccessStory::STATUS_APPROVED,
                    'approved_by' => $adminId,
                    'approved_at' => $now,
                    'rejection_reason' => null,
                ]);

            return response()->json([
                'success' => true,
                'message' => "{$count} stories approved successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk approve stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reject stories.
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'story_ids' => 'required|array|min:1',
            'story_ids.*' => 'integer|exists:success_stories,id',
            'reason' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $count = SuccessStory::whereIn('id', $request->story_ids)
                ->where('status', SuccessStory::STATUS_PENDING)
                ->update([
                    'status' => SuccessStory::STATUS_REJECTED,
                    'rejection_reason' => $request->reason,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

            return response()->json([
                'success' => true,
                'message' => "{$count} stories rejected"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk reject stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set a story as featured.
     */
    public function setFeatured(SuccessStory $successStory): JsonResponse
    {
        if ($successStory->status !== SuccessStory::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Only approved stories can be featured'
            ], 400);
        }

        if ($successStory->featured) {
            return response()->json([
                'success' => false,
                'message' => 'Story is already featured'
            ], 400);
        }

        try {
            $successStory->setFeatured();

            return response()->json([
                'success' => true,
                'message' => 'Story is now featured'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to feature story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove featured status from a story.
     */
    public function removeFeatured(SuccessStory $successStory): JsonResponse
    {
        if (!$successStory->featured) {
            return response()->json([
                'success' => false,
                'message' => 'Story is not featured'
            ], 400);
        }

        try {
            $successStory->removeFeatured();

            return response()->json([
                'success' => true,
                'message' => 'Story is no longer featured'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unfeature story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a story (admin only).
     */
    public function delete(SuccessStory $successStory): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete all photos
            $successStory->photos->each(fn($photo) => $photo->delete());

            // Delete the story
            $successStory->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Story deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for admin dashboard.
     */
    private function getStats(): array
    {
        return [
            'total' => SuccessStory::count(),
            'pending' => SuccessStory::pending()->count(),
            'approved' => SuccessStory::approved()->count(),
            'rejected' => SuccessStory::where('status', SuccessStory::STATUS_REJECTED)->count(),
            'featured' => SuccessStory::featured()->count(),
            'drafts' => SuccessStory::where('status', SuccessStory::STATUS_DRAFT)->count(),
            'total_views' => SuccessStory::sum('view_count'),
        ];
    }

    /**
     * Format story for admin view.
     */
    private function formatStoryForAdmin(SuccessStory $story, bool $includeFullContent = false): array
    {
        $coverPhoto = $story->photos->first();

        $data = [
            'id' => $story->id,
            'title' => $story->title,
            'description' => $includeFullContent ? $story->description : \Str::limit($story->description, 150),
            'story_location' => $story->story_location,
            'marriage_date' => $story->marriage_date?->format('F Y'),
            'cover_photo' => $coverPhoto?->thumbnail_url ?? $coverPhoto?->file_url,
            'status' => $story->status,
            'featured' => $story->featured,
            'view_count' => $story->view_count,
            'share_count' => $story->share_count,
            'submitted_by' => $story->submittedBy ? [
                'id' => $story->submittedBy->id,
                'name' => $story->submittedBy->first_name . ' ' . $story->submittedBy->last_name,
                'email' => $story->submittedBy->email,
            ] : null,
            'couple_user2' => $story->coupleUser2 ? [
                'id' => $story->coupleUser2->id,
                'name' => $story->coupleUser2->first_name . ' ' . $story->coupleUser2->last_name,
            ] : null,
            'approved_by' => $story->approvedBy ? [
                'id' => $story->approvedBy->id,
                'name' => $story->approvedBy->first_name . ' ' . $story->approvedBy->last_name,
            ] : null,
            'approved_at' => $story->approved_at?->format('M j, Y g:i A'),
            'rejection_reason' => $story->rejection_reason,
            'admin_notes' => $story->admin_notes,
            'created_at' => $story->created_at->format('M j, Y g:i A'),
            'updated_at' => $story->updated_at->format('M j, Y g:i A'),
        ];

        if ($includeFullContent) {
            $data['how_they_met'] = $story->how_they_met;
            $data['couple_info'] = $story->couple_info;
            $data['photos'] = $story->photos->map(fn($photo) => [
                'id' => $photo->id,
                'url' => $photo->file_url,
                'thumbnail' => $photo->thumbnail_url,
                'medium' => $photo->medium_url,
                'caption' => $photo->caption,
                'is_cover' => $photo->is_cover_photo,
            ]);
            $data['ip_address'] = $story->ip_address;
            $data['user_agent'] = $story->user_agent;
        }

        return $data;
    }
}
