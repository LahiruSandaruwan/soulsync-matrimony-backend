<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserPhoto;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PhotoController extends Controller
{
    /**
     * Get all pending photos for moderation
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'asc');

            $query = UserPhoto::with(['user:id,first_name,last_name,email'])
                ->where('status', 'pending');

            // Apply sorting
            $allowedSortFields = ['created_at', 'file_size', 'user_id'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $photos = $query->paginate($perPage);

            // Format response
            $photos->getCollection()->transform(function ($photo) {
                return [
                    'id' => $photo->id,
                    'user' => [
                        'id' => $photo->user->id,
                        'name' => $photo->user->first_name . ' ' . $photo->user->last_name,
                        'email' => $photo->user->email,
                    ],
                    'file_path' => $photo->file_path,
                    'thumbnail_path' => $photo->thumbnail_path,
                    'mime_type' => $photo->mime_type,
                    'file_size' => $photo->file_size,
                    'is_profile_picture' => $photo->is_profile_picture,
                    'is_private' => $photo->is_private,
                    'status' => $photo->status,
                    'ai_analysis' => $photo->ai_analysis ? json_decode($photo->ai_analysis, true) : null,
                    'uploaded_at' => $photo->created_at,
                    'days_pending' => $photo->created_at->diffInDays(now()),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $photos,
                'summary' => [
                    'total_pending' => UserPhoto::where('status', 'pending')->count(),
                    'pending_profile_pictures' => UserPhoto::where('status', 'pending')
                        ->where('is_profile_picture', true)->count(),
                    'pending_private_photos' => UserPhoto::where('status', 'pending')
                        ->where('is_private', true)->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin pending photos error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pending photos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a photo
     */
    public function approve(Request $request, UserPhoto $photo): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($photo->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo is not pending approval'
                ], 400);
            }

            DB::transaction(function () use ($photo, $request) {
                $photo->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                    'admin_notes' => $request->get('admin_notes'),
                ]);

                // Update user profile completion if this was pending
                $user = $photo->user;
                if ($user->profile_status === 'pending_approval') {
                    $pendingPhotos = $user->photos()->where('status', 'pending')->count();
                    if ($pendingPhotos === 0) {
                        $user->update(['profile_status' => 'approved']);
                    }
                }

                // Send notification to user
                Notification::create([
                    'user_id' => $photo->user_id,
                    'type' => 'photo_approved',
                    'title' => 'Photo Approved',
                    'message' => 'Your photo has been approved and is now visible on your profile.',
                    'data' => json_encode(['photo_id' => $photo->id]),
                ]);

                Log::info('Photo approved by admin', [
                    'photo_id' => $photo->id,
                    'user_id' => $photo->user_id,
                    'approved_by' => $request->user()->id,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Photo approved successfully',
                'data' => [
                    'photo_id' => $photo->id,
                    'status' => 'approved',
                    'approved_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin approve photo error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a photo
     */
    public function reject(Request $request, UserPhoto $photo): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'admin_notes' => 'sometimes|string|max:500',
            'delete_file' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($photo->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo is not pending approval'
                ], 400);
            }

            DB::transaction(function () use ($photo, $request) {
                $deleteFile = $request->get('delete_file', true);

                // Store file paths before updating record
                $filePath = $photo->file_path;
                $thumbnailPath = $photo->thumbnail_path;

                $photo->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejected_by' => $request->user()->id,
                    'rejection_reason' => $request->reason,
                    'admin_notes' => $request->get('admin_notes'),
                ]);

                // Delete physical files if requested
                if ($deleteFile) {
                    if ($filePath && Storage::exists($filePath)) {
                        Storage::delete($filePath);
                    }
                    if ($thumbnailPath && Storage::exists($thumbnailPath)) {
                        Storage::delete($thumbnailPath);
                    }
                    
                    // Mark as deleted
                    $photo->update([
                        'file_path' => null,
                        'thumbnail_path' => null,
                    ]);
                }

                // If this was a profile picture, user needs to upload a new one
                if ($photo->is_profile_picture) {
                    $user = $photo->user;
                    $user->update(['profile_status' => 'incomplete']);
                }

                // Send notification to user
                Notification::create([
                    'user_id' => $photo->user_id,
                    'type' => 'photo_rejected',
                    'title' => 'Photo Rejected',
                    'message' => "Your photo was rejected. Reason: {$request->reason}",
                    'data' => json_encode([
                        'photo_id' => $photo->id,
                        'reason' => $request->reason,
                    ]),
                ]);

                Log::warning('Photo rejected by admin', [
                    'photo_id' => $photo->id,
                    'user_id' => $photo->user_id,
                    'rejected_by' => $request->user()->id,
                    'reason' => $request->reason,
                    'file_deleted' => $deleteFile,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Photo rejected successfully',
                'data' => [
                    'photo_id' => $photo->id,
                    'status' => 'rejected',
                    'reason' => $request->reason,
                    'rejected_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin reject photo error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve photos
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo_ids' => 'required|array|min:1',
            'photo_ids.*' => 'required|integer|exists:user_photos,id',
            'admin_notes' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $photoIds = $request->photo_ids;
            $adminNotes = $request->get('admin_notes');

            DB::transaction(function () use ($photoIds, $adminNotes, $request) {
                $photos = UserPhoto::whereIn('id', $photoIds)
                    ->where('status', 'pending')
                    ->with('user')
                    ->get();

                foreach ($photos as $photo) {
                    $photo->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => $request->user()->id,
                        'admin_notes' => $adminNotes,
                    ]);

                    // Send notification to user
                    Notification::create([
                        'user_id' => $photo->user_id,
                        'type' => 'photo_approved',
                        'title' => 'Photo Approved',
                        'message' => 'Your photo has been approved and is now visible on your profile.',
                        'data' => json_encode(['photo_id' => $photo->id]),
                    ]);
                }

                Log::info('Bulk photos approved by admin', [
                    'photo_ids' => $photoIds,
                    'count' => count($photos),
                    'approved_by' => $request->user()->id,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => count($photoIds) . ' photos approved successfully',
                'data' => [
                    'approved_count' => count($photoIds),
                    'approved_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin bulk approve photos error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk approve photos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reject photos
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo_ids' => 'required|array|min:1',
            'photo_ids.*' => 'required|integer|exists:user_photos,id',
            'reason' => 'required|string|max:500',
            'admin_notes' => 'sometimes|string|max:500',
            'delete_files' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $photoIds = $request->photo_ids;
            $reason = $request->reason;
            $adminNotes = $request->get('admin_notes');
            $deleteFiles = $request->get('delete_files', true);

            DB::transaction(function () use ($photoIds, $reason, $adminNotes, $deleteFiles, $request) {
                $photos = UserPhoto::whereIn('id', $photoIds)
                    ->where('status', 'pending')
                    ->with('user')
                    ->get();

                foreach ($photos as $photo) {
                    // Store file paths before updating
                    $filePath = $photo->file_path;
                    $thumbnailPath = $photo->thumbnail_path;

                    $photo->update([
                        'status' => 'rejected',
                        'rejected_at' => now(),
                        'rejected_by' => $request->user()->id,
                        'rejection_reason' => $reason,
                        'admin_notes' => $adminNotes,
                    ]);

                    // Delete physical files if requested
                    if ($deleteFiles) {
                        if ($filePath && Storage::exists($filePath)) {
                            Storage::delete($filePath);
                        }
                        if ($thumbnailPath && Storage::exists($thumbnailPath)) {
                            Storage::delete($thumbnailPath);
                        }
                        
                        $photo->update([
                            'file_path' => null,
                            'thumbnail_path' => null,
                        ]);
                    }

                    // Send notification to user
                    Notification::create([
                        'user_id' => $photo->user_id,
                        'type' => 'photo_rejected',
                        'title' => 'Photo Rejected',
                        'message' => "Your photo was rejected. Reason: {$reason}",
                        'data' => json_encode([
                            'photo_id' => $photo->id,
                            'reason' => $reason,
                        ]),
                    ]);
                }

                Log::warning('Bulk photos rejected by admin', [
                    'photo_ids' => $photoIds,
                    'count' => count($photos),
                    'rejected_by' => $request->user()->id,
                    'reason' => $reason,
                    'files_deleted' => $deleteFiles,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => count($photoIds) . ' photos rejected successfully',
                'data' => [
                    'rejected_count' => count($photoIds),
                    'reason' => $reason,
                    'rejected_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin bulk reject photos error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk reject photos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 