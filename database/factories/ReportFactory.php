<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create();
        $assignedUser = User::factory()->create();
        $reviewedUser = User::factory()->create();

        return [
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'type' => $this->faker->randomElement([
                'inappropriate_content', 'fake_profile', 'harassment', 'spam', 
                'inappropriate_photos', 'scam', 'violence_threat', 'hate_speech',
                'underage', 'married_person', 'duplicate_account', 'other'
            ]),
            'description' => $this->faker->paragraph(),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'evidence_photos' => $this->faker->optional()->randomElement([json_encode($this->faker->words(3)), json_encode([])]),
            'evidence_messages' => $this->faker->optional()->randomElement([json_encode($this->faker->words(3)), json_encode([])]),
            'evidence_data' => $this->faker->optional()->randomElement([json_encode($this->faker->words(3)), json_encode([])]),
            'reported_content_type' => $this->faker->optional()->randomElement(['profile', 'photo', 'message']),
            'reported_content_id' => $this->faker->optional()->numberBetween(1, 100),
            'context_data' => $this->faker->optional()->randomElement([json_encode($this->faker->words(3)), json_encode([])]),
            'status' => $this->faker->randomElement(['pending', 'under_review', 'resolved', 'dismissed', 'escalated']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'assigned_to' => $this->faker->optional()->randomElement([$assignedUser->id, null]),
            'assigned_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'moderator_notes' => $this->faker->optional()->paragraph(),
            'reviewed_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'reviewed_by' => $this->faker->optional()->randomElement([$reviewedUser->id, null]),
            'resolution' => $this->faker->optional()->randomElement(['no_action', 'warning_sent', 'profile_suspended', 'account_banned', 'content_removed', 'profile_restricted', 'investigation_needed']),
            'resolution_notes' => $this->faker->optional()->paragraph(),
            'resolved_at' => $this->faker->optional()->dateTime(),
            'resolved_by' => $this->faker->optional()->randomElement([$reviewedUser->id, null]),
            'actions_taken' => $this->faker->optional()->randomElement([json_encode($this->faker->words(3)), json_encode([])]),
            'user_notified' => $this->faker->boolean(),
            'reporter_notified' => $this->faker->boolean(),
            'requires_followup' => $this->faker->boolean(),
            'followup_date' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'followup_notes' => $this->faker->optional()->paragraph(),
            'legal_concern' => $this->faker->boolean(),
            'law_enforcement_notified' => $this->faker->boolean(),
            'legal_notes' => $this->faker->optional()->paragraph(),
            'patterns_detected' => $this->faker->optional()->randomElement([json_encode($this->faker->words(3)), json_encode([])]),
            'similar_reports_count' => $this->faker->numberBetween(0, 10),
            'is_repeat_offender' => $this->faker->boolean(),
            'is_serial_reporter' => $this->faker->boolean(),
        ];
    }

    /**
     * Indicate that the report is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution' => null,
        ]);
    }

    /**
     * Indicate that the report is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'resolved_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'resolved_by' => User::factory(),
            'resolution' => $this->faker->randomElement([
                'no_action',
                'warning_sent',
                'profile_suspended',
                'account_banned',
                'content_removed',
                'profile_restricted',
                'investigation_needed'
            ]),
        ]);
    }

    /**
     * Indicate that the report is for inappropriate content.
     */
    public function inappropriateContent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'inappropriate_content',
            'description' => 'Contains inappropriate or offensive content',
        ]);
    }

    /**
     * Indicate that the report is for a fake profile.
     */
    public function fakeProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fake_profile',
            'description' => 'Profile appears to be fake or impersonating someone',
        ]);
    }

    /**
     * Indicate that the report is for harassment.
     */
    public function harassment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'harassment',
            'description' => 'User is harassing or bullying others',
        ]);
    }
} 