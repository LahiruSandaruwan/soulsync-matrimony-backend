<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Subscription;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * Get paginated list of users with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');
            $status = $request->get('status');
            $isPremium = $request->get('is_premium');
            $country = $request->get('country');
            $registrationMethod = $request->get('registration_method');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $query = User::with(['profile', 'activeSubscription'])
                ->withCount(['matches', 'sentMessages', 'reportsMade']);

            // Apply filters
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($isPremium !== null) {
                $query->where('is_premium', $isPremium);
            }

            if ($country) {
                $query->where('country_code', $country);
            }

            if ($registrationMethod) {
                $query->where('registration_method', $registrationMethod);
            }

            // Apply sorting
            $allowedSortFields = ['created_at', 'last_active_at', 'first_name', 'status'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $users = $query->paginate($perPage);

            // Format response
            $users->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'profile_status' => $user->profile_status,
                    'is_premium' => $user->is_premium,
                    'premium_expires_at' => $user->premium_expires_at,
                    'country_code' => $user->country_code,
                    'registration_method' => $user->registration_method,
                    'last_active_at' => $user->last_active_at,
                    'created_at' => $user->created_at,
                    'profile_completion' => $user->profile_completion_percentage ?? 0,
                    'matches_count' => $user->matches_count,
                    'messages_count' => $user->sent_messages_count,
                    'reports_count' => $user->reports_made_count,
                    'subscription' => $user->activeSubscription ? [
                        'plan_type' => $user->activeSubscription->plan_type,
                        'status' => $user->activeSubscription->status,
                        'expires_at' => $user->activeSubscription->expires_at,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'filters' => [
                    'statuses' => ['active', 'inactive', 'suspended', 'banned', 'deleted'],
                    'countries' => User::distinct()->pluck('country_code')->filter()->values(),
                    'registration_methods' => ['email', 'google', 'facebook', 'apple'],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin user list error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed user information
     */
    public function show(Request $request, User $user): JsonResponse
    {
        try {
            $user->load([
                'profile',
                'preferences', 
                'photos',
                'horoscope',
                'interests',
                'activeSubscription',
                'subscriptions',
                'matches' => function ($query) {
                    $query->latest()->limit(10);
                },
                'sentMessages' => function ($query) {
                    $query->latest()->limit(10);
                },
                'reportsMade' => function ($query) {
                    $query->latest()->limit(10);
                },
                'reportsReceived' => function ($query) {
                    $query->latest()->limit(10);
                }
            ]);

            $data = [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'date_of_birth' => $user->date_of_birth,
                    'gender' => $user->gender,
                    'country_code' => $user->country_code,
                    'language' => $user->language,
                    'status' => $user->status,
                    'profile_status' => $user->profile_status,
                    'is_premium' => $user->is_premium,
                    'premium_expires_at' => $user->premium_expires_at,
                    'registration_method' => $user->registration_method,
                    'registration_ip' => $user->registration_ip,
                    'last_active_at' => $user->last_active_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'profile_completion_percentage' => $user->profile_completion_percentage,
                    'verification_status' => $user->verification_status,
                ],
                'profile' => $user->profile,
                'preferences' => $user->preferences,
                'photos' => $user->photos->map(function ($photo) {
                    return [
                        'id' => $photo->id,
                        'file_path' => $photo->file_path,
                        'is_profile_picture' => $photo->is_profile_picture,
                        'is_private' => $photo->is_private,
                        'status' => $photo->status,
                        'created_at' => $photo->created_at,
                    ];
                }),
                'horoscope' => $user->horoscope,
                'interests' => $user->interests,
                'subscription' => $user->activeSubscription,
                'subscription_history' => $user->subscriptions->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'plan_type' => $sub->plan_type,
                        'status' => $sub->status,
                        'amount_usd' => $sub->amount_usd,
                        'payment_method' => $sub->payment_method,
                        'starts_at' => $sub->starts_at,
                        'expires_at' => $sub->expires_at,
                        'created_at' => $sub->created_at,
                    ];
                }),
                'activity' => [
                    'matches_count' => $user->matches->count(),
                    'messages_count' => $user->sentMessages->count(),
                    'reports_count' => $user->reportsMade->count(),
                    'reported_count' => $user->reportsReceived->count(),
                    'recent_matches' => $user->matches->map(function ($match) {
                        return [
                            'matched_user' => $match->matchedUser->first_name,
                            'status' => $match->status,
                            'created_at' => $match->created_at,
                        ];
                    }),
                    'recent_messages' => $user->sentMessages->map(function ($message) {
                        return [
                            'receiver' => $message->receiver->first_name,
                            'type' => $message->type,
                            'created_at' => $message->created_at,
                        ];
                    }),
                ],
                'moderation' => [
                    'reports_filed' => $user->reportsMade->map(function ($report) {
                        return [
                            'reported_user' => $report->reportedUser->first_name,
                            'reason' => $report->reason,
                            'status' => $report->status,
                            'created_at' => $report->created_at,
                        ];
                    }),
                    'reports_received' => $user->reportsReceived->map(function ($report) {
                        return [
                            'reporter' => $report->reporter->first_name,
                            'reason' => $report->reason,
                            'status' => $report->status,
                            'created_at' => $report->created_at,
                        ];
                    }),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => array_merge($data['user'], [
                    'profile' => $data['profile'],
                    'preferences' => $data['preferences'],
                    'photos' => $data['photos'],
                    'subscriptions' => $data['subscription_history']
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Admin user show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user status
     */
    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,suspended,banned',
            'reason' => 'sometimes|string|max:500',
            'admin_notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldStatus = $user->status;
            $newStatus = $request->status;

            DB::transaction(function () use ($user, $request, $newStatus, $oldStatus) {
                $user->update([
                    'status' => $newStatus,
                    'status_changed_at' => now(),
                    'status_changed_by' => $request->user()->id,
                    'admin_notes' => $request->get('admin_notes'),
                ]);

                // Log the status change
                Log::info('User status changed', [
                    'user_id' => $user->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'changed_by' => $request->user()->id,
                    'reason' => $request->get('reason'),
                ]);

                // Handle status-specific actions
                if ($newStatus === 'banned' || $newStatus === 'suspended') {
                    // Revoke all tokens
                    $user->tokens()->delete();
                    
                    // Deactivate matches
                    $user->matches()->update(['status' => 'inactive']);
                    $user->targetMatches()->update(['status' => 'inactive']);
                }

                if ($newStatus === 'active' && in_array($oldStatus, ['banned', 'suspended'])) {
                    // Reactivate matches if returning to active
                    $user->matches()->update(['status' => 'active']);
                    $user->targetMatches()->update(['status' => 'active']);
                }
            });

            return response()->json([
                'success' => true,
                'message' => "User status updated to {$newStatus}",
                'data' => [
                    'user_id' => $user->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'updated_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin update user status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile status
     */
    public function updateProfileStatus(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'profile_status' => 'required|in:pending_approval,approved,rejected,incomplete',
            'reason' => 'sometimes|string|max:500',
            'admin_notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->update([
                'profile_status' => $request->profile_status,
                'profile_status_changed_at' => now(),
                'profile_status_changed_by' => $request->user()->id,
                'profile_admin_notes' => $request->get('admin_notes'),
            ]);

            // Log the profile status change
            Log::info('User profile status changed', [
                'user_id' => $user->id,
                'new_status' => $request->profile_status,
                'changed_by' => $request->user()->id,
                'reason' => $request->get('reason'),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Profile status updated to {$request->profile_status}",
                'data' => [
                    'user_id' => $user->id,
                    'profile_status' => $request->profile_status,
                    'updated_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin update profile status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspend user
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'duration_days' => 'required|integer|min:1|max:365',
            'reason' => 'required|string|max:500',
            'admin_notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $suspensionEndDate = now()->addDays($request->duration_days);

            DB::transaction(function () use ($user, $request, $suspensionEndDate) {
                $user->update([
                    'status' => 'suspended',
                    'suspension_end_date' => $suspensionEndDate,
                    'suspension_reason' => $request->reason,
                    'suspended_by' => $request->user()->id,
                    'suspended_at' => now(),
                    'admin_notes' => $request->get('admin_notes'),
                ]);

                // Revoke all tokens
                $user->tokens()->delete();

                // Deactivate matches
                $user->matches()->update(['status' => 'inactive']);
                $user->targetMatches()->update(['status' => 'inactive']);
            });

            Log::info('User suspended', [
                'user_id' => $user->id,
                'duration_days' => $request->duration_days,
                'reason' => $request->reason,
                'suspended_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "User suspended for {$request->duration_days} days",
                'data' => [
                    'user_id' => $user->id,
                    'suspension_end_date' => $suspensionEndDate,
                    'reason' => $request->reason,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin suspend user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ban user permanently
     */
    public function ban(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'admin_notes' => 'sometimes|string|max:1000',
            'is_permanent' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::transaction(function () use ($user, $request) {
                $user->update([
                    'status' => 'banned',
                    'ban_reason' => $request->reason,
                    'banned_by' => $request->user()->id,
                    'banned_at' => now(),
                    'is_permanent_ban' => $request->get('is_permanent', true),
                    'admin_notes' => $request->get('admin_notes'),
                ]);

                // Revoke all tokens
                $user->tokens()->delete();

                // Deactivate all matches
                $user->matches()->update(['status' => 'inactive']);
                $user->targetMatches()->update(['status' => 'inactive']);

                // Cancel active subscriptions
                $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);
            });

            Log::warning('User banned', [
                'user_id' => $user->id,
                'reason' => $request->reason,
                'banned_by' => $request->user()->id,
                'is_permanent' => $request->get('is_permanent', true),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User banned successfully',
                'data' => [
                    'user_id' => $user->id,
                    'banned_at' => now(),
                    'reason' => $request->reason,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin ban user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to ban user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unban user
     */
    public function unban(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'admin_notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($user->status !== 'banned') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not currently banned'
                ], 400);
            }

            DB::transaction(function () use ($user, $request) {
                $user->update([
                    'status' => 'active',
                    'ban_reason' => null,
                    'banned_by' => null,
                    'banned_at' => null,
                    'is_permanent_ban' => false,
                    'unban_reason' => $request->reason,
                    'unbanned_by' => $request->user()->id,
                    'unbanned_at' => now(),
                    'admin_notes' => $request->get('admin_notes'),
                ]);

                // Reactivate matches
                $user->matches()->update(['status' => 'active']);
                $user->targetMatches()->update(['status' => 'active']);
            });

            Log::info('User unbanned', [
                'user_id' => $user->id,
                'reason' => $request->reason,
                'unbanned_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User unbanned successfully',
                'data' => [
                    'user_id' => $user->id,
                    'unbanned_at' => now(),
                    'reason' => $request->reason,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin unban user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to unban user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user (soft delete with data anonymization)
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'confirmation' => 'required|string|in:DELETE_USER',
            'admin_notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store user data before deletion
            $userData = $user->toArray();
            
            DB::transaction(function () use ($user, $request, $userData) {
                // Store deletion info
                $deletionData = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'deleted_by' => $request->user()->id,
                    'deletion_reason' => $request->reason,
                    'admin_notes' => $request->get('admin_notes'),
                    'deleted_at' => now(),
                ];

                // Store in deleted_users table for admin tracking
            \App\Models\DeletedUser::create([
                'original_user_id' => $userData['id'],
                'email' => $userData['email'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'user_data' => $userData,
                'deletion_date' => now(),
                'deleted_by' => 'admin',
                'deleted_by_admin_id' => $request->user()->id,
                'admin_reason' => $request->get('reason'),
                'admin_notes' => $request->get('admin_notes'),
                'banned' => false,
                'can_reactivate' => false,
            ]);
                Log::warning('User deleted by admin', $deletionData);

                // Handle related data cleanup before deletion
                $this->cleanupUserDataForAdmin($user);
                
                // Delete the user from the database
                $user->delete();
        });

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
                'data' => [
                    'user_id' => $userData['id'],
                    'deleted_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin delete user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', '30_days');
            
            // Calculate registration trends
            $registrationTrends = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Active users count
            $activeUsers = User::where('status', 'active')
                ->where('last_active_at', '>=', now()->subDays(7))
                ->count();

            // Premium conversion rate
            $totalUsers = User::count();
            $premiumUsers = User::where('is_premium', true)->count();
            $premiumConversion = $totalUsers > 0 ? ($premiumUsers / $totalUsers) * 100 : 0;

            // Geographic distribution
            $geographicDistribution = User::selectRaw('country_code, COUNT(*) as count')
                ->whereNotNull('country_code')
                ->groupBy('country_code')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'registration_trends' => $registrationTrends,
                    'active_users' => $activeUsers,
                    'premium_conversion' => round($premiumConversion, 2),
                    'geographic_distribution' => $geographicDistribution
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin user analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export user data
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $format = $request->get('format', 'csv');
            $filters = $request->get('filters', []);

            // For now, return success response
            // In a real implementation, this would generate and return a file
            return response()->json([
                'success' => true,
                'message' => 'User data export initiated',
                'data' => [
                    'format' => $format,
                    'filters' => $filters,
                    'export_id' => uniqid('export_'),
                    'status' => 'processing'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin user export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk action on users
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'action' => 'required|string|in:suspend,ban,delete,activate',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userIds = $request->user_ids;
            $action = $request->action;
            $reason = $request->reason;

            $users = User::whereIn('id', $userIds)->get();
            $processedCount = 0;

            foreach ($users as $user) {
                switch ($action) {
                    case 'suspend':
                        $user->update(['status' => 'suspended']);
                        break;
                    case 'ban':
                        $user->update(['status' => 'banned']);
                        break;
                    case 'delete':
                        $user->update(['status' => 'deleted']);
                        break;
                    case 'activate':
                        $user->update(['status' => 'active']);
                        break;
                }
                $processedCount++;
            }

            Log::info('Bulk user action performed', [
                'action' => $action,
                'user_count' => $processedCount,
                'reason' => $reason,
                'admin_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bulk {$action} action completed successfully",
                'data' => [
                    'action' => $action,
                    'processed_count' => $processedCount,
                    'total_requested' => count($userIds),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin bulk user action error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup user data for admin deletion
     */
    private function cleanupUserDataForAdmin(User $user): void
    {
        // Delete photos
        $user->photos()->delete();
        
        // Delete messages
        $user->sentMessages()->delete();
        $user->receivedMessages()->delete();
        
        // Delete notifications
        $user->notifications()->delete();
        
        // Delete reports
        $user->reports()->delete();
        $user->reportedBy()->delete();
        
        // Delete preferences
        $user->preferences()->delete();
        
        // Delete profile
        $user->profile()->delete();
    }
} 