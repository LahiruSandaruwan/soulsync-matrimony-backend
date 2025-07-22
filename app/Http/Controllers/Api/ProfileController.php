<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'profile',
            'preferences', 
            'photos' => function($query) {
                $query->orderBy('sort_order')->orderBy('created_at');
            },
            'profilePicture',
            'horoscope',
            'interests',
            'activeSubscription'
        ]);

        // Calculate profile completion percentage
        $completionPercentage = $this->calculateProfileCompletion($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'profile_completion' => $completionPercentage,
                'is_premium' => $user->is_premium_active,
                'age' => $user->age,
            ]
        ]);
    }

    /**
     * Update the user's profile
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            // Basic user fields
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'date_of_birth' => 'sometimes|date|before:18 years ago',
            'gender' => 'sometimes|in:male,female,other',
            'country_code' => 'sometimes|string|size:2',
            'language' => 'sometimes|string|size:2',

            // Profile fields
            'bio' => 'sometimes|string|max:1000',
            'occupation' => 'sometimes|string|max:255',
            'company' => 'sometimes|string|max:255',
            'education' => 'sometimes|string|max:255',
            'height' => 'sometimes|numeric|between:120,250',
            'weight' => 'sometimes|numeric|between:30,200',
            'body_type' => 'sometimes|in:slim,average,athletic,heavy',
            'complexion' => 'sometimes|in:very_fair,fair,wheatish,dark',
            'marital_status' => 'sometimes|in:never_married,divorced,widowed,separated',
            'children_count' => 'sometimes|integer|min:0|max:10',
            'wants_children' => 'sometimes|boolean',

            // Location
            'city' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
            'zip_code' => 'sometimes|string|max:20',

            // Cultural Information
            'religion' => 'sometimes|string|max:255',
            'caste' => 'sometimes|string|max:255',
            'sub_caste' => 'sometimes|string|max:255',
            'mother_tongue' => 'sometimes|string|max:255',
            'languages_spoken' => 'sometimes|array',

            // Family Information
            'family_type' => 'sometimes|in:nuclear,joint',
            'family_status' => 'sometimes|in:middle_class,upper_middle_class,rich,affluent',
            'father_occupation' => 'sometimes|string|max:255',
            'mother_occupation' => 'sometimes|string|max:255',
            'siblings_count' => 'sometimes|integer|min:0|max:20',
            'family_details' => 'sometimes|string|max:1000',

            // Lifestyle
            'smoking' => 'sometimes|in:never,occasionally,regularly',
            'drinking' => 'sometimes|in:never,occasionally,socially,regularly',
            'diet' => 'sometimes|in:vegetarian,non_vegetarian,vegan,jain',
            'hobbies' => 'sometimes|array',

            // Financial Information
            'annual_income_usd' => 'sometimes|numeric|min:0',
            'income_currency' => 'sometimes|in:USD,LKR,INR,EUR,GBP',

            // Contact & Social
            'whatsapp_number' => 'sometimes|string|max:20',
            'instagram_handle' => 'sometimes|string|max:255',
            'facebook_profile' => 'sometimes|url',

            // Privacy Settings
            'show_contact_info' => 'sometimes|boolean',
            'show_horoscope' => 'sometimes|boolean',
            'show_income' => 'sometimes|boolean',
            'show_photos_to_premium_only' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Separate user fields from profile fields
            $userFields = collect($request->only([
                'first_name', 'last_name', 'phone', 'date_of_birth', 
                'gender', 'country_code', 'language'
            ]))->filter()->toArray();

            $profileFields = collect($request->except([
                'first_name', 'last_name', 'phone', 'date_of_birth', 
                'gender', 'country_code', 'language'
            ]))->filter()->toArray();

            // Update user fields if provided
            if (!empty($userFields)) {
                $user->update($userFields);
            }

            // Update or create profile
            if (!empty($profileFields)) {
                $profileFields['last_updated_at'] = now();
                
                if ($user->profile) {
                    $user->profile->update($profileFields);
                } else {
                    $user->profile()->create(array_merge(
                        ['user_id' => $user->id],
                        $profileFields
                    ));
                }
            }

            // Calculate and update profile completion
            $user->refresh();
            $completionPercentage = $this->calculateProfileCompletion($user);
            
            if ($user->profile) {
                $user->profile->update(['profile_completion_percentage' => $completionPercentage]);
            }

            // Update profile status if completion is high enough
            if ($completionPercentage >= 80 && $user->profile_status === 'incomplete') {
                $user->update(['profile_status' => 'pending_approval']);
            }

            $user->load(['profile', 'preferences', 'photos', 'horoscope', 'interests']);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user,
                    'profile_completion' => $completionPercentage,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete profile setup (for new users)
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'bio' => 'required|string|max:1000',
            'occupation' => 'required|string|max:255',
            'education' => 'required|string|max:255',
            'height' => 'required|numeric|between:120,250',
            'marital_status' => 'required|in:never_married,divorced,widowed,separated',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'religion' => 'required|string|max:255',
            'mother_tongue' => 'required|string|max:255',
            'family_type' => 'required|in:nuclear,joint',
            'diet' => 'required|in:vegetarian,non_vegetarian,vegan,jain',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $profileData = $validator->validated();
            $profileData['last_updated_at'] = now();

            // Create or update profile
            $profile = $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            // Calculate completion percentage
            $completionPercentage = $this->calculateProfileCompletion($user);
            $profile->update(['profile_completion_percentage' => $completionPercentage]);

            // Update user status
            $user->update([
                'profile_status' => $completionPercentage >= 80 ? 'pending_approval' : 'incomplete'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile setup completed successfully',
                'data' => [
                    'user' => $user->load(['profile', 'preferences']),
                    'profile_completion' => $completionPercentage,
                    'next_step' => $completionPercentage >= 80 ? 'photo_upload' : 'complete_details'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get profile completion status
     */
    public function completionStatus(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');
        $completionPercentage = $this->calculateProfileCompletion($user);

        $missingFields = $this->getMissingProfileFields($user);

        return response()->json([
            'success' => true,
            'data' => [
                'completion_percentage' => $completionPercentage,
                'missing_fields' => $missingFields,
                'required_for_approval' => [
                    'profile_picture' => $user->profilePicture ? true : false,
                    'basic_info' => $completionPercentage >= 60,
                    'detailed_info' => $completionPercentage >= 80,
                    'preferences_set' => $user->preferences ? true : false,
                ],
                'can_submit_for_approval' => $completionPercentage >= 80,
            ]
        ]);
    }

    /**
     * Submit profile for approval
     */
    public function submitForApproval(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');
        $completionPercentage = $this->calculateProfileCompletion($user);

        if ($completionPercentage < 80) {
            return response()->json([
                'success' => false,
                'message' => 'Profile must be at least 80% complete to submit for approval',
                'data' => [
                    'current_completion' => $completionPercentage,
                    'required_completion' => 80
                ]
            ], 400);
        }

        if (!$user->profilePicture) {
            return response()->json([
                'success' => false,
                'message' => 'Profile picture is required for approval'
            ], 400);
        }

        $user->update(['profile_status' => 'pending_approval']);

        // Here you could trigger an admin notification
        // Notification::create([...]);

        return response()->json([
            'success' => true,
            'message' => 'Profile submitted for approval successfully'
        ]);
    }

    /**
     * Delete user profile (soft delete or deactivate)
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify password
        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password verification failed'
            ], 400);
        }

        try {
            // Deactivate instead of delete to preserve data integrity
            $user->update([
                'status' => 'inactive',
                'profile_status' => 'incomplete',
            ]);

            // Revoke all tokens
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Profile deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion(User $user): int
    {
        $totalFields = 0;
        $completedFields = 0;

        // Basic user fields (20 points)
        $basicFields = ['first_name', 'last_name', 'date_of_birth', 'gender'];
        foreach ($basicFields as $field) {
            $totalFields++;
            if ($user->$field) $completedFields++;
        }

        if (!$user->profile) {
            return round(($completedFields / $totalFields) * 100);
        }

        $profile = $user->profile;

        // Essential profile fields (40 points)
        $essentialFields = [
            'bio', 'occupation', 'education', 'height', 'marital_status', 
            'city', 'religion', 'mother_tongue', 'family_type'
        ];
        foreach ($essentialFields as $field) {
            $totalFields++;
            if ($profile->$field) $completedFields++;
        }

        // Additional profile fields (30 points)
        $additionalFields = [
            'company', 'weight', 'body_type', 'complexion', 'country', 
            'caste', 'family_status', 'smoking', 'drinking', 'diet'
        ];
        foreach ($additionalFields as $field) {
            $totalFields++;
            if ($profile->$field) $completedFields++;
        }

        // Photos (10 points)
        $totalFields++;
        if ($user->profilePicture) $completedFields++;

        return min(100, round(($completedFields / $totalFields) * 100));
    }

    /**
     * Get missing profile fields
     */
    private function getMissingProfileFields(User $user): array
    {
        $missing = [];

        // Check basic user fields
        $basicFields = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name', 
            'date_of_birth' => 'Date of Birth',
            'gender' => 'Gender'
        ];

        foreach ($basicFields as $field => $label) {
            if (!$user->$field) {
                $missing[] = $label;
            }
        }

        if (!$user->profile) {
            $missing = array_merge($missing, [
                'Bio', 'Occupation', 'Education', 'Height', 'Marital Status',
                'City', 'Religion', 'Mother Tongue', 'Family Type'
            ]);
            return $missing;
        }

        // Check essential profile fields
        $essentialFields = [
            'bio' => 'Bio',
            'occupation' => 'Occupation',
            'education' => 'Education',
            'height' => 'Height',
            'marital_status' => 'Marital Status',
            'city' => 'City',
            'religion' => 'Religion',
            'mother_tongue' => 'Mother Tongue',
            'family_type' => 'Family Type'
        ];

        foreach ($essentialFields as $field => $label) {
            if (!$user->profile->$field) {
                $missing[] = $label;
            }
        }

        // Check for profile picture
        if (!$user->profilePicture) {
            $missing[] = 'Profile Picture';
        }

        return $missing;
    }
}
