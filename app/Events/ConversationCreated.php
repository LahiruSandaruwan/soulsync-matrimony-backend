<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation;
    public $createdBy;
    public $participants;

    /**
     * Create a new event instance.
     */
    public function __construct(Conversation $conversation, User $createdBy, $participants = [])
    {
        $this->conversation = $conversation;
        $this->createdBy = $createdBy;
        $this->participants = $participants;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Broadcast to all participants
        foreach ($this->conversation->participants as $participant) {
            $channels[] = new PrivateChannel('user.' . $participant->id);
        }
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'conversation' => [
                'id' => $this->conversation->id,
                'type' => $this->conversation->conversation_type,
                'name' => $this->conversation->name,
                'created_at' => $this->conversation->created_at->toISOString(),
                'updated_at' => $this->conversation->updated_at->toISOString(),
            ],
            'created_by' => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->first_name . ' ' . $this->createdBy->last_name,
            ],
            'participants' => $this->conversation->participants->map(function ($participant) {
                return [
                    'id' => $participant->id,
                    'name' => $participant->first_name . ' ' . $participant->last_name,
                    'profile_picture' => $participant->profilePicture?->file_path,
                ];
            }),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.conversation.created';
    }
} 