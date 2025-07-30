<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Notification;
use App\Models\Message;
use App\Models\UserMatch;
use Laravel\Sanctum\Sanctum;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_status' => 'approved'
        ]);
        
        $this->otherUser = User::factory()->create([
            'email_verified_at' => now(),
            'profile_status' => 'approved'
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function user_can_get_notifications_list()
    {
        // Create some notifications
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id
        ]);
        
        $response = $this->getJson('/api/v1/notifications');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'message',
                            'read_at',
                            'created_at',
                            'priority',
                            'category'
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'total',
                        'per_page'
                    ]
                ]);
    }

    /** @test */
    public function user_can_get_unread_notifications_count()
    {
        // Create read and unread notifications
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'read_at' => null
        ]);
        
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'read_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/notifications/unread-count');
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total_unread' => 3
                    ]
                ]);
    }

    /** @test */
    public function user_can_mark_notification_as_read()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'read_at' => null
        ]);
        
        $response = $this->postJson("/api/v1/notifications/{$notification->id}/read");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => now()
        ]);
    }

    /** @test */
    public function user_can_mark_all_notifications_as_read()
    {
        // Create multiple unread notifications
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'read_at' => null
        ]);
        
        $response = $this->postJson('/api/v1/notifications/read-all');
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'read_at' => null
        ]);
    }

    /** @test */
    public function user_can_delete_notification()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id
        ]);
        
        $response = $this->deleteJson("/api/v1/notifications/{$notification->id}");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id
        ]);
    }

    /** @test */
    public function user_cannot_access_others_notifications()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->otherUser->id
        ]);
        
        $response = $this->getJson("/api/v1/notifications/{$notification->id}");
        
        $response->assertStatus(403);
    }

    /** @test */
    public function user_cannot_mark_others_notifications_as_read()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->otherUser->id
        ]);
        
        $response = $this->postJson("/api/v1/notifications/{$notification->id}/read");
        
        $response->assertStatus(403);
    }

    /** @test */
    public function notifications_are_paginated()
    {
        // Create more notifications than default per page
        Notification::factory()->count(25)->create([
            'user_id' => $this->user->id
        ]);
        
        $response = $this->getJson('/api/v1/notifications?page=2');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total'
                    ]
                ])
                ->assertJson([
                    'meta' => [
                        'current_page' => 2
                    ]
                ]);
    }

    /** @test */
    public function notifications_can_be_filtered_by_type()
    {
        Notification::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'match'
        ]);
        
        Notification::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'message'
        ]);
        
        $response = $this->getJson('/api/v1/notifications?type=match');
        
        $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function notifications_can_be_filtered_by_category()
    {
        Notification::factory()->create([
            'user_id' => $this->user->id,
            'category' => 'matching'
        ]);
        
        Notification::factory()->create([
            'user_id' => $this->user->id,
            'category' => 'message'
        ]);
        
        $response = $this->getJson('/api/v1/notifications?category=matching');
        
        $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function notifications_can_be_filtered_by_read_status()
    {
        Notification::factory()->create([
            'user_id' => $this->user->id,
            'read_at' => null
        ]);
        
        Notification::factory()->create([
            'user_id' => $this->user->id,
            'read_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/notifications?unread_only=true');
        
        $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function high_priority_notifications_are_highlighted()
    {
        $highPriorityNotification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'priority' => 'high'
        ]);
        
        $normalNotification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'priority' => 'medium'
        ]);
        
        $response = $this->getJson('/api/v1/notifications');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'is_high_priority'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function notification_creation_for_match()
    {
        $match = UserMatch::factory()->create([
            'user_id' => $this->user->id,
            'matched_user_id' => $this->otherUser->id,
            'status' => 'mutual'
        ]);
        
        $notification = Notification::createMatchNotification($this->user, $this->otherUser);
        
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->user->id,
            'type' => 'match',
            'category' => 'matching'
        ]);
    }

    /** @test */
    public function notification_creation_for_message()
    {
        $conversation = \App\Models\Conversation::createDirectConversation($this->user, $this->otherUser);
        $message = Message::createTextMessage($conversation, $this->otherUser, 'Hello!');
        
        $notification = Notification::createMessageNotification($this->user, $message);
        
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->user->id,
            'type' => 'message',
            'category' => 'message'
        ]);
    }

    /** @test */
    public function notification_creation_for_profile_view()
    {
        $notification = Notification::createProfileViewNotification($this->user, $this->otherUser);
        
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->user->id,
            'type' => 'profile_view',
            'category' => 'profile'
        ]);
    }

    /** @test */
    public function notification_creation_for_subscription()
    {
        $notification = Notification::createSubscriptionNotification($this->user, 'trial_started');
        
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->user->id,
            'type' => 'trial_started',
            'category' => 'subscription'
        ]);
    }

    /** @test */
    public function notification_with_action_url()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'action_url' => '/matches/123',
            'action_text' => 'View Match'
        ]);
        
        $response = $this->getJson("/api/v1/notifications/{$notification->id}");
        
        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'action_url' => '/matches/123?notification_id=' . $notification->id,
                        'action_text' => 'View Match'
                    ]
                ]);
    }

    /** @test */
    public function notification_expiration_handling()
    {
        $expiredNotification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->subDay()
        ]);
        
        $activeNotification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->addDay()
        ]);
        
        $response = $this->getJson('/api/v1/notifications');
        
        $response->assertStatus(200)
                ->assertJsonCount(1, 'data'); // Only active notification should be returned
    }

    /** @test */
    public function notification_batch_processing()
    {
        $batchId = 'batch_' . uniqid();
        
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'batch_id' => $batchId
        ]);
        
        $response = $this->postJson('/api/v1/notifications/batch/read', [
            'batch_id' => $batchId
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('notifications', [
            'batch_id' => $batchId,
            'read_at' => null
        ]);
    }

    /** @test */
    public function notification_preferences_affect_delivery()
    {
        // Update user preferences to disable email notifications
        $this->user->update([
            'email_notifications' => false,
            'push_notifications' => true
        ]);
        
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'match'
        ]);
        
        // Should not send email but should send push notification
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'sent_at' => null // Email not sent
        ]);
    }

    /** @test */
    public function notification_metadata_storage()
    {
        $metadata = [
            'match_id' => 123,
            'conversation_id' => 456,
            'custom_data' => 'test_value'
        ];
        
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'metadata' => $metadata
        ]);
        
        $response = $this->getJson("/api/v1/notifications/{$notification->id}");
        
        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'metadata' => $metadata
                    ]
                ]);
    }

    /** @test */
    public function notification_cleanup_old_notifications()
    {
        // Create old notifications
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subMonths(3)
        ]);
        
        // Create recent notifications
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5)
        ]);
        
        $response = $this->postJson('/api/v1/notifications/cleanup', [
            'older_than_days' => 60
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        // Only recent notifications should remain
        $this->assertDatabaseCount('notifications', 3);
    }
} 