<?php

namespace App\Listeners;

use App\Events\MatchFound;
use App\Jobs\SendMatchNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendMatchNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(MatchFound $event): void
    {
        try {
            $match = $event->match;
            $user1 = $event->user1;
            $user2 = $event->user2;

            // Determine notification type based on match status
            $notificationType = $this->getNotificationType($match);

            // Get compatibility score for notifications
            $metadata = [
                'compatibility_score' => $match->compatibility_score,
                'match_id' => $match->id,
                'match_type' => $match->match_type,
            ];

            // Send notification to user1 about user2
            SendMatchNotification::dispatch($user1, $user2, $notificationType, $metadata)
                ->delay(now()->addSeconds(rand(5, 30))); // Random delay for natural feeling

            // Send notification to user2 about user1 (if it's a mutual match)
            if ($notificationType === 'mutual_match') {
                SendMatchNotification::dispatch($user2, $user1, $notificationType, $metadata)
                    ->delay(now()->addSeconds(rand(5, 30)));
            }

            Log::info('Match notification jobs dispatched', [
                'match_id' => $match->id,
                'user1_id' => $user1->id,
                'user2_id' => $user2->id,
                'notification_type' => $notificationType
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch match notification jobs', [
                'match_id' => $event->match->id,
                'error' => $e->getMessage()
            ]);
            
            // Don't fail the entire event if notification fails
        }
    }

    /**
     * Determine the notification type based on match status.
     */
    private function getNotificationType($match): string
    {
        // Check if it's a mutual match (both users liked each other)
        if ($match->status === 'mutual' || $match->can_communicate) {
            return 'mutual_match';
        }

        // Check if it's a super like
        if ($match->user_action === 'super_liked' || $match->matched_user_action === 'super_liked') {
            return 'super_like';
        }

        // Default to new match
        return 'new_match';
    }

    /**
     * Handle a job failure.
     */
    public function failed(MatchFound $event, \Throwable $exception): void
    {
        Log::error('SendMatchNotificationListener failed', [
            'match_id' => $event->match->id,
            'user1_id' => $event->user1->id,
            'user2_id' => $event->user2->id,
            'error' => $exception->getMessage()
        ]);
    }
}
