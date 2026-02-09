<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPhoto;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(Request $request, User $user = null): JsonResponse
    {
        try {
            $user = $user ?? $request->user();
            $currentUser = $request->user();
            
            // Load relationships
            $user->load(['profile', 'photos' => function($q) {
                $q->where('status', 'approved')->orderBy('is_profile_picture', 'desc');
            }, 'interests', 'horoscope']);

            // Check if current user can view this profile
            if ($user->id !== $currentUser->id) {
                if (!$this->canViewProfile($currentUser, $user)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Profile not accessible'
                    ], 403);
                }

                // Record profile view
                $this->recordProfileView($currentUser, $user);
            }

            // Ensure completion percentage is up to date
            if ($user->profile) {
                $user->profile_completion_percentage = $user->profile->calculateCompletionPercentage();
                $user->save();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->formatProfileResponse($user, $currentUser),
                    'can_edit' => $user->id === $currentUser->id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Basic user information
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',

            // Physical attributes
            'height_cm' => 'nullable|integer|min:100|max:250',
            'weight_kg' => 'nullable|numeric|min:30|max:200',
            'body_type' => 'nullable|in:slim,average,athletic,heavy',
            'complexion' => 'nullable|in:very_fair,fair,wheatish,brown,dark,very_dark',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'physically_challenged' => 'nullable|boolean',
            'physical_challenge_details' => 'nullable|string|max:500',

            // Location
            'current_city' => 'nullable|string|max:100',
            'current_state' => 'nullable|string|max:100',
            'current_country' => 'nullable|string|max:100',
            'hometown_city' => 'nullable|string|max:100',
            'hometown_state' => 'nullable|string|max:100',
            'hometown_country' => 'nullable|string|max:100',

            // Education & Career
            'education_level' => 'nullable|string|max:100',
            'education_field' => 'nullable|string|max:100',
            'college_university' => 'nullable|string|max:200',
            'occupation' => 'nullable|string|max:100',
            'company' => 'nullable|string|max:200',
            'job_title' => 'nullable|string|max:100',
            'annual_income_usd' => 'nullable|numeric|min:0|max:10000000',
            'working_status' => 'nullable|in:employed,self_employed,business,not_working,student',

            // Cultural & Religious
            'religion' => 'nullable|string|max:50',
            'caste' => 'nullable|string|max:50',
            'sub_caste' => 'nullable|string|max:50',
            'mother_tongue' => 'nullable|string|max:50',
            'languages_known' => 'nullable|array',
            'religiousness' => 'nullable|in:very_religious,religious,somewhat_religious,not_religious',

            // Family
            'family_type' => 'nullable|in:nuclear,joint',
            'family_status' => 'nullable|in:middle_class,upper_middle_class,rich,affluent',
            'father_occupation' => 'nullable|string|max:100',
            'mother_occupation' => 'nullable|string|max:100',
            'brothers_count' => 'nullable|integer|min:0|max:20',
            'sisters_count' => 'nullable|integer|min:0|max:20',
            'brothers_married' => 'nullable|integer|min:0|max:20',
            'sisters_married' => 'nullable|integer|min:0|max:20',
            'family_details' => 'nullable|string|max:1000',

            // Lifestyle
            'diet' => 'nullable|in:vegetarian,non_vegetarian,vegan,jain,occasionally_non_veg',
            'smoking' => 'nullable|in:never,occasionally,regularly',
            'drinking' => 'nullable|in:never,occasionally,socially,regularly',
            'hobbies' => 'nullable|array',
            'about_me' => 'nullable|string|max:1000',
            'looking_for' => 'nullable|string|max:1000',

            // Matrimonial specific
            'marital_status' => 'nullable|in:never_married,divorced,widowed,separated',
            'have_children' => 'nullable|boolean',
            'children_count' => 'nullable|integer|min:0|max:10',
            'children_living_status' => 'nullable|in:with_me,with_ex,independent',
            'willing_to_relocate' => 'nullable|boolean',
            'preferred_locations' => 'nullable|array',
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
            
            $user = $request->user();
            $data = $validator->validated();
            
            // Separate user data from profile data
            $userData = array_intersect_key($data, array_flip(['first_name', 'last_name']));
            $profileData = array_diff_key($data, array_flip(['first_name', 'last_name']));

            // Sanitize integer fields - convert empty strings/null to 0 (database has NOT NULL with default 0)
            $integerFields = ['children_count', 'brothers_count', 'sisters_count', 'brothers_married', 'sisters_married'];
            foreach ($integerFields as $field) {
                if (array_key_exists($field, $profileData) && ($profileData[$field] === '' || $profileData[$field] === null)) {
                    $profileData[$field] = 0;
                }
            }

            // Update user basic information if provided
            if (!empty($userData)) {
                $user->update($userData);
            }

            // Get or create profile
            $profile = $user->profile ?? $user->profile()->create([]);

            // Update profile
            $profile->update($profileData);
            
            // Update user's profile completion
            $completionPercentage = $profile->calculateCompletionPercentage();
            $user->update(['profile_completion_percentage' => $completionPercentage]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'profile' => $this->formatProfileResponse($user->fresh(['profile']), $user),
                    'completion_percentage' => $completionPercentage,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload profile photo
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 10MB max
            'is_profile_picture' => 'sometimes|boolean',
            'caption' => 'sometimes|string|max:255',
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
            
            // Check photo limit (free users: 5, premium: unlimited)
            $currentPhotos = $user->photos()->count();
            $maxPhotos = $user->is_premium ? 50 : 5;
            
            if ($currentPhotos >= $maxPhotos) {
                return response()->json([
                    'success' => false,
                    'message' => "Photo limit reached. You can upload up to {$maxPhotos} photos.",
                    'upgrade_required' => !$user->is_premium
                ], 400);
            }

            DB::beginTransaction();

            $photo = $request->file('photo');
            $isProfilePicture = $request->get('is_profile_picture', false);
            
            // If this is set as profile picture, unset others
            if ($isProfilePicture) {
                $user->photos()->update(['is_profile_picture' => false]);
            }

            // Generate unique filename
            $filename = Str::random(40) . '.' . $photo->getClientOriginalExtension();
            
            // Create different sizes
            $paths = $this->processAndStoreImage($photo, $filename);
            
            // Create photo record
            $userPhoto = $user->photos()->create([
                'original_filename' => $photo->getClientOriginalName(),
                'file_path' => $paths['original'],
                'thumbnail_path' => $paths['thumbnail'],
                'medium_path' => $paths['medium'],
                'large_path' => $paths['large'],
                'mime_type' => $photo->getMimeType(),
                'file_size' => $photo->getSize(),
                'width' => $paths['width'],
                'height' => $paths['height'],
                'is_profile_picture' => $isProfilePicture,
                'caption' => $request->get('caption'),
                'status' => 'pending', // Requires admin approval
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Photo uploaded successfully. It will be reviewed shortly.',
                'data' => [
                    'photo' => $this->formatPhotoResponse($userPhoto),
                    'photos_count' => $user->photos()->count(),
                    'max_photos' => $maxPhotos,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user photos
     */
    public function photos(Request $request, User $user = null): JsonResponse
    {
        try {
            $user = $user ?? $request->user();
            $currentUser = $request->user();
            
            // Check permissions
            if ($user->id !== $currentUser->id && !$this->canViewProfile($currentUser, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Photos not accessible'
                ], 403);
            }

            $query = $user->photos();
            
            // If viewing someone else's profile, only show approved photos
            if ($user->id !== $currentUser->id) {
                $query->where('status', 'approved');
            }
            
            $photos = $query->orderBy('is_profile_picture', 'desc')
                          ->orderBy('sort_order')
                          ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'photos' => $photos->map(function ($photo) {
                        return $this->formatPhotoResponse($photo);
                    }),
                    'total' => $photos->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get photos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete photo
     */
    public function deletePhoto(Request $request, UserPhoto $photo): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Check ownership
            if ($photo->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Delete files from storage
            $this->deletePhotoFiles($photo);
            
            // Delete record
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
     * Set profile picture
     */
    public function setProfilePicture(Request $request, UserPhoto $photo): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Check ownership
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

            DB::beginTransaction();

            // Unset current profile picture
            $user->photos()->update(['is_profile_picture' => false]);
            
            // Set new profile picture
            $photo->update(['is_profile_picture' => true]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get profile visitors
     */
    public function visitors(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get recent profile visitors
            $visitors = \DB::table('profile_views')
                ->join('users', 'profile_views.viewer_id', '=', 'users.id')
                ->where('profile_views.profile_user_id', $user->id)
                ->where('profile_views.viewer_id', '!=', $user->id) // Exclude self-views
                ->select([
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'profile_views.viewed_at',
                    'profile_views.view_count'
                ])
                ->orderBy('profile_views.viewed_at', 'desc')
                ->limit(20)
                ->get();
            
            // Get total view count
            $totalViews = \DB::table('profile_views')
                ->where('profile_user_id', $user->id)
                ->where('viewer_id', '!=', $user->id)
                ->sum('view_count');
            
            // Get recent views count (last 7 days)
            $recentViews = \DB::table('profile_views')
                ->where('profile_user_id', $user->id)
                ->where('viewer_id', '!=', $user->id)
                ->where('viewed_at', '>=', now()->subDays(7))
                ->sum('view_count');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'visitors' => $visitors->map(function ($visitor) {
                        return [
                            'id' => $visitor->id,
                            'name' => $visitor->first_name . ' ' . $visitor->last_name,
                            'email' => $visitor->email,
                            'viewed_at' => $visitor->viewed_at,
                            'view_count' => $visitor->view_count
                        ];
                    }),
                    'total_views' => $totalViews,
                    'recent_views' => $recentViews,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get visitors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'profile_visibility' => 'sometimes|in:public,members_only,premium_only,private',
            'hide_last_seen' => 'sometimes|boolean',
            'email_notifications' => 'sometimes|boolean',
            'sms_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'incognito_mode' => 'sometimes|boolean',
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
            $data = $validator->validated();
            
            // Incognito mode is premium feature
            if (isset($data['incognito_mode']) && $data['incognito_mode'] && !$user->is_premium) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incognito mode is a premium feature',
                    'upgrade_required' => true
                ], 403);
            }
            
            $user->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update privacy settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get profile completion status
     */
    public function completionStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;
        $preferences = $user->preferences;

        $completionPercentage = $profile ? $profile->calculateCompletionPercentage() : 0;
        $completedSections = [
            'profile' => $profile ? $completionPercentage >= 80 : false,
            'preferences' => $preferences ? $preferences->areComplete() : false,
            'photo' => $user->photos()->where('is_profile_picture', true)->exists(),
        ];
        $missingSections = [];
        if (!$completedSections['profile']) $missingSections[] = 'profile';
        if (!$completedSections['preferences']) $missingSections[] = 'preferences';
        if (!$completedSections['photo']) $missingSections[] = 'photo';
        $nextSteps = $missingSections;

        return response()->json([
            'success' => true,
            'data' => [
                'completion_percentage' => $completionPercentage,
                'completed_sections' => $completedSections,
                'missing_sections' => $missingSections,
                'next_steps' => $nextSteps,
            ]
        ]);
    }

    /**
     * Complete profile setup (profile + preferences)
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $profileData = $request->input('profile', []);
        $preferenceData = $request->input('preferences', []);

        DB::beginTransaction();
        try {
            // Update or create profile
            if (!empty($profileData)) {
                $profile = $user->profile ?: $user->profile()->create([]);
                $profile->fill($profileData);
                $profile->save();
                $user->profile_completion_percentage = $profile->calculateCompletionPercentage();
            }
            // Update or create preferences
            if (!empty($preferenceData)) {
                $preferences = $user->preferences ?: $user->preferences()->create([]);
                $preferences->fill($preferenceData);
                $preferences->save();
            }
            $user->save();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Profile setup completed',
                'data' => [
                    'completion_percentage' => $user->profile_completion_percentage,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete profile setup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Helper Methods

    /**
     * Check if user can view profile
     */
    private function canViewProfile(User $viewer, User $profileUser): bool
    {
        // Check if profile is public or viewer has permissions
        if ($profileUser->profile_visibility === 'public') {
            return true;
        }
        
        if ($profileUser->profile_visibility === 'premium_only' && !$viewer->is_premium) {
            return false;
        }
        
        if ($profileUser->profile_visibility === 'private') {
            // Check if they have matched or are connected
            return $viewer->matches()->where('matched_user_id', $profileUser->id)
                         ->where('can_communicate', true)->exists();
        }
        
        return true; // members_only
    }

    /**
     * Record profile view
     */
    private function recordProfileView(User $viewer, User $profileUser): void
    {
        // Use the ProfileView model to record the view
        \App\Models\ProfileView::recordView($profileUser, $viewer);
    }

    /**
     * Process and store image in multiple sizes
     */
    private function processAndStoreImage($file, string $filename): array
    {
        $originalImage = Image::make($file);
        
        // Get original dimensions
        $width = $originalImage->width();
        $height = $originalImage->height();
        
        // Create different sizes
        $paths = [
            'width' => $width,
            'height' => $height,
        ];
        
        // Original
        $originalPath = 'photos/original/' . $filename;
        Storage::disk('public')->put($originalPath, $originalImage->encode());
        $paths['original'] = $originalPath;
        
        // Thumbnail (200x200)
        $thumbnail = clone $originalImage;
        $thumbnail->fit(200, 200);
        $thumbnailPath = 'photos/thumbnails/' . $filename;
        Storage::disk('public')->put($thumbnailPath, $thumbnail->encode());
        $paths['thumbnail'] = $thumbnailPath;
        
        // Medium (600x600)
        $medium = clone $originalImage;
        $medium->resize(600, 600, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $mediumPath = 'photos/medium/' . $filename;
        Storage::disk('public')->put($mediumPath, $medium->encode());
        $paths['medium'] = $mediumPath;
        
        // Large (1200x1200)
        $large = clone $originalImage;
        $large->resize(1200, 1200, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $largePath = 'photos/large/' . $filename;
        Storage::disk('public')->put($largePath, $large->encode());
        $paths['large'] = $largePath;
        
        return $paths;
    }

    /**
     * Delete photo files from storage
     */
    private function deletePhotoFiles(UserPhoto $photo): void
    {
        $paths = [
            $photo->file_path,
            $photo->thumbnail_path,
            $photo->medium_path,
            $photo->large_path,
        ];
        
        foreach ($paths as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * Format profile response
     */
    private function formatProfileResponse(User $user, User $currentUser): array
    {
        $profile = $user->profile;
        $localCurrency = $this->getLocalCurrency($user->country_code);
        
        $data = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'age' => $user->age,
            'gender' => $user->gender,
            'country_code' => $user->country_code,
            'is_premium' => $user->is_premium,
            'last_active' => $user->hide_last_seen ? null : $user->last_active_at?->diffForHumans(),
            'completion_percentage' => $user->profile_completion_percentage ?? 0,
            'verification' => [
                'profile_verified' => $profile?->profile_verified ?? false,
                'photo_verified' => $user->photo_verified,
                'email_verified' => $user->email_verified,
                'phone_verified' => $user->phone_verified,
                'verification_score' => $profile?->verification_score ?? 0,
            ],
        ];

        // Add profile details if available
        if ($profile) {
            $data['profile'] = [
                // Physical
                'height_cm' => $profile->height_cm,
                'height_feet' => $profile->height_feet,
                'weight_kg' => $profile->weight_kg,
                'bmi' => $profile->bmi,
                'bmi_category' => $profile->bmi_category,
                'body_type' => $profile->body_type,
                'complexion' => $profile->complexion,
                'blood_group' => $profile->blood_group,
                
                // Location
                'current_location' => $profile->full_location,
                'current_city' => $profile->current_city,
                'current_state' => $profile->current_state,
                'current_country' => $profile->current_country,
                'hometown_city' => $profile->hometown_city,
                'hometown_state' => $profile->hometown_state,
                'hometown_country' => $profile->hometown_country,
                'willing_to_relocate' => $profile->willing_to_relocate,
                
                // Education & Career
                'education_level' => $profile->education_level,
                'education_field' => $profile->education_field,
                'college_university' => $profile->college_university,
                'occupation' => $profile->occupation,
                'company' => $profile->company,
                'job_title' => $profile->job_title,
                'working_status' => $profile->working_status,
                
                // Cultural & Religious
                'religion' => $profile->religion,
                'caste' => $profile->caste,
                'sub_caste' => $profile->sub_caste,
                'mother_tongue' => $profile->mother_tongue,
                'languages_known' => $profile->languages_known,
                'religiousness' => $profile->religiousness,
                
                // Family
                'family_type' => $profile->family_type,
                'family_status' => $profile->family_status,
                'father_occupation' => $profile->father_occupation,
                'mother_occupation' => $profile->mother_occupation,
                'brothers_count' => $profile->brothers_count,
                'sisters_count' => $profile->sisters_count,
                'family_details' => $profile->family_details,
                
                // Lifestyle
                'diet' => $profile->diet,
                'smoking' => $profile->smoking,
                'drinking' => $profile->drinking,
                'hobbies' => $profile->hobbies,
                'about_me' => $profile->about_me,
                'looking_for' => $profile->looking_for,
                
                // Matrimonial
                'marital_status' => $profile->marital_status,
                'have_children' => $profile->have_children,
                'children_count' => $profile->children_count,
                'children_living_status' => $profile->children_living_status,
            ];

            // Show income only to premium users or matched users
            if ($currentUser->is_premium || $this->areMatched($currentUser, $user)) {
                $data['profile']['annual_income_usd'] = $profile->annual_income_usd;
                $data['profile']['annual_income_local'] = [
                    'amount' => $profile->getIncomeInCurrency($localCurrency),
                    'currency' => $localCurrency,
                ];
            }
        }

        // Add photos
        $photos = $user->photos()->where('status', 'approved')
                      ->orderBy('is_profile_picture', 'desc')
                      ->get();
        
        $data['photos'] = $photos->map(function ($photo) {
            return $this->formatPhotoResponse($photo);
        });

        return $data;
    }

    /**
     * Format photo response
     */
    private function formatPhotoResponse(UserPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'is_profile_picture' => $photo->is_profile_picture,
            'caption' => $photo->caption,
            'status' => $photo->status,
            'urls' => [
                'thumbnail' => asset('storage/' . $photo->thumbnail_path),
                'medium' => asset('storage/' . $photo->medium_path),
                'large' => asset('storage/' . $photo->large_path),
                'original' => asset('storage/' . $photo->file_path),
            ],
            'uploaded_at' => $photo->created_at->toISOString(),
        ];
    }

    /**
     * Check if users are matched
     */
    private function areMatched(User $user1, User $user2): bool
    {
        return $user1->matches()
                    ->where('matched_user_id', $user2->id)
                    ->where('can_communicate', true)
                    ->exists();
    }

    /**
     * Get local currency based on country
     */
    private function getLocalCurrency(string $countryCode): string
    {
        $currencies = [
            'LK' => 'LKR',
            'IN' => 'INR',
            'GB' => 'GBP',
            'AU' => 'AUD',
            'CA' => 'CAD',
            'SG' => 'SGD',
            'AE' => 'AED',
            'SA' => 'SAR',
        ];

        return $currencies[$countryCode] ?? 'USD';
    }
}
