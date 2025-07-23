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
                $query->where('user1_id', $user->id)
                      ->orWhere('user2_id', $user->id);
            })
            ->where('status', $status)
            ->with(['lastMessage', 'user1', 'user2'])
            ->orderBy('last_message_at', 'desc');

            $totalCount = $conversationsQuery->count();
            $conversations = $conversationsQuery->offset($offset)->limit($limit)->get();

            $formattedConversations = $conversations->map(function ($conversation) use ($user) {
                $otherUser = $conversation->user1_id === $user->id ? $conversation->user2 : $conversation->user1;
                $unreadCount = $conversation->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->where('status', '!=', 'read')
                    ->count();

                return [
                    'id' => $conversation->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'first_name' => $otherUser->first_name,
                        'profile_picture' => $otherUser->profilePicture ? 
                            Storage::url($otherUser->profilePicture->file_path) : null,
                        'last_active_at' => $otherUser->last_active_at,
                        'is_online' => $otherUser->last_active_at && 
                            $otherUser->last_active_at->diffInMinutes(now()) < 15,
                    ],
                    'last_message' => $conversation->lastMessage ? [
                        'id' => $conversation->lastMessage->id,
                        'content' => $conversation->lastMessage->content,
                        'type' => $conversation->lastMessage->type,
                        'sender_id' => $conversation->lastMessage->sender_id,
                        'sent_at' => $conversation->lastMessage->created_at,
                        'status' => $conversation->lastMessage->status,
                    ] : null,
                    'unread_count' => $unreadCount,
                    'status' => $conversation->status,
                    'created_at' => $conversation->created_at,
                    'last_message_at' => $conversation->last_message_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'conversations' => $formattedConversations,
                    'pagination' => [
                        'current_page' => $page,
                        'total_conversations' => $totalCount,
                        'has_more' => ($offset + $limit) < $totalCount,
                    ],
                    'unread_total' => $conversations->sum(function ($conv) use ($user) {
                        return $conv->messages()
                            ->where('sender_id', '!=', $user->id)
                            ->where('status', '!=', 'read')
                            ->count();
                    }),
                ]
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
        if ($conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id) {
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
            $otherUser = $conversation->user1_id === $user->id ? $conversation->user2 : $conversation->user1;

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
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'type' => $message->type,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->first_name,
                    'media_url' => $message->media_path ? Storage::url($message->media_path) : null,
                    'status' => $message->status,
                    'sent_at' => $message->created_at,
                    'read_at' => $message->read_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation' => [
                        'id' => $conversation->id,
                        'status' => $conversation->status,
                        'created_at' => $conversation->created_at,
                    ],
                    'other_user' => [
                        'id' => $otherUser->id,
                        'first_name' => $otherUser->first_name,
                        'profile_picture' => $otherUser->profilePicture ? 
                            Storage::url($otherUser->profilePicture->file_path) : null,
                        'last_active_at' => $otherUser->last_active_at,
                        'is_online' => $otherUser->last_active_at && 
                            $otherUser->last_active_at->diffInMinutes(now()) < 15,
                    ],
                    'messages' => $formattedMessages,
                    'pagination' => [
                        'current_page' => $page,
                        'total_messages' => $totalMessages,
                        'has_more' => ($offset + $limit) < $totalMessages,
                    ],
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
        if ($conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id) {
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
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required_without:media|string|max:1000',
            'type' => 'sometimes|in:text,image,voice,file',
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
            $messageData = [
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'content' => $request->get('content'),
                'type' => $request->get('type', 'text'),
                'status' => 'sent',
            ];

            // Handle media upload
            if ($request->hasFile('media')) {
                $media = $request->file('media');
                $mediaPath = 'chat/' . $conversation->id . '/' . Str::uuid() . '.' . $media->getClientOriginalExtension();
                
                Storage::put($mediaPath, file_get_contents($media));
                
                $messageData['media_path'] = $mediaPath;
                $messageData['media_size'] = $media->getSize();
                $messageData['media_type'] = $media->getMimeType();
                $messageData['original_filename'] = $media->getClientOriginalName();
                
                // Set type based on media
                if (str_starts_with($media->getMimeType(), 'image/')) {
                    $messageData['type'] = 'image';
                } elseif (str_starts_with($media->getMimeType(), 'audio/')) {
                    $messageData['type'] = 'voice';
                } else {
                    $messageData['type'] = 'file';
                }
            }

            // Create message
            $message = Message::create($messageData);

            // Update conversation
            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);

            // Get other user for notifications
            $otherUser = $conversation->user1_id === $user->id ? 
                $conversation->user2 : $conversation->user1;
            
            // Fire message sent event (handles both real-time and push notifications)
            event(new \App\Events\MessageSent($message, $user, $otherUser));

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'content' => $message->content,
                        'type' => $message->type,
                        'sender_id' => $message->sender_id,
                        'media_url' => $message->media_path ? Storage::url($message->media_path) : null,
                        'status' => $message->status,
                        'sent_at' => $message->created_at,
                    ]
                ]
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
        if ($message->created_at->diffInMinutes(now()) > 15) {
            return response()->json([
                'success' => false,
                'message' => 'Message can only be edited within 15 minutes of sending'
            ], 400);
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
                'content' => $request->content,
                'edited_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message updated successfully',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'content' => $message->content,
                        'edited_at' => $message->edited_at,
                    ]
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
            // Delete media file if exists
            if ($message->media_path && Storage::exists($message->media_path)) {
                Storage::delete($message->media_path);
            }

            // Soft delete or mark as deleted
            $message->update([
                'content' => '[Message deleted]',
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
        if ($conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id) {
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
        if ($conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id) {
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
     * Delete/Archive a conversation
     */
    public function deleteConversation(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Check if user is part of this conversation
        if ($conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id) {
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
