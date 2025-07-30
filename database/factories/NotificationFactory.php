<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_id' => User::factory(),
            'type' => $this->faker->randomElement(['match', 'message', 'like', 'profile_view', 'subscription', 'payment', 'system']),
            'title' => $this->faker->sentence(),
            'message' => $this->faker->paragraph(),
            'data' => json_encode(['key' => 'value']),
            'status' => $this->faker->randomElement(['unread', 'read', 'archived']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'category' => $this->faker->randomElement(['match', 'message', 'like', 'super_like', 'profile_view', 'subscription', 'payment', 'system', 'admin', 'promotion', 'matching', 'communication', 'profile']),
            'sent_in_app' => true,
            'sent_email' => false,
            'sent_push' => false,
            'sent_sms' => false,
            'actions' => json_encode(['view_profile', 'reply']),
            'action_url' => $this->faker->url(),
            'group_key' => null,
            'batch_id' => null,
            'is_grouped' => false,
            'group_count' => 1,
            'is_persistent' => false,
            'click_tracked' => false,
        ];
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    /**
     * Indicate that the notification is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Indicate that the notification is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
        ]);
    }

    /**
     * Indicate that the notification is a match notification.
     */
    public function match(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'match',
            'category' => 'match',
            'title' => 'New Match Found!',
            'message' => 'You have a new potential match.',
        ]);
    }

    /**
     * Indicate that the notification is a message notification.
     */
    public function message(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'message',
            'category' => 'message',
            'title' => 'New Message',
            'message' => 'You have received a new message.',
        ]);
    }
} 