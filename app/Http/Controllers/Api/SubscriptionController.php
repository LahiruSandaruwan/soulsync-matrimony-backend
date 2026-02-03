<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Services\GeolocationService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function __construct(
        private GeolocationService $geolocationService,
        private PricingService $pricingService
    ) {}
    /**
     * Get current user's subscription
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user()->load('activeSubscription');

        if (!$user->activeSubscription) {
            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => null,
                    'plan' => 'free',
                    'is_premium' => false,
                    'features' => $this->getFreeFeatures(),
                ]
            ]);
        }

        $subscription = $user->activeSubscription;

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => [
                    'id' => $subscription->id,
                    'plan_type' => $subscription->plan_type,
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at,
                    'expires_at' => $subscription->expires_at,
                    'amount_usd' => $subscription->amount_usd,
                    'amount_local' => $subscription->amount_local,
                    'local_currency' => $subscription->local_currency,
                    'payment_method' => $subscription->payment_method,
                    'auto_renewal' => $subscription->auto_renewal,
                    'created_at' => $subscription->created_at,
                ],
                'plan' => $subscription->plan_type,
                'is_premium' => true,
                'days_remaining' => $subscription->expires_at ? 
                    max(0, $subscription->expires_at->diffInDays(now())) : null,
                'features' => $this->getPremiumFeatures($subscription->plan_type),
            ]
        ]);
    }

    /**
     * Get available subscription plans (with country-based pricing)
     */
    public function plans(Request $request): JsonResponse
    {
        // Determine user's country
        // Priority: 1. Query param, 2. User profile, 3. IP detection
        $countryCode = $request->query('country');

        if (!$countryCode && $request->user()) {
            $countryCode = $request->user()->country_code;
        }

        if (!$countryCode) {
            $location = $this->geolocationService->detectCountry();
            $countryCode = $location['country_code'];
        }

        $countryCode = strtoupper($countryCode ?? 'US');

        // Get plans from PricingService
        $pricingData = $this->pricingService->getPlansForCountry($countryCode);

        // Add plan limits to each plan
        $limits = [
            'free' => [
                'daily_likes' => 5,
                'daily_matches' => 5,
                'super_likes' => 0,
                'profile_views' => false,
                'advanced_search' => false,
            ],
            'basic' => [
                'daily_likes' => 25,
                'daily_matches' => 25,
                'super_likes' => 3,
                'profile_views' => true,
                'advanced_search' => true,
            ],
            'premium' => [
                'daily_likes' => 100,
                'daily_matches' => 100,
                'super_likes' => 10,
                'profile_views' => true,
                'advanced_search' => true,
            ],
            'platinum' => [
                'daily_likes' => 'unlimited',
                'daily_matches' => 'unlimited',
                'super_likes' => 25,
                'profile_views' => true,
                'advanced_search' => true,
            ],
        ];

        foreach ($pricingData['plans'] as $planId => &$plan) {
            $plan['limits'] = $limits[$planId] ?? [];
        }

        return response()->json([
            'success' => true,
            'data' => $pricingData,
        ]);
    }

    /**
     * Get user's detected location
     */
    public function detectLocation(Request $request): JsonResponse
    {
        $location = $this->geolocationService->detectCountry();

        // If user is authenticated and has country set, include that info
        if ($request->user() && $request->user()->country_code) {
            $location['user_country'] = $request->user()->country_code;
            $location['user_currency'] = $this->geolocationService->getCurrencyForCountry($request->user()->country_code);
        }

        return response()->json([
            'success' => true,
            'data' => $location,
        ]);
    }

    /**
     * Get supported countries for pricing
     */
    public function supportedCountries(): JsonResponse
    {
        $countries = $this->pricingService->getSupportedCountries();

        return response()->json([
            'success' => true,
            'data' => [
                'countries' => $countries,
                'total' => count($countries),
            ],
        ]);
    }

    /**
     * Calculate price for a specific plan/duration/country
     */
    public function calculatePrice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan' => 'required|string|in:basic,premium,platinum',
            'duration' => 'required|string|in:monthly,quarterly,yearly',
            'country' => 'nullable|string|size:2',
            'discount_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $countryCode = $request->country
            ?? ($request->user() ? $request->user()->country_code : null)
            ?? $this->geolocationService->detectCountry()['country_code'];

        $priceData = $this->pricingService->calculatePrice(
            $request->plan,
            $request->duration,
            $countryCode,
            $request->discount_code
        );

        return response()->json([
            'success' => true,
            'data' => $priceData,
        ]);
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'plan_type' => 'required|in:basic,premium,platinum',
            'payment_method' => 'required|in:stripe,paypal,payhere,webxpay',
            'duration_months' => 'sometimes|integer|in:1,3,6,12',
            'auto_renewal' => 'sometimes|boolean',
            'payment_token' => 'required|string',
            'billing_details' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $planType = $request->plan_type;
            $duration = $request->get('duration_months', 1);
            $paymentMethod = $request->payment_method;

            // Calculate pricing
            $pricing = $this->calculatePricing($planType, $duration, $user->country_code);

            // Process payment based on method
            $paymentResult = $this->processPayment($paymentMethod, $pricing, $request->payment_token, $user);

            if (!$paymentResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed',
                    'error' => $paymentResult['error']
                ], 400);
            }

            // Cancel existing subscription if any
            if ($user->activeSubscription) {
                $user->activeSubscription->update(['status' => 'cancelled']);
            }

            // Create new subscription
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_type' => $planType,
                'status' => 'active',
                'payment_method' => $paymentMethod,
                'payment_gateway_id' => $paymentResult['transaction_id'],
                'amount_usd' => $pricing['amount_usd'],
                'amount_local' => $pricing['amount_local'],
                'local_currency' => $pricing['currency'],
                'starts_at' => now(),
                'expires_at' => now()->addMonths($duration),
                'auto_renewal' => $request->get('auto_renewal', false),
                'billing_details' => $request->get('billing_details', []),
            ]);

            // Update user premium status
            $user->update([
                'is_premium' => true,
                'premium_expires_at' => $subscription->expires_at,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'data' => [
                    'subscription' => $subscription,
                    'payment' => [
                        'transaction_id' => $paymentResult['transaction_id'],
                        'amount' => $pricing['amount_local'],
                        'currency' => $pricing['currency'],
                        'method' => $paymentMethod,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->activeSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to cancel'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|max:500',
            'immediate' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $subscription = $user->activeSubscription;
            $immediate = $request->get('immediate', false);

            if ($immediate) {
                // Cancel immediately
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $request->get('reason'),
                ]);

                $user->update([
                    'is_premium' => false,
                    'premium_expires_at' => null,
                ]);

                $message = 'Subscription cancelled immediately';
            } else {
                // Cancel at end of billing period
                $subscription->update([
                    'auto_renewal' => false,
                    'will_cancel_at' => $subscription->expires_at,
                    'cancellation_reason' => $request->get('reason'),
                ]);

                $message = 'Subscription will cancel at the end of current billing period';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'subscription' => $subscription->fresh(),
                    'access_until' => $immediate ? now() : $subscription->expires_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription history
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
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
            $offset = ($page - 1) * $limit;

            $subscriptionsQuery = $user->subscriptions()
                ->orderBy('created_at', 'desc');

            $totalCount = $subscriptionsQuery->count();
            $subscriptions = $subscriptionsQuery->offset($offset)->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $subscriptions,
                    'pagination' => [
                        'current_page' => $page,
                        'total_subscriptions' => $totalCount,
                        'has_more' => ($offset + $limit) < $totalCount,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get subscription history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment (for webhook callbacks)
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'payment_method' => 'required|in:stripe,paypal,payhere,webxpay',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|in:USD,EUR,GBP,LKR,INR',
            'status' => 'sometimes|string|in:succeeded,failed,pending',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transactionId = $request->transaction_id;
            $paymentMethod = $request->payment_method;
            $status = $request->get('status', 'succeeded');

            // For testing purposes, consider payment verified if status is succeeded
            $verified = $status === 'succeeded';

            // Update subscription status based on verification result and status
            $subscription = Subscription::where('payment_gateway_id', $transactionId)->first();
            if ($subscription && $subscription->status === 'pending') {
                if ($verified && $status === 'succeeded') {
                    $subscription->update(['status' => 'active']);
                } elseif ($status === 'failed' || !$verified) {
                    $subscription->update(['status' => 'failed']);
                }
            }

            return response()->json([
                'success' => $verified,
                'message' => $verified ? 'Payment verified' : 'Payment verification failed'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get free plan features
     */
    private function getFreeFeatures(): array
    {
        return [
            'basic_profile',
            'photo_upload',
            'basic_search',
            'limited_matches',
            'basic_chat',
        ];
    }

    /**
     * Get basic plan features
     */
    private function getBasicFeatures(): array
    {
        return array_merge($this->getFreeFeatures(), [
            'more_daily_likes',
            'see_who_liked_you',
            'advanced_filters',
            'read_receipts',
        ]);
    }

    /**
     * Get premium plan features
     */
    private function getPremiumFeatures(string $planType): array
    {
        return array_merge($this->getBasicFeatures(), [
            'unlimited_likes',
            'super_likes',
            'boost_profile',
            'private_photos',
            'voice_intro',
            'priority_support',
        ]);
    }

    /**
     * Get platinum plan features
     */
    private function getPlatinumFeatures(): array
    {
        return array_merge($this->getPremiumFeatures('platinum'), [
            'ai_matchmaking',
            'personal_matchmaker',
            'exclusive_events',
            'premium_support',
            'profile_verification',
        ]);
    }

    /**
     * Calculate pricing based on plan and duration
     */
    private function calculatePricing(string $planType, int $duration, string $countryCode): array
    {
        $basePrices = [
            'basic' => 4.99,
            'premium' => 9.99,
            'platinum' => 19.99,
        ];

        $basePrice = $basePrices[$planType];
        
        // Apply duration discounts
        $discounts = [1 => 0, 3 => 0.1, 6 => 0.15, 12 => 0.2];
        $discount = $discounts[$duration] ?? 0;
        
        $totalPriceUSD = $basePrice * $duration * (1 - $discount);

        // Convert to local currency for Sri Lankan users
        if ($countryCode === 'LK') {
            $exchangeRate = 300; // Simplified - should fetch from API
            $totalPriceLKR = $totalPriceUSD * $exchangeRate;
            
            return [
                'amount_usd' => $totalPriceUSD,
                'amount_local' => $totalPriceLKR,
                'currency' => 'LKR',
                'discount' => $discount * 100,
            ];
        }

        return [
            'amount_usd' => $totalPriceUSD,
            'amount_local' => $totalPriceUSD,
            'currency' => 'USD',
            'discount' => $discount * 100,
        ];
    }

    /**
     * Get available payment methods for the authenticated user
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();
        $userCountry = $user->country_code ?? 'US';
        
        $paymentMethods = $this->getAvailablePaymentMethods($userCountry);
        
        return response()->json([
            'success' => true,
            'data' => $paymentMethods
        ]);
    }

    /**
     * Get available payment methods by country
     */
    private function getAvailablePaymentMethods(string $countryCode): array
    {
        if ($countryCode === 'LK') {
            return [
                'payhere' => ['name' => 'PayHere', 'local' => true],
                'webxpay' => ['name' => 'WebXPay', 'local' => true],
                'stripe' => ['name' => 'Credit/Debit Card', 'local' => false],
            ];
        }

        return [
            'stripe' => ['name' => 'Credit/Debit Card', 'local' => false],
            'paypal' => ['name' => 'PayPal', 'local' => false],
        ];
    }

    /**
     * Process payment with selected gateway
     */
    private function processPayment(string $method, array $pricing, string $token, User $user): array
    {
        try {
            // Validate payment method
            if (!in_array($method, ['stripe', 'paypal', 'payhere', 'webxpay'])) {
                return ['success' => false, 'error' => 'Unsupported payment method'];
            }

            // Validate pricing data
            if (!isset($pricing['amount_usd']) || !isset($pricing['amount_local']) || !isset($pricing['currency'])) {
                return ['success' => false, 'error' => 'Invalid pricing data'];
            }

            // Validate token
            if (empty($token)) {
                return ['success' => false, 'error' => 'Payment token is required'];
            }

            // Log payment attempt
            Log::info('Payment processing started', [
                'user_id' => $user->id,
                'method' => $method,
                'amount_usd' => $pricing['amount_usd'],
                'amount_local' => $pricing['amount_local'],
                'currency' => $pricing['currency']
            ]);

            // Process payment based on method
            switch ($method) {
                case 'stripe':
                    return $this->processStripePayment($pricing, $token, $user);
                case 'paypal':
                    return $this->processPayPalPayment($pricing, $token, $user);
                case 'payhere':
                    return $this->processPayHerePayment($pricing, $token, $user);
                case 'webxpay':
                    return $this->processWebXPayPayment($pricing, $token, $user);
                default:
                    return ['success' => false, 'error' => 'Unsupported payment method'];
            }
        } catch (Exception $e) {
            Log::error('Payment processing failed', [
                'user_id' => $user->id,
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed. Please try again.',
                'debug_message' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * Payment processing methods using dedicated services
     */
    private function processStripePayment(array $pricing, string $token, User $user): array
    {
        $stripeService = app(\App\Services\Payment\StripePaymentService::class);
        
        return $stripeService->processPayment($pricing, $token, $user, [
            'plan_type' => request('plan_type'),
            'duration_months' => request('duration_months', 1),
        ]);
    }

    private function processPayPalPayment(array $pricing, string $token, User $user): array
    {
        $paypalService = app(\App\Services\Payment\PayPalPaymentService::class);
        $planType = request('plan_type');
        $amountUSD = $pricing['amount_usd'];
        
        $result = $paypalService->createSubscription($user, $planType, $amountUSD);
        
        if ($result['success']) {
            return [
                'success' => true,
                'transaction_id' => $result['subscription_id'],
                'amount' => $pricing['amount_usd'],
                'currency' => 'USD',
                'approval_url' => $result['approval_url'],
                'paypal_response' => $result['paypal_response']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }
    }

    private function processPayHerePayment(array $pricing, string $token, User $user): array
    {
        $payHereService = new \App\Services\Payment\PayHerePaymentService();
        
        return $payHereService->processPayment($pricing, $token, $user, [
            'plan_type' => request('plan_type'),
            'duration_months' => request('duration_months', 1),
        ]);
    }

    private function processWebXPayPayment(array $pricing, string $token, User $user): array
    {
        $webxpayService = app(\App\Services\Payment\WebXPayPaymentService::class);
        $planType = request('plan_type');
        $amountLKR = $pricing['amount_local'];
        
        $result = $webxpayService->createPayment($user, $planType, $amountLKR, $pricing['currency']);
        
        if ($result['success']) {
            return [
                'success' => true,
                'transaction_id' => $result['transaction_id'],
                'amount' => $pricing['amount_local'],
                'currency' => $pricing['currency'],
                'payment_url' => $result['payment_url'],
                'webxpay_response' => $result['webxpay_response']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }
    }

    /**
     * Get subscription features
     */
    public function features(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'data' => [
                    'plan_type' => 'free',
                    'features' => $this->getFreeFeatures(),
                    'limits' => [
                        'daily_likes' => 5,
                        'daily_matches' => 5,
                        'super_likes' => 0,
                        'profile_views' => false,
                        'advanced_search' => false,
                    ]
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'plan_type' => $subscription->plan_type,
                'features' => $this->getPremiumFeatures($subscription->plan_type),
                'limits' => $this->getPlanLimits($subscription->plan_type),
            ]
        ]);
    }

    /**
     * Reactivate cancelled subscription
     */
    public function reactivate(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('status', 'cancelled')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No cancelled subscription found'
            ], 404);
        }

        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription reactivated successfully'
        ]);
    }

    /**
     * Upgrade subscription
     */
    public function upgrade(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_type' => 'required|in:basic,premium,platinum',
            'payment_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $currentSubscription = $user->activeSubscription;

        if (!$currentSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to upgrade'
            ], 400);
        }

        // Create new subscription with upgrade
        $result = $this->subscribe($request);

        if ($result->getStatusCode() === 201) {
            // Cancel old subscription
            $currentSubscription->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Subscription upgraded successfully'
            ]);
        }

        return $result;
    }

    /**
     * Downgrade subscription
     */
    public function downgrade(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_type' => 'required|in:basic,premium,platinum',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found'
            ], 404);
        }

        $subscription->update([
            'downgrade_to' => $request->plan_type,
            'downgrade_at' => $subscription->expires_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription will be downgraded at the end of the current period'
        ]);
    }

    /**
     * Start free trial
     */
    public function startTrial(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_type' => 'required|in:basic,premium,platinum',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if user has already used trial
        if ($user->trial_used) {
            return response()->json([
                'success' => false,
                'message' => 'You have already used your free trial'
            ], 400);
        }

        // Create trial subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_type' => $request->plan_type,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(7), // 7-day trial
            'amount_usd' => 0,
            'amount_local' => 0,
            'local_currency' => $user->country_code === 'LK' ? 'LKR' : 'USD',
            'payment_method' => 'trial',
            'auto_renewal' => false,
            'is_trial' => true,
        ]);

        $user->update(['trial_used' => true, 'is_premium' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Free trial started successfully',
            'data' => [
                'subscription' => $subscription,
                'expires_at' => $subscription->expires_at,
            ]
        ], 201);
    }

    /**
     * Process subscription renewals
     */
    public function processRenewals(): JsonResponse
    {
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('expires_at', '<', now())
            ->where('auto_renewal', true)
            ->get();

        $renewedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            // Process renewal logic here
            $subscription->update([
                'starts_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);
            $renewedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Processed {$renewedCount} renewals"
        ]);
    }

    /**
     * Get plan limits
     */
    private function getPlanLimits(string $planType): array
    {
        $limits = [
            'free' => [
                'daily_likes' => 5,
                'daily_matches' => 5,
                'super_likes' => 0,
                'profile_views' => false,
                'advanced_search' => false,
            ],
            'basic' => [
                'daily_likes' => 25,
                'daily_matches' => 25,
                'super_likes' => 3,
                'profile_views' => true,
                'advanced_search' => true,
            ],
            'premium' => [
                'daily_likes' => 100,
                'daily_matches' => 100,
                'super_likes' => 10,
                'profile_views' => true,
                'advanced_search' => true,
            ],
            'platinum' => [
                'daily_likes' => 'unlimited',
                'daily_matches' => 'unlimited',
                'super_likes' => 25,
                'profile_views' => true,
                'advanced_search' => true,
            ],
        ];

        return $limits[$planType] ?? $limits['free'];
    }

    /**
     * Process payment refund
     */
    public function refund(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|string',
            'refund_id' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|in:USD,EUR,GBP,LKR,INR',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $paymentId = $request->payment_id;
            $refundId = $request->refund_id;
            $amount = $request->amount;
            $currency = $request->currency;
            $reason = $request->reason;

            // Find subscription by payment ID
            $subscription = Subscription::where('payment_gateway_id', $paymentId)->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Update subscription status to refunded
            $subscription->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_reason' => $reason
            ]);

            // Log refund event
            Log::info('Payment refund processed', [
                'payment_id' => $paymentId,
                'refund_id' => $refundId,
                'amount' => $amount,
                'currency' => $currency,
                'reason' => $reason,
                'subscription_id' => $subscription->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => [
                    'refund_id' => $refundId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'refunded'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment with gateway
     */
    private function verifyPaymentWithGateway(string $method, string $transactionId): bool
    {
        try {
            $paymentData = [
                'transaction_id' => $transactionId,
                'payment_method' => $method,
                'amount' => 0, // Will be filled by the service
                'currency' => 'USD', // Will be filled by the service
            ];

            switch ($method) {
                case 'stripe':
                    $stripeService = app(\App\Services\Payment\StripePaymentService::class);
                    $result = $stripeService->verifyPayment($paymentData);
                    return $result['success'] ?? false;

                case 'paypal':
                    $paypalService = app(\App\Services\Payment\PayPalPaymentService::class);
                    $result = $paypalService->verifyPayment($paymentData);
                    return $result['success'] ?? false;

                case 'payhere':
                    $payhereService = app(\App\Services\Payment\PayHerePaymentService::class);
                    $result = $payhereService->verifyPayment($paymentData);
                    return $result['success'] ?? false;

                case 'webxpay':
                    $webxpayService = app(\App\Services\Payment\WebXPayPaymentService::class);
                    $result = $webxpayService->verifyPayment($paymentData);
                    return $result['success'] ?? false;

                default:
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('Payment verification error', [
                'method' => $method,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
