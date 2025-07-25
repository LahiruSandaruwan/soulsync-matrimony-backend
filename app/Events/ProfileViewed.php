<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileViewed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $viewer;
    public $viewedUser;
    public $viewedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(User $viewer, User $viewedUser, $viewedAt = null)
    {
        $this->viewer = $viewer;
        $this->viewedUser = $viewedUser;
        $this->viewedAt = $viewedAt ?? now();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('profile-views.' . $this->viewedUser->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'viewer' => [
                'id' => $this->viewer->id,
                'name' => $this->viewer->first_name . ' ' . $this->viewer->last_name,
                'profile_picture' => $this->viewer->profilePicture?->file_path,
            ],
            'viewed_user' => [
                'id' => $this->viewedUser->id,
                'name' => $this->viewedUser->first_name . ' ' . $this->viewedUser->last_name,
            ],
            'viewed_at' => $this->viewedAt->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'profile.viewed';
    }
} 