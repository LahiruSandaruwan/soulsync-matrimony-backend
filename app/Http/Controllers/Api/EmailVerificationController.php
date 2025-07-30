<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(Request $request): JsonResponse
    {
        $user = User::find($request->route('id'));

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if (!hash_equals(
            (string) $request->route('hash'),
            sha1($user->getEmailForVerification())
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link',
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            Log::info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'user' => $user->fresh(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to verify email',
        ], 500);
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified',
            ], 400);
        }

        // Check if we can resend (rate limiting)
        $lastSent = $user->email_verification_sent_at;
        if ($lastSent && $lastSent->addMinutes(5)->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait 5 minutes before requesting another verification email',
            ], 429);
        }

        $user->sendVerificationEmail();

        Log::info('Verification email resent', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent successfully',
        ]);
    }

    /**
     * Check if user's email is verified
     */
    public function check(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'verified' => $user->hasVerifiedEmail(),
            'email' => $user->email,
        ]);
    }

    /**
     * Get verification status for unauthenticated users (for email links)
     */
    public function status(Request $request): JsonResponse
    {
        $user = User::find($request->route('id'));

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'verified' => $user->hasVerifiedEmail(),
            'email' => $user->email,
        ]);
    }
} 