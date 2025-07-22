<?php

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PayPalPaymentService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('services.paypal.sandbox') ? 
            'https://api.sandbox.paypal.com' : 
            'https://api.paypal.com';
        
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                return $this->accessToken;
            }

            throw new Exception('Failed to get PayPal access token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('PayPal access token error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create PayPal subscription
     */
    public function createSubscription(User $user, string $planType, float $amount): array
    {
        try {
            $accessToken = $this->getAccessToken();

            // First create a product if it doesn't exist
            $productId = $this->createOrGetProduct();
            
            // Create a plan
            $planId = $this->createOrGetPlan($productId, $planType, $amount);

            // Create subscription
            $subscriptionData = [
                'plan_id' => $planId,
                'subscriber' => [
                    'name' => [
                        'given_name' => $user->first_name,
                        'surname' => $user->last_name ?? ''
                    ],
                    'email_address' => $user->email
                ],
                'application_context' => [
                    'brand_name' => 'SoulSync',
                    'locale' => 'en-US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'payment_method' => [
                        'payer_selected' => 'PAYPAL',
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'
                    ],
                    'return_url' => config('app.url') . '/payment/paypal/success',
                    'cancel_url' => config('app.url') . '/payment/paypal/cancel'
                ]
            ];

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/v1/billing/subscriptions', $subscriptionData);

            if ($response->successful()) {
                $subscription = $response->json();
                
                return [
                    'success' => true,
                    'subscription_id' => $subscription['id'],
                    'approval_url' => $this->getApprovalUrl($subscription['links']),
                    'paypal_response' => $subscription
                ];
            }

            throw new Exception('PayPal subscription creation failed: ' . $response->body());

        } catch (Exception $e) {
            Log::error('PayPal subscription creation error', [
                'user_id' => $user->id,
                'plan_type' => $planType,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create or get PayPal product
     */
    private function createOrGetProduct(): string
    {
        $accessToken = $this->getAccessToken();

        $productData = [
            'id' => 'SOULSYNC_MATRIMONY',
            'name' => 'SoulSync Matrimony Platform',
            'description' => 'Premium matrimony platform subscriptions',
            'type' => 'SERVICE',
            'category' => 'SOFTWARE'
        ];

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl . '/v1/catalogs/products', $productData);

        if ($response->successful()) {
            return $productData['id'];
        }

        // Product might already exist, try to get it
        $getResponse = Http::withToken($accessToken)
            ->get($this->baseUrl . '/v1/catalogs/products/' . $productData['id']);

        if ($getResponse->successful()) {
            return $productData['id'];
        }

        throw new Exception('Failed to create or get PayPal product');
    }

    /**
     * Create or get PayPal plan
     */
    private function createOrGetPlan(string $productId, string $planType, float $amount): string
    {
        $accessToken = $this->getAccessToken();

        $planId = 'SOULSYNC_' . strtoupper($planType) . '_MONTHLY';

        $planData = [
            'product_id' => $productId,
            'name' => 'SoulSync ' . ucfirst($planType) . ' Plan',
            'description' => 'Monthly subscription for SoulSync ' . $planType . ' features',
            'status' => 'ACTIVE',
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit' => 'MONTH',
                        'interval_count' => 1
                    ],
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => 0, // Infinite cycles
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value' => number_format($amount, 2, '.', ''),
                            'currency_code' => 'USD'
                        ]
                    ]
                ]
            ],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => [
                    'value' => '0.00',
                    'currency_code' => 'USD'
                ],
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3
            ],
            'taxes' => [
                'percentage' => '0.00',
                'inclusive' => false
            ]
        ];

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl . '/v1/billing/plans', $planData);

        if ($response->successful()) {
            $plan = $response->json();
            return $plan['id'];
        }

        // Plan might already exist, try to list plans and find it
        $listResponse = Http::withToken($accessToken)
            ->get($this->baseUrl . '/v1/billing/plans', [
                'product_id' => $productId,
                'page_size' => 20
            ]);

        if ($listResponse->successful()) {
            $plans = $listResponse->json()['plans'] ?? [];
            foreach ($plans as $plan) {
                if (str_contains($plan['name'], ucfirst($planType))) {
                    return $plan['id'];
                }
            }
        }

        throw new Exception('Failed to create or get PayPal plan');
    }

    /**
     * Get approval URL from PayPal response links
     */
    private function getApprovalUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        return null;
    }

    /**
     * Verify PayPal subscription
     */
    public function verifySubscription(string $subscriptionId): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->get($this->baseUrl . '/v1/billing/subscriptions/' . $subscriptionId);

            if ($response->successful()) {
                $subscription = $response->json();
                
                return [
                    'success' => true,
                    'status' => $subscription['status'],
                    'subscription' => $subscription
                ];
            }

            return [
                'success' => false,
                'error' => 'Subscription not found'
            ];

        } catch (Exception $e) {
            Log::error('PayPal subscription verification error', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel PayPal subscription
     */
    public function cancelSubscription(string $subscriptionId, string $reason = 'User requested cancellation'): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $cancelData = [
                'reason' => $reason
            ];

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/v1/billing/subscriptions/' . $subscriptionId . '/cancel', $cancelData);

            if ($response->status() === 204) { // PayPal returns 204 for successful cancellation
                return [
                    'success' => true,
                    'message' => 'Subscription cancelled successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to cancel subscription: ' . $response->body()
            ];

        } catch (Exception $e) {
            Log::error('PayPal subscription cancellation error', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get subscription details
     */
    public function getSubscriptionDetails(string $subscriptionId): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->get($this->baseUrl . '/v1/billing/subscriptions/' . $subscriptionId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'subscription' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Subscription not found'
            ];

        } catch (Exception $e) {
            Log::error('PayPal get subscription details error', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process PayPal webhook
     */
    public function processWebhook(array $webhookData): array
    {
        try {
            $eventType = $webhookData['event_type'] ?? '';
            $resource = $webhookData['resource'] ?? [];

            switch ($eventType) {
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    return $this->handleSubscriptionActivated($resource);
                    
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                    return $this->handleSubscriptionCancelled($resource);
                    
                case 'PAYMENT.SALE.COMPLETED':
                    return $this->handlePaymentCompleted($resource);
                    
                case 'PAYMENT.SALE.DENIED':
                case 'PAYMENT.SALE.FAILED':
                    return $this->handlePaymentFailed($resource);
                    
                default:
                    Log::info('Unhandled PayPal webhook event', ['event_type' => $eventType]);
                    return ['success' => true, 'message' => 'Event not handled'];
            }

        } catch (Exception $e) {
            Log::error('PayPal webhook processing error', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle subscription activated webhook
     */
    private function handleSubscriptionActivated(array $resource): array
    {
        $subscriptionId = $resource['id'] ?? null;
        
        if (!$subscriptionId) {
            return ['success' => false, 'error' => 'No subscription ID'];
        }

        $subscription = Subscription::where('payment_gateway_id', $subscriptionId)->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => 'active',
                'payment_status' => 'paid',
                'starts_at' => now(),
                'payment_details' => json_encode($resource)
            ]);

            // Activate user premium
            $subscription->user->update([
                'is_premium' => true,
                'premium_expires_at' => now()->addMonth()
            ]);
        }

        return ['success' => true, 'message' => 'Subscription activated'];
    }

    /**
     * Handle subscription cancelled webhook
     */
    private function handleSubscriptionCancelled(array $resource): array
    {
        $subscriptionId = $resource['id'] ?? null;
        
        if (!$subscriptionId) {
            return ['success' => false, 'error' => 'No subscription ID'];
        }

        $subscription = Subscription::where('payment_gateway_id', $subscriptionId)->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'payment_details' => json_encode($resource)
            ]);

            // Deactivate user premium
            $subscription->user->update([
                'is_premium' => false,
                'premium_expires_at' => null
            ]);
        }

        return ['success' => true, 'message' => 'Subscription cancelled'];
    }

    /**
     * Handle payment completed webhook
     */
    private function handlePaymentCompleted(array $resource): array
    {
        // Handle individual payment completion
        Log::info('PayPal payment completed', ['resource' => $resource]);
        return ['success' => true, 'message' => 'Payment completed'];
    }

    /**
     * Handle payment failed webhook
     */
    private function handlePaymentFailed(array $resource): array
    {
        // Handle payment failure
        Log::warning('PayPal payment failed', ['resource' => $resource]);
        return ['success' => true, 'message' => 'Payment failed handled'];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(array $headers, string $payload): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $verifyData = [
                'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
                'cert_id' => $headers['PAYPAL-CERT-ID'] ?? '',
                'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
                'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
                'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                'webhook_id' => config('services.paypal.webhook_id'),
                'webhook_event' => json_decode($payload, true)
            ];

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/v1/notifications/verify-webhook-signature', $verifyData);

            if ($response->successful()) {
                $result = $response->json();
                return ($result['verification_status'] ?? '') === 'SUCCESS';
            }

            return false;

        } catch (Exception $e) {
            Log::error('PayPal webhook signature verification error', ['error' => $e->getMessage()]);
            return false;
        }
    }
} 