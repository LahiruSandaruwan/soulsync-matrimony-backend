<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $liker;
    public $likedUser;
    public $isMatch;

    /**
     * Create a new event instance.
     */
    public function __construct(User $liker, User $likedUser, bool $isMatch = false)
    {
        $this->liker = $liker;
        $this->likedUser = $likedUser;
        $this->isMatch = $isMatch;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->likedUser->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'liker' => [
                'id' => $this->liker->id,
                'name' => $this->liker->first_name . ' ' . $this->liker->last_name,
                'profile_picture' => $this->liker->profilePicture?->file_path,
            ],
            'liked_user' => [
                'id' => $this->likedUser->id,
                'name' => $this->likedUser->first_name . ' ' . $this->likedUser->last_name,
            ],
            'is_match' => $this->isMatch,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'match.liked';
    }
} 