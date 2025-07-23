<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserMatch;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMatchNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [30, 120, 300]; // Retry after 30s, 2min, 5min

    protected User $user;
    protected User $matchedUser;
    protected string $notificationType;
    protected array $metadata;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, User $matchedUser, string $notificationType = 'new_match', array $metadata = [])
    {
        $this->user = $user;
        $this->matchedUser = $matchedUser;
        $this->notificationType = $notificationType;
        $this->metadata = $metadata;
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(PushNotificationService $pushService): void
    {
        try {
            // Check user notification preferences
            if (!$this->shouldSendNotification()) {
                Log::info('Notification skipped due to user preferences', [
                    'user_id' => $this->user->id,
                    'type' => $this->notificationType
                ]);
                return;
            }

            $this->sendNotifications($pushService);

            Log::info('Match notification sent successfully', [
                'user_id' => $this->user->id,
                'matched_user_id' => $this->matchedUser->id,
                'type' => $this->notificationType
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send match notification', [
                'user_id' => $this->user->id,
                'matched_user_id' => $this->matchedUser->id,
                'type' => $this->notificationType,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Send various types of notifications.
     */
    private function sendNotifications(PushNotificationService $pushService): void
    {
        switch ($this->notificationType) {
            case 'new_match':
                $this->sendNewMatchNotification($pushService);
                break;
            case 'mutual_match':
                $this->sendMutualMatchNotification($pushService);
                break;
            case 'super_like':
                $this->sendSuperLikeNotification($pushService);
                break;
            case 'profile_view':
                $this->sendProfileViewNotification($pushService);
                break;
            case 'interest_expressed':
                $this->sendInterestExpressedNotification($pushService);
                break;
            default:
                Log::warning('Unknown notification type', ['type' => $this->notificationType]);
        }
    }

    /**
     * Send new match notification.
     */
    private function sendNewMatchNotification(PushNotificationService $pushService): void
    {
        $title = "New Match Found! 💕";
        $body = "You have a new potential match waiting for you.";
        
        $data = [
            'type' => 'new_match',
            'matched_user_id' => $this->matchedUser->id,
            'matched_user_name' => $this->matchedUser->first_name,
            'action_url' => '/matches/' . $this->matchedUser->id,
            'compatibility_score' => $this->metadata['compatibility_score'] ?? null,
        ];

        // Send push notification
        $pushService->sendToUser($this->user, $title, $body, $data);

        // Create in-app notification
        $this->createInAppNotification($title, $body, $data);

        // Send email if enabled
        if ($this->user->email_notifications ?? true) {
            $this->sendNewMatchEmail();
        }
    }

    /**
     * Send mutual match notification.
     */
    private function sendMutualMatchNotification(PushNotificationService $pushService): void
    {
        $title = "It's a Match! 🎉";
        $body = "You and {$this->matchedUser->first_name} liked each other! Start chatting now.";
        
        $data = [
            'type' => 'mutual_match',
            'matched_user_id' => $this->matchedUser->id,
            'matched_user_name' => $this->matchedUser->first_name,
            'action_url' => '/chat/' . $this->matchedUser->id,
            'can_chat' => true,
        ];

        // Send push notification
        $pushService->sendToUser($this->user, $title, $body, $data);

        // Create in-app notification
        $this->createInAppNotification($title, $body, $data);

        // Send congratulations email
        if ($this->user->email_notifications ?? true) {
            $this->sendMutualMatchEmail();
        }
    }

    /**
     * Send super like notification.
     */
    private function sendSuperLikeNotification(PushNotificationService $pushService): void
    {
        $title = "Someone Super Liked You! ⭐";
        $body = "{$this->matchedUser->first_name} thinks you're amazing!";
        
        $data = [
            'type' => 'super_like',
            'liked_by_user_id' => $this->matchedUser->id,
            'liked_by_user_name' => $this->matchedUser->first_name,
            'action_url' => '/profile/' . $this->matchedUser->id,
            'is_premium_feature' => true,
        ];

        // Send push notification
        $pushService->sendToUser($this->user, $title, $body, $data);

        // Create in-app notification
        $this->createInAppNotification($title, $body, $data);
    }

    /**
     * Send profile view notification.
     */
    private function sendProfileViewNotification(PushNotificationService $pushService): void
    {
        // Only send if user is premium or it's a significant event
        if (!$this->user->is_premium_active && !($this->metadata['special_view'] ?? false)) {
            return;
        }

        $title = "Profile View";
        $body = "{$this->matchedUser->first_name} viewed your profile";
        
        $data = [
            'type' => 'profile_view',
            'viewer_user_id' => $this->matchedUser->id,
            'viewer_user_name' => $this->matchedUser->first_name,
            'action_url' => '/profile/' . $this->matchedUser->id,
        ];

        // Send push notification (less intrusive)
        $pushService->sendToUser($this->user, $title, $body, $data);

        // Create in-app notification
        $this->createInAppNotification($title, $body, $data, 'low');
    }

    /**
     * Send interest expressed notification.
     */
    private function sendInterestExpressedNotification(PushNotificationService $pushService): void
    {
        $title = "Someone is Interested! 💖";
        $body = "{$this->matchedUser->first_name} expressed interest in your profile";
        
        $data = [
            'type' => 'interest_expressed',
            'interested_user_id' => $this->matchedUser->id,
            'interested_user_name' => $this->matchedUser->first_name,
            'action_url' => '/profile/' . $this->matchedUser->id,
            'message' => $this->metadata['message'] ?? null,
        ];

        // Send push notification
        $pushService->sendToUser($this->user, $title, $body, $data);

        // Create in-app notification
        $this->createInAppNotification($title, $body, $data);
    }

    /**
     * Create in-app notification record.
     */
    private function createInAppNotification(string $title, string $body, array $data, string $priority = 'medium'): void
    {
        $this->user->notifications()->create([
            'type' => $this->notificationType,
            'title' => $title,
            'message' => $body,
            'data' => $data,
            'priority' => $priority,
            'is_read' => false,
            'expires_at' => now()->addDays(30), // Notifications expire after 30 days
        ]);
    }

    /**
     * Send new match email.
     */
    private function sendNewMatchEmail(): void
    {
        $data = [
            'user' => $this->user,
            'matched_user' => $this->matchedUser,
            'compatibility_score' => $this->metadata['compatibility_score'] ?? null,
            'view_match_url' => config('app.frontend_url') . '/matches/' . $this->matchedUser->id,
        ];

        Mail::send('emails.new-match', $data, function ($message) {
            $message->to($this->user->email, $this->user->full_name)
                   ->subject('New Match Found on ' . config('app.name'));
        });
    }

    /**
     * Send mutual match email.
     */
    private function sendMutualMatchEmail(): void
    {
        $data = [
            'user' => $this->user,
            'matched_user' => $this->matchedUser,
            'chat_url' => config('app.frontend_url') . '/chat/' . $this->matchedUser->id,
        ];

        Mail::send('emails.mutual-match', $data, function ($message) {
            $message->to($this->user->email, $this->user->full_name)
                   ->subject("It's a Match! 🎉 - " . config('app.name'));
        });
    }

    /**
     * Check if notification should be sent based on user preferences.
     */
    private function shouldSendNotification(): bool
    {
        // Check if user has notifications enabled
        if (!($this->user->push_notifications ?? true)) {
            return false;
        }

        // Check quiet hours
        $currentHour = now()->hour;
        $quietStart = 22; // 10 PM
        $quietEnd = 7;    // 7 AM
        
        if ($currentHour >= $quietStart || $currentHour < $quietEnd) {
            // Only send high-priority notifications during quiet hours
            return in_array($this->notificationType, ['mutual_match', 'super_like']);
        }

        // Check notification frequency limits
        $today = now()->startOfDay();
        $notificationCount = $this->user->notifications()
            ->where('type', $this->notificationType)
            ->where('created_at', '>=', $today)
            ->count();

        $dailyLimits = [
            'new_match' => 5,
            'profile_view' => $this->user->is_premium_active ? 20 : 5,
            'interest_expressed' => 10,
            'mutual_match' => 50, // No limit on mutual matches
            'super_like' => 50,   // No limit on super likes
        ];

        $limit = $dailyLimits[$this->notificationType] ?? 10;
        
        return $notificationCount < $limit;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendMatchNotification job failed permanently', [
            'user_id' => $this->user->id,
            'matched_user_id' => $this->matchedUser->id,
            'type' => $this->notificationType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could create a failed notification record for admin review
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'notifications',
            'match-notifications',
            "user:{$this->user->id}",
            "type:{$this->notificationType}"
        ];
    }
}
