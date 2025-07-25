<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDisliked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $disliker;
    public $dislikedUser;

    /**
     * Create a new event instance.
     */
    public function __construct(User $disliker, User $dislikedUser)
    {
        $this->disliker = $disliker;
        $this->dislikedUser = $dislikedUser;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Note: We typically don't broadcast dislikes to the disliked user
        // This event is mainly for internal tracking and analytics
        return [
            new PrivateChannel('user.' . $this->disliker->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'disliker' => [
                'id' => $this->disliker->id,
                'name' => $this->disliker->first_name . ' ' . $this->disliker->last_name,
            ],
            'disliked_user' => [
                'id' => $this->dislikedUser->id,
                'name' => $this->dislikedUser->first_name . ' ' . $this->dislikedUser->last_name,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'match.disliked';
    }
} 