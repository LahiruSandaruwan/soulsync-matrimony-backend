<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserPhoto>
 */
class UserPhotoFactory extends Factory
{
    protected $model = UserPhoto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'file_path' => 'photos/' . $this->faker->uuid() . '.jpg',
            'file_size' => $this->faker->numberBetween(100000, 5000000), // 100KB to 5MB
            'mime_type' => 'image/jpeg',
            'original_filename' => $this->faker->word() . '.jpg',
            'is_profile_picture' => false,
            'is_private' => false,
            'status' => $this->faker->randomElement(['pending_approval', 'approved', 'rejected']),
            'admin_notes' => null,
            'upload_ip' => $this->faker->ipv4(),
            'exif_data' => json_encode([
                'camera' => $this->faker->randomElement(['iPhone 13', 'Samsung Galaxy', 'Canon EOS']),
                'dimensions' => $this->faker->randomElement(['1080x1920', '720x1280', '1440x2560']),
            ]),
        ];
    }

    /**
     * Indicate that the photo is a profile picture.
     */
    public function profilePicture(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_profile_picture' => true,
        ]);
    }

    /**
     * Indicate that the photo is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    /**
     * Indicate that the photo is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_approval',
        ]);
    }

    /**
     * Indicate that the photo is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }
}
