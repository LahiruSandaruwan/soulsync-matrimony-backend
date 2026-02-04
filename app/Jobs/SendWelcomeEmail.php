<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [60, 300, 600]; // Retry after 1min, 5min, 10min

    protected User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->sendWelcomeEmail();
            
            Log::info('Welcome email sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Send the welcome email.
     */
    private function sendWelcomeEmail(): void
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));

        $data = [
            'user' => $this->user,
            'app_name' => config('app.name'),
            'frontend_url' => $frontendUrl,
            'login_url' => $frontendUrl . '/login',
            'profile_url' => $frontendUrl . '/app/profile',
            'completeProfileUrl' => $frontendUrl . '/app/profile/edit',
            'next_steps' => $this->getNextSteps(),
        ];

        Mail::send('emails.welcome', $data, function ($message) {
            $message->to($this->user->email, $this->user->full_name)
                   ->subject('Welcome to ' . config('app.name') . ' - Find Your Perfect Match!');
        });
    }

    /**
     * Get personalized next steps for the user.
     */
    private function getNextSteps(): array
    {
        $steps = [];
        
        if (!$this->user->profile || $this->user->profile_completion_percentage < 50) {
            $steps[] = [
                'title' => 'Complete Your Profile',
                'description' => 'Add photos, personal details, and preferences to get better matches',
                'url' => config('app.frontend_url') . '/profile/edit',
                'priority' => 'high'
            ];
        }

        if (!$this->user->photos()->where('is_profile_picture', true)->exists()) {
            $steps[] = [
                'title' => 'Upload Profile Photo',
                'description' => 'Profiles with photos get 10x more matches',
                'url' => config('app.frontend_url') . '/profile/photos',
                'priority' => 'high'
            ];
        }

        if (!$this->user->preferences) {
            $steps[] = [
                'title' => 'Set Partner Preferences',
                'description' => 'Tell us what you\'re looking for in a life partner',
                'url' => config('app.frontend_url') . '/preferences',
                'priority' => 'medium'
            ];
        }

        $steps[] = [
            'title' => 'Browse Matches',
            'description' => 'Start exploring potential matches in your area',
            'url' => config('app.frontend_url') . '/matches',
            'priority' => 'medium'
        ];

        return $steps;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendWelcomeEmail job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could send admin notification here or create a failed email record
    }
}
