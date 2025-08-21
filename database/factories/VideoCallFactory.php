<?php

namespace Database\Factories;

use App\Models\VideoCall;
use App\Models\User;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VideoCall>
 */
class VideoCallFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = VideoCall::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['pending', 'accepted', 'rejected', 'ended', 'missed'];
        $status = $this->faker->randomElement($statuses);
        
        $initiatedAt = $this->faker->dateTimeBetween('-1 month', 'now');
        $acceptedAt = null;
        $endedAt = null;
        $duration = null;

        // Set timestamps based on status
        if (in_array($status, ['accepted', 'ended'])) {
            $acceptedAt = $this->faker->dateTimeBetween($initiatedAt, 'now');
        }

        if (in_array($status, ['ended', 'rejected', 'missed'])) {
            $endedAt = $acceptedAt 
                ? $this->faker->dateTimeBetween($acceptedAt, 'now')
                : $this->faker->dateTimeBetween($initiatedAt, 'now');
        }

        // Calculate duration for ended calls
        if ($status === 'ended' && $acceptedAt && $endedAt) {
            $duration = max(1, $endedAt->getTimestamp() - $acceptedAt->getTimestamp());
        }

        return [
            'caller_id' => User::factory(),
            'callee_id' => User::factory(),
            'conversation_id' => Conversation::factory(),
            'call_id' => 'call_' . Str::random(16),
            'room_id' => 'room_' . $this->faker->randomNumber(8) . '_' . time(),
            'caller_token' => $status !== 'pending' ? 'token_caller_' . Str::random(32) : null,
            'callee_token' => $status === 'accepted' ? 'token_callee_' . Str::random(32) : null,
            'status' => $status,
            'initiated_at' => $initiatedAt,
            'accepted_at' => $acceptedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'end_reason' => $status === 'ended' ? $this->faker->randomElement(['normal', 'network_issue', 'technical_issue', 'user_ended']) : null,
            'quality_rating' => $status === 'ended' && $this->faker->boolean(70) ? $this->faker->numberBetween(1, 5) : null,
            'feedback' => $status === 'ended' && $this->faker->boolean(30) ? $this->faker->sentence() : null,
            'metadata' => $this->faker->boolean(20) ? [
                'connection_quality' => $this->faker->randomElement(['excellent', 'good', 'fair', 'poor']),
                'device_type' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
                'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            ] : null,
        ];
    }

    /**
     * Indicate that the video call is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'accepted_at' => null,
            'ended_at' => null,
            'duration_seconds' => null,
            'end_reason' => null,
            'quality_rating' => null,
            'feedback' => null,
            'caller_token' => 'token_caller_' . Str::random(32),
            'callee_token' => 'token_callee_' . Str::random(32),
        ]);
    }

    /**
     * Indicate that the video call is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'accepted_at' => $this->faker->dateTimeBetween($attributes['initiated_at'] ?? '-1 hour', 'now'),
            'ended_at' => null,
            'duration_seconds' => null,
            'end_reason' => null,
            'caller_token' => 'token_caller_' . Str::random(32),
            'callee_token' => 'token_callee_' . Str::random(32),
        ]);
    }

    /**
     * Indicate that the video call is ended.
     */
    public function ended(): static
    {
        $acceptedAt = $this->faker->dateTimeBetween('-2 hours', '-30 minutes');
        $endedAt = $this->faker->dateTimeBetween($acceptedAt, 'now');
        $duration = $endedAt->getTimestamp() - $acceptedAt->getTimestamp();

        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'accepted_at' => $acceptedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'end_reason' => $this->faker->randomElement(['normal', 'network_issue', 'technical_issue', 'user_ended']),
            'quality_rating' => $this->faker->boolean(80) ? $this->faker->numberBetween(1, 5) : null,
            'feedback' => $this->faker->boolean(40) ? $this->faker->sentence() : null,
            'caller_token' => 'token_caller_' . Str::random(32),
            'callee_token' => 'token_callee_' . Str::random(32),
        ]);
    }

    /**
     * Indicate that the video call was rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'accepted_at' => null,
            'ended_at' => $this->faker->dateTimeBetween($attributes['initiated_at'] ?? '-1 hour', 'now'),
            'duration_seconds' => null,
            'end_reason' => null,
            'quality_rating' => null,
            'feedback' => null,
        ]);
    }

    /**
     * Indicate that the video call was missed.
     */
    public function missed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'missed',
            'accepted_at' => null,
            'ended_at' => $this->faker->dateTimeBetween($attributes['initiated_at'] ?? '-1 hour', 'now'),
            'duration_seconds' => null,
            'end_reason' => 'timeout',
            'quality_rating' => null,
            'feedback' => null,
        ]);
    }

    /**
     * Indicate that the video call has a specific quality rating.
     */
    public function withQuality(int $rating, string $feedback = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quality_rating' => $rating,
            'feedback' => $feedback ?? $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the video call is between specific users.
     */
    public function betweenUsers(int $callerId, int $calleeId): static
    {
        return $this->state(fn (array $attributes) => [
            'caller_id' => $callerId,
            'callee_id' => $calleeId,
        ]);
    }

    /**
     * Indicate that the video call is associated with a conversation.
     */
    public function withConversation(int $conversationId): static
    {
        return $this->state(fn (array $attributes) => [
            'conversation_id' => $conversationId,
        ]);
    }

    /**
     * Indicate that the video call has specific metadata.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }
}
