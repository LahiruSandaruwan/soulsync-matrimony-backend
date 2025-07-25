<?php

namespace Database\Factories;

use App\Models\Horoscope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HoroscopeFactory extends Factory
{
    protected $model = Horoscope::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'moon_sign' => $this->faker->randomElement(['aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo', 'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces']),
            'nakshatra' => $this->faker->word(),
            'zodiac_sign' => $this->faker->randomElement(['aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo', 'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces']),
            'guna_milan_score' => $this->faker->numberBetween(0, 36),
            'manglik' => $this->faker->boolean(),
            'manglik_severity' => $this->faker->randomElement(['none', 'low', 'medium', 'high']),
            'is_public' => $this->faker->boolean(),
            'birth_date' => $this->faker->date(),
            'birth_place' => $this->faker->city(),
        ];
    }
} 