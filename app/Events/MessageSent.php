<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $conversation;
    public $sender;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message, Conversation $conversation)
    {
        $this->message = $message;
        $this->conversation = $conversation;
        $this->sender = $message->sender;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->conversation->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'content' => $this->message->content,
                'message_type' => $this->message->message_type,
                'attachment_url' => $this->message->attachment_url,
                'attachment_type' => $this->message->attachment_type,
                'reply_to_id' => $this->message->reply_to_id,
                'forwarded_from_id' => $this->message->forwarded_from_id,
                'is_edited' => $this->message->is_edited,
                'created_at' => $this->message->created_at->toISOString(),
                'updated_at' => $this->message->updated_at->toISOString(),
            ],
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'profile_photo_url' => $this->sender->profile_photo_url,
            ],
            'conversation' => [
                'id' => $this->conversation->id,
                'type' => $this->conversation->conversation_type,
                'display_name' => $this->conversation->getDisplayName(),
                'last_message_at' => $this->conversation->last_message_at?->toISOString(),
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        return $this->message->message_type !== Message::MESSAGE_TYPE_SYSTEM;
    }
} 