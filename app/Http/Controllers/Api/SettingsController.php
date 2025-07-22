<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    /**
     * Get user settings
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'preferences']);

        try {
            $settings = [
                'account' => [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'date_of_birth' => $user->date_of_birth,
                    'gender' => $user->gender,
                    'language' => $user->language,
                    'country_code' => $user->country_code,
                ],
                'privacy' => [
                    'show_profile_to_premium_only' => $user->profile?->show_profile_to_premium_only ?? false,
                    'show_contact_info' => $user->profile?->show_contact_info ?? true,
                    'show_horoscope' => $user->profile?->show_horoscope ?? true,
                    'show_income' => $user->profile?->show_income ?? false,
                    'show_last_seen' => $user->profile?->show_last_seen ?? true,
                    'allow_photo_requests' => $user->profile?->allow_photo_requests ?? true,
                ],
                'notifications' => json_decode($user->notification_preferences ?? '{}', true) ?: [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'notification_types' => ['match', 'message', 'like', 'super_like', 'subscription'],
                    'quiet_hours_start' => null,
                    'quiet_hours_end' => null,
                    'frequency' => 'immediate',
                ],
                'matching' => [
                    'auto_accept_matches' => $user->preferences?->auto_accept_matches ?? false,
                    'show_me_on_search' => $user->preferences?->show_me_on_search ?? true,
                    'preferred_distance_km' => $user->preferences?->preferred_distance_km ?? 50,
                ],
                'subscription' => [
                    'is_premium' => $user->is_premium_active,
                    'premium_expires_at' => $user->premium_expires_at,
                    'current_plan' => $user->activeSubscription?->plan_type ?? 'free',
                ],
                'security' => [
                    'two_factor_enabled' => false, // TODO: Implement 2FA
                    'login_notifications' => true,
                    'last_password_change' => null, // TODO: Track password changes
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update general settings
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'language' => 'sometimes|string|size:2|in:en,si,ta',
            'country_code' => 'sometimes|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->update($request->only([
                'first_name', 'last_name', 'phone', 'language', 'country_code'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => [
                    'user' => $user->only([
                        'first_name', 'last_name', 'phone', 'language', 'country_code'
                    ])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacy(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'show_profile_to_premium_only' => 'sometimes|boolean',
            'show_contact_info' => 'sometimes|boolean',
            'show_horoscope' => 'sometimes|boolean',
            'show_income' => 'sometimes|boolean',
            'show_last_seen' => 'sometimes|boolean',
            'allow_photo_requests' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Ensure user has a profile
            if (!$user->profile) {
                $user->profile()->create(['user_id' => $user->id]);
                $user->refresh();
            }

            $user->profile->update($request->only([
                'show_profile_to_premium_only',
                'show_contact_info',
                'show_horoscope',
                'show_income',
                'show_last_seen',
                'allow_photo_requests',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
                'data' => [
                    'privacy' => $user->profile->only([
                        'show_profile_to_premium_only',
                        'show_contact_info',
                        'show_horoscope',
                        'show_income',
                        'show_last_seen',
                        'allow_photo_requests',
                    ])
                ]
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
     * Update notification settings
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'notification_types' => 'sometimes|array',
            'notification_types.*' => 'in:match,message,like,super_like,profile_view,subscription,admin,system',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
            'frequency' => 'sometimes|in:immediate,hourly,daily,weekly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $currentPreferences = json_decode($user->notification_preferences ?? '{}', true);
            
            $newPreferences = array_merge($currentPreferences, $request->only([
                'email_notifications',
                'push_notifications',
                'notification_types',
                'quiet_hours_start',
                'quiet_hours_end',
                'frequency',
            ]));

            $user->update(['notification_preferences' => json_encode($newPreferences)]);

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully',
                'data' => [
                    'notification_preferences' => $newPreferences
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Revoke all other tokens for security
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update email
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            $user->update([
                'email' => $request->email,
                'email_verified_at' => null, // Require re-verification
            ]);

            // TODO: Send email verification
            // $user->sendEmailVerificationNotification();

            return response()->json([
                'success' => true,
                'message' => 'Email updated successfully. Please verify your new email address.',
                'data' => [
                    'email' => $user->email,
                    'email_verified' => false,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate account (temporary)
     */
    public function deactivateAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|max:500',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            $user->update([
                'status' => 'deactivated',
                'deactivated_at' => now(),
                'deactivation_reason' => $request->get('reason'),
            ]);

            // Revoke all tokens
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deactivated successfully. You can reactivate by logging in again.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete account (permanent)
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'confirmation' => 'required|string|in:DELETE_MY_ACCOUNT',
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            // Store deletion info before deleting
            $deletionData = [
                'user_id' => $user->id,
                'email' => $user->email,
                'deleted_at' => now(),
                'reason' => $request->get('reason'),
                'ip_address' => $request->ip(),
            ];

            // TODO: Store in separate deleted_accounts table
            // \App\Models\DeletedAccount::create($deletionData);

            // Soft delete or anonymize user data
            $user->update([
                'status' => 'deleted',
                'email' => 'deleted_' . $user->id . '@soulsync.com',
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'phone' => null,
                'deleted_at' => now(),
            ]);

            // Revoke all tokens
            $user->tokens()->delete();

            // TODO: Clean up related data (photos, messages, etc.)
            // Or keep for data integrity and just mark as deleted

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully. We\'re sorry to see you go.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account statistics
     */
    public function getAccountStats(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $stats = [
                'profile_completion' => $this->calculateProfileCompletion($user),
                'total_matches' => $user->matches()->whereNotNull('matched_at')->count(),
                'total_likes_sent' => $user->matches()->where('action', 'like')->count(),
                'total_likes_received' => \App\Models\UserMatch::where('target_user_id', $user->id)
                    ->where('action', 'like')->count(),
                'total_conversations' => $user->conversations()->count(),
                'total_messages_sent' => $user->sentMessages()->count(),
                'profile_views' => 0, // TODO: Implement profile views tracking
                'photos_count' => $user->photos()->count(),
                'account_age_days' => $user->created_at->diffInDays(now()),
                'last_active' => $user->last_active_at,
                'subscription_status' => [
                    'is_premium' => $user->is_premium_active,
                    'plan' => $user->activeSubscription?->plan_type ?? 'free',
                    'expires_at' => $user->premium_expires_at,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get account statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export user data (GDPR compliance)
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $userData = [
                'personal_info' => $user->only([
                    'first_name', 'last_name', 'email', 'phone', 'date_of_birth',
                    'gender', 'language', 'country_code', 'created_at'
                ]),
                'profile' => $user->profile?->toArray(),
                'preferences' => $user->preferences?->toArray(),
                'photos' => $user->photos()->get(['file_path', 'created_at'])->toArray(),
                'matches' => $user->matches()->get(['target_user_id', 'action', 'created_at'])->toArray(),
                'messages' => $user->sentMessages()->get(['content', 'type', 'created_at'])->toArray(),
                'subscriptions' => $user->subscriptions()->get(['plan_type', 'amount_usd', 'created_at'])->toArray(),
                'notifications' => $user->notifications()->get(['type', 'title', 'created_at'])->toArray(),
            ];

            // TODO: Generate downloadable file (JSON/CSV)
            // For now, return JSON response

            return response()->json([
                'success' => true,
                'message' => 'Data export generated successfully',
                'data' => $userData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion($user): int
    {
        $requiredFields = [
            'first_name', 'last_name', 'date_of_birth', 'gender'
        ];

        $profileFields = [
            'bio', 'occupation', 'education', 'height', 'religion', 'caste',
            'city', 'state', 'country'
        ];

        $completed = 0;
        $total = count($requiredFields) + count($profileFields) + 2; // +2 for photo and preferences

        // Check required user fields
        foreach ($requiredFields as $field) {
            if (!empty($user->$field)) {
                $completed++;
            }
        }

        // Check profile fields
        if ($user->profile) {
            foreach ($profileFields as $field) {
                if (!empty($user->profile->$field)) {
                    $completed++;
                }
            }
        }

        // Check for profile photo
        if ($user->photos()->where('is_profile_picture', true)->exists()) {
            $completed++;
        }

        // Check for preferences
        if ($user->preferences) {
            $completed++;
        }

        return (int) (($completed / $total) * 100);
    }
}
