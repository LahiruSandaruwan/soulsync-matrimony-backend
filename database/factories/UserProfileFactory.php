<?php

namespace Database\Factories;

use App\Models\UserProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'height_cm' => $this->faker->numberBetween(150, 190),
            'weight_kg' => $this->faker->randomFloat(2, 50, 100),
            'body_type' => $this->faker->randomElement(['slim', 'average', 'athletic', 'heavy']),
            'complexion' => $this->faker->randomElement(['very_fair', 'fair', 'wheatish', 'dark', 'very_dark']),
            'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'physically_challenged' => false,
            'current_city' => 'Colombo',
            'current_country' => 'Sri Lanka',
            'education_level' => 'bachelors',
            'occupation' => 'engineer',
            'religion' => 'buddhist',
            'diet' => 'vegetarian',
            'smoking' => 'never',
            'drinking' => 'never',
            'marital_status' => $this->faker->randomElement(['never_married', 'divorced', 'widowed', 'separated']),
            'profile_verified' => false,
            'income_verified' => false,
            'education_verified' => false,
            'profile_completion_percentage' => 100,
        ];
    }
} 