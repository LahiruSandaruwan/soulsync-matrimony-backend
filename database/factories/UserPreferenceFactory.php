<?php

namespace Database\Factories;

use App\Models\UserPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPreferenceFactory extends Factory
{
    protected $model = UserPreference::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'min_age' => 25,
            'max_age' => 35,
            'preferred_genders' => ['male', 'female'],
            'preferred_countries' => ['Sri Lanka'],
            'preferred_cities' => ['Colombo'],
            'preferred_religions' => ['buddhist'],
            'preferred_education_levels' => ['bachelors', 'masters'],
            'preferred_diets' => ['vegetarian'],
            'preferred_smoking_habits' => ['never'],
            'preferred_drinking_habits' => ['never'],
            'accept_physically_challenged' => false,
            'accept_with_children' => false,
            'show_only_verified_profiles' => false,
        ];
    }
} 