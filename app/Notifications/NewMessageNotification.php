<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The sender user.
     */
    public User $sender;

    /**
     * The message preview.
     */
    public ?string $preview;

    /**
     * The conversation ID.
     */
    public int $conversationId;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $sender, ?string $preview = null, int $conversationId = 0)
    {
        $this->sender = $sender;
        $this->preview = $preview;
        $this->conversationId = $conversationId;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Check if user has email notifications enabled
        if (!$notifiable->email_notifications) {
            return ['database'];
        }

        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $chatUrl = config('app.frontend_url', 'http://localhost:4200') . '/app/chat';
        if ($this->conversationId) {
            $chatUrl .= '/' . $this->conversationId;
        }

        // Load sender with photos for the template
        $this->sender->load('photos');

        return (new MailMessage)
            ->subject('New Message from ' . $this->sender->first_name . ' - ' . config('app.name'))
            ->view('emails.message.new-message', [
                'user' => $notifiable,
                'sender' => $this->sender,
                'preview' => $this->preview,
                'chatUrl' => $chatUrl,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_message',
            'title' => 'New Message',
            'message' => $this->sender->first_name . ' sent you a message',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name,
            'conversation_id' => $this->conversationId,
            'preview' => $this->preview ? \Str::limit($this->preview, 100) : null,
            'action_url' => config('app.frontend_url') . '/app/chat/' . $this->conversationId,
            'action_text' => 'View Message',
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
