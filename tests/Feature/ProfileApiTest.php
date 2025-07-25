<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function user_can_view_their_own_profile()
    {
        $user = $this->createUserWithProfile();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');
        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'first_name',
                            'last_name',
                            'email',
                            'age',
                            'profile' => [
                                'height_cm',
                                'current_city',
                                'religion',
                                'education_level',
                                'occupation',
                            ],
                            'photos',
                            'profile_completion',
                        ],
                        'can_edit',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'can_edit' => true,
                    ]
                ]);
    }

    /** @test */
    public function user_can_update_their_profile()
    {
        $user = $this->createUserWithProfile();
        
        Sanctum::actingAs($user);

        $updateData = [
            'height_cm' => 180,
            'current_city' => 'Kandy',
            'occupation' => 'Software Engineer',
            'about_me' => 'Updated bio text',
        ];

        $response = $this->putJson('/api/v1/profile', $updateData);

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'height_cm' => 180,
            'current_city' => 'Kandy',
            'occupation' => 'Software Engineer',
            'about_me' => 'Updated bio text',
        ]);
    }

    /** @test */
    public function user_can_upload_profile_photo()
    {
        Storage::fake('public');
        
        $user = $this->createUserWithProfile();
        
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('profile.jpg', 800, 600);

        $response = $this->postJson('/api/v1/profile/photos', [
            'photo' => $file,
            'is_profile_picture' => true,
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'photo' => [
                            'id',
                            'file_path',
                            'is_profile_picture',
                            'status',
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('user_photos', [
            'user_id' => $user->id,
            'is_profile_picture' => true,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function user_can_set_photo_as_profile_picture()
    {
        $user = $this->createUserWithProfile();
        $photo = UserPhoto::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/profile/photos/{$photo->id}/set-profile");

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Profile picture updated successfully'
                ]);

        $this->assertDatabaseHas('user_photos', [
            'id' => $photo->id,
            'is_profile_picture' => true,
        ]);
    }

    /** @test */
    public function user_can_delete_their_photo()
    {
        $user = $this->createUserWithProfile();
        $photo = UserPhoto::factory()->create([
            'user_id' => $user->id,
            'is_profile_picture' => false,
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/profile/photos/{$photo->id}");

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Photo deleted successfully'
                ]);

        $this->assertDatabaseMissing('user_photos', [
            'id' => $photo->id,
        ]);
    }

    /** @test */
    public function user_cannot_delete_other_users_photo()
    {
        $user = $this->createUserWithProfile();
        $otherUser = $this->createUserWithProfile();
        $photo = UserPhoto::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/profile/photos/{$photo->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('user_photos', [
            'id' => $photo->id,
        ]);
    }

    /** @test */
    public function user_can_get_profile_completion_status()
    {
        $user = $this->createUserWithProfile();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile/completion-status');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'completion_percentage',
                        'completed_sections',
                        'missing_sections',
                        'next_steps',
                    ]
                ]);
    }

    /** @test */
    public function user_profile_validation_works_correctly()
    {
        $user = $this->createUserWithProfile();
        
        Sanctum::actingAs($user);

        $invalidData = [
            'height_cm' => 'invalid', // Should be integer
            'annual_income_usd' => -1000, // Should be positive
            'religion' => str_repeat('a', 101), // Too long
        ];

        $response = $this->putJson('/api/v1/profile', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['height_cm', 'annual_income_usd']);
    }

    /** @test */
    public function user_can_view_another_users_public_profile()
    {
        $user = $this->createUserWithProfile();
        $targetUser = $this->createUserWithProfile(['gender' => 'female']);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/users/{$targetUser->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => [
                    'id',
                    'first_name',
                    'age',
                    'profile_completion',
                    'profile' => [
                        'height_cm',
                        'current_city',
                        'religion',
                        'education_level',
                    ],
                    'photos',
                ]
            ]
        ]);
        $response->assertJsonMissing([
            'email', // Email should not be exposed
            'phone', // Phone should not be exposed
        ]);
    }

    /** @test */
    public function user_can_complete_profile_setup()
    {
        $user = User::factory()->create([
            'profile_completion_percentage' => 30,
        ]);
        
        $user->profile()->create([
            'height_cm' => 175,
            'current_city' => 'Colombo',
        ]);
        
        Sanctum::actingAs($user);

        $completeData = [
            'profile' => [
                'height_cm' => 175,
                'body_type' => 'average',
                'current_city' => 'Colombo',
                'current_country' => 'Sri Lanka',
                'education_level' => 'bachelor',
                'occupation' => 'Engineer',
                'religion' => 'Buddhist',
                'mother_tongue' => 'Sinhala',
                'family_type' => 'nuclear',
                'diet' => 'non_vegetarian',
                'marital_status' => 'never_married',
                'about_me' => 'Looking for a life partner',
                'weight_kg' => 70,
                'complexion' => 'fair',
                'blood_group' => 'O+',
                'education_field' => 'Engineering',
                'company' => 'Tech Corp',
                'annual_income_usd' => 35000,
                'caste' => 'General',
                'smoking' => 'never',
                'drinking' => 'never',
                'hobbies' => ['reading', 'traveling'],
                'looking_for' => 'Life partner',
                'family_details' => 'Close-knit family',
            ]
        ];

        $response = $this->postJson('/api/v1/profile/complete', $completeData);

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                ]);

        $user->refresh();
        $this->assertGreaterThan(80, $user->profile_completion_percentage);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_profile_endpoints()
    {
        $response = $this->getJson('/api/v1/profile');
        $response->assertStatus(401);

        $response = $this->putJson('/api/v1/profile', []);
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/profile/photos', []);
        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_record_profile_view()
    {
        $user = $this->createUserWithProfile();
        $targetUser = $this->createUserWithProfile(['gender' => 'female']);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/users/{$targetUser->id}/view");
        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('profile_views', [
            'viewer_id' => $user->id,
            'viewed_user_id' => $targetUser->id,
        ]);
    }

    // Helper methods

    private function createUserWithProfile(array $userOverrides = [], array $profileOverrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'gender' => 'male',
            'status' => 'active',
            'profile_status' => 'approved',
            'profile_completion_percentage' => 75,
        ], $userOverrides));

        $user->profile()->create(array_merge([
            'height_cm' => 175,
            'current_city' => 'Colombo',
            'current_country' => 'Sri Lanka',
            'religion' => 'Buddhist',
            'education_level' => 'Bachelor\'s',
            'occupation' => 'Engineer',
            'annual_income_usd' => 30000,
            'marital_status' => 'never_married',
            'about_me' => 'Test bio',
            'profile_completion_percentage' => 75,
        ], $profileOverrides));

        return $user->load('profile');
    }
}
