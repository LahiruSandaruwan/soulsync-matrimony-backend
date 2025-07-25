<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\App;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchFound implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;
    public $user1;
    public $user2;

    /**
     * Create a new event instance.
     */
    public function __construct(UserMatch $match, User $user1, User $user2)
    {
        $this->match = $match;
        $this->user1 = $user1;
        $this->user2 = $user2;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Don't broadcast in testing environment
        if (App::environment('testing')) {
            return [];
        }
        
        return [
            new PrivateChannel('matches.user.' . $this->user1->id),
            new PrivateChannel('matches.user.' . $this->user2->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'match' => [
                'id' => $this->match->id,
                'matched_at' => $this->match->matched_at ? $this->match->matched_at->toISOString() : now()->toISOString(),
                'user1' => [
                    'id' => $this->user1->id,
                    'first_name' => $this->user1->first_name,
                    'profile_picture' => $this->user1->profilePicture?->file_path,
                ],
                'user2' => [
                    'id' => $this->user2->id,
                    'first_name' => $this->user2->first_name,
                    'profile_picture' => $this->user2->profilePicture?->file_path,
                ],
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'match.found';
    }
} 