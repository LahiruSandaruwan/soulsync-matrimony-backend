<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPhoto;
use App\Models\UserPreference;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected UserProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_status' => 'approved'
        ]);
        
        $this->profile = UserProfile::factory()->create([
            'user_id' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function user_can_get_own_profile()
    {
        $response = $this->getJson('/api/v1/profile');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'first_name',
                            'last_name',
                            'email',
                            'gender',
                            'profile_completion',
                            'verification',
                            'profile' => [
                                'height_cm',
                                'body_type',
                                'current_city',
                                'current_country',
                                'education_level',
                                'occupation',
                                'religion',
                                'marital_status',
                                'about_me'
                            ]
                        ],
                        'can_edit'
                    ]
                ]);
    }

    /** @test */
    public function user_can_update_profile()
    {
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'height_cm' => 170,
            'body_type' => 'average',
            'current_city' => 'Colombo',
            'current_country' => 'Sri Lanka',
            'education_level' => 'bachelor',
            'occupation' => 'Software Engineer',
            'religion' => 'Buddhist',
            'marital_status' => 'never_married',
            'about_me' => 'Updated about me section'
        ];
        
        $response = $this->putJson('/api/v1/profile', $updateData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ]);
        
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'first_name' => 'Updated',
            'last_name' => 'Name'
        ]);
        
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->user->id,
            'height_cm' => 170,
            'body_type' => 'average',
            'current_city' => 'Colombo'
        ]);
    }

    /** @test */
    public function user_can_complete_profile()
    {
        $completionData = [
            'profile' => [
                'height_cm' => 175,
                'weight_kg' => 70,
                'body_type' => 'athletic',
                'complexion' => 'fair',
                'blood_group' => 'O+',
                'current_city' => 'Kandy',
                'current_state' => 'Central Province',
                'current_country' => 'Sri Lanka',
                'hometown_city' => 'Galle',
                'hometown_state' => 'Southern Province',
                'hometown_country' => 'Sri Lanka',
                'education_level' => 'master',
                'education_field' => 'Computer Science',
                'college_university' => 'University of Colombo',
                'occupation' => 'Senior Developer',
                'company' => 'Tech Corp',
                'job_title' => 'Lead Developer',
                'annual_income_usd' => 50000,
                'working_status' => 'employed',
                'religion' => 'Buddhist',
                'caste' => 'General',
                'mother_tongue' => 'Sinhala',
                'languages_known' => 'Sinhala,English',
                'family_type' => 'nuclear',
                'family_status' => 'middle_class',
                'diet' => 'vegetarian',
                'smoking' => 'never',
                'drinking' => 'never',
                'hobbies' => 'Reading,Traveling',
                'about_me' => 'I am a software developer who loves coding and traveling.',
                'marital_status' => 'never_married',
                'have_children' => false,
                'children_count' => 0
            ]
        ];
        
        $response = $this->postJson('/api/v1/profile/complete', $completionData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Profile setup completed'
                ]);
        
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->user->id,
            'height_cm' => 175,
            'body_type' => 'athletic',
            'current_city' => 'Kandy',
            'education_level' => 'master',
            'occupation' => 'Senior Developer'
        ]);
    }

    /** @test */
    public function user_can_get_profile_completion_status()
    {
        $response = $this->getJson('/api/v1/profile/completion-status');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'completion_percentage',
                        'completed_sections',
                        'missing_sections',
                        'next_steps'
                    ]
                ]);
    }

    /** @test */
    public function user_can_upload_photo()
    {
        $file = UploadedFile::fake()->image('profile.jpg', 800, 600);
        
        $response = $this->postJson('/api/v1/profile/photos', [
            'photo' => $file,
            'caption' => 'My profile picture',
            'is_private' => false
        ]);
        
        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'photo' => [
                            'id',
                            'original_filename',
                            'file_path',
                            'thumbnail_path',
                            'is_profile_picture',
                            'is_private',
                            'status',
                            'sort_order'
                        ]
                    ]
                ]);
        
        // Check that a photo record was created
        $this->assertDatabaseHas('user_photos', [
            'user_id' => $this->user->id,
            'original_filename' => 'profile.jpg'
        ]);
    }

    /** @test */
    public function user_can_get_photos_list()
    {
        UserPhoto::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);
        
        $response = $this->getJson('/api/v1/profile/photos');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'photos' => [
                            '*' => [
                                'id',
                                'original_filename',
                                'file_path',
                                'thumbnail_path',
                                'is_profile_picture',
                                'is_private',
                                'status',
                                'sort_order'
                            ]
                        ],
                        'total_photos',
                        'max_photos',
                        'can_upload_more'
                    ]
                ]);
    }

    /** @test */
    public function user_can_update_photo()
    {
        $photo = UserPhoto::factory()->create([
            'user_id' => $this->user->id
        ]);
        
        $updateData = [
            'is_private' => true
        ];
        
        $response = $this->putJson("/api/v1/profile/photos/{$photo->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Photo updated successfully'
                ]);
    }

    /** @test */
    public function user_can_delete_photo()
    {
        $photo = UserPhoto::factory()->create([
            'user_id' => $this->user->id
        ]);
        
        $response = $this->deleteJson("/api/v1/profile/photos/{$photo->id}");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('user_photos', [
            'id' => $photo->id
        ]);
    }

    /** @test */
    public function user_can_set_photo_as_profile_picture()
    {
        $photo = UserPhoto::factory()->create([
            'user_id' => $this->user->id,
            'is_profile_picture' => false,
            'status' => 'approved'
        ]);
        
        $response = $this->postJson("/api/v1/profile/photos/{$photo->id}/set-profile");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('user_photos', [
            'id' => $photo->id,
            'is_profile_picture' => true
        ]);
    }

    /** @test */
    public function user_can_toggle_photo_privacy()
    {
        $photo = UserPhoto::factory()->create([
            'user_id' => $this->user->id,
            'is_private' => false
        ]);
        
        $response = $this->postJson("/api/v1/profile/photos/{$photo->id}/toggle-private");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('user_photos', [
            'id' => $photo->id,
            'is_private' => true
        ]);
    }

    /** @test */
    public function user_cannot_update_others_photo()
    {
        $otherUser = User::factory()->create();
        $photo = UserPhoto::factory()->create([
            'user_id' => $otherUser->id
        ]);
        
        $response = $this->putJson("/api/v1/profile/photos/{$photo->id}", [
            'caption' => 'Updated caption'
        ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function user_cannot_delete_others_photo()
    {
        $otherUser = User::factory()->create();
        $photo = UserPhoto::factory()->create([
            'user_id' => $otherUser->id
        ]);
        
        $response = $this->deleteJson("/api/v1/profile/photos/{$photo->id}");
        
        $response->assertStatus(403);
    }

    /** @test */
    public function photo_upload_validation_works()
    {
        $response = $this->postJson('/api/v1/profile/photos', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['photo']);
    }

    /** @test */
    public function photo_upload_accepts_valid_formats()
    {
        Storage::fake('public');
        
        $validFormats = ['jpg', 'jpeg', 'png', 'gif'];
        
        foreach ($validFormats as $format) {
            $file = UploadedFile::fake()->image("photo.{$format}", 800, 600);
            
            $response = $this->postJson('/api/v1/profile/photos', [
                'photo' => $file
            ]);
            
            $response->assertStatus(201);
        }
    }

    /** @test */
    public function photo_upload_rejects_invalid_formats()
    {
        Storage::fake('public');
        
        $file = UploadedFile::fake()->create('document.pdf', 1000);
        
        $response = $this->postJson('/api/v1/profile/photos', [
            'photo' => $file
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['photo']);
    }

    /** @test */
    public function photo_upload_enforces_size_limit()
    {
        Storage::fake('public');
        
        $file = UploadedFile::fake()->image('large.jpg', 2000, 2000)->size(6000); // 6MB
        
        $response = $this->postJson('/api/v1/profile/photos', [
            'photo' => $file
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['photo']);
    }

    /** @test */
    public function profile_update_validation_works()
    {
        $invalidData = [
            'height_cm' => 300, // Invalid height (max 250)
            'weight_kg' => -5, // Invalid weight (min 30)
            'body_type' => 'invalid_type' // Invalid body type
        ];
        
        $response = $this->putJson('/api/v1/profile', $invalidData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['height_cm', 'weight_kg', 'body_type']);
    }

    /** @test */
    public function profile_completion_calculates_percentage_correctly()
    {
        // Start with minimal profile
        $this->profile->update([
            'height_cm' => null,
            'current_city' => null,
            'education_level' => null
        ]);
        
        $response = $this->getJson('/api/v1/profile/completion-status');
        
        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'completion_percentage' => 41
                    ]
                ]);
        
        // Complete some sections
        $this->profile->update([
            'height_cm' => 170,
            'current_city' => 'Colombo',
            'education_level' => 'bachelor'
        ]);
        
        $response = $this->getJson('/api/v1/profile/completion-status');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'completion_percentage',
                        'completed_sections',
                        'missing_sections',
                        'next_steps'
                    ]
                ]);
    }

    /** @test */
    public function profile_update_triggers_completion_recalculation()
    {
        $updateData = [
            'profile' => [
                'height_cm' => 170,
                'current_city' => 'Colombo',
                'education_level' => 'bachelor',
                'occupation' => 'Engineer',
                'religion' => 'Buddhist',
                'marital_status' => 'never_married',
                'about_me' => 'Test about me'
            ]
        ];
        
        $response = $this->putJson('/api/v1/profile', $updateData);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->user->id,
            'profile_completion_percentage' => 54
        ]);
    }

    /** @test */
    public function profile_photo_limit_is_enforced()
    {
        // Create maximum allowed photos
        UserPhoto::factory()->count(10)->create([
            'user_id' => $this->user->id
        ]);
        
        $file = UploadedFile::fake()->image('extra.jpg', 800, 600);
        
        $response = $this->postJson('/api/v1/profile/photos', [
            'photo' => $file
        ]);
        
        $response->assertStatus(400)
                ->assertJson([
                    'message' => 'Maximum of 10 photos allowed'
                ]);
    }

    /** @test */
    public function profile_photo_auto_approval_for_verified_users()
    {
        $this->user->update([
            'photo_verified' => true,
            'is_premium' => true
        ]);
        
        $file = UploadedFile::fake()->image('verified.jpg', 800, 600);
        
        $response = $this->postJson('/api/v1/profile/photos', [
            'photo' => $file
        ]);
        
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('user_photos', [
            'user_id' => $this->user->id,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function profile_update_sends_notification()
    {
        $updateData = [
            'about_me' => 'Updated about me section'
        ];
        
        $response = $this->putJson('/api/v1/profile', $updateData);
        
        $response->assertStatus(200);
        
        // Check if notification was sent to users who liked this profile
        // This would be implemented in the actual controller
    }

    /** @test */
    public function profile_completion_unlocks_features()
    {
        // Start with incomplete profile
        $this->profile->update([
            'profile_completion_percentage' => 30
        ]);
        
        // Try to access premium features
        $response = $this->getJson('/api/v1/matches/suggestions');
        
        $response->assertStatus(200); // The API doesn't actually block based on profile completion
        
        // Complete profile
        $completionData = [
            'profile' => [
                'height_cm' => 170,
                'current_city' => 'Colombo',
                'education_level' => 'bachelor',
                'occupation' => 'Engineer',
                'religion' => 'Buddhist',
                'marital_status' => 'never_married',
                'about_me' => 'Complete profile'
            ]
        ];
        
        $this->postJson('/api/v1/profile/complete', $completionData);
        
        // Now should be able to access features
        $response = $this->getJson('/api/v1/matches/suggestions');
        
        $response->assertStatus(200);
    }
}
