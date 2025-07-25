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

class PaymentSuccessful implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;
    public $user;
    public $paymentData;
    public $processedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, User $user, array $paymentData = [], $processedAt = null)
    {
        $this->subscription = $subscription;
        $this->user = $user;
        $this->paymentData = $paymentData;
        $this->processedAt = $processedAt ?? now();
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
            'payment_data' => $this->paymentData,
            'processed_at' => $this->processedAt->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.successful';
    }
} 