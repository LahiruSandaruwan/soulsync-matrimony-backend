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

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation;
    public $updatedBy;
    public $changes;

    /**
     * Create a new event instance.
     */
    public function __construct(Conversation $conversation, User $updatedBy, array $changes = [])
    {
        $this->conversation = $conversation;
        $this->updatedBy = $updatedBy;
        $this->changes = $changes;
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
                'status' => $this->conversation->status,
                'updated_at' => $this->conversation->updated_at->toISOString(),
            ],
            'updated_by' => [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->first_name . ' ' . $this->updatedBy->last_name,
            ],
            'changes' => $this->changes,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.conversation.updated';
    }
} 