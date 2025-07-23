<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Message;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60]; // Retry after 10s, 30s, 1min

    protected User $recipient;
    protected User $sender;
    protected Message $message;

    /**
     * Create a new job instance.
     */
    public function __construct(User $recipient, User $sender, Message $message)
    {
        $this->recipient = $recipient;
        $this->sender = $sender;
        $this->message = $message;
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(PushNotificationService $pushService): void
    {
        try {
            // Check if notification should be sent
            if (!$this->shouldSendNotification()) {
                Log::info('Message notification skipped', [
                    'recipient_id' => $this->recipient->id,
                    'sender_id' => $this->sender->id,
                    'message_id' => $this->message->id
                ]);
                return;
            }

            $this->sendNotifications($pushService);

            Log::info('Message notification sent successfully', [
                'recipient_id' => $this->recipient->id,
                'sender_id' => $this->sender->id,
                'message_id' => $this->message->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send message notification', [
                'recipient_id' => $this->recipient->id,
                'sender_id' => $this->sender->id,
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Send push and in-app notifications.
     */
    private function sendNotifications(PushNotificationService $pushService): void
    {
        $title = $this->getNotificationTitle();
        $body = $this->getNotificationBody();
        $data = $this->getNotificationData();

        // Send push notification
        $pushService->sendMessageNotification($this->recipient, $this->sender, $this->message->content);

        // Create in-app notification
        $this->createInAppNotification($title, $body, $data);
    }

    /**
     * Get notification title based on message type.
     */
    private function getNotificationTitle(): string
    {
        switch ($this->message->type) {
            case 'text':
                return "New message from {$this->sender->first_name}";
            case 'image':
                return "{$this->sender->first_name} sent you a photo";
            case 'audio':
                return "{$this->sender->first_name} sent you a voice message";
            case 'video':
                return "{$this->sender->first_name} sent you a video";
            case 'file':
                return "{$this->sender->first_name} sent you a file";
            default:
                return "New message from {$this->sender->first_name}";
        }
    }

    /**
     * Get notification body based on message content.
     */
    private function getNotificationBody(): string
    {
        switch ($this->message->type) {
            case 'text':
                return $this->truncateMessage($this->message->content);
            case 'image':
                return "📷 Photo";
            case 'audio':
                return "🎤 Voice message";
            case 'video':
                return "🎥 Video";
            case 'file':
                return "📎 File attachment";
            default:
                return "New message";
        }
    }

    /**
     * Get notification data payload.
     */
    private function getNotificationData(): array
    {
        return [
            'type' => 'message',
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name,
            'sender_avatar' => $this->sender->profilePicture?->url,
            'message_type' => $this->message->type,
            'message_preview' => $this->getNotificationBody(),
            'action_url' => '/chat/' . $this->message->conversation_id,
            'timestamp' => $this->message->created_at->toISOString(),
        ];
    }

    /**
     * Create in-app notification.
     */
    private function createInAppNotification(string $title, string $body, array $data): void
    {
        $this->recipient->notifications()->create([
            'type' => 'message',
            'title' => $title,
            'message' => $body,
            'data' => $data,
            'priority' => 'high', // Messages are high priority
            'is_read' => false,
            'expires_at' => now()->addDays(7), // Message notifications expire after 7 days
        ]);
    }

    /**
     * Truncate message content for notification display.
     */
    private function truncateMessage(string $content, int $length = 100): string
    {
        if (strlen($content) <= $length) {
            return $content;
        }
        
        return substr($content, 0, $length - 3) . '...';
    }

    /**
     * Check if notification should be sent.
     */
    private function shouldSendNotification(): bool
    {
        // Don't send notifications to yourself
        if ($this->recipient->id === $this->sender->id) {
            return false;
        }

        // Check if recipient has notifications enabled
        if (!($this->recipient->push_notifications ?? true)) {
            return false;
        }

        // Check if recipient is currently online and active in this conversation
        if ($this->isRecipientActiveInConversation()) {
            return false; // Don't send notification if user is actively chatting
        }

        // Check conversation status
        $conversation = $this->message->conversation;
        if (!$conversation || $conversation->status !== 'active') {
            return false;
        }

        // Check if sender is blocked by recipient
        if ($this->isUserBlocked()) {
            return false;
        }

        // Check quiet hours for the recipient
        if ($this->isQuietHours()) {
            return false;
        }

        // Check rate limiting for message notifications
        if ($this->isRateLimited()) {
            return false;
        }

        return true;
    }

    /**
     * Check if recipient is currently active in this conversation.
     */
    private function isRecipientActiveInConversation(): bool
    {
        // Check if user was active in the last 30 seconds
        $lastActivity = $this->recipient->last_active_at;
        
        if (!$lastActivity || $lastActivity->diffInSeconds(now()) > 30) {
            return false;
        }

        // Check if user is currently viewing this conversation (if we track this)
        $currentConversationKey = "user:{$this->recipient->id}:current_conversation";
        $currentConversation = cache()->get($currentConversationKey);
        
        return $currentConversation == $this->message->conversation_id;
    }

    /**
     * Check if sender is blocked by recipient.
     */
    private function isUserBlocked(): bool
    {
        $conversation = $this->message->conversation;
        
        return $conversation->blocked_by === $this->recipient->id;
    }

    /**
     * Check if it's quiet hours for the recipient.
     */
    private function isQuietHours(): bool
    {
        // Use recipient's timezone if available, otherwise use app timezone
        $timezone = $this->recipient->timezone ?? config('app.timezone');
        $recipientTime = now()->setTimezone($timezone);
        
        $currentHour = $recipientTime->hour;
        $quietStart = 22; // 10 PM
        $quietEnd = 7;    // 7 AM
        
        return $currentHour >= $quietStart || $currentHour < $quietEnd;
    }

    /**
     * Check if message notifications are rate limited.
     */
    private function isRateLimited(): bool
    {
        $rateLimitKey = "message_notifications:{$this->recipient->id}";
        $rateLimitPeriod = 60; // 1 minute
        $maxNotifications = 5; // Max 5 notifications per minute
        
        $currentCount = cache()->get($rateLimitKey, 0);
        
        if ($currentCount >= $maxNotifications) {
            return true;
        }
        
        // Increment counter
        cache()->put($rateLimitKey, $currentCount + 1, $rateLimitPeriod);
        
        return false;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendMessageNotification job failed permanently', [
            'recipient_id' => $this->recipient->id,
            'sender_id' => $this->sender->id,
            'message_id' => $this->message->id,
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
            'message-notifications',
            "recipient:{$this->recipient->id}",
            "sender:{$this->sender->id}",
            "conversation:{$this->message->conversation_id}"
        ];
    }
}
