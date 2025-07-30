<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        return [
            'user_one_id' => $user1->id,
            'user_two_id' => $user2->id,
            'type' => $this->faker->randomElement(['match', 'interest', 'premium']),
            'is_group' => false,
            'status' => 'active',
            'created_by' => $user1->id,
            'metadata' => $this->faker->optional()->json(),
            'user_one_unread_count' => $this->faker->numberBetween(0, 10),
            'user_two_unread_count' => $this->faker->numberBetween(0, 10),
            'total_messages' => $this->faker->numberBetween(0, 100),
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the conversation is a match conversation.
     */
    public function match(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'match',
        ]);
    }

    /**
     * Indicate that the conversation is a direct conversation.
     */
    public function direct(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'direct',
        ]);
    }

    /**
     * Indicate that the conversation is blocked.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
            'blocked_by' => $this->faker->randomElement([1, 2]),
            'blocked_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'block_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the conversation is archived by user one.
     */
    public function archivedByUserOne(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived_user_one' => true,
        ]);
    }

    /**
     * Indicate that the conversation is archived by user two.
     */
    public function archivedByUserTwo(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived_user_two' => true,
        ]);
    }
} 