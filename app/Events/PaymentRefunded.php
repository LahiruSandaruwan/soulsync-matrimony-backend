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

class PaymentRefunded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;
    public $user;
    public $refundData;
    public $refundedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, User $user, array $refundData = [], $refundedAt = null)
    {
        $this->subscription = $subscription;
        $this->user = $user;
        $this->refundData = $refundData;
        $this->refundedAt = $refundedAt ?? now();
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
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name . ' ' . $this->user->last_name,
            ],
            'refund_data' => $this->refundData,
            'refunded_at' => $this->refundedAt->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.refunded';
    }
} 