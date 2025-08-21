<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\VideoCall;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class VideoCallApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $caller;
    private User $callee;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->caller = User::factory()->create([
            'is_premium' => true,
            'premium_expires_at' => now()->addMonth(),
        ]);
        
        $this->callee = User::factory()->create([
            'status' => 'active',
        ]);

        $this->conversation = Conversation::factory()->create([
            'user_one_id' => $this->caller->id,
            'user_two_id' => $this->callee->id,
        ]);
    }

    public function test_user_can_get_video_call_history()
    {
        Sanctum::actingAs($this->caller);

        // Create some video calls
        $calls = VideoCall::factory(3)->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
        ]);

        $response = $this->getJson('/api/v1/video-calls');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'calls' => [
                            '*' => [
                                'id',
                                'caller_id',
                                'callee_id',
                                'status',
                                'initiated_at',
                                'created_at',
                                'updated_at',
                            ]
                        ],
                        'pagination' => [
                            'current_page',
                            'total_calls',
                            'has_more',
                        ]
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data.calls'));
    }

    public function test_user_can_initiate_video_call()
    {
        Sanctum::actingAs($this->caller);

        $response = $this->postJson('/api/v1/video-calls/initiate', [
            'callee_id' => $this->callee->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'call' => [
                            'id',
                            'caller_id',
                            'callee_id',
                            'call_id',
                            'status',
                            'room_id',
                        ],
                        'caller_token',
                        'room_id',
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('pending', $response->json('data.call.status'));
        
        // Verify call was created in database
        $this->assertDatabaseHas('video_calls', [
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'pending',
        ]);
    }

    public function test_non_premium_user_cannot_initiate_video_call()
    {
        $nonPremiumCaller = User::factory()->create(['is_premium' => false]);
        Sanctum::actingAs($nonPremiumCaller);

        $response = $this->postJson('/api/v1/video-calls/initiate', [
            'callee_id' => $this->callee->id,
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Video calling is a premium feature'
                ]);
    }

    public function test_user_cannot_call_themselves()
    {
        Sanctum::actingAs($this->caller);

        $response = $this->postJson('/api/v1/video-calls/initiate', [
            'callee_id' => $this->caller->id,
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'You cannot call yourself'
                ]);
    }

    public function test_user_can_accept_video_call()
    {
        $videoCall = VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'pending',
            'initiated_at' => now(),
        ]);

        Sanctum::actingAs($this->callee);

        $response = $this->postJson("/api/v1/video-calls/{$videoCall->id}/accept");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Video call accepted',
                ]);

        $this->assertDatabaseHas('video_calls', [
            'id' => $videoCall->id,
            'status' => 'accepted',
        ]);
    }

    public function test_user_can_reject_video_call()
    {
        $videoCall = VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'pending',
            'initiated_at' => now(),
        ]);

        Sanctum::actingAs($this->callee);

        $response = $this->postJson("/api/v1/video-calls/{$videoCall->id}/reject");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Video call rejected',
                ]);

        $this->assertDatabaseHas('video_calls', [
            'id' => $videoCall->id,
            'status' => 'rejected',
        ]);
    }

    public function test_user_can_end_video_call()
    {
        $videoCall = VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinutes(2),
        ]);

        Sanctum::actingAs($this->caller);

        $response = $this->postJson("/api/v1/video-calls/{$videoCall->id}/end", [
            'reason' => 'normal',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Video call ended',
                ]);

        $this->assertDatabaseHas('video_calls', [
            'id' => $videoCall->id,
            'status' => 'ended',
            'end_reason' => 'normal',
        ]);
    }

    public function test_unauthorized_user_cannot_accept_call()
    {
        $videoCall = VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'pending',
        ]);

        $unauthorizedUser = User::factory()->create();
        Sanctum::actingAs($unauthorizedUser);

        $response = $this->postJson("/api/v1/video-calls/{$videoCall->id}/accept");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'You are not authorized to accept this call'
                ]);
    }

    public function test_expired_call_cannot_be_accepted()
    {
        $videoCall = VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'pending',
            'initiated_at' => now()->subMinutes(2), // Expired (>60 seconds)
        ]);

        Sanctum::actingAs($this->callee);

        $response = $this->postJson("/api/v1/video-calls/{$videoCall->id}/accept");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Call has expired'
                ]);

        // Should be marked as missed
        $this->assertDatabaseHas('video_calls', [
            'id' => $videoCall->id,
            'status' => 'missed',
        ]);
    }

    public function test_user_can_get_call_details()
    {
        $videoCall = VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
        ]);

        Sanctum::actingAs($this->caller);

        $response = $this->getJson("/api/v1/video-calls/{$videoCall->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'call' => [
                            'id',
                            'caller_id',
                            'callee_id',
                            'status',
                            'caller',
                            'callee',
                        ]
                    ]
                ]);
    }

    public function test_user_cannot_get_others_call_details()
    {
        $videoCall = VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
        ]);

        $unauthorizedUser = User::factory()->create();
        Sanctum::actingAs($unauthorizedUser);

        $response = $this->getJson("/api/v1/video-calls/{$videoCall->id}");

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Call not found'
                ]);
    }

    public function test_initiate_call_validation_errors()
    {
        Sanctum::actingAs($this->caller);

        // Test missing callee_id
        $response = $this->postJson('/api/v1/video-calls/initiate', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['callee_id']);

        // Test invalid callee_id
        $response = $this->postJson('/api/v1/video-calls/initiate', [
            'callee_id' => 99999, // Non-existent user
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['callee_id']);
    }

    public function test_cannot_initiate_call_with_existing_ongoing_call()
    {
        // Create an existing pending call
        VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->caller);

        $response = $this->postJson('/api/v1/video-calls/initiate', [
            'callee_id' => $this->callee->id,
        ]);

        $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'There is already an ongoing call between these users'
                ]);
    }

    public function test_cannot_accept_already_accepted_call()
    {
        $videoCall = VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($this->callee);

        $response = $this->postJson("/api/v1/video-calls/{$videoCall->id}/accept");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Call is no longer pending'
                ]);
    }

    public function test_filter_calls_by_status()
    {
        Sanctum::actingAs($this->caller);

        // Create calls with different statuses
        VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'pending',
        ]);

        VideoCall::factory()->create([
            'caller_id' => $this->caller->id,
            'callee_id' => $this->callee->id,
            'status' => 'ended',
        ]);

        $response = $this->getJson('/api/v1/video-calls?status=pending');

        $response->assertStatus(200);
        $calls = $response->json('data.calls');
        
        $this->assertCount(1, $calls);
        $this->assertEquals('pending', $calls[0]['status']);
    }
}
