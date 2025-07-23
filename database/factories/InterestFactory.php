<?php

namespace Database\Factories;

use App\Models\Interest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Interest>
 */
class InterestFactory extends Factory
{
    protected $model = Interest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['hobbies', 'sports', 'music', 'movies', 'books', 'travel', 'food', 'technology'];
        
        $interests = [
            'hobbies' => ['Reading', 'Writing', 'Painting', 'Photography', 'Gardening', 'Cooking'],
            'sports' => ['Football', 'Cricket', 'Tennis', 'Swimming', 'Basketball', 'Badminton'],
            'music' => ['Classical', 'Pop', 'Rock', 'Jazz', 'Hip Hop', 'Country'],
            'movies' => ['Action', 'Comedy', 'Drama', 'Horror', 'Romance', 'Sci-Fi'],
            'books' => ['Fiction', 'Non-Fiction', 'Mystery', 'Romance', 'Biography', 'Science'],
            'travel' => ['Beach', 'Mountains', 'Cities', 'Adventure', 'Cultural', 'Road Trips'],
            'food' => ['Italian', 'Chinese', 'Indian', 'Mexican', 'Thai', 'Mediterranean'],
            'technology' => ['Programming', 'Gaming', 'AI/ML', 'Web Development', 'Mobile Apps', 'Cybersecurity'],
        ];

        $category = $this->faker->randomElement($categories);
        $name = $this->faker->randomElement($interests[$category]);
        $slug = $this->faker->unique()->slug . '-' . $this->faker->unique()->uuid;

        return [
            'name' => $name,
            'slug' => $slug,
            'category' => $category,
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the interest is in a specific category.
     */
    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Indicate that the interest is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
