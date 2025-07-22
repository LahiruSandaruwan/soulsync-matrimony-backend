<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VoiceController extends Controller
{
    /**
     * Upload voice introduction
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'voice_file' => 'required|file|mimes:mp3,wav,m4a,aac|max:10240', // Max 10MB
            'duration' => 'required|integer|min:5|max:120', // 5 seconds to 2 minutes
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
            $voiceFile = $request->file('voice_file');
            $duration = $request->get('duration');
            $description = $request->get('description');

            // Validate file duration (basic check)
            if ($duration < 5 || $duration > 120) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voice introduction must be between 5 seconds and 2 minutes',
                ], 422);
            }

            // Generate unique filename
            $fileName = 'voice_intro_' . $user->id . '_' . Str::uuid() . '.' . $voiceFile->getClientOriginalExtension();
            $filePath = 'voice-intros/' . $fileName;

            // Store the file
            $stored = Storage::put($filePath, file_get_contents($voiceFile));

            if (!$stored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload voice file',
                ], 500);
            }

            // Remove old voice intro if exists
            if ($user->profile && $user->profile->voice_intro_path) {
                Storage::delete($user->profile->voice_intro_path);
            }

            // Update user profile with voice intro info
            $profileData = [
                'voice_intro_path' => $filePath,
                'voice_intro_duration' => $duration,
                'voice_intro_description' => $description,
                'voice_intro_uploaded_at' => now(),
            ];

            if ($user->profile) {
                $user->profile->update($profileData);
            } else {
                $user->profile()->create(array_merge($profileData, [
                    'user_id' => $user->id,
                ]));
            }

            // Update profile completion percentage
            $this->updateProfileCompletion($user);

            return response()->json([
                'success' => true,
                'message' => 'Voice introduction uploaded successfully',
                'data' => [
                    'voice_intro' => [
                        'path' => $filePath,
                        'duration' => $duration,
                        'description' => $description,
                        'uploaded_at' => now()->toISOString(),
                        'file_size' => $voiceFile->getSize(),
                        'mime_type' => $voiceFile->getMimeType(),
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload voice introduction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete voice introduction
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            if (!$user->profile || !$user->profile->voice_intro_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'No voice introduction found',
                ], 404);
            }

            // Delete the file from storage
            $filePath = $user->profile->voice_intro_path;
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            // Remove voice intro data from profile
            $user->profile->update([
                'voice_intro_path' => null,
                'voice_intro_duration' => null,
                'voice_intro_description' => null,
                'voice_intro_uploaded_at' => null,
            ]);

            // Update profile completion percentage
            $this->updateProfileCompletion($user);

            return response()->json([
                'success' => true,
                'message' => 'Voice introduction deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete voice introduction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get voice introduction details
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            if (!$user->profile || !$user->profile->voice_intro_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'No voice introduction found',
                    'data' => ['voice_intro' => null]
                ], 404);
            }

            $profile = $user->profile;
            $voiceIntro = [
                'path' => $profile->voice_intro_path,
                'duration' => $profile->voice_intro_duration,
                'description' => $profile->voice_intro_description,
                'uploaded_at' => $profile->voice_intro_uploaded_at?->toISOString(),
                'file_exists' => Storage::exists($profile->voice_intro_path),
            ];

            // Add file size if file exists
            if ($voiceIntro['file_exists']) {
                $voiceIntro['file_size'] = Storage::size($profile->voice_intro_path);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'voice_intro' => $voiceIntro,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get voice introduction details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stream voice introduction file
     */
    public function stream(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();

        try {
            if (!$user->profile || !$user->profile->voice_intro_path) {
                abort(404, 'Voice introduction not found');
            }

            $filePath = $user->profile->voice_intro_path;

            if (!Storage::exists($filePath)) {
                abort(404, 'Voice file not found');
            }

            return Storage::download($filePath);

        } catch (\Exception $e) {
            abort(500, 'Failed to stream voice introduction');
        }
    }

    /**
     * Get voice introduction of another user (premium feature)
     */
    public function getUserVoice(Request $request, $userId): JsonResponse
    {
        $currentUser = $request->user();
        
        // Check if current user has premium access or if voice intro is public
        if (!$currentUser->is_premium_active) {
            return response()->json([
                'success' => false,
                'message' => 'Premium subscription required to access voice introductions',
                'upgrade_required' => true,
            ], 403);
        }

        try {
            $targetUser = User::findOrFail($userId);
            
            if (!$targetUser->profile || !$targetUser->profile->voice_intro_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has no voice introduction',
                    'data' => ['voice_intro' => null]
                ], 404);
            }

            // Check if users are matched or if voice intro is public
            $areMatched = $this->checkIfMatched($currentUser, $targetUser);
            
            if (!$areMatched && !$targetUser->profile->voice_intro_public) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voice introduction is private. Match with this user to listen.',
                    'requires_match' => true,
                ], 403);
            }

            $profile = $targetUser->profile;
            $voiceIntro = [
                'duration' => $profile->voice_intro_duration,
                'description' => $profile->voice_intro_description,
                'uploaded_at' => $profile->voice_intro_uploaded_at?->toISOString(),
                'stream_url' => route('api.voice.stream-user', ['userId' => $userId]),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $targetUser->id,
                    'user_name' => $targetUser->first_name,
                    'voice_intro' => $voiceIntro,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get voice introduction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stream another user's voice introduction
     */
    public function streamUserVoice(Request $request, $userId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $currentUser = $request->user();
        
        // Check premium access
        if (!$currentUser->is_premium_active) {
            abort(403, 'Premium subscription required');
        }

        try {
            $targetUser = User::findOrFail($userId);
            
            if (!$targetUser->profile || !$targetUser->profile->voice_intro_path) {
                abort(404, 'Voice introduction not found');
            }

            // Check if users are matched or if voice intro is public
            $areMatched = $this->checkIfMatched($currentUser, $targetUser);
            
            if (!$areMatched && !$targetUser->profile->voice_intro_public) {
                abort(403, 'Voice introduction is private');
            }

            $filePath = $targetUser->profile->voice_intro_path;

            if (!Storage::exists($filePath)) {
                abort(404, 'Voice file not found');
            }

            return Storage::download($filePath);

        } catch (\Exception $e) {
            abort(500, 'Failed to stream voice introduction');
        }
    }

    /**
     * Update voice introduction settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'description' => 'sometimes|string|max:500',
            'is_public' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!$user->profile || !$user->profile->voice_intro_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'No voice introduction found to update',
                ], 404);
            }

            $updateData = [];
            
            if ($request->has('description')) {
                $updateData['voice_intro_description'] = $request->get('description');
            }
            
            if ($request->has('is_public')) {
                $updateData['voice_intro_public'] = $request->get('is_public');
            }

            $user->profile->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Voice introduction settings updated successfully',
                'data' => [
                    'voice_intro' => [
                        'description' => $user->profile->voice_intro_description,
                        'is_public' => $user->profile->voice_intro_public ?? false,
                        'duration' => $user->profile->voice_intro_duration,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update voice introduction settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if two users are matched
     */
    private function checkIfMatched($user1, $user2): bool
    {
        return \App\Models\UserMatch::where('user_id', $user1->id)
            ->where('target_user_id', $user2->id)
            ->where('action', 'like')
            ->whereExists(function ($query) use ($user1, $user2) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('user_matches as um2')
                    ->where('um2.user_id', $user2->id)
                    ->where('um2.target_user_id', $user1->id)
                    ->where('um2.action', 'like');
            })
            ->exists();
    }

    /**
     * Update profile completion percentage
     */
    private function updateProfileCompletion($user): void
    {
        // This would typically be in a service, but adding here for completeness
        $profile = $user->profile;
        if (!$profile) return;

        $fields = [
            'bio' => !empty($profile->bio),
            'date_of_birth' => !empty($profile->date_of_birth),
            'height_cm' => !empty($profile->height_cm),
            'profession' => !empty($profile->profession),
            'education_level' => !empty($profile->education_level),
            'religion' => !empty($profile->religion),
            'city' => !empty($profile->city),
            'voice_intro' => !empty($profile->voice_intro_path),
        ];

        $completedFields = count(array_filter($fields));
        $totalFields = count($fields);
        $completionPercentage = round(($completedFields / $totalFields) * 100);

        $user->update(['profile_completion_percentage' => $completionPercentage]);
    }
} 