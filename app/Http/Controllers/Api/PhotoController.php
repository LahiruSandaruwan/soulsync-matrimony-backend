<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    protected $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Get user's photos
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $photos = $user->photos()
            ->orderBy('is_profile_picture', 'desc')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'original_filename' => $photo->original_filename,
                    'file_path' => Storage::url($photo->file_path),
                    'thumbnail_path' => $photo->thumbnail_path ? Storage::url($photo->thumbnail_path) : null,
                    'medium_path' => $photo->medium_path ? Storage::url($photo->medium_path) : null,
                    'is_profile_picture' => $photo->is_profile_picture,
                    'is_private' => $photo->is_private,
                    'status' => $photo->status,
                    'sort_order' => $photo->sort_order,
                    'created_at' => $photo->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'photos' => $photos,
                'total_photos' => $photos->count(),
                'max_photos' => 10, // You can make this configurable
                'can_upload_more' => $photos->count() < 10,
            ]
        ]);
    }

    /**
     * Upload a new photo
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has reached photo limit
        $currentPhotoCount = $user->photos()->count();
        $maxPhotos = 10; // Configurable limit

        if ($currentPhotoCount >= $maxPhotos) {
            return response()->json([
                'success' => false,
                'message' => "Maximum of {$maxPhotos} photos allowed"
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,jpg,png,gif|max:5120', // 5MB max
            'is_profile_picture' => 'sometimes|boolean',
            'is_private' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $photo = $request->file('photo');
            $originalFilename = $photo->getClientOriginalName();
            $extension = $photo->getClientOriginalExtension();
            $mimeType = $photo->getMimeType();
            $fileSize = $photo->getSize();

            // Generate unique filename
            $filename = Str::uuid() . '.' . $extension;
            $userDirectory = 'photos/' . $user->id;

            // Create different sizes
            $fullPath = $userDirectory . '/full/' . $filename;
            $mediumPath = $userDirectory . '/medium/' . $filename;
            $thumbnailPath = $userDirectory . '/thumbnails/' . $filename;

            // Process and store images
            $this->processAndStoreImage($photo, $fullPath, 1200, 1600); // Full size (max 1200x1600)
            $this->processAndStoreImage($photo, $mediumPath, 600, 800);   // Medium size
            $this->processAndStoreImage($photo, $thumbnailPath, 200, 267); // Thumbnail

            // If this is set as profile picture, unset others
            if ($request->get('is_profile_picture', false)) {
                $user->photos()->update(['is_profile_picture' => false]);
            }

            // Determine sort order
            $sortOrder = $request->get('sort_order', 0);
            if ($sortOrder === 0) {
                $maxOrder = $user->photos()->max('sort_order') ?? 0;
                $sortOrder = $maxOrder + 1;
            }

            // Create photo record
            $userPhoto = UserPhoto::create([
                'user_id' => $user->id,
                'original_filename' => $originalFilename,
                'stored_filename' => $filename,
                'file_path' => $fullPath,
                'thumbnail_path' => $thumbnailPath,
                'medium_path' => $mediumPath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'width' => null, // Will be set after image processing
                'height' => null, // Will be set after image processing
                'is_profile_picture' => $request->get('is_profile_picture', false),
                'is_private' => $request->get('is_private', false),
                'sort_order' => $sortOrder,
                'status' => 'pending', // Photos need approval
            ]);

            // Get image dimensions and update
            $imageDimensions = getimagesize(Storage::path($fullPath));
            if ($imageDimensions) {
                $userPhoto->update([
                    'width' => $imageDimensions[0],
                    'height' => $imageDimensions[1],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Photo uploaded successfully',
                'data' => [
                    'photo' => [
                        'id' => $userPhoto->id,
                        'original_filename' => $userPhoto->original_filename,
                        'file_path' => Storage::url($userPhoto->file_path),
                        'thumbnail_path' => Storage::url($userPhoto->thumbnail_path),
                        'medium_path' => Storage::url($userPhoto->medium_path),
                        'is_profile_picture' => $userPhoto->is_profile_picture,
                        'is_private' => $userPhoto->is_private,
                        'status' => $userPhoto->status,
                        'sort_order' => $userPhoto->sort_order,
                        'created_at' => $userPhoto->created_at,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update photo details
     */
    public function update(Request $request, UserPhoto $photo): JsonResponse
    {
        $user = $request->user();

        // Check if user owns this photo
        if ($photo->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'is_private' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $photo->update($request->only(['is_private', 'sort_order']));

            return response()->json([
                'success' => true,
                'message' => 'Photo updated successfully',
                'data' => [
                    'photo' => [
                        'id' => $photo->id,
                        'is_private' => $photo->is_private,
                        'sort_order' => $photo->sort_order,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a photo
     */
    public function destroy(Request $request, UserPhoto $photo): JsonResponse
    {
        $user = $request->user();

        // Check if user owns this photo
        if ($photo->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            // Delete physical files
            if (Storage::exists($photo->file_path)) {
                Storage::delete($photo->file_path);
            }
            if ($photo->thumbnail_path && Storage::exists($photo->thumbnail_path)) {
                Storage::delete($photo->thumbnail_path);
            }
            if ($photo->medium_path && Storage::exists($photo->medium_path)) {
                Storage::delete($photo->medium_path);
            }

            // Delete database record
            $photo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Photo deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set photo as profile picture
     */
    public function setAsProfile(Request $request, UserPhoto $photo): JsonResponse
    {
        $user = $request->user();

        // Check if user owns this photo
        if ($photo->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if photo is approved
        if ($photo->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved photos can be set as profile picture'
            ], 400);
        }

        try {
            // Unset current profile picture
            $user->photos()->update(['is_profile_picture' => false]);

            // Set this photo as profile picture
            $photo->update(['is_profile_picture' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle photo privacy
     */
    public function togglePrivate(Request $request, UserPhoto $photo): JsonResponse
    {
        $user = $request->user();

        // Check if user owns this photo
        if ($photo->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Profile pictures cannot be private
        if ($photo->is_profile_picture && !$photo->is_private) {
            return response()->json([
                'success' => false,
                'message' => 'Profile picture cannot be made private'
            ], 400);
        }

        try {
            $photo->update(['is_private' => !$photo->is_private]);

            return response()->json([
                'success' => true,
                'message' => 'Photo privacy updated successfully',
                'data' => [
                    'is_private' => $photo->is_private
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update photo privacy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update photo sort order
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'photos' => 'required|array',
            'photos.*.id' => 'required|integer|exists:user_photos,id',
            'photos.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->photos as $photoData) {
                $photo = UserPhoto::find($photoData['id']);
                
                // Check if user owns this photo
                if ($photo->user_id !== $user->id) {
                    continue;
                }

                $photo->update(['sort_order' => $photoData['sort_order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Photo order updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update photo order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process and store image with resizing
     */
    private function processAndStoreImage($file, $path, $maxWidth, $maxHeight): void
    {
        $image = $this->imageManager->read($file);
        
        // Resize image while maintaining aspect ratio
        $image->resize($maxWidth, $maxHeight, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize(); // Don't upsize smaller images
        });

        // Convert to JPEG for consistency and smaller file size
        $image->toJpeg(85); // 85% quality

        // Store the processed image
        Storage::put($path, (string) $image);
    }
}
