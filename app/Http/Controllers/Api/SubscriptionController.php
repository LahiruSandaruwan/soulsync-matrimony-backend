<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
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
     * Get available subscription plans
     */
    public function plans(Request $request): JsonResponse
    {
        $userCountry = $request->user() ? $request->user()->country_code : 'US';
        $isLocalUser = in_array($userCountry, ['LK']); // Sri Lankan users

        $plans = [
            'free' => [
                'name' => 'Free',
                'price_usd' => 0,
                'price_local' => $isLocalUser ? 0 : 0,
                'local_currency' => $isLocalUser ? 'LKR' : 'USD',
                'duration_days' => null,
                'features' => $this->getFreeFeatures(),
                'limits' => [
                    'daily_likes' => 5,
                    'daily_matches' => 5,
                    'super_likes' => 0,
                    'profile_views' => false,
                    'advanced_search' => false,
                ]
            ],
            'basic' => [
                'name' => 'Basic',
                'price_usd' => 4.99,
                'price_local' => $isLocalUser ? 1500 : 4.99, // ~1500 LKR
                'local_currency' => $isLocalUser ? 'LKR' : 'USD',
                'duration_days' => 30,
                'popular' => false,
                'features' => $this->getBasicFeatures(),
                'limits' => [
                    'daily_likes' => 25,
                    'daily_matches' => 25,
                    'super_likes' => 3,
                    'profile_views' => true,
                    'advanced_search' => true,
                ]
            ],
            'premium' => [
                'name' => 'Premium',
                'price_usd' => 9.99,
                'price_local' => $isLocalUser ? 3000 : 9.99, // ~3000 LKR
                'local_currency' => $isLocalUser ? 'LKR' : 'USD',
                'duration_days' => 30,
                'popular' => true,
                'features' => $this->getPremiumFeatures('premium'),
                'limits' => [
                    'daily_likes' => 100,
                    'daily_matches' => 100,
                    'super_likes' => 10,
                    'profile_views' => true,
                    'advanced_search' => true,
                ]
            ],
            'platinum' => [
                'name' => 'Platinum',
                'price_usd' => 19.99,
                'price_local' => $isLocalUser ? 6000 : 19.99, // ~6000 LKR
                'local_currency' => $isLocalUser ? 'LKR' : 'USD',
                'duration_days' => 30,
                'popular' => false,
                'features' => $this->getPlatinumFeatures(),
                'limits' => [
                    'daily_likes' => 'unlimited',
                    'daily_matches' => 'unlimited',
                    'super_likes' => 25,
                    'profile_views' => true,
                    'advanced_search' => true,
                ]
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => $plans,
                'user_country' => $userCountry,
                'local_currency' => $isLocalUser ? 'LKR' : 'USD',
                'payment_methods' => $this->getAvailablePaymentMethods($userCountry),
            ]
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
                    'subscriptions' => $subscriptions,
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

            // Verify payment with respective gateway
            $verified = $this->verifyPaymentWithGateway($paymentMethod, $transactionId);

            if ($verified) {
                // Update subscription status if needed
                $subscription = Subscription::where('payment_gateway_id', $transactionId)->first();
                if ($subscription && $subscription->status === 'pending') {
                    $subscription->update(['status' => 'active']);
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
        // This is a placeholder - actual implementation would integrate with payment gateways
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
    }

    /**
     * Placeholder payment processing methods
     */
    private function processStripePayment(array $pricing, string $token, User $user): array
    {
        // TODO: Implement Stripe payment processing
        return [
            'success' => true,
            'transaction_id' => 'stripe_' . uniqid(),
            'amount' => $pricing['amount_local'],
            'currency' => $pricing['currency'],
        ];
    }

    private function processPayPalPayment(array $pricing, string $token, User $user): array
    {
        // TODO: Implement PayPal payment processing
        return [
            'success' => true,
            'transaction_id' => 'paypal_' . uniqid(),
            'amount' => $pricing['amount_local'],
            'currency' => $pricing['currency'],
        ];
    }

    private function processPayHerePayment(array $pricing, string $token, User $user): array
    {
        // TODO: Implement PayHere payment processing
        return [
            'success' => true,
            'transaction_id' => 'payhere_' . uniqid(),
            'amount' => $pricing['amount_local'],
            'currency' => $pricing['currency'],
        ];
    }

    private function processWebXPayPayment(array $pricing, string $token, User $user): array
    {
        // TODO: Implement WebXPay payment processing
        return [
            'success' => true,
            'transaction_id' => 'webxpay_' . uniqid(),
            'amount' => $pricing['amount_local'],
            'currency' => $pricing['currency'],
        ];
    }

    /**
     * Verify payment with gateway
     */
    private function verifyPaymentWithGateway(string $method, string $transactionId): bool
    {
        // TODO: Implement actual payment verification
        return true; // Placeholder
    }
}
