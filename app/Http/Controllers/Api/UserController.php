<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\Report;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Get user profile (for viewing others)
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        // Check if user is trying to view their own profile
        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Use profile endpoint to view your own profile'
            ], 400);
        }

        // Check if user exists and is active
        if ($user->status !== 'active' || $user->profile_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'User profile not available'
            ], 404);
        }

        try {
            // Check if current user has access to view this profile
            $canViewProfile = $this->canViewProfile($currentUser, $user);
            
            if (!$canViewProfile['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $canViewProfile['reason'],
                    'upgrade_required' => $canViewProfile['upgrade_required'] ?? false
                ], 403);
            }

            // Record profile view
            $this->recordProfileView($currentUser, $user);

            // Load user data with appropriate privacy settings
            $userData = $this->formatUserProfile($user, $currentUser);

            // Check interaction history
            $interaction = UserMatch::where(function ($query) use ($currentUser, $user) {
                $query->where('user_id', $currentUser->id)->where('target_user_id', $user->id);
            })->orWhere(function ($query) use ($currentUser, $user) {
                $query->where('user_id', $user->id)->where('target_user_id', $currentUser->id);
            })->first();

            $interactionStatus = [
                'has_liked' => false,
                'has_been_liked' => false,
                'is_match' => false,
                'can_message' => false,
                'action_taken' => null,
            ];

            if ($interaction) {
                if ($interaction->user_id === $currentUser->id) {
                    $interactionStatus['action_taken'] = $interaction->action;
                    $interactionStatus['has_liked'] = in_array($interaction->action, ['like', 'super_like']);
                }
                
                if ($interaction->user_id === $user->id) {
                    $interactionStatus['has_been_liked'] = in_array($interaction->action, ['like', 'super_like']);
                }

                $interactionStatus['is_match'] = !is_null($interaction->matched_at);
                $interactionStatus['can_message'] = $interactionStatus['is_match'];
            }

            // Calculate compatibility if both users have preferences
            $compatibilityScore = null;
            if ($currentUser->preferences && $user->profile) {
                // Use the same compatibility calculation from MatchController
                $compatibilityScore = $this->calculateBasicCompatibility($currentUser, $user);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $userData,
                    'interaction' => $interactionStatus,
                    'compatibility_score' => $compatibilityScore,
                    'view_permissions' => $canViewProfile['permissions'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record profile view
     */
    public function recordView(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot record view of own profile'
            ], 400);
        }

        try {
            // Record the view (could be in a separate profile_views table)
            $this->recordProfileView($currentUser, $user);

            // Send notification to viewed user if they have premium
            if ($user->is_premium_active) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'profile_view',
                    'title' => 'Someone viewed your profile',
                    'body' => $currentUser->first_name . ' viewed your profile',
                    'data' => json_encode([
                        'viewer_id' => $currentUser->id,
                        'viewer_name' => $currentUser->first_name,
                        'viewer_photo' => $currentUser->profilePicture ? 
                            Storage::url($currentUser->profilePicture->file_path) : null,
                    ]),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile view recorded'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record profile view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Express interest (quick like)
     */
    public function expressInterest(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot express interest in own profile'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if already interacted
            $existingMatch = UserMatch::where('user_id', $currentUser->id)
                ->where('target_user_id', $user->id)
                ->first();

            if ($existingMatch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already interacted with this user'
                ], 400);
            }

            // Check daily limits (similar to MatchController)
            $dailyLikes = UserMatch::where('user_id', $currentUser->id)
                ->where('action', 'like')
                ->whereDate('created_at', today())
                ->count();

            $limit = $currentUser->is_premium_active ? 100 : 20;
            
            if ($dailyLikes >= $limit) {
                return response()->json([
                    'success' => false,
                    'message' => "Daily like limit reached ({$limit} likes per day)",
                    'upgrade_required' => !$currentUser->is_premium_active
                ], 400);
            }

            // Create the interest/like
            $match = UserMatch::create([
                'user_id' => $currentUser->id,
                'target_user_id' => $user->id,
                'action' => 'like',
                'message' => $request->get('message'),
            ]);

            // Check for mutual match
            $mutualLike = UserMatch::where('user_id', $user->id)
                ->where('target_user_id', $currentUser->id)
                ->where('action', 'like')
                ->first();

            $isMatch = false;
            if ($mutualLike) {
                $match->update(['matched_at' => now()]);
                $mutualLike->update(['matched_at' => now()]);
                $isMatch = true;

                // Create conversation
                \App\Models\Conversation::firstOrCreate([
                    'user1_id' => min($currentUser->id, $user->id),
                    'user2_id' => max($currentUser->id, $user->id),
                ], ['status' => 'active']);

                // Broadcast match event
                broadcast(new \App\Events\MatchFound($match, $currentUser, $user))->toOthers();

                // Send push notifications
                $pushService = app(\App\Services\PushNotificationService::class);
                $pushService->sendMatchNotification($user, $currentUser);
                $pushService->sendMatchNotification($currentUser, $user);
            } else {
                // Send like notification
                $pushService = app(\App\Services\PushNotificationService::class);
                $pushService->sendLikeNotification($user, $currentUser);
            }

            // Send notification
            Notification::create([
                'user_id' => $user->id,
                'type' => $isMatch ? 'match' : 'like',
                'title' => $isMatch ? 'New Match!' : 'Someone liked you',
                'body' => $isMatch ? 
                    "You and {$currentUser->first_name} liked each other!" :
                    "{$currentUser->first_name} liked your profile",
                'data' => json_encode([
                    'user_id' => $currentUser->id,
                    'user_name' => $currentUser->first_name,
                    'is_match' => $isMatch,
                    'message' => $request->get('message'),
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => $isMatch ? 'It\'s a match! 🎉' : 'Interest expressed successfully',
                'data' => [
                    'is_match' => $isMatch,
                    'can_message' => $isMatch,
                    'remaining_likes' => max(0, $limit - $dailyLikes - 1),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to express interest',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report a user
     */
    public function report(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot report own profile'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|in:inappropriate_content,fake_profile,harassment,spam,other',
            'description' => 'required|string|max:1000',
            'evidence_photos' => 'sometimes|array|max:5',
            'evidence_photos.*' => 'image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if already reported
            $existingReport = Report::where('reporter_id', $currentUser->id)
                ->where('reported_user_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if ($existingReport) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this user'
                ], 400);
            }

            $evidencePaths = [];
            
            // Handle evidence photo uploads
            if ($request->hasFile('evidence_photos')) {
                foreach ($request->file('evidence_photos') as $photo) {
                    $path = 'reports/' . $user->id . '/' . uniqid() . '.' . $photo->getClientOriginalExtension();
                    Storage::put($path, file_get_contents($photo));
                    $evidencePaths[] = $path;
                }
            }

            // Create report
            $report = Report::create([
                'reporter_id' => $currentUser->id,
                'reported_user_id' => $user->id,
                'reason' => $request->reason,
                'description' => $request->description,
                'evidence_data' => json_encode([
                    'photos' => $evidencePaths,
                    'timestamp' => now()->toISOString(),
                    'reporter_ip' => $request->ip(),
                ]),
                'status' => 'pending',
            ]);

            // Send notification to admins
            $admins = User::role(['admin', 'moderator'])->get();
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'admin',
                    'title' => 'New User Report',
                    'body' => "User {$user->first_name} has been reported for {$request->reason}",
                    'data' => json_encode([
                        'report_id' => $report->id,
                        'reported_user_id' => $user->id,
                        'reporter_id' => $currentUser->id,
                        'reason' => $request->reason,
                    ]),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User reported successfully. Our team will review this report.',
                'data' => [
                    'report_id' => $report->id,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to report user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's photos (with privacy checks)
     */
    public function photos(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Use profile photos endpoint for own photos'
            ], 400);
        }

        try {
            // Check if can view photos
            $canView = $this->canViewUserPhotos($currentUser, $user);
            
            if (!$canView['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $canView['reason'],
                    'upgrade_required' => $canView['upgrade_required'] ?? false
                ], 403);
            }

            // Get photos based on privacy settings
            $photosQuery = $user->photos()
                ->where('status', 'approved')
                ->orderBy('is_profile_picture', 'desc')
                ->orderBy('sort_order');

            // Apply privacy filters
            if (!$canView['can_view_private']) {
                $photosQuery->where('is_private', false);
            }

            $photos = $photosQuery->get()->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'file_path' => Storage::url($photo->file_path),
                    'thumbnail_path' => Storage::url($photo->thumbnail_path),
                    'medium_path' => Storage::url($photo->medium_path),
                    'is_profile_picture' => $photo->is_profile_picture,
                    'is_private' => $photo->is_private,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'photos' => $photos,
                    'can_view_private' => $canView['can_view_private'],
                    'total_photos' => $photos->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user photos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request access to private photos
     */
    public function requestPhotoAccess(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot request access to own photos'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Send notification to user for photo access request
            Notification::create([
                'user_id' => $user->id,
                'type' => 'photo_request',
                'title' => 'Photo Access Request',
                'body' => $currentUser->first_name . ' wants to see your private photos',
                'data' => json_encode([
                    'requester_id' => $currentUser->id,
                    'requester_name' => $currentUser->first_name,
                    'message' => $request->get('message'),
                    'can_approve' => true,
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Photo access request sent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send photo access request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if current user can view target user's profile
     */
    private function canViewProfile($currentUser, $targetUser): array
    {
        // Basic checks
        if ($targetUser->status !== 'active') {
            return ['allowed' => false, 'reason' => 'User is not active'];
        }

        if ($targetUser->profile_status !== 'approved') {
            return ['allowed' => false, 'reason' => 'Profile not approved'];
        }

        // Check if blocked
        $isBlocked = UserMatch::where(function ($query) use ($currentUser, $targetUser) {
            $query->where('user_id', $currentUser->id)
                  ->where('target_user_id', $targetUser->id)
                  ->where('action', 'block');
        })->orWhere(function ($query) use ($currentUser, $targetUser) {
            $query->where('user_id', $targetUser->id)
                  ->where('target_user_id', $currentUser->id)
                  ->where('action', 'block');
        })->exists();

        if ($isBlocked) {
            return ['allowed' => false, 'reason' => 'Cannot view blocked profile'];
        }

        // Premium restrictions
        $targetProfile = $targetUser->profile;
        if ($targetProfile && $targetProfile->show_profile_to_premium_only && !$currentUser->is_premium_active) {
            return [
                'allowed' => false, 
                'reason' => 'This profile is only visible to premium members',
                'upgrade_required' => true
            ];
        }

        return [
            'allowed' => true,
            'permissions' => [
                'can_view_basic' => true,
                'can_view_contact' => $this->canViewContactInfo($currentUser, $targetUser),
                'can_view_horoscope' => $this->canViewHoroscope($currentUser, $targetUser),
                'can_view_income' => $this->canViewIncome($currentUser, $targetUser),
            ]
        ];
    }

    /**
     * Additional privacy check methods
     */
    private function canViewContactInfo($currentUser, $targetUser): bool
    {
        $profile = $targetUser->profile;
        if (!$profile) return false;

        // If show_contact_info is false, only matches can see
        if (!$profile->show_contact_info) {
            return UserMatch::where(function ($query) use ($currentUser, $targetUser) {
                $query->where('user_id', $currentUser->id)->where('target_user_id', $targetUser->id);
            })->orWhere(function ($query) use ($currentUser, $targetUser) {
                $query->where('user_id', $targetUser->id)->where('target_user_id', $currentUser->id);
            })->whereNotNull('matched_at')->exists();
        }

        return true;
    }

    private function canViewHoroscope($currentUser, $targetUser): bool
    {
        $profile = $targetUser->profile;
        return $profile ? $profile->show_horoscope : false;
    }

    private function canViewIncome($currentUser, $targetUser): bool
    {
        $profile = $targetUser->profile;
        return $profile ? $profile->show_income : false;
    }

    private function canViewUserPhotos($currentUser, $targetUser): array
    {
        $profileCheck = $this->canViewProfile($currentUser, $targetUser);
        if (!$profileCheck['allowed']) {
            return $profileCheck;
        }

        $canViewPrivate = false;

        // Premium users or matches can view private photos
        if ($currentUser->is_premium_active) {
            $canViewPrivate = true;
        }

        // Matches can always view private photos
        $isMatch = UserMatch::where(function ($query) use ($currentUser, $targetUser) {
            $query->where('user_id', $currentUser->id)->where('target_user_id', $targetUser->id);
        })->orWhere(function ($query) use ($currentUser, $targetUser) {
            $query->where('user_id', $targetUser->id)->where('target_user_id', $currentUser->id);
        })->whereNotNull('matched_at')->exists();

        if ($isMatch) {
            $canViewPrivate = true;
        }

        return [
            'allowed' => true,
            'can_view_private' => $canViewPrivate,
            'reason' => null,
        ];
    }

    /**
     * Format user profile data with privacy
     */
    private function formatUserProfile($user, $currentUser): array
    {
        $permissions = $this->canViewProfile($currentUser, $user)['permissions'];
        
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'age' => $user->age,
            'gender' => $user->gender,
            'last_active_at' => $user->last_active_at,
            'is_online' => $user->last_active_at && $user->last_active_at->diffInMinutes(now()) < 15,
            'is_premium' => $user->is_premium_active,
            'profile_picture' => $user->profilePicture ? 
                Storage::url($user->profilePicture->file_path) : null,
        ];

        // Add profile data if available
        if ($user->profile) {
            $profile = $user->profile;
            $userData = array_merge($userData, [
                'bio' => $profile->bio,
                'occupation' => $profile->occupation,
                'education' => $profile->education,
                'height' => $profile->height,
                'marital_status' => $profile->marital_status,
                'religion' => $profile->religion,
                'caste' => $profile->caste,
                'mother_tongue' => $profile->mother_tongue,
                'city' => $profile->city,
                'state' => $profile->state,
                'country' => $profile->country,
                'family_type' => $profile->family_type,
                'diet' => $profile->diet,
                'smoking' => $profile->smoking,
                'drinking' => $profile->drinking,
            ]);

            // Conditional data based on permissions
            if ($permissions['can_view_contact']) {
                $userData['phone'] = $user->phone;
                $userData['whatsapp_number'] = $profile->whatsapp_number;
            }

            if ($permissions['can_view_income']) {
                $userData['annual_income_usd'] = $profile->annual_income_usd;
                $userData['income_currency'] = $profile->income_currency;
            }
        }

        return $userData;
    }

    /**
     * Record profile view (could be expanded to separate table)
     */
    private function recordProfileView($viewer, $viewed): void
    {
        // Simple implementation - could be expanded to profile_views table
        // For now, we'll just update view count if field exists
    }

    /**
     * Basic compatibility calculation
     */
    private function calculateBasicCompatibility($user1, $user2): int
    {
        // Simplified version - full implementation would be in MatchController
        $score = 50; // Base score

        if ($user1->preferences && $user2->profile) {
            $preferences = $user1->preferences;
            $profile = $user2->profile;

            // Age compatibility
            if ($user2->age >= $preferences->min_age && $user2->age <= $preferences->max_age) {
                $score += 20;
            }

            // Location compatibility
            if ($preferences->preferred_countries && 
                in_array($profile->country, $preferences->preferred_countries)) {
                $score += 15;
            }

            // Religion compatibility
            if ($preferences->religions && in_array($profile->religion, $preferences->religions)) {
                $score += 15;
            }
        }

        return min(100, $score);
    }
}
