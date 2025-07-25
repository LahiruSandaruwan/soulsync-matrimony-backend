<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class StripePaymentService
{
    private string $secretKey = 'sk_test_dummy';
    private string $publishableKey;
    private string $webhookSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret_key') ?? 'sk_test_dummy';
        $this->publishableKey = config('services.stripe.publishable_key') ?? 'pk_test_dummy';
        $this->webhookSecret = config('services.stripe.webhook_secret') ?? 'whsec_dummy';
        $this->baseUrl = 'https://api.stripe.com/v1';
    }

    /**
     * Process a payment using Stripe
     */
    public function processPayment(array $pricing, string $paymentMethodId, User $user, array $metadata = []): array
    {
        try {
            // Create or retrieve customer
            $customer = $this->createOrGetCustomer($user);
            
            // Create payment intent
            $paymentIntent = $this->createPaymentIntent([
                'amount' => round($pricing['amount_usd'] * 100), // Amount in cents
                'currency' => 'usd',
                'customer' => $customer['id'],
                'payment_method' => $paymentMethodId,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'metadata' => array_merge($metadata, [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]),
                'description' => "SoulSync Subscription - {$user->email}",
            ]);

            if ($paymentIntent['status'] === 'succeeded') {
                return [
                    'success' => true,
                    'transaction_id' => $paymentIntent['id'],
                    'amount' => $pricing['amount_usd'],
                    'currency' => 'USD',
                    'customer_id' => $customer['id'],
                    'payment_method_id' => $paymentMethodId,
                    'status' => 'completed',
                ];
            } elseif ($paymentIntent['status'] === 'requires_action') {
                return [
                    'success' => false,
                    'requires_action' => true,
                    'client_secret' => $paymentIntent['client_secret'],
                    'payment_intent_id' => $paymentIntent['id'],
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Payment failed: ' . ($paymentIntent['last_payment_error']['message'] ?? 'Unknown error'),
                ];
            }

        } catch (\Exception $e) {
            Log::error('Stripe payment processing error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'amount' => $pricing['amount_usd'],
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a subscription with Stripe
     */
    public function createSubscription(User $user, string $priceId, string $paymentMethodId, array $metadata = []): array
    {
        try {
            // Create or retrieve customer
            $customer = $this->createOrGetCustomer($user);

            // Attach payment method to customer
            $this->attachPaymentMethodToCustomer($paymentMethodId, $customer['id']);

            // Set as default payment method
            $this->updateCustomer($customer['id'], [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            // Create subscription
            $subscription = $this->makeStripeRequest('POST', '/subscriptions', [
                'customer' => $customer['id'],
                'items' => [
                    ['price' => $priceId],
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription',
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => array_merge($metadata, [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]),
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscription['id'],
                'client_secret' => $subscription['latest_invoice']['payment_intent']['client_secret'],
                'status' => $subscription['status'],
            ];

        } catch (\Exception $e) {
            Log::error('Stripe subscription creation error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Subscription creation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        try {
            $subscription = $this->makeStripeRequest('DELETE', "/subscriptions/{$subscriptionId}");

            return [
                'success' => true,
                'subscription_id' => $subscription['id'],
                'status' => $subscription['status'],
                'canceled_at' => $subscription['canceled_at'],
            ];

        } catch (\Exception $e) {
            Log::error('Stripe subscription cancellation error', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Subscription cancellation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process a refund
     */
    public function refund(string $paymentIntentId, ?int $amount = null): array
    {
        try {
            $refundData = ['payment_intent' => $paymentIntentId];
            
            if ($amount) {
                $refundData['amount'] = $amount; // Amount in cents
            }

            $refund = $this->makeStripeRequest('POST', '/refunds', $refundData);

            return [
                'success' => true,
                'refund_id' => $refund['id'],
                'amount' => $refund['amount'] / 100, // Convert back to dollars
                'status' => $refund['status'],
            ];

        } catch (\Exception $e) {
            Log::error('Stripe refund error', [
                'payment_intent_id' => $paymentIntentId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Refund failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (!$this->webhookSecret) {
            return true; // Skip verification if no secret configured
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        $signatures = explode(',', $signature);

        foreach ($signatures as $sig) {
            $sig = trim($sig);
            if (strpos($sig, 'v1=') === 0) {
                $providedSignature = substr($sig, 3);
                if (hash_equals($expectedSignature, $providedSignature)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get payment method details
     */
    public function getPaymentMethod(string $paymentMethodId): array
    {
        try {
            return $this->makeStripeRequest('GET', "/payment_methods/{$paymentMethodId}");
        } catch (\Exception $e) {
            Log::error('Stripe get payment method error', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Verify payment with Stripe
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = $this->makeStripeRequest('GET', "/payment_intents/{$transactionId}");
            
            if (isset($response['status']) && $response['status'] === 'succeeded') {
                return [
                    'success' => true,
                    'verified' => true,
                    'amount' => $response['amount'] / 100, // Convert from cents
                    'currency' => $response['currency'],
                    'status' => $response['status'],
                ];
            } else {
                return [
                    'success' => false,
                    'verified' => false,
                    'status' => $response['status'] ?? 'unknown',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Stripe payment verification error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'verified' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create or retrieve a Stripe customer
     */
    private function createOrGetCustomer(User $user): array
    {
        // First, try to find existing customer
        if ($user->stripe_customer_id) {
            try {
                return $this->makeStripeRequest('GET', "/customers/{$user->stripe_customer_id}");
            } catch (\Exception $e) {
                Log::warning('Existing Stripe customer not found, creating new one', [
                    'user_id' => $user->id,
                    'stripe_customer_id' => $user->stripe_customer_id,
                ]);
            }
        }

        // Create new customer
        $customer = $this->makeStripeRequest('POST', '/customers', [
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
            'phone' => $user->phone,
            'metadata' => [
                'user_id' => $user->id,
                'platform' => 'soulsync',
            ],
        ]);

        // Save customer ID to user
        $user->update(['stripe_customer_id' => $customer['id']]);

        return $customer;
    }

    /**
     * Create a payment intent
     */
    private function createPaymentIntent(array $data): array
    {
        return $this->makeStripeRequest('POST', '/payment_intents', $data);
    }

    /**
     * Attach payment method to customer
     */
    private function attachPaymentMethodToCustomer(string $paymentMethodId, string $customerId): array
    {
        return $this->makeStripeRequest('POST', "/payment_methods/{$paymentMethodId}/attach", [
            'customer' => $customerId,
        ]);
    }

    /**
     * Update customer
     */
    private function updateCustomer(string $customerId, array $data): array
    {
        return $this->makeStripeRequest('POST', "/customers/{$customerId}", $data);
    }

    /**
     * Make a request to Stripe API
     */
    private function makeStripeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->withOptions([
            'verify' => true,
            'timeout' => 30,
        ]);

        switch (strtoupper($method)) {
            case 'GET':
                $response = $response->get($url, $data);
                break;
            case 'POST':
                $response = $response->asForm()->post($url, $data);
                break;
            case 'DELETE':
                $response = $response->delete($url, $data);
                break;
            default:
                throw new \Exception("Unsupported HTTP method: {$method}");
        }

        if (!$response->successful()) {
            $error = $response->json()['error'] ?? [];
            throw new \Exception(
                $error['message'] ?? 'Stripe API request failed',
                $response->status()
            );
        }

        return $response->json();
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    /**
     * Create a setup intent for saving payment methods
     */
    public function createSetupIntent(User $user): array
    {
        try {
            $customer = $this->createOrGetCustomer($user);

            $setupIntent = $this->makeStripeRequest('POST', '/setup_intents', [
                'customer' => $customer['id'],
                'usage' => 'off_session',
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            return [
                'success' => true,
                'client_secret' => $setupIntent['client_secret'],
                'setup_intent_id' => $setupIntent['id'],
            ];

        } catch (\Exception $e) {
            Log::error('Stripe setup intent creation error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Setup intent creation failed: ' . $e->getMessage(),
            ];
        }
    }
} 