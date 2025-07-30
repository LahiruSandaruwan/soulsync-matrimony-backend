<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPreference;
use App\Models\UserMatch;
use App\Models\Interest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchingApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test interests
        Interest::factory()->createMany([
            ['name' => 'Reading', 'category' => 'hobbies'],
            ['name' => 'Travel', 'category' => 'hobbies'],
            ['name' => 'Music', 'category' => 'hobbies'],
        ]);
    }

    /** @test */
    public function user_can_get_daily_matches()
    {
        $user = $this->createUserWithProfile();
        $potentialMatches = $this->createPotentialMatches($user, 5);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/matches/daily');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'first_name',
                            'age',
                            'compatibility_score',
                            'profile_picture',
                            'location',
                        ]
                    ]
                ]);
    }

    /** @test */
    public function user_can_like_another_user()
    {
        $user = $this->createUserWithProfile();
        $targetUser = $this->createUserWithProfile(['gender' => 'female']);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/matches/{$targetUser->id}/like");

        // Debug: dump the response JSON if not OK
        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'is_match' => false,
                    ]
                ]);

        $this->assertDatabaseHas('user_matches', [
            'user_id' => $user->id,
            'matched_user_id' => $targetUser->id,
            'user_action' => 'liked',
        ]);
    }

    /** @test */
    public function user_can_super_like_another_user()
    {
        $user = $this->createUserWithProfile(['super_likes_count' => 5]);
        $targetUser = $this->createUserWithProfile(['gender' => 'female']);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/matches/{$targetUser->id}/super-like");

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'is_match' => false,
                    ]
                ]);

        $this->assertDatabaseHas('user_matches', [
            'user_id' => $user->id,
            'matched_user_id' => $targetUser->id,
            'user_action' => 'super_liked',
        ]);

        $user->refresh();
        $this->assertEquals(4, $user->super_likes_count);
    }

    /** @test */
    public function users_get_mutual_match_when_both_like_each_other()
    {
        $user1 = $this->createUserWithProfile();
        $user2 = $this->createUserWithProfile(['gender' => 'female']);
        
        // User1 likes User2
        Sanctum::actingAs($user1);
        $this->postJson("/api/v1/matches/{$user2->id}/like");

        // User2 likes User1 back
        Sanctum::actingAs($user2);
        $response = $this->postJson("/api/v1/matches/{$user1->id}/like");

        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'is_match' => true,
                    ]
                ]);

        $this->assertDatabaseHas('user_matches', [
            'user_id' => $user1->id,
            'matched_user_id' => $user2->id,
            'status' => 'mutual',
        ]);
    }

    /** @test */
    public function user_cannot_like_themselves()
    {
        $user = $this->createUserWithProfile();
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/matches/{$user->id}/like");

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'You cannot like your own profile'
                ]);
    }

    /** @test */
    public function user_can_dislike_another_user()
    {
        $user = $this->createUserWithProfile();
        $targetUser = $this->createUserWithProfile(['gender' => 'female']);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/matches/{$targetUser->id}/dislike");

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'User passed'
                ]);

        $this->assertDatabaseHas('user_matches', [
            'user_id' => $user->id,
            'matched_user_id' => $targetUser->id,
            'user_action' => 'disliked',
        ]);
    }

    /** @test */
    public function user_can_get_mutual_matches()
    {
        $user = $this->createUserWithProfile();
        $match1 = $this->createUserWithProfile(['gender' => 'female']);
        $match2 = $this->createUserWithProfile(['gender' => 'female']);
        
        // Create mutual matches
        UserMatch::create([
            'user_id' => $user->id,
            'matched_user_id' => $match1->id,
            'status' => 'mutual',
            'can_communicate' => true,
        ]);

        UserMatch::create([
            'user_id' => $user->id,
            'matched_user_id' => $match2->id,
            'status' => 'mutual',
            'can_communicate' => true,
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/matches/mutual');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'matches' => [
                            '*' => [
                                'id',
                                'first_name',
                                'age',
                                'location',
                                'occupation',
                                'profile_picture',
                                'match_id',
                                'match_status',
                                'can_communicate',
                                'conversation_id',
                                'matched_at',
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function premium_user_can_see_who_liked_them()
    {
        $user = $this->createUserWithProfile(['is_premium' => true]);
        $liker1 = $this->createUserWithProfile(['gender' => 'female']);
        $liker2 = $this->createUserWithProfile(['gender' => 'female']);
        
        // Create likes from other users
        UserMatch::create([
            'user_id' => $liker1->id,
            'matched_user_id' => $user->id,
            'user_action' => 'liked',
            'status' => 'pending',
        ]);

        UserMatch::create([
            'user_id' => $liker2->id,
            'matched_user_id' => $user->id,
            'user_action' => 'super_liked',
            'status' => 'pending',
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/matches/liked-me');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'likes' => [
                            '*' => [
                                'id',
                                'first_name',
                                'age',
                                'location',
                                'occupation',
                                'profile_picture',
                                'match_id',
                                'match_status',
                                'action_type',
                                'liked_at',
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function free_user_cannot_see_who_liked_them()
    {
        $user = $this->createUserWithProfile(['is_premium' => false]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/matches/liked-me');

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'upgrade_required' => true,
                ]);
    }

    /** @test */
    public function user_cannot_super_like_without_remaining_super_likes()
    {
        $user = $this->createUserWithProfile(['super_likes_count' => 0, 'is_premium' => false]);
        $targetUser = $this->createUserWithProfile(['gender' => 'female']);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/matches/{$targetUser->id}/super-like");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'upgrade_required' => true,
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_matches()
    {
        $response = $this->getJson('/api/v1/matches/daily');

        $response->assertStatus(401);
    }

    // Helper methods

    private function createUserWithProfile(array $userOverrides = [], array $profileOverrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'gender' => 'male',
            'status' => 'active',
            'profile_status' => 'approved',
            'is_premium' => false,
            'super_likes_count' => 1,
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
            'profile_completion_percentage' => 80,
        ], $profileOverrides));

        $user->preferences()->create([
            'min_age' => 22,
            'max_age' => 32,
            'preferred_genders' => [$user->gender === 'male' ? 'female' : 'male'],
            'preferred_countries' => ['Sri Lanka'],
            'preferred_religions' => ['Buddhist', 'Christian'],
        ]);

        return $user->load('profile', 'preferences');
    }

    private function createPotentialMatches(User $user, int $count): array
    {
        $matches = [];
        
        for ($i = 0; $i < $count; $i++) {
            $match = $this->createUserWithProfile([
                'gender' => $user->gender === 'male' ? 'female' : 'male',
            ]);
            $matches[] = $match;
        }
        
        return $matches;
    }
}
