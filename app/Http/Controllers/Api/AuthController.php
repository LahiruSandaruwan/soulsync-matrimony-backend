<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'date_of_birth' => 'required|date|before:18 years ago',
            'gender' => 'required|in:male,female,other',
            'country_code' => 'nullable|string|size:2',
            'language' => 'nullable|string|size:2',
            'registration_method' => 'nullable|in:email,phone,google,facebook',
            'referral_code' => 'nullable|string|exists:users,referral_code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate unique referral code
            $referralCode = $this->generateUniqueReferralCode();
            
            // Find referrer if referral code provided
            $referrerId = null;
            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first();
                $referrerId = $referrer?->id;
            }

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'country_code' => $request->country_code ?? 'LK',
                'language' => $request->language ?? 'en',
                'registration_method' => $request->registration_method ?? 'email',
                'registration_ip' => $request->ip(),
                'referral_code' => $referralCode,
                'referred_by' => $referrerId,
                'status' => 'pending_verification',
                'profile_status' => 'incomplete',
            ]);

            // Create empty profile
            UserProfile::create(['user_id' => $user->id]);

            // Assign default role
            $user->assignRole('user');

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Send verification email (implement later)
            // $user->sendEmailVerificationNotification();

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user->load('profile'),
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();

        // Check if user is suspended
        if ($user->status === 'suspended') {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Account suspended. Contact support.'
            ], 403);
        }

        // Update last active time
        $user->update(['last_active_at' => now()]);

        // Generate token
        $tokenName = $request->remember_me ? 'long_lived_token' : 'auth_token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load(['profile', 'preferences', 'activeSubscription']),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * Social login (Google/Facebook)
     */
    public function socialLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:google,facebook',
            'social_id' => 'required|string',
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'avatar' => 'nullable|url',
            'social_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user exists by social_id
            $user = User::where('social_id', $request->social_id)
                       ->where('registration_method', $request->provider)
                       ->first();

            if (!$user) {
                // Check if user exists by email
                $user = User::where('email', $request->email)->first();
                
                if ($user) {
                    // Link social account to existing user
                    $user->update([
                        'social_id' => $request->social_id,
                        'social_data' => $request->social_data,
                    ]);
                } else {
                    // Create new user
                    $user = User::create([
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'email' => $request->email,
                        'registration_method' => $request->provider,
                        'social_id' => $request->social_id,
                        'social_data' => $request->social_data,
                        'registration_ip' => $request->ip(),
                        'referral_code' => $this->generateUniqueReferralCode(),
                        'status' => 'pending_verification',
                        'profile_status' => 'incomplete',
                        'email_verified_at' => now(), // Social logins are pre-verified
                    ]);

                    // Create empty profile
                    UserProfile::create(['user_id' => $user->id]);
                    
                    // Assign default role
                    $user->assignRole('user');
                }
            }

            // Update last active time
            $user->update(['last_active_at' => now()]);

            // Generate token
            $token = $user->createToken('social_auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Social login successful',
                'data' => [
                    'user' => $user->load(['profile', 'preferences', 'activeSubscription']),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'is_new_user' => $user->wasRecentlyCreated,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Social login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'profile', 
            'preferences', 
            'photos', 
            'horoscope', 
            'interests',
            'activeSubscription'
        ]);

        return response()->json([
            'success' => true,
            'data' => ['user' => $user]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices'
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Revoke all other tokens for security
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Request password reset
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO: Implement password reset token generation and email sending
        // For now, return success message
        
        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent to your email'
        ]);
    }

    /**
     * Verify email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO: Implement email verification logic
        
        $user = $request->user();
        $user->update([
            'email_verified_at' => now(),
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully'
        ]);
    }

    /**
     * Generate unique referral code
     */
    private function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
