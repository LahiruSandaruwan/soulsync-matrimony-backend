<?php

namespace Database\Factories;

use App\Models\UserMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserMatchFactory extends Factory
{
    protected $model = UserMatch::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'matched_user_id' => User::factory(),
            'match_type' => $this->faker->randomElement(['ai_suggestion', 'search_result', 'mutual_interest', 'premium_suggestion']),
            'status' => $this->faker->randomElement(['pending', 'liked', 'super_liked', 'disliked', 'blocked', 'mutual', 'expired']),
            'user_action' => $this->faker->randomElement(['none', 'liked', 'super_liked', 'disliked', 'blocked']),
            'matched_user_action' => $this->faker->randomElement(['none', 'liked', 'super_liked', 'disliked', 'blocked']),
            'compatibility_score' => $this->faker->randomFloat(2, 0, 100),
            'preference_score' => $this->faker->randomFloat(2, 0, 100),
            'horoscope_score' => $this->faker->randomFloat(2, 0, 100),
            'ai_score' => $this->faker->randomFloat(2, 0, 100),
            'matching_factors' => [],
            'common_interests' => [],
            'compatibility_details' => [],
            'can_communicate' => true,
            'is_premium_match' => false,
            'is_boosted' => false,
        ];
    }
} 