<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Jobs\SendMessageNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendMessageNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $sender = $event->sender;
            $recipient = $event->recipient;

            // Dispatch notification job
            SendMessageNotification::dispatch($recipient, $sender, $message)
                ->delay(now()->addSeconds(2)); // Small delay to ensure message is saved

            Log::info('Message notification job dispatched', [
                'message_id' => $message->id,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'conversation_id' => $message->conversation_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch message notification job', [
                'message_id' => $event->message->id,
                'error' => $e->getMessage()
            ]);
            
            // Don't fail the entire event if notification fails
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(MessageSent $event, \Throwable $exception): void
    {
        Log::error('SendMessageNotificationListener failed', [
            'message_id' => $event->message->id,
            'sender_id' => $event->sender->id,
            'recipient_id' => $event->recipient->id,
            'error' => $exception->getMessage()
        ]);
    }
}
