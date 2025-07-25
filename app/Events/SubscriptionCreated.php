<?php

namespace App\Events;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;
    public $user;
    public $createdAt;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, User $user, $createdAt = null)
    {
        $this->subscription = $subscription;
        $this->user = $user;
        $this->createdAt = $createdAt ?? now();
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
            'subscription' => [
                'id' => $this->subscription->id,
                'plan_name' => $this->subscription->plan_name,
                'plan_type' => $this->subscription->plan_type,
                'amount' => $this->subscription->amount,
                'currency' => $this->subscription->currency,
                'status' => $this->subscription->status,
                'start_date' => $this->subscription->start_date?->toISOString(),
                'end_date' => $this->subscription->end_date?->toISOString(),
                'created_at' => $this->subscription->created_at->toISOString(),
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name . ' ' . $this->user->last_name,
            ],
            'created_at' => $this->createdAt->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'subscription.created';
    }
} 