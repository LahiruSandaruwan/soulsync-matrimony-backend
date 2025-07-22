<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorAuth;
use App\Models\TwoFactorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    /**
     * Get 2FA status and settings
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $twoFactor = $user->twoFactorAuth;

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $twoFactor?->enabled ?? false,
                'method' => $twoFactor?->method ?? 'totp',
                'phone_verified' => $twoFactor?->phone_verified ?? false,
                'recovery_codes_count' => count($twoFactor?->recovery_codes ?? []),
                'low_recovery_codes' => $twoFactor?->hasLowRecoveryCodes() ?? false,
                'enabled_at' => $twoFactor?->enabled_at?->toISOString(),
            ]
        ]);
    }

    /**
     * Setup 2FA - Generate secret and QR code
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'method' => 'required|in:totp,sms,email',
            'phone' => 'required_if:method,sms|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $method = $request->get('method');

            // Create or update 2FA configuration
            $twoFactor = $user->twoFactorAuth ?: new TwoFactorAuth(['user_id' => $user->id]);

            if ($method === 'totp') {
                // Generate TOTP secret
                $secret = $this->generateTotpSecret();
                $twoFactor->fill([
                    'method' => 'totp',
                    'secret' => $secret,
                    'enabled' => false, // Will be enabled after verification
                ]);
                $twoFactor->save();

                return response()->json([
                    'success' => true,
                    'message' => '2FA setup initiated',
                    'data' => [
                        'method' => 'totp',
                        'secret' => $secret,
                        'qr_code_url' => $twoFactor->getQrCodeUrl(),
                        'manual_entry_key' => $secret,
                        'backup_codes' => [], // Will be generated after verification
                    ]
                ]);

            } elseif ($method === 'sms') {
                $phone = $request->get('phone');
                $twoFactor->fill([
                    'method' => 'sms',
                    'phone' => $phone,
                    'phone_verified' => false,
                    'enabled' => false,
                ]);
                $twoFactor->save();

                // Send verification code
                $code = TwoFactorCode::generateFor($user, 'setup');
                $code->sendViaSms();

                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent to your phone',
                    'data' => [
                        'method' => 'sms',
                        'phone' => $this->maskPhone($phone),
                        'code_expires_in' => 600, // 10 minutes
                    ]
                ]);

            } elseif ($method === 'email') {
                $twoFactor->fill([
                    'method' => 'email',
                    'enabled' => false,
                ]);
                $twoFactor->save();

                // Send verification code
                $code = TwoFactorCode::generateFor($user, 'setup');
                $code->sendViaEmail();

                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent to your email',
                    'data' => [
                        'method' => 'email',
                        'email' => $this->maskEmail($user->email),
                        'code_expires_in' => 600, // 10 minutes
                    ]
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to setup 2FA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify setup and enable 2FA
     */
    public function verifySetup(Request $request): JsonResponse
    {
        $user = $request->user();
        $twoFactor = $user->twoFactorAuth;

        if (!$twoFactor) {
            return response()->json([
                'success' => false,
                'message' => '2FA setup not initiated'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password'
            ], 400);
        }

        try {
            $code = $request->get('code');
            $verified = false;

            if ($twoFactor->method === 'totp') {
                $verified = $twoFactor->verifyTotp($code);
            } else {
                $verified = TwoFactorCode::verify($user, $code, 'setup');
            }

            if (!$verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code'
                ], 400);
            }

            // Enable 2FA
            $twoFactor->enable();

            // Generate recovery codes
            $recoveryCodes = $twoFactor->generateRecoveryCodes();

            // Mark phone as verified for SMS
            if ($twoFactor->method === 'sms') {
                $twoFactor->update(['phone_verified' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => '2FA enabled successfully',
                'data' => [
                    'enabled' => true,
                    'method' => $twoFactor->method,
                    'recovery_codes' => $recoveryCodes,
                    'warning' => 'Please save these recovery codes in a safe place. They can be used to access your account if you lose your 2FA device.',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify 2FA setup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();
        $twoFactor = $user->twoFactorAuth;

        if (!$twoFactor || !$twoFactor->enabled) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password'
            ], 400);
        }

        try {
            $code = $request->get('code');
            $verified = false;

            // Try TOTP verification
            if ($twoFactor->method === 'totp') {
                $verified = $twoFactor->verifyTotp($code);
            }

            // Try recovery code
            if (!$verified) {
                $verified = $twoFactor->useRecoveryCode($code);
            }

            // Try verification code
            if (!$verified && in_array($twoFactor->method, ['sms', 'email'])) {
                $verified = TwoFactorCode::verify($user, $code, 'disable');
            }

            if (!$verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code or recovery code'
                ], 400);
            }

            // Disable 2FA
            $twoFactor->disable();

            return response()->json([
                'success' => true,
                'message' => '2FA disabled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable 2FA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate new recovery codes
     */
    public function generateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        $twoFactor = $user->twoFactorAuth;

        if (!$twoFactor || !$twoFactor->enabled) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password'
            ], 400);
        }

        try {
            $recoveryCodes = $twoFactor->generateRecoveryCodes();

            return response()->json([
                'success' => true,
                'message' => 'New recovery codes generated',
                'data' => [
                    'recovery_codes' => $recoveryCodes,
                    'warning' => 'These codes replace your previous recovery codes. Save them in a safe place.',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate recovery codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send verification code for SMS/Email 2FA
     */
    public function sendCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $twoFactor = $user->twoFactorAuth;

        if (!$twoFactor) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not configured'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:login,disable,setup',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $type = $request->get('type');

            // Check rate limiting
            if (TwoFactorCode::hasExceededGenerationLimit($user, $type)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many code requests. Please try again later.'
                ], 429);
            }

            $code = TwoFactorCode::generateFor($user, $type);

            if ($twoFactor->method === 'sms') {
                $sent = $code->sendViaSms();
                $destination = $this->maskPhone($twoFactor->phone);
            } else {
                $sent = $code->sendViaEmail();
                $destination = $this->maskEmail($user->email);
            }

            if (!$sent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent',
                'data' => [
                    'method' => $twoFactor->method,
                    'destination' => $destination,
                    'expires_in' => 600, // 10 minutes
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate TOTP secret
     */
    private function generateTotpSecret(): string
    {
        return strtoupper(Str::random(32));
    }

    /**
     * Mask phone number for privacy
     */
    private function maskPhone(string $phone): string
    {
        return substr($phone, 0, 3) . '****' . substr($phone, -3);
    }

    /**
     * Mask email for privacy
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1];
        
        $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 4)) . substr($name, -2);
        
        return $maskedName . '@' . $domain;
    }
} 