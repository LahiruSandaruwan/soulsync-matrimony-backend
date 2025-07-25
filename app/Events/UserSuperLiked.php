<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserSuperLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $superLiker;
    public $superLikedUser;
    public $isMatch;

    /**
     * Create a new event instance.
     */
    public function __construct(User $superLiker, User $superLikedUser, bool $isMatch = false)
    {
        $this->superLiker = $superLiker;
        $this->superLikedUser = $superLikedUser;
        $this->isMatch = $isMatch;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->superLikedUser->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'super_liker' => [
                'id' => $this->superLiker->id,
                'name' => $this->superLiker->first_name . ' ' . $this->superLiker->last_name,
                'profile_picture' => $this->superLiker->profilePicture?->file_path,
            ],
            'super_liked_user' => [
                'id' => $this->superLikedUser->id,
                'name' => $this->superLikedUser->first_name . ' ' . $this->superLikedUser->last_name,
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
        return 'match.super_liked';
    }
} 