<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'type' => 'sometimes|in:match,message,like,super_like,profile_view,subscription,admin,system',
            'category' => 'sometimes|in:match,message,like,super_like,profile_view,subscription,payment,system,admin,promotion,matching,communication,profile',
            'status' => 'sometimes|in:unread,read,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 50);
            $type = $request->get('type');
            $category = $request->get('category');
            $status = $request->get('status', 'all');
            $unreadOnly = $request->get('unread_only', false);
            $offset = ($page - 1) * $limit;

            // Build query
            $query = $user->notifications()->orderBy('created_at', 'desc');

            // Apply filters
            if ($type) {
                $query->where('type', $type);
            }

            if ($category) {
                $query->where('category', $category);
            }

            if ($unreadOnly || $status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($status === 'read') {
                $query->whereNotNull('read_at');
            }

            // Filter out expired notifications
            $query->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });

            $totalCount = $query->count();
            $notifications = $query->offset($offset)->limit($limit)->get();

            // Format notifications
            $formattedNotifications = $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data ? json_decode($notification->data, true) : null,
                    'priority' => $notification->priority,
                    'category' => $notification->category,
                    'action_url' => $notification->getActionUrl(),
                    'action_text' => $notification->action_text,
                    'metadata' => $notification->metadata,
                    'is_high_priority' => $notification->priority === 'high' || $notification->priority === 'urgent',
                    'is_read' => !is_null($notification->read_at),
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'time_ago' => $notification->created_at->diffForHumans(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedNotifications,
                'meta' => [
                    'current_page' => $page,
                    'total' => $totalCount,
                    'per_page' => $limit,
                    'last_page' => ceil($totalCount / $limit),
                    'has_more' => ($offset + $limit) < $totalCount,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Check if notification belongs to user
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to notification'
            ], 403);
        }

        try {
            $notification->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => [
                    'notification_id' => $notification->id,
                    'read_at' => $notification->read_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $updatedCount = $user->notifications()
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'data' => [
                    'updated_count' => $updatedCount,
                    'unread_count' => 0,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Check if notification belongs to user
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to notification'
            ], 403);
        }

        try {
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $unreadCount = $user->notifications()->whereNull('read_at')->count();

            // Get counts by type
            $countsByType = $user->notifications()
                ->whereNull('read_at')
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_unread' => $unreadCount,
                    'by_type' => $countsByType,
                    'has_unread' => $unreadCount > 0,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'notification_types' => 'sometimes|array',
            'notification_types.*' => 'in:match,message,like,super_like,profile_view,subscription,admin,system',
            'quiet_hours_start' => 'sometimes|date_format:H:i',
            'quiet_hours_end' => 'sometimes|date_format:H:i',
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
            // Update user notification preferences
            $preferences = [
                'email_notifications' => $request->get('email_notifications', true),
                'push_notifications' => $request->get('push_notifications', true),
                'notification_types' => $request->get('notification_types', [
                    'match', 'message', 'like', 'super_like', 'subscription'
                ]),
                'quiet_hours_start' => $request->get('quiet_hours_start'),
                'quiet_hours_end' => $request->get('quiet_hours_end'),
                'frequency' => $request->get('frequency', 'immediate'),
            ];

            // Store in user preferences or separate table
            $user->update(['notification_preferences' => json_encode($preferences)]);

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated successfully',
                'data' => [
                    'preferences' => $preferences,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register device for push notifications
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string',
            'device_type' => 'required|in:ios,android,web',
            'device_name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store device token for push notifications
            $deviceData = [
                'token' => $request->device_token,
                'type' => $request->device_type,
                'name' => $request->get('device_name', ''),
                'registered_at' => now(),
            ];

            // Update user's device tokens (stored as JSON)
            $devices = json_decode($user->device_tokens ?? '[]', true);
            
            // Remove existing token if found and add new one
            $devices = array_filter($devices, function($device) use ($request) {
                return $device['token'] !== $request->device_token;
            });
            
            $devices[] = $deviceData;
            
            $user->update(['device_tokens' => json_encode($devices)]);

            return response()->json([
                'success' => true,
                'message' => 'Device registered for push notifications',
                'data' => [
                    'device_token' => $request->device_token,
                    'device_type' => $request->device_type,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register device',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send test notification
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Create test notification
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => 'system',
                'title' => 'Test Notification',
                'body' => 'This is a test notification to verify your notification settings.',
                'data' => json_encode([
                    'test' => true,
                    'timestamp' => now()->toISOString(),
                ]),
            ]);

            // Send push notification if enabled
            $pushService = app(\App\Services\PushNotificationService::class);
            $pushService->sendCustomNotification($user, $notification);
            // $this->sendPushNotification($user, $notification);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully',
                'data' => [
                    'notification_id' => $notification->id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific notification
     */
    public function show(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Check if user owns this notification
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }

                    return response()->json([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data ? json_decode($notification->data, true) : null,
                    'priority' => $notification->priority,
                    'category' => $notification->category,
                    'action_url' => $notification->getActionUrl(),
                    'action_text' => $notification->action_text,
                    'metadata' => $notification->metadata,
                    'is_read' => !is_null($notification->read_at),
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'time_ago' => $notification->created_at->diffForHumans(),
                ]
            ]);
    }

    /**
     * Mark batch of notifications as read
     */
    public function markBatchAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $batchId = $request->batch_id;
            
            // Mark notifications with the batch_id as read and remove batch_id
            $updatedCount = $user->notifications()
                ->where('batch_id', $batchId)
                ->whereNull('read_at')
                ->update(['read_at' => now(), 'batch_id' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Batch notifications marked as read',
                'data' => [
                    'updated_count' => $updatedCount,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark batch as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup old notifications
     */
    public function cleanup(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'older_than_days' => 'required|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $olderThanDays = $request->older_than_days;
            $cutoffDate = now()->subDays($olderThanDays);
            
            // Delete old notifications that are not persistent
            $deletedCount = $user->notifications()
                ->where('created_at', '<', $cutoffDate)
                ->where('is_persistent', false)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Old notifications cleaned up',
                'data' => [
                    'deleted_count' => $deletedCount,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all notifications
     */
    public function clearAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:match,message,like,super_like,profile_view,subscription,admin,system',
            'older_than_days' => 'sometimes|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $user->notifications();

            // Apply filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('older_than_days')) {
                $cutoffDate = now()->subDays($request->older_than_days);
                $query->where('created_at', '<', $cutoffDate);
            }

            $deletedCount = $query->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notifications cleared successfully',
                'data' => [
                    'deleted_count' => $deletedCount,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $stats = [
                'total_notifications' => $user->notifications()->count(),
                'unread_notifications' => $user->notifications()->whereNull('read_at')->count(),
                'today_notifications' => $user->notifications()
                    ->whereDate('created_at', today())
                    ->count(),
                'this_week_notifications' => $user->notifications()
                    ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
                'by_type' => $user->notifications()
                    ->selectRaw('type, count(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'recent_activity' => $user->notifications()
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['type', 'title', 'created_at'])
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notification statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
