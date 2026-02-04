<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The rejection reason.
     */
    public ?string $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct(?string $reason = null)
    {
        $this->reason = $reason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $editProfileUrl = config('app.frontend_url', 'http://localhost:4200') . '/app/profile/edit';

        return (new MailMessage)
            ->subject('Profile Update Required - ' . config('app.name'))
            ->view('emails.profile.rejected', [
                'user' => $notifiable,
                'reason' => $this->reason,
                'editProfileUrl' => $editProfileUrl,
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
            'type' => 'profile_rejected',
            'title' => 'Profile Update Required',
            'message' => $this->reason ?? 'Your profile requires updates before it can be approved.',
            'user_id' => $notifiable->id,
            'action_url' => config('app.frontend_url') . '/app/profile/edit',
            'action_text' => 'Update Profile',
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
