<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Get user's conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
            'status' => 'sometimes|in:active,archived,blocked',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $status = $request->get('status', 'active');
            $offset = ($page - 1) * $limit;

            // Get conversations where user is a participant
            $conversationsQuery = Conversation::where(function ($query) use ($user) {
                $query->where('user_one_id', $user->id)
                      ->orWhere('user_two_id', $user->id);
            })
            ->where('status', $status)
            ->with(['lastMessage', 'userOne', 'userTwo'])
            ->orderBy('last_message_at', 'desc');

            $totalCount = $conversationsQuery->count();
            $conversations = $conversationsQuery->offset($offset)->limit($limit)->get();

            $formattedConversations = $conversations->map(function ($conversation) use ($user) {
                $otherUser = $conversation->user_one_id === $user->id ? $conversation->userTwo : $conversation->userOne;
                $unreadCount = $conversation->getUnreadCount($user);

                return [
                    'id' => $conversation->id,
                    'name' => $conversation->getDisplayName($user),
                    'type' => $conversation->type,
                    'is_group' => $conversation->is_group,
                    'last_message_at' => $conversation->last_message_at,
                    'unread_count' => $unreadCount,
                    'participants' => [
                        [
                            'id' => $user->id,
                            'name' => $user->first_name,
                        ],
                        [
                            'id' => $otherUser->id,
                            'name' => $otherUser->first_name,
                        ]
                    ],
                    'last_message' => $conversation->lastMessage ? [
                        'id' => $conversation->lastMessage->id,
                        'content' => $conversation->lastMessage->content,
                        'type' => $conversation->lastMessage->type,
                        'sender_id' => $conversation->lastMessage->sender_id,
                        'sent_at' => $conversation->lastMessage->created_at,
                        'status' => $conversation->lastMessage->status,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedConversations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversation details with messages
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Check if user is part of this conversation
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to conversation'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 50);
            $offset = ($page - 1) * $limit;

            // Get other user
            $otherUser = $conversation->user_one_id === $user->id ? $conversation->userTwo : $conversation->userOne;

            // Get messages
            $messagesQuery = $conversation->messages()
                ->with('sender')
                ->orderBy('created_at', 'desc');

            $totalMessages = $messagesQuery->count();
            $messages = $messagesQuery->offset($offset)->limit($limit)->get()->reverse()->values();

            // Mark messages as read
            $conversation->messages()
                ->where('sender_id', '!=', $user->id)
                ->where('status', '!=', 'read')
                ->update(['status' => 'read', 'read_at' => now()]);

            $formattedMessages = $messages->map(function ($message) {
                $formattedMessage = [
                    'id' => $message->id,
                    'content' => $message->message,
                    'type' => $message->type,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender ? $message->sender->first_name : 'System',
                    'media_url' => $message->media_files ? $message->media_files[0] : null,
                    'status' => $message->status,
                    'sent_at' => $message->created_at,
                    'read_at' => $message->read_at,
                ];
                
                // Add system message specific fields
                if ($message->type === 'system') {
                    $formattedMessage['is_system_message'] = true;
                    $formattedMessage['system_message_type'] = $message->system_data['type'] ?? null;
                }
                
                return $formattedMessage;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $conversation->id,
                    'name' => $conversation->name,
                    'type' => $conversation->type,
                    'participants' => [
                        [
                            'id' => $conversation->userOne->id,
                            'name' => $conversation->userOne->first_name,
                        ],
                        [
                            'id' => $conversation->userTwo->id,
                            'name' => $conversation->userTwo->first_name,
                        ],
                    ],
                    'messages' => $formattedMessages,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Check if user is part of this conversation
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to conversation'
            ], 403);
        }

        // Check if conversation is blocked
        if ($conversation->status === 'blocked') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send message to blocked conversation'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required_without:media|string|max:1000',
            'type' => 'sometimes|in:text,image,voice,file',
            'message_type' => 'sometimes|in:text,image,voice,file',
            'media' => 'required_without:content|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $otherUser = $conversation->user_one_id === $user->id ? $conversation->userTwo : $conversation->userOne;
            
            $messageData = [
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'receiver_id' => $otherUser->id,
                'message' => $request->get('content'),
                'type' => $request->get('type') ?? $request->get('message_type', 'text'),
                'status' => 'sent',
            ];

            // Handle media upload
            if ($request->hasFile('media')) {
                $media = $request->file('media');
                $mediaPath = 'chat/' . $conversation->id . '/' . Str::uuid() . '.' . $media->getClientOriginalExtension();
                
                Storage::put($mediaPath, file_get_contents($media));
                
                $messageData['media_files'] = [Storage::url($mediaPath)];
                $messageData['metadata'] = [
                    'media_size' => $media->getSize(),
                    'media_type' => $media->getMimeType(),
                    'original_filename' => $media->getClientOriginalName(),
                ];
                
                // Set type based on media
                if (str_starts_with($media->getMimeType(), 'image/')) {
                    $messageData['type'] = 'image';
                } elseif (str_starts_with($media->getMimeType(), 'audio/')) {
                    $messageData['type'] = 'voice';
                } else {
                    $messageData['type'] = 'file';
                }
            } else {
                // Handle direct URL input (for testing)
                if ($request->has('attachment_url')) {
                    $messageData['media_files'] = [$request->get('attachment_url')];
                    $messageData['metadata'] = $request->get('attachment_metadata', []);
                    $messageData['type'] = 'image'; // Force type to image for attachment_url
                }
                
                if ($request->has('voice_url')) {
                    $messageData['media_files'] = [$request->get('voice_url')];
                    $messageData['metadata'] = [
                        'duration' => $request->get('voice_duration', 0),
                        'media_type' => 'audio/mpeg'
                    ];
                    $messageData['type'] = 'voice'; // Force type to voice for voice_url
                }
            }

            // Create message using the appropriate static method
            if ($request->hasFile('media')) {
                if (str_starts_with($media->getMimeType(), 'image/')) {
                    $message = Message::createImageMessage($conversation, $user, Storage::url($mediaPath), $messageData['metadata']);
                } elseif (str_starts_with($media->getMimeType(), 'audio/')) {
                    $message = Message::createVoiceMessage($conversation, $user, Storage::url($mediaPath), 0); // Duration not available
                } else {
                    $message = Message::create($messageData);
                }
            } else {
                // Check if we have special message types (image, voice) from direct URL input
                if (isset($messageData['type']) && $messageData['type'] === 'image') {
                    $message = Message::createImageMessage($conversation, $user, $messageData['media_files'][0], $messageData['metadata']);
                } elseif (isset($messageData['type']) && $messageData['type'] === 'voice') {
                    // Create voice message using the proper method
                    $message = Message::createVoiceMessage($conversation, $user, $messageData['media_files'][0], $messageData['metadata']['duration'] ?? 0);
                } else {
                    $message = Message::createTextMessage($conversation, $user, $request->get('content'));
                }
            }

            // Update conversation
            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);
            
            // Increment unread count for the receiver
            $conversation->incrementUnreadCount($otherUser);

            // Get other user for notifications
            $otherUser = $conversation->user_one_id === $user->id ? 
                $conversation->userTwo : $conversation->userOne;
            
            // Load the sender with profile picture for the event
            $message->load('sender.profilePicture');
            
            // Load conversation relationships for the event
            $conversation->load('userOne', 'userTwo');
            
            // Fire message sent event (handles both real-time and push notifications)
            // Temporarily disabled for debugging
            // event(new \App\Events\MessageSent($message, $conversation));

            $responseData = [
                'id' => $message->id,
                'content' => $message->message,
                'message_type' => $message->type,
                'sender_id' => $message->sender_id,
                'created_at' => $message->created_at,
            ];
            
            // Add media-specific fields for testing
            if ($message->type === 'image' && $message->media_files) {
                $responseData['attachment_url'] = $message->media_files[0] ?? null;
            }
            
            if ($message->type === 'voice' && $message->media_files) {
                $responseData['voice_url'] = $message->media_files[0] ?? null;
                $responseData['voice_duration'] = $message->metadata['duration'] ?? 0;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $responseData
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update message (for editing)
     */
    public function updateMessage(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        // Check if user owns this message
        if ($message->sender_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit this message'
            ], 403);
        }

        // Check if message can be edited (within time limit)
        if ($message->created_at->diffInMinutes(now()) > 5) {
            return response()->json([
                'success' => false,
                'message' => 'Message can only be edited within 5 minutes of sending'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $message->update([
                'message' => $request->content,
                'metadata' => array_merge($message->metadata ?? [], [
                    'edited_at' => now()->toISOString(),
                    'is_edited' => true,
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message updated successfully',
                'data' => [
                    'content' => $message->message,
                    'is_edited' => true,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a message
     */
    public function deleteMessage(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        // Check if user owns this message
        if ($message->sender_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this message'
            ], 403);
        }

        try {
            // Delete media files if exist
            if ($message->media_files) {
                foreach ($message->media_files as $mediaFile) {
                    $path = str_replace('/storage/', '', $mediaFile);
                    if (Storage::exists($path)) {
                        Storage::delete($path);
                    }
                }
            }

            // Soft delete or mark as deleted
            $message->update([
                'message' => '[Message deleted]',
                'status' => 'deleted',
                'deleted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        // Check if user is the recipient
        if ($message->sender_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mark own message as read'
            ], 400);
        }

        // Check if user is part of the conversation
        $conversation = $message->conversation;
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $message->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark message as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Block a conversation
     */
    public function blockConversation(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Check if user is part of this conversation
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to conversation'
            ], 403);
        }

        try {
            $conversation->update(['status' => 'blocked']);

            return response()->json([
                'success' => true,
                'message' => 'Conversation blocked successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to block conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start a new conversation with a user
     */
    public function startConversation(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $otherUserId = $request->user_id;

            // Check if user is trying to start conversation with themselves
            if ($user->id === $otherUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot start conversation with yourself'
                ], 400);
            }

            // Check if conversation already exists
            $existingConversation = Conversation::where(function ($query) use ($user, $otherUserId) {
                $query->where(function ($q) use ($user, $otherUserId) {
                    $q->where('user_one_id', $user->id)
                      ->where('user_two_id', $otherUserId);
                })->orWhere(function ($q) use ($user, $otherUserId) {
                    $q->where('user_one_id', $otherUserId)
                      ->where('user_two_id', $user->id);
                });
            })->where('status', 'active')->first();

            if ($existingConversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation already exists',
                    'data' => [
                        'conversation_id' => $existingConversation->id,
                        'conversation' => $this->formatConversation($existingConversation, $user)
                    ]
                ], 409);
            }

            // Check if users are matched (optional requirement)
            $match = UserMatch::where(function ($query) use ($user, $otherUserId) {
                $query->where('user_one_id', $user->id)
                      ->where('user_two_id', $otherUserId)
                      ->where('status', 'matched');
            })->orWhere(function ($query) use ($user, $otherUserId) {
                $query->where('user_one_id', $otherUserId)
                      ->where('user_two_id', $user->id)
                      ->where('status', 'matched');
            })->first();

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Users must be matched to start a conversation'
                ], 403);
            }

            // Create new conversation
            $conversation = Conversation::create([
                'user_one_id' => $user->id,
                'user_two_id' => $otherUserId,
                'type' => 'direct',
                'is_group' => false,
                'status' => 'active',
                'last_message_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation started successfully',
                'data' => [
                    'conversation_id' => $conversation->id,
                    'conversation' => $this->formatConversation($conversation, $user)
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format conversation for response
     */
    private function formatConversation(Conversation $conversation, User $currentUser): array
    {
        $otherUser = $conversation->user_one_id === $currentUser->id ? $conversation->userTwo : $conversation->userOne;
        $unreadCount = $conversation->getUnreadCount($currentUser);

        return [
            'id' => $conversation->id,
            'name' => $conversation->getDisplayName($currentUser),
            'type' => $conversation->type,
            'is_group' => $conversation->is_group,
            'last_message_at' => $conversation->last_message_at,
            'unread_count' => $unreadCount,
            'participants' => [
                [
                    'id' => $currentUser->id,
                    'name' => $currentUser->first_name,
                ],
                [
                    'id' => $otherUser->id,
                    'name' => $otherUser->first_name,
                ]
            ],
            'last_message' => $conversation->lastMessage ? [
                'id' => $conversation->lastMessage->id,
                'content' => $conversation->lastMessage->content,
                'type' => $conversation->lastMessage->type,
                'sender_id' => $conversation->lastMessage->sender_id,
                'sent_at' => $conversation->lastMessage->created_at,
                'status' => $conversation->lastMessage->status,
            ] : null,
        ];
    }

    /**
     * Delete/Archive a conversation
     */
    public function deleteConversation(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Check if user is part of this conversation
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to conversation'
            ], 403);
        }

        try {
            // Archive instead of permanent delete
            $conversation->update(['status' => 'archived']);

            return response()->json([
                'success' => true,
                'message' => 'Conversation archived successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
