<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
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
        $dashboardUrl = config('app.frontend_url', 'http://localhost:4200') . '/app/dashboard';

        return (new MailMessage)
            ->subject('Your Profile is Approved! - ' . config('app.name'))
            ->view('emails.profile.approved', [
                'user' => $notifiable,
                'dashboardUrl' => $dashboardUrl,
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
            'type' => 'profile_approved',
            'title' => 'Profile Approved',
            'message' => 'Your profile has been approved and is now visible to other members.',
            'user_id' => $notifiable->id,
            'action_url' => config('app.frontend_url') . '/app/dashboard',
            'action_text' => 'Go to Dashboard',
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
