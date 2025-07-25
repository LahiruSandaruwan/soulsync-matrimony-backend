<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PushNotificationService
{
    private ?string $fcmServerKey;
    private string $fcmUrl;

    public function __construct()
    {
        $this->fcmServerKey = config('services.firebase.fcm_server_key');
        $this->fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    }

    /**
     * Send push notification to a user
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        try {
            // Check if user has push notifications enabled
            $preferences = json_decode($user->notification_preferences ?? '{}', true);
            
            if (!($preferences['push_notifications'] ?? true)) {
                Log::info('Push notifications disabled for user', ['user_id' => $user->id]);
                return true; // Not an error, just disabled
            }

            // Check quiet hours
            if ($this->isQuietHours($preferences)) {
                Log::info('Message not sent due to quiet hours', ['user_id' => $user->id]);
                return true;
            }

            // Get user's FCM tokens (users can have multiple devices)
            $fcmTokens = $this->getUserFCMTokens($user);
            
            if (empty($fcmTokens)) {
                Log::info('No FCM tokens found for user', ['user_id' => $user->id]);
                return true;
            }

            $success = true;
            foreach ($fcmTokens as $token) {
                if (!$this->sendNotification($token, $title, $body, $data)) {
                    $success = false;
                }
            }

            return $success;

        } catch (Exception $e) {
            Log::error('Push notification error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send push notification for a message
     */
    public function sendMessageNotification(User $recipient, User $sender, string $messageContent): bool
    {
        $title = "New message from {$sender->first_name}";
        $body = $this->truncateMessage($messageContent);
        
        $data = [
            'type' => 'message',
            'sender_id' => $sender->id,
            'sender_name' => $sender->first_name,
            'message_preview' => $body,
            'click_action' => 'OPEN_CHAT',
        ];

        return $this->sendToUser($recipient, $title, $body, $data);
    }

    /**
     * Send push notification for a match
     */
    public function sendMatchNotification(User $user, User $matchedUser): bool
    {
        $title = "It's a Match! 💕";
        $body = "You and {$matchedUser->first_name} liked each other!";
        
        $data = [
            'type' => 'match',
            'matched_user_id' => $matchedUser->id,
            'matched_user_name' => $matchedUser->first_name,
            'click_action' => 'OPEN_MATCHES',
        ];

        return $this->sendToUser($user, $title, $body, $data);
    }

    /**
     * Send push notification for a like
     */
    public function sendLikeNotification(User $recipient, User $liker): bool
    {
        $title = "Someone liked you! ❤️";
        $body = "{$liker->first_name} liked your profile";
        
        $data = [
            'type' => 'like',
            'liker_id' => $liker->id,
            'liker_name' => $liker->first_name,
            'click_action' => 'OPEN_LIKES',
        ];

        return $this->sendToUser($recipient, $title, $body, $data);
    }

    /**
     * Send push notification for profile view
     */
    public function sendProfileViewNotification(User $recipient, User $viewer): bool
    {
        // Only send if user is premium and has this notification enabled
        if (!$recipient->is_premium_active) {
            return true;
        }

        $title = "Profile View 👀";
        $body = "{$viewer->first_name} viewed your profile";
        
        $data = [
            'type' => 'profile_view',
            'viewer_id' => $viewer->id,
            'viewer_name' => $viewer->first_name,
            'click_action' => 'OPEN_PROFILE_VIEWS',
        ];

        return $this->sendToUser($recipient, $title, $body, $data);
    }

    /**
     * Send subscription notification
     */
    public function sendSubscriptionNotification(User $user, string $action, array $details = []): bool
    {
        $titles = [
            'activated' => 'Premium Activated! 🎉',
            'expired' => 'Premium Expired',
            'cancelled' => 'Subscription Cancelled',
            'failed' => 'Payment Failed',
        ];

        $bodies = [
            'activated' => 'Welcome to SoulSync Premium! Enjoy unlimited features.',
            'expired' => 'Your premium subscription has expired. Renew to continue enjoying premium features.',
            'cancelled' => 'Your subscription has been cancelled as requested.',
            'failed' => 'We couldn\'t process your payment. Please update your payment method.',
        ];

        $title = $titles[$action] ?? 'Subscription Update';
        $body = $bodies[$action] ?? 'Your subscription status has been updated.';
        
        $data = [
            'type' => 'subscription',
            'action' => $action,
            'click_action' => 'OPEN_SUBSCRIPTION',
        ];

        return $this->sendToUser($user, $title, $body, $data);
    }

    /**
     * Send custom notification
     */
    public function sendCustomNotification(User $user, Notification $notification): bool
    {
        $data = [
            'type' => $notification->type,
            'notification_id' => $notification->id,
            'click_action' => 'OPEN_NOTIFICATIONS',
        ];

        if ($notification->data) {
            $notificationData = json_decode($notification->data, true);
            $data = array_merge($data, $notificationData);
        }

        return $this->sendToUser($user, $notification->title, $notification->body, $data);
    }

    /**
     * Send FCM notification to a specific token
     */
    private function sendNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        // Skip sending if FCM server key is not configured (e.g., in testing)
        if (!$this->fcmServerKey) {
            Log::info('FCM server key not configured, skipping push notification', [
                'title' => $title,
                'fcm_token' => substr($fcmToken, 0, 20) . '...',
            ]);
            return true;
        }

        try {
            $payload = [
                'to' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => $this->getUnreadCount($fcmToken),
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'click_action' => $data['click_action'] ?? 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => $this->getUnreadCount($fcmToken),
                        ],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->fcmServerKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                if (($result['success'] ?? 0) > 0) {
                    Log::info('Push notification sent successfully', [
                        'fcm_token' => substr($fcmToken, 0, 20) . '...',
                        'title' => $title
                    ]);
                    return true;
                } else {
                    Log::warning('FCM returned failure', [
                        'result' => $result,
                        'fcm_token' => substr($fcmToken, 0, 20) . '...',
                    ]);
                    
                    // Handle invalid tokens
                    if (isset($result['results'][0]['error'])) {
                        $error = $result['results'][0]['error'];
                        if (in_array($error, ['InvalidRegistration', 'NotRegistered'])) {
                            $this->removeInvalidFCMToken($fcmToken);
                        }
                    }
                    return false;
                }
            } else {
                Log::error('FCM request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

        } catch (Exception $e) {
            Log::error('FCM notification error', [
                'error' => $e->getMessage(),
                'fcm_token' => substr($fcmToken, 0, 20) . '...',
            ]);
            return false;
        }
    }

    /**
     * Get user's FCM tokens from database
     */
    private function getUserFCMTokens(User $user): array
    {
        // This would typically be stored in a user_devices table
        // For now, return from user preferences or a simple field
        $tokens = [];
        
        if ($user->fcm_token) {
            $tokens[] = $user->fcm_token;
        }

        // If stored as JSON array in preferences
        $preferences = json_decode($user->notification_preferences ?? '{}', true);
        if (isset($preferences['fcm_tokens']) && is_array($preferences['fcm_tokens'])) {
            $tokens = array_merge($tokens, $preferences['fcm_tokens']);
        }

        return array_unique(array_filter($tokens));
    }

    /**
     * Remove invalid FCM token
     */
    private function removeInvalidFCMToken(string $fcmToken): void
    {
        try {
            // Remove from all users who have this token
            // This is a simplified implementation
            Log::info('Removing invalid FCM token', ['token' => substr($fcmToken, 0, 20) . '...']);
            
            // In a real implementation, you'd update the user_devices table
            // or remove from user preferences
            
        } catch (Exception $e) {
            Log::error('Error removing invalid FCM token', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get unread notification count for badge
     */
    private function getUnreadCount(string $fcmToken): int
    {
        try {
            // This would get the count from the user associated with this token
            // For now, return a default
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Check if current time is within user's quiet hours
     */
    private function isQuietHours(array $preferences): bool
    {
        $quietStart = $preferences['quiet_hours_start'] ?? null;
        $quietEnd = $preferences['quiet_hours_end'] ?? null;
        
        if (!$quietStart || !$quietEnd) {
            return false;
        }

        $now = now()->format('H:i');
        
        // Handle overnight quiet hours (e.g., 22:00 to 08:00)
        if ($quietStart > $quietEnd) {
            return $now >= $quietStart || $now <= $quietEnd;
        }
        
        // Handle same-day quiet hours (e.g., 13:00 to 15:00)
        return $now >= $quietStart && $now <= $quietEnd;
    }

    /**
     * Truncate message content for notification
     */
    private function truncateMessage(string $content, int $maxLength = 100): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        
        return substr($content, 0, $maxLength - 3) . '...';
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications(array $userIds, string $title, string $body, array $data = []): array
    {
        $results = [];
        
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $results[$userId] = $this->sendToUser($user, $title, $body, $data);
            } else {
                $results[$userId] = false;
            }
        }
        
        return $results;
    }

    /**
     * Register FCM token for user
     */
    public function registerFCMToken(User $user, string $fcmToken, string $deviceType = 'mobile'): bool
    {
        try {
            $preferences = json_decode($user->notification_preferences ?? '{}', true);
            $tokens = $preferences['fcm_tokens'] ?? [];
            
            if (!in_array($fcmToken, $tokens)) {
                $tokens[] = $fcmToken;
                $preferences['fcm_tokens'] = $tokens;
                
                $user->update([
                    'notification_preferences' => json_encode($preferences),
                    'fcm_token' => $fcmToken, // Keep most recent as primary
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('Error registering FCM token', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 