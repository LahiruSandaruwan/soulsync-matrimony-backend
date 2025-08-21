<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VideoCall;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VideoCallController extends Controller
{
    /**
     * Get video call history for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
            'status' => 'sometimes|in:pending,accepted,rejected,ended,missed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $status = $request->get('status');
            $offset = ($page - 1) * $limit;

            $query = VideoCall::where(function($q) use ($user) {
                $q->where('caller_id', $user->id)
                  ->orWhere('callee_id', $user->id);
            })
            ->with(['caller:id,first_name,last_name', 'callee:id,first_name,last_name'])
            ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $totalCount = $query->count();
            $calls = $query->offset($offset)->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'calls' => $calls,
                    'pagination' => [
                        'current_page' => $page,
                        'total_calls' => $totalCount,
                        'has_more' => ($offset + $limit) < $totalCount,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get video call history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate a video call
     */
    public function initiate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'callee_id' => 'required|integer|exists:users,id',
            'conversation_id' => 'sometimes|integer|exists:conversations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $caller = $request->user();
            $calleeId = $request->callee_id;

            // Check if caller is trying to call themselves
            if ($caller->id === $calleeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot call yourself'
                ], 400);
            }

            // Check if callee exists and is active
            $callee = User::where('id', $calleeId)
                         ->where('status', 'active')
                         ->first();

            if (!$callee) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or not available'
                ], 404);
            }

            // Check if caller has video call privileges (premium feature)
            if (!$caller->is_premium && !env('ENABLE_VIDEO_CALLS', false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video calling is a premium feature'
                ], 403);
            }

            // Check if there's already an ongoing call between these users
            $existingCall = VideoCall::where(function($q) use ($caller, $callee) {
                $q->where('caller_id', $caller->id)->where('callee_id', $callee->id);
            })
            ->orWhere(function($q) use ($caller, $callee) {
                $q->where('caller_id', $callee->id)->where('callee_id', $caller->id);
            })
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

            if ($existingCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'There is already an ongoing call between these users'
                ], 409);
            }

            DB::beginTransaction();

            // Create video call record
            $videoCall = VideoCall::create([
                'caller_id' => $caller->id,
                'callee_id' => $callee->id,
                'conversation_id' => $request->conversation_id,
                'call_id' => $this->generateCallId(),
                'status' => 'pending',
                'initiated_at' => now(),
            ]);

            // Generate video call tokens (using a simple implementation here)
            $tokens = $this->generateVideoCallTokens($videoCall);

            $videoCall->update([
                'caller_token' => $tokens['caller_token'],
                'callee_token' => $tokens['callee_token'],
                'room_id' => $tokens['room_id'],
            ]);

            DB::commit();

            // Send real-time notification to callee
            $this->notifyCallee($callee, $videoCall, $caller);

            return response()->json([
                'success' => true,
                'message' => 'Video call initiated',
                'data' => [
                    'call' => $videoCall->fresh(['caller', 'callee']),
                    'caller_token' => $tokens['caller_token'],
                    'room_id' => $tokens['room_id'],
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate video call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept a video call
     */
    public function accept(Request $request, VideoCall $videoCall): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is the callee
            if ($videoCall->callee_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to accept this call'
                ], 403);
            }

            // Check if call is still pending
            if ($videoCall->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Call is no longer pending'
                ], 400);
            }

            // Check if call has expired (e.g., after 60 seconds)
            if ($videoCall->initiated_at->diffInSeconds(now()) > 60) {
                $videoCall->update(['status' => 'missed']);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Call has expired'
                ], 400);
            }

            $videoCall->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            // Notify caller that call was accepted
            $this->notifyCaller($videoCall->caller, $videoCall);

            return response()->json([
                'success' => true,
                'message' => 'Video call accepted',
                'data' => [
                    'call' => $videoCall->fresh(['caller', 'callee']),
                    'callee_token' => $videoCall->callee_token,
                    'room_id' => $videoCall->room_id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept video call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a video call
     */
    public function reject(Request $request, VideoCall $videoCall): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is the callee
            if ($videoCall->callee_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to reject this call'
                ], 403);
            }

            // Check if call is still pending
            if ($videoCall->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Call is no longer pending'
                ], 400);
            }

            $videoCall->update([
                'status' => 'rejected',
                'ended_at' => now(),
            ]);

            // Notify caller that call was rejected
            $this->notifyCaller($videoCall->caller, $videoCall);

            return response()->json([
                'success' => true,
                'message' => 'Video call rejected'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject video call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * End a video call
     */
    public function end(Request $request, VideoCall $videoCall): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|in:normal,network_issue,technical_issue,user_ended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Check if user is part of this call
            if ($videoCall->caller_id !== $user->id && $videoCall->callee_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to end this call'
                ], 403);
            }

            // Check if call is in a state that can be ended
            if (!in_array($videoCall->status, ['pending', 'accepted'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Call cannot be ended in its current state'
                ], 400);
            }

            // Calculate call duration if it was accepted
            $duration = null;
            if ($videoCall->status === 'accepted' && $videoCall->accepted_at) {
                $duration = $videoCall->accepted_at->diffInSeconds(now());
            }

            $videoCall->update([
                'status' => 'ended',
                'ended_at' => now(),
                'duration_seconds' => $duration,
                'end_reason' => $request->get('reason', 'normal'),
            ]);

            // Notify the other participant
            $otherUser = $videoCall->caller_id === $user->id ? $videoCall->callee : $videoCall->caller;
            $this->notifyCallEnded($otherUser, $videoCall);

            return response()->json([
                'success' => true,
                'message' => 'Video call ended',
                'data' => [
                    'call' => $videoCall->fresh(),
                    'duration_seconds' => $duration,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to end video call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get call details
     */
    public function show(Request $request, VideoCall $videoCall): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is part of this call
            if ($videoCall->caller_id !== $user->id && $videoCall->callee_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Call not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'call' => $videoCall->load(['caller', 'callee']),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get call details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper Methods

    /**
     * Generate unique call ID
     */
    private function generateCallId(): string
    {
        do {
            $callId = 'call_' . Str::random(16);
        } while (VideoCall::where('call_id', $callId)->exists());

        return $callId;
    }

    /**
     * Generate video call tokens (simplified implementation)
     * In production, you would integrate with services like Agora, Twilio, or WebRTC
     */
    private function generateVideoCallTokens(VideoCall $videoCall): array
    {
        $roomId = 'room_' . $videoCall->id . '_' . time();
        
        // In production, these would be actual tokens from video service provider
        return [
            'room_id' => $roomId,
            'caller_token' => 'token_caller_' . Str::random(32),
            'callee_token' => 'token_callee_' . Str::random(32),
        ];
    }

    /**
     * Notify callee about incoming call
     */
    private function notifyCallee(User $callee, VideoCall $videoCall, User $caller): void
    {
        // Send push notification
        $notification = [
            'type' => 'video_call_incoming',
            'title' => 'Incoming Video Call',
            'body' => $caller->first_name . ' is calling you',
            'data' => [
                'call_id' => $videoCall->id,
                'caller_id' => $caller->id,
                'caller_name' => $caller->first_name . ' ' . $caller->last_name,
                'room_id' => $videoCall->room_id,
            ]
        ];

        // Send via WebSocket (real-time)
        // broadcast(new \App\Events\VideoCallIncoming($callee->id, $notification));
    }

    /**
     * Notify caller about call acceptance
     */
    private function notifyCaller(User $caller, VideoCall $videoCall): void
    {
        $notification = [
            'type' => 'video_call_accepted',
            'data' => [
                'call_id' => $videoCall->id,
                'status' => $videoCall->status,
                'room_id' => $videoCall->room_id,
            ]
        ];

        // broadcast(new \App\Events\VideoCallStatusChanged($caller->id, $notification));
    }

    /**
     * Notify user about call ending
     */
    private function notifyCallEnded(User $user, VideoCall $videoCall): void
    {
        $notification = [
            'type' => 'video_call_ended',
            'data' => [
                'call_id' => $videoCall->id,
                'duration_seconds' => $videoCall->duration_seconds,
                'end_reason' => $videoCall->end_reason,
            ]
        ];

        // broadcast(new \App\Events\VideoCallEnded($user->id, $notification));
    }
}
