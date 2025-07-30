<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UserMatch;
use Laravel\Sanctum\Sanctum;

class ChatApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $otherUser;
    protected Conversation $conversation;

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
    public function user_can_get_conversations_list()
    {
        // Create a conversation
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        $response = $this->getJson('/api/v1/chat/conversations');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'is_group',
                            'last_message_at',
                            'unread_count',
                            'participants',
                            'last_message'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function user_can_get_specific_conversation()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'type',
                        'participants',
                        'messages'
                    ]
                ]);
    }

    /** @test */
    public function user_can_send_message_to_conversation()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        $messageData = [
            'content' => 'Hello, how are you?',
            'message_type' => 'text'
        ];
        
        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", $messageData);
        
        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'content',
                        'message_type',
                        'sender_id',
                        'created_at'
                    ]
                ]);
    }

    /** @test */
    public function user_can_update_own_message()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        $message = Message::createTextMessage($conversation, $this->user, 'Original message');
        
        $updateData = [
            'content' => 'Updated message content'
        ];
        
        $response = $this->putJson("/api/v1/chat/messages/{$message->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'content' => 'Updated message content',
                        'is_edited' => true
                    ]
                ]);
    }

    /** @test */
    public function user_cannot_update_others_message()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        $message = Message::createTextMessage($conversation, $this->otherUser, 'Original message');
        
        $updateData = [
            'content' => 'Updated message content'
        ];
        
        $response = $this->putJson("/api/v1/chat/messages/{$message->id}", $updateData);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_delete_own_message()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        $message = Message::createTextMessage($conversation, $this->user, 'Message to delete');
        
        $response = $this->deleteJson("/api/v1/chat/messages/{$message->id}");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_can_mark_message_as_read()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        $message = Message::createTextMessage($conversation, $this->otherUser, 'Unread message');
        
        $response = $this->postJson("/api/v1/chat/messages/{$message->id}/read");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_can_block_conversation()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/block");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_can_delete_conversation()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        $response = $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_cannot_access_conversation_they_are_not_part_of()
    {
        $otherUser1 = User::factory()->create();
        $otherUser2 = User::factory()->create();
        $conversation = Conversation::createDirectConversation($otherUser1, $otherUser2);
        
        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}");
        
        $response->assertStatus(403);
    }

    /** @test */
    public function user_cannot_send_message_to_blocked_conversation()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        $conversation->block($this->user);
        
        $messageData = [
            'content' => 'This should fail',
            'message_type' => 'text'
        ];
        
        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", $messageData);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function message_validation_works()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['content']);
    }

    /** @test */
    public function user_can_send_image_message()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        $messageData = [
            'content' => 'Check out this image!',
            'message_type' => 'image',
            'attachment_url' => 'https://example.com/image.jpg',
            'attachment_metadata' => [
                'size' => 1024000,
                'filename' => 'image.jpg',
                'mime_type' => 'image/jpeg'
            ]
        ];
        
        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", $messageData);
        
        $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'message_type' => 'image',
                        'attachment_url' => 'https://example.com/image.jpg'
                    ]
                ]);
    }

    /** @test */
    public function user_can_send_voice_message()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        $messageData = [
            'content' => 'Voice message',
            'message_type' => 'voice',
            'voice_url' => 'https://example.com/voice.mp3',
            'voice_duration' => 30
        ];
        
        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", $messageData);
        
        $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'message_type' => 'voice',
                        'voice_url' => 'https://example.com/voice.mp3',
                        'voice_duration' => 30
                    ]
                ]);
    }

    /** @test */
    public function conversation_creates_system_message_for_mutual_match()
    {
        // Create a mutual match
        $match = UserMatch::factory()->create([
            'user_id' => $this->user->id,
            'matched_user_id' => $this->otherUser->id,
            'status' => 'mutual',
            'can_communicate' => true
        ]);
        
        $conversation = Conversation::createMatchConversation($match);
        
        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'messages' => [
                            '*' => [
                                'id',
                                'content',
                                'is_system_message',
                                'system_message_type'
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function unread_count_updates_correctly()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        
        // Send message from other user
        Message::createTextMessage($conversation, $this->otherUser, 'Unread message');
        
        $response = $this->getJson('/api/v1/chat/conversations');
        
        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        [
                            'unread_count' => 1
                        ]
                    ]
                ]);
    }

    /** @test */
    public function message_edit_time_limit_enforced()
    {
        $conversation = Conversation::createDirectConversation($this->user, $this->otherUser);
        $message = Message::createTextMessage($conversation, $this->user, 'Original message');
        
        // Simulate time passing (more than 5 minutes) by directly updating the database
        \DB::table('messages')->where('id', $message->id)->update(['created_at' => now()->subMinutes(6)]);
        
        // Refresh the message from database to ensure the timestamp is updated
        $message->refresh();
        
        $updateData = ['content' => 'Updated message'];
        
        $response = $this->putJson("/api/v1/chat/messages/{$message->id}", $updateData);
        
        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Message can only be edited within 5 minutes of sending'
                ]);
    }
}
