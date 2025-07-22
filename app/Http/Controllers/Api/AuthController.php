<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'sometimes|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'date_of_birth' => 'required|date|before:' . now()->subYears(18)->format('Y-m-d'),
            'gender' => 'required|in:male,female,other',
            'country_code' => 'sometimes|string|size:2',
            'language' => 'sometimes|string|size:2',
            'registration_method' => 'sometimes|in:email,phone,google,facebook,apple',
            'referral_code' => 'sometimes|string|exists:users,referral_code',
            'terms_accepted' => 'required|accepted',
            'privacy_accepted' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            
            // Generate unique referral code
            $data['referral_code'] = $this->generateReferralCode();
            
            // Set registration details
            $data['registration_ip'] = $request->ip();
            $data['registration_method'] = $data['registration_method'] ?? 'email';
            $data['country_code'] = $data['country_code'] ?? 'US';
            $data['language'] = $data['language'] ?? 'en';
            $data['name'] = $data['first_name'] . ' ' . $data['last_name']; // For compatibility
            
            // Handle referral
            $referrer = null;
            if (!empty($data['referral_code'])) {
                $referrer = User::where('referral_code', $data['referral_code'])->first();
                $data['referred_by'] = $referrer?->id;
            }
            
            // Hash password
            $data['password'] = Hash::make($data['password']);
            
            // Create user
            $user = User::create($data);
            
            // Create empty profile and preferences
            $user->profile()->create([]);
            $user->preferences()->create([
                'min_age' => 18,
                'max_age' => 50,
                'preferred_genders' => [$user->gender === 'male' ? 'female' : 'male'],
                'preferred_countries' => [$user->country_code],
            ]);
            
            // Give bonus for referrer
            if ($referrer) {
                $this->handleReferralBonus($referrer, $user);
            }
            
            // Create API token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            DB::commit();

            // Send welcome email (queue this in production)
            $this->sendWelcomeEmail($user);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => $this->formatUserResponse($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'next_step' => 'complete_profile'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
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
            'remember_me' => 'sometimes|boolean',
            'device_name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $credentials = $request->only('email', 'password');
            $user = User::where('email', $credentials['email'])->first();

            // Check if user exists
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if account is locked
            if ($user->locked_until && $user->locked_until->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account temporarily locked due to multiple failed attempts',
                    'locked_until' => $user->locked_until->toISOString()
                ], 423);
            }

            // Check password
            if (!Hash::check($credentials['password'], $user->password)) {
                $this->handleFailedLogin($user, $request);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'failed_attempts' => $user->failed_login_attempts
                ], 401);
            }

            // Check if account is active
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is not active. Please contact support.',
                    'account_status' => $user->status
                ], 403);
            }

            // Successful login
            $this->handleSuccessfulLogin($user, $request);
            
            // Create token
            $deviceName = $request->get('device_name', 'API');
            $token = $user->createToken($deviceName)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $this->formatUserResponse($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'profile_completion' => $user->profile_completion_percentage ?? 0,
                    'next_step' => $this->getNextStep($user)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Social login (Google, Facebook, Apple)
     */
    public function socialLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:google,facebook,apple',
            'social_id' => 'required|string',
            'email' => 'required|email',
            'first_name' => 'required|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'avatar' => 'sometimes|url',
            'social_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $provider = $data['provider'];
            $socialId = $data['social_id'];
            $email = $data['email'];

            // Check if user exists with this social account
            $user = User::where('social_id', $socialId)
                       ->where('registration_method', $provider)
                       ->first();

            if (!$user) {
                // Check if user exists with this email
                $user = User::where('email', $email)->first();
                
                if ($user) {
                    // Link social account to existing user
                    $user->update([
                        'social_id' => $socialId,
                        'social_data' => $data['social_data'] ?? null,
                    ]);
                } else {
                    // Create new user
                    $user = User::create([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'] ?? '',
                        'name' => $data['first_name'] . ' ' . ($data['last_name'] ?? ''),
                        'email' => $email,
                        'social_id' => $socialId,
                        'registration_method' => $provider,
                        'registration_ip' => $request->ip(),
                        'social_data' => $data['social_data'] ?? null,
                        'referral_code' => $this->generateReferralCode(),
                        'email_verified' => true, // Social accounts are pre-verified
                        'password' => Hash::make(Str::random(32)), // Random password
                    ]);

                    // Create empty profile and preferences
                    $user->profile()->create([]);
                    $user->preferences()->create([
                        'min_age' => 18,
                        'max_age' => 50,
                    ]);
                }
            }

            // Update last login
            $this->handleSuccessfulLogin($user, $request);
            
            // Create token
            $token = $user->createToken($provider . '_login')->plainTextToken;
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Social login successful',
                'data' => [
                    'user' => $this->formatUserResponse($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'is_new_user' => $user->wasRecentlyCreated,
                    'next_step' => $this->getNextStep($user)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
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
        try {
            $user = $request->user()->load(['profile', 'preferences']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->formatUserResponse($user),
                    'profile_completion' => $user->profile_completion_percentage ?? 0,
                    'is_premium' => $user->is_premium,
                    'premium_expires_at' => $user->premium_expires_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Delete current access token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            // Delete all user tokens
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all devices'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send password reset email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to send reset link'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'password_changed_at' => now(),
                        'failed_login_attempts' => 0,
                        'locked_until' => null,
                    ])->save();

                    // Revoke all existing tokens for security
                    $user->tokens()->delete();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token or email'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password',
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

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verification_code' => 'required|string|size:6',
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
            
            // In a real implementation, you'd check against a verification code
            // For now, we'll just mark as verified
            $user->update([
                'email_verified' => true,
                'email_verified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'reason' => 'sometimes|string|max:500',
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

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            DB::beginTransaction();

            // Soft delete by marking as deleted
            $user->update([
                'status' => 'deleted',
                'email' => 'deleted_' . $user->id . '_' . $user->email,
                'phone' => null,
            ]);

            // Delete all tokens
            $user->tokens()->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Account deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper Methods

    /**
     * Generate unique referral code
     */
    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Handle failed login attempt
     */
    private function handleFailedLogin(User $user, Request $request): void
    {
        $attempts = $user->failed_login_attempts + 1;
        
        $updateData = [
            'failed_login_attempts' => $attempts,
            'last_login_ip' => $request->ip(),
        ];

        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $updateData['locked_until'] = now()->addMinutes(30);
        }

        $user->update($updateData);
    }

    /**
     * Handle successful login
     */
    private function handleSuccessfulLogin(User $user, Request $request): void
    {
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_active_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_device' => $request->userAgent(),
            'login_count' => $user->login_count + 1,
            'first_login_at' => $user->first_login_at ?? now(),
        ]);
    }

    /**
     * Handle referral bonus
     */
    private function handleReferralBonus(User $referrer, User $newUser): void
    {
        // Give premium days to referrer
        if ($referrer->is_premium) {
            $referrer->premium_expires_at = $referrer->premium_expires_at->addDays(7);
        } else {
            $referrer->update([
                'is_premium' => true,
                'premium_expires_at' => now()->addDays(3),
            ]);
        }
        $referrer->save();
    }

    /**
     * Send welcome email
     */
    private function sendWelcomeEmail(User $user): void
    {
        // In production, queue this email
        // Mail::to($user->email)->queue(new WelcomeEmail($user));
    }

    /**
     * Format user response
     */
    private function formatUserResponse(User $user): array
    {
        $localCurrency = $this->getLocalCurrency($user->country_code);
        
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'age' => $user->age,
            'gender' => $user->gender,
            'country_code' => $user->country_code,
            'language' => $user->language,
            'status' => $user->status,
            'profile_status' => $user->profile_status,
            'is_premium' => $user->is_premium,
            'premium_expires_at' => $user->premium_expires_at?->toISOString(),
            'profile_completion' => $user->profile_completion_percentage ?? 0,
            'last_active_at' => $user->last_active_at?->toISOString(),
            'verification' => [
                'email_verified' => $user->email_verified,
                'phone_verified' => $user->phone_verified,
                'photo_verified' => $user->photo_verified,
                'id_verified' => $user->id_verified,
            ],
            'referral_code' => $user->referral_code,
            'currency' => [
                'base' => 'USD',
                'local' => $localCurrency,
                'exchange_rate' => ExchangeRate::getRate('USD', $localCurrency),
            ],
        ];
    }

    /**
     * Get local currency based on country
     */
    private function getLocalCurrency(string $countryCode): string
    {
        $currencies = [
            'LK' => 'LKR',
            'IN' => 'INR',
            'GB' => 'GBP',
            'AU' => 'AUD',
            'CA' => 'CAD',
            'SG' => 'SGD',
            'AE' => 'AED',
            'SA' => 'SAR',
        ];

        return $currencies[$countryCode] ?? 'USD';
    }

    /**
     * Get next step for user onboarding
     */
    private function getNextStep(User $user): string
    {
        if (!$user->profile || $user->profile_completion_percentage < 50) {
            return 'complete_profile';
        }
        
        if (!$user->preferences || !$user->preferences->areComplete()) {
            return 'set_preferences';
        }
        
        if (!$user->photos()->where('status', 'approved')->exists()) {
            return 'upload_photos';
        }
        
        return 'start_matching';
    }
}
