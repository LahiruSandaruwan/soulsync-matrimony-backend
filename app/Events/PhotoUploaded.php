<?php

namespace App\Events;

use App\Models\UserPhoto;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $photo;
    public $user;
    public $uploadedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(UserPhoto $photo, User $user, $uploadedAt = null)
    {
        $this->photo = $photo;
        $this->user = $user;
        $this->uploadedAt = $uploadedAt ?? now();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'photo' => [
                'id' => $this->photo->id,
                'file_path' => $this->photo->file_path,
                'file_name' => $this->photo->file_name,
                'file_size' => $this->photo->file_size,
                'mime_type' => $this->photo->mime_type,
                'is_primary' => $this->photo->is_primary,
                'is_verified' => $this->photo->is_verified,
                'uploaded_at' => $this->photo->created_at->toISOString(),
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name . ' ' . $this->user->last_name,
            ],
            'uploaded_at' => $this->uploadedAt->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'profile.photo.uploaded';
    }
} 