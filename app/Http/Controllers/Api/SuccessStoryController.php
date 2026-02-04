<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuccessStory;
use App\Models\SuccessStoryPhoto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class SuccessStoryController extends Controller
{
    /**
     * Get public list of approved success stories.
     * Public endpoint - no auth required.
     */
    public function publicList(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->get('per_page', 12), 50);

            $stories = SuccessStory::with(['submittedBy:id,first_name', 'coupleUser2:id,first_name', 'photos' => fn($q) => $q->where('is_cover_photo', true)->orWhere('sort_order', 0)->limit(1)])
                ->approved()
                ->orderBy('featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $stories->getCollection()->map(fn($story) => $this->formatStoryCard($story)),
                'meta' => [
                    'current_page' => $stories->currentPage(),
                    'last_page' => $stories->lastPage(),
                    'per_page' => $stories->perPage(),
                    'total' => $stories->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get success stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured success stories for homepage.
     * Public endpoint - no auth required.
     */
    public function featured(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 6), 12);

            $stories = SuccessStory::with(['submittedBy:id,first_name', 'coupleUser2:id,first_name', 'photos' => fn($q) => $q->where('is_cover_photo', true)->orWhere('sort_order', 0)->limit(1)])
                ->approved()
                ->featured()
                ->orderBy('featured_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stories->map(fn($story) => $this->formatStoryCard($story))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get featured stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single success story by ID.
     * Public endpoint for approved stories, auth required for own drafts.
     */
    public function show(Request $request, SuccessStory $successStory): JsonResponse
    {
        try {
            $user = $request->user();

            // Check access permissions
            if ($successStory->status !== SuccessStory::STATUS_APPROVED) {
                // Only owner can view non-approved stories
                if (!$user || $successStory->couple_user1_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Story not found'
                    ], 404);
                }
            }

            $successStory->load(['submittedBy:id,first_name,last_name', 'coupleUser2:id,first_name,last_name', 'photos']);

            // Increment view count for public views
            if ($successStory->status === SuccessStory::STATUS_APPROVED) {
                $successStory->incrementViewCount();
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatStoryDetail($successStory)
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
     * Get user's own success stories.
     * Authenticated endpoint.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $stories = SuccessStory::with(['coupleUser2:id,first_name', 'photos' => fn($q) => $q->limit(1)])
                ->byUser($user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stories->map(fn($story) => $this->formatStoryForOwner($story))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get your stories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new success story.
     * Authenticated endpoint.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'description' => 'required|string|min:100',
            'how_they_met' => 'nullable|string|max:2000',
            'story_location' => 'nullable|string|max:255',
            'marriage_date' => 'nullable|date|before_or_equal:today',
            'couple_user2_id' => 'nullable|exists:users,id',
            'couple_info' => 'nullable|array',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:5120', // 5MB max per photo
            'submit_for_approval' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Check if user already has a pending or approved story
            $existingStory = SuccessStory::byUser($user->id)
                ->whereIn('status', [SuccessStory::STATUS_PENDING, SuccessStory::STATUS_APPROVED])
                ->first();

            if ($existingStory) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a success story ' . ($existingStory->status === 'pending' ? 'pending approval' : 'published') . '.'
                ], 400);
            }

            DB::beginTransaction();

            $status = $request->get('submit_for_approval') ? SuccessStory::STATUS_PENDING : SuccessStory::STATUS_DRAFT;

            $story = SuccessStory::create([
                'couple_user1_id' => $user->id,
                'couple_user2_id' => $request->couple_user2_id,
                'title' => $request->title,
                'description' => $request->description,
                'how_they_met' => $request->how_they_met,
                'story_location' => $request->story_location,
                'marriage_date' => $request->marriage_date,
                'couple_info' => $request->couple_info,
                'status' => $status,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Handle photo uploads
            if ($request->hasFile('photos')) {
                $this->handlePhotoUploads($story, $request->file('photos'));
            }

            DB::commit();

            $story->load(['coupleUser2:id,first_name', 'photos']);

            return response()->json([
                'success' => true,
                'message' => $status === SuccessStory::STATUS_PENDING
                    ? 'Your success story has been submitted for review!'
                    : 'Your success story has been saved as draft.',
                'data' => $this->formatStoryForOwner($story)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a draft or rejected success story.
     * Authenticated endpoint.
     */
    public function update(Request $request, SuccessStory $successStory): JsonResponse
    {
        // Check ownership
        if ($successStory->couple_user1_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if editable
        if (!$successStory->can_edit) {
            return response()->json([
                'success' => false,
                'message' => 'This story cannot be edited. Only draft or rejected stories can be modified.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string|min:100',
            'how_they_met' => 'nullable|string|max:2000',
            'story_location' => 'nullable|string|max:255',
            'marriage_date' => 'nullable|date|before_or_equal:today',
            'couple_user2_id' => 'nullable|exists:users,id',
            'couple_info' => 'nullable|array',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'delete_photos' => 'nullable|array',
            'delete_photos.*' => 'integer|exists:success_story_photos,id',
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

            $successStory->update($request->only([
                'title',
                'description',
                'how_they_met',
                'story_location',
                'marriage_date',
                'couple_user2_id',
                'couple_info',
            ]));

            // Delete specified photos
            if ($request->has('delete_photos')) {
                $successStory->photos()
                    ->whereIn('id', $request->delete_photos)
                    ->get()
                    ->each(fn($photo) => $photo->delete());
            }

            // Handle new photo uploads
            if ($request->hasFile('photos')) {
                $this->handlePhotoUploads($successStory, $request->file('photos'));
            }

            DB::commit();

            $successStory->load(['coupleUser2:id,first_name', 'photos']);

            return response()->json([
                'success' => true,
                'message' => 'Story updated successfully',
                'data' => $this->formatStoryForOwner($successStory)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a draft story for approval.
     * Authenticated endpoint.
     */
    public function submitForApproval(Request $request, SuccessStory $successStory): JsonResponse
    {
        // Check ownership
        if ($successStory->couple_user1_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($successStory->status !== SuccessStory::STATUS_DRAFT && $successStory->status !== SuccessStory::STATUS_REJECTED) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft or rejected stories can be submitted for approval'
            ], 400);
        }

        // Validate completeness
        if (strlen($successStory->description) < 100) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a more detailed description (at least 100 characters)'
            ], 400);
        }

        $successStory->update([
            'status' => SuccessStory::STATUS_PENDING,
            'rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your story has been submitted for review. We will notify you once approved!'
        ]);
    }

    /**
     * Delete a draft success story.
     * Authenticated endpoint.
     */
    public function destroy(Request $request, SuccessStory $successStory): JsonResponse
    {
        // Check ownership
        if ($successStory->couple_user1_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Only drafts can be deleted by users
        if ($successStory->status !== SuccessStory::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft stories can be deleted. Contact support to remove approved stories.'
            ], 400);
        }

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
     * Search for users (to select partner).
     * Authenticated endpoint.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a search query',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->query;
            $currentUserId = $request->user()->id;

            $users = User::where('id', '!=', $currentUserId)
                ->where('status', 'active')
                ->where(function ($q) use ($query) {
                    $q->where('first_name', 'like', "%{$query}%")
                      ->orWhere('last_name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->select('id', 'first_name', 'last_name')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                ])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle photo uploads for a story.
     */
    private function handlePhotoUploads(SuccessStory $story, array $photos): void
    {
        $existingCount = $story->photos()->count();
        $isFirst = $existingCount === 0;

        foreach ($photos as $index => $photo) {
            $filename = Str::random(40) . '.' . $photo->getClientOriginalExtension();
            $paths = $this->processAndStoreImage($photo, $filename);

            $story->photos()->create([
                'file_path' => $paths['original'],
                'thumbnail_path' => $paths['thumbnail'],
                'medium_path' => $paths['medium'],
                'original_filename' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getMimeType(),
                'file_size' => $photo->getSize(),
                'width' => $paths['width'],
                'height' => $paths['height'],
                'sort_order' => $existingCount + $index,
                'is_cover_photo' => $isFirst && $index === 0,
            ]);
        }

        // Set cover photo path if this was the first upload
        if ($isFirst && count($photos) > 0) {
            $coverPhoto = $story->photos()->where('is_cover_photo', true)->first();
            if ($coverPhoto) {
                $story->update(['cover_photo_path' => $coverPhoto->file_path]);
            }
        }
    }

    /**
     * Process and store image with different sizes.
     */
    private function processAndStoreImage($photo, string $filename): array
    {
        $paths = [];
        $originalImage = Image::make($photo);

        // Store original dimensions
        $paths['width'] = $originalImage->width();
        $paths['height'] = $originalImage->height();

        // Resize if too large (max 2000px)
        $originalImage->resize(2000, 2000, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        // Original
        $originalPath = 'success-stories/original/' . $filename;
        Storage::disk('public')->put($originalPath, $originalImage->encode());
        $paths['original'] = $originalPath;

        // Thumbnail (300x300)
        $thumbnail = clone $originalImage;
        $thumbnail->fit(300, 300);
        $thumbnailPath = 'success-stories/thumbnails/' . $filename;
        Storage::disk('public')->put($thumbnailPath, $thumbnail->encode());
        $paths['thumbnail'] = $thumbnailPath;

        // Medium (800x600)
        $medium = clone $originalImage;
        $medium->resize(800, 600, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $mediumPath = 'success-stories/medium/' . $filename;
        Storage::disk('public')->put($mediumPath, $medium->encode());
        $paths['medium'] = $mediumPath;

        return $paths;
    }

    /**
     * Format story for card display (public list).
     */
    private function formatStoryCard(SuccessStory $story): array
    {
        $coverPhoto = $story->photos->first();

        return [
            'id' => $story->id,
            'title' => $story->title,
            'description' => Str::limit($story->description, 200),
            'story_location' => $story->story_location,
            'marriage_date' => $story->marriage_date?->format('F Y'),
            'cover_photo' => $coverPhoto?->thumbnail_url ?? $coverPhoto?->file_url,
            'featured' => $story->featured,
            'view_count' => $story->view_count,
            'couple' => [
                'user1_name' => $story->submittedBy?->first_name,
                'user2_name' => $story->coupleUser2?->first_name,
            ],
            'created_at' => $story->created_at->diffForHumans(),
        ];
    }

    /**
     * Format story for detail view.
     */
    private function formatStoryDetail(SuccessStory $story): array
    {
        return [
            'id' => $story->id,
            'title' => $story->title,
            'description' => $story->description,
            'how_they_met' => $story->how_they_met,
            'story_location' => $story->story_location,
            'marriage_date' => $story->marriage_date?->format('F j, Y'),
            'couple_info' => $story->couple_info,
            'cover_photo' => $story->cover_photo_url,
            'photos' => $story->photos->map(fn($photo) => [
                'id' => $photo->id,
                'url' => $photo->file_url,
                'thumbnail' => $photo->thumbnail_url,
                'medium' => $photo->medium_url,
                'caption' => $photo->caption,
                'is_cover' => $photo->is_cover_photo,
            ]),
            'featured' => $story->featured,
            'view_count' => $story->view_count,
            'share_count' => $story->share_count,
            'status' => $story->status,
            'couple' => [
                'user1_name' => $story->submittedBy?->first_name,
                'user2_name' => $story->coupleUser2?->first_name,
            ],
            'created_at' => $story->created_at->format('F j, Y'),
            'approved_at' => $story->approved_at?->format('F j, Y'),
        ];
    }

    /**
     * Format story for owner view (includes status, rejection reason, etc.).
     */
    private function formatStoryForOwner(SuccessStory $story): array
    {
        $data = $this->formatStoryDetail($story);
        $data['rejection_reason'] = $story->rejection_reason;
        $data['can_edit'] = $story->can_edit;
        $data['is_draft'] = $story->is_draft;
        $data['is_pending'] = $story->is_pending;
        $data['is_approved'] = $story->is_approved;
        $data['is_rejected'] = $story->is_rejected;

        return $data;
    }
}
