<?php

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class PayPalPaymentService
{
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;
    private string $baseUrl;
    private string $accessToken;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->webhookId = config('services.paypal.webhook_id');
        $this->baseUrl = config('app.env') === 'production' 
            ? 'https://api-m.paypal.com' 
            : 'https://api-m.sandbox.paypal.com';
        
        $this->accessToken = $this->getAccessToken();
    }

    /**
     * Create a PayPal order
     */
    public function createOrder(User $user, array $subscriptionData): array
    {
        try {
            // Validate subscription data
            $validator = Validator::make($subscriptionData, [
                'plan_id' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|size:3',
                'description' => 'required|string',
            ]);

            if ($validator->fails()) {
                throw new \InvalidArgumentException('Invalid subscription data: ' . $validator->errors()->first());
            }

            $orderData = [
                'intent' => 'CAPTURE',
                'application_context' => [
                    'return_url' => config('app.url') . '/payment/paypal/success',
                    'cancel_url' => config('app.url') . '/payment/paypal/cancel',
                    'brand_name' => config('app.name'),
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                ],
                'purchase_units' => [
                    [
                        'reference_id' => 'subscription_' . $user->id . '_' . time(),
                        'description' => $subscriptionData['description'],
                        'custom_id' => $user->id,
                        'invoice_id' => 'INV-' . $user->id . '-' . time(),
                        'amount' => [
                            'currency_code' => strtoupper($subscriptionData['currency']),
                            'value' => number_format($subscriptionData['amount'], 2, '.', ''),
                        ],
                        'items' => [
                            [
                                'name' => $subscriptionData['description'],
                                'unit_amount' => [
                                    'currency_code' => strtoupper($subscriptionData['currency']),
                                    'value' => number_format($subscriptionData['amount'], 2, '.', ''),
                                ],
                                'quantity' => '1',
                                'category' => 'DIGITAL_GOODS',
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ])->post($this->baseUrl . '/v2/checkout/orders', $orderData);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('PayPal order creation failed', [
                    'user_id' => $user->id,
                    'error' => $error,
                    'status_code' => $response->status(),
                ]);

                return [
                    'success' => false,
                    'error' => $this->getUserFriendlyError($error),
                ];
            }

            $order = $response->json();

            Log::info('PayPal order created', [
                'user_id' => $user->id,
                'order_id' => $order['id'],
                'amount' => $subscriptionData['amount'],
                'currency' => $subscriptionData['currency'],
            ]);

            return [
                'success' => true,
                'order_id' => $order['id'],
                'approval_url' => $order['links'][1]['href'] ?? null,
                'status' => $order['status'],
                'amount' => $subscriptionData['amount'],
                'currency' => $subscriptionData['currency'],
            ];

        } catch (\Exception $e) {
            Log::error('Error creating PayPal order', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed. Please try again.',
            ];
        }
    }

    /**
     * Capture a PayPal order
     */
    public function captureOrder(string $orderId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ])->post($this->baseUrl . "/v2/checkout/orders/{$orderId}/capture");

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('PayPal order capture failed', [
                    'order_id' => $orderId,
                    'error' => $error,
                    'status_code' => $response->status(),
                ]);

                return [
                    'success' => false,
                    'error' => $this->getUserFriendlyError($error),
                ];
            }

            $capture = $response->json();

            Log::info('PayPal order captured', [
                'order_id' => $orderId,
                'capture_id' => $capture['purchase_units'][0]['payments']['captures'][0]['id'],
                'status' => $capture['status'],
            ]);

            return [
                'success' => true,
                'capture_id' => $capture['purchase_units'][0]['payments']['captures'][0]['id'],
                'status' => $capture['status'],
                'amount' => $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'currency' => $capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'],
            ];

        } catch (\Exception $e) {
            Log::error('Error capturing PayPal order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment capture failed. Please try again.',
            ];
        }
    }

    /**
     * Create a PayPal subscription
     */
    public function createSubscription(User $user, array $subscriptionData): array
    {
        try {
            // Validate subscription data
            $validator = Validator::make($subscriptionData, [
                'plan_id' => 'required|string',
                'start_time' => 'required|date',
                'quantity' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                throw new \InvalidArgumentException('Invalid subscription data: ' . $validator->errors()->first());
            }

            $subscriptionData = [
                'plan_id' => $subscriptionData['plan_id'],
                'start_time' => $subscriptionData['start_time'],
                'quantity' => $subscriptionData['quantity'] ?? 1,
                'subscriber' => [
                    'name' => [
                        'given_name' => $user->first_name,
                        'surname' => $user->last_name,
                    ],
                    'email_address' => $user->email,
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'payment_method' => [
                        'payer_selected' => 'PAYPAL',
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    ],
                    'return_url' => config('app.url') . '/subscription/paypal/success',
                    'cancel_url' => config('app.url') . '/subscription/paypal/cancel',
                ],
                'custom_id' => $user->id,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ])->post($this->baseUrl . '/v1/billing/subscriptions', $subscriptionData);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('PayPal subscription creation failed', [
                    'user_id' => $user->id,
                    'error' => $error,
                    'status_code' => $response->status(),
                ]);

                return [
                    'success' => false,
                    'error' => $this->getUserFriendlyError($error),
                ];
            }

            $subscription = $response->json();

            Log::info('PayPal subscription created', [
                'user_id' => $user->id,
                'subscription_id' => $subscription['id'],
                'plan_id' => $subscriptionData['plan_id'],
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscription['id'],
                'approval_url' => $subscription['links'][0]['href'] ?? null,
                'status' => $subscription['status'],
            ];

        } catch (\Exception $e) {
            Log::error('Error creating PayPal subscription', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Subscription creation failed. Please try again.',
            ];
        }
    }

    /**
     * Verify webhook signature and process events
     */
    public function processWebhook(Request $request): array
    {
        try {
            $payload = $request->getContent();
            $headers = $request->headers->all();

            // Verify webhook signature
            if (!$this->verifyWebhookSignature($payload, $headers)) {
                Log::error('PayPal webhook signature verification failed');
                return ['success' => false, 'error' => 'Invalid signature'];
            }

            $event = json_decode($payload, true);

            Log::info('PayPal webhook received', [
                'event_type' => $event['event_type'] ?? 'unknown',
                'resource_type' => $event['resource_type'] ?? 'unknown',
            ]);

            // Process the event
            switch ($event['event_type']) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    return $this->handlePaymentCaptureCompleted($event['resource']);
                
                case 'PAYMENT.CAPTURE.DENIED':
                    return $this->handlePaymentCaptureDenied($event['resource']);
                
                case 'BILLING.SUBSCRIPTION.CREATED':
                    return $this->handleSubscriptionCreated($event['resource']);
                
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    return $this->handleSubscriptionActivated($event['resource']);
                
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    return $this->handleSubscriptionCancelled($event['resource']);
                
                case 'BILLING.SUBSCRIPTION.EXPIRED':
                    return $this->handleSubscriptionExpired($event['resource']);
                
                case 'BILLING.SUBSCRIPTION.PAYMENT.COMPLETED':
                    return $this->handleSubscriptionPaymentCompleted($event['resource']);
                
                case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                    return $this->handleSubscriptionPaymentFailed($event['resource']);
                
                default:
                    Log::info('Unhandled PayPal webhook event', ['event_type' => $event['event_type']]);
                    return ['success' => true, 'message' => 'Event ignored'];
            }

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing error', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Webhook processing failed'];
        }
    }

    /**
     * Handle successful payment capture
     */
    private function handlePaymentCaptureCompleted($resource): array
    {
        try {
            $customId = $resource['custom_id'] ?? null;
            $captureId = $resource['id'];
            $amount = $resource['amount']['value'];
            $currency = $resource['amount']['currency_code'];

            if ($customId) {
                $user = User::find($customId);
                if ($user) {
                    // Update user subscription or create new one
                    $subscription = $user->subscriptions()->where('paypal_capture_id', $captureId)->first();
                    if ($subscription) {
                        $subscription->update([
                            'status' => 'active',
                            'paid_at' => now(),
                        ]);
                    }

                    Log::info('PayPal payment capture completed', [
                        'user_id' => $customId,
                        'capture_id' => $captureId,
                        'amount' => $amount,
                        'currency' => $currency,
                    ]);
                }
            }

            return ['success' => true, 'message' => 'Payment processed successfully'];

        } catch (\Exception $e) {
            Log::error('Error handling PayPal payment capture completed', [
                'capture_id' => $resource['id'],
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }

    /**
     * Handle denied payment capture
     */
    private function handlePaymentCaptureDenied($resource): array
    {
        try {
            $customId = $resource['custom_id'] ?? null;
            $captureId = $resource['id'];

            if ($customId) {
                $user = User::find($customId);
                if ($user) {
                    $subscription = $user->subscriptions()->where('paypal_capture_id', $captureId)->first();
                    if ($subscription) {
                        $subscription->update([
                            'status' => 'failed',
                        ]);
                    }
                }
            }

            Log::warning('PayPal payment capture denied', [
                'capture_id' => $captureId,
                'user_id' => $customId,
            ]);

            return ['success' => true, 'message' => 'Payment denial recorded'];

        } catch (\Exception $e) {
            Log::error('Error handling PayPal payment capture denied', [
                'capture_id' => $resource['id'],
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Payment denial processing failed'];
        }
    }

    /**
     * Handle subscription created
     */
    private function handleSubscriptionCreated($resource): array
    {
        try {
            $customId = $resource['custom_id'] ?? null;
            $subscriptionId = $resource['id'];
            $planId = $resource['plan_id'];

            if ($customId) {
                $user = User::find($customId);
                if ($user) {
                    Subscription::create([
                        'user_id' => $user->id,
                        'paypal_subscription_id' => $subscriptionId,
                        'plan_id' => $planId,
                        'status' => $resource['status'],
                        'start_time' => $resource['start_time'],
                        'next_billing_time' => $resource['next_billing_time'] ?? null,
                    ]);

                    Log::info('PayPal subscription created', [
                        'user_id' => $customId,
                        'subscription_id' => $subscriptionId,
                    ]);
                }
            }

            return ['success' => true, 'message' => 'Subscription created'];

        } catch (\Exception $e) {
            Log::error('Error handling PayPal subscription created', [
                'subscription_id' => $resource['id'],
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription creation failed'];
        }
    }

    /**
     * Handle subscription activated
     */
    private function handleSubscriptionActivated($resource): array
    {
        try {
            $subscriptionId = $resource['id'];
            $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'activated_at' => now(),
                ]);

                Log::info('PayPal subscription activated', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            return ['success' => true, 'message' => 'Subscription activated'];

        } catch (\Exception $e) {
            Log::error('Error handling PayPal subscription activated', [
                'subscription_id' => $resource['id'],
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription activation failed'];
        }
    }

    /**
     * Handle subscription cancelled
     */
    private function handleSubscriptionCancelled($resource): array
    {
        try {
            $subscriptionId = $resource['id'];
            $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

                Log::info('PayPal subscription cancelled', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            return ['success' => true, 'message' => 'Subscription cancelled'];

        } catch (\Exception $e) {
            Log::error('Error handling PayPal subscription cancelled', [
                'subscription_id' => $resource['id'],
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription cancellation failed'];
        }
    }

    /**
     * Handle subscription expired
     */
    private function handleSubscriptionExpired($resource): array
    {
        try {
            $subscriptionId = $resource['id'];
            $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                ]);

                Log::info('PayPal subscription expired', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            return ['success' => true, 'message' => 'Subscription expired'];

        } catch (\Exception $e) {
            Log::error('Error handling PayPal subscription expired', [
                'subscription_id' => $resource['id'],
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription expiration failed'];
        }
    }

    /**
     * Handle subscription payment completed
     */
    private function handleSubscriptionPaymentCompleted($resource): array
    {
        try {
            $subscriptionId = $resource['billing_agreement_id'];
            $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'last_payment_at' => now(),
                    'next_billing_date' => $resource['next_billing_time'] ?? null,
                ]);

                Log::info('PayPal subscription payment completed', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            return ['success' => true, 'message' => 'Subscription payment processed'];

        } catch (\Exception $e) {
            Log::error('Error handling PayPal subscription payment completed', [
                'subscription_id' => $resource['billing_agreement_id'],
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription payment processing failed'];
        }
    }

    /**
     * Handle subscription payment failed
     */
    private function handleSubscriptionPaymentFailed($resource): array
    {
        try {
            $subscriptionId = $resource['billing_agreement_id'];
            $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'past_due',
                    'last_payment_failed_at' => now(),
                ]);

                Log::warning('PayPal subscription payment failed', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            return ['success' => true, 'message' => 'Subscription payment failure recorded'];

        } catch (\Exception $e) {
            Log::error('Error handling PayPal subscription payment failed', [
                'subscription_id' => $resource['billing_agreement_id'],
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription payment failure processing failed'];
        }
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken(): string
    {
        $cacheKey = 'paypal_access_token';
        
        // Check if we have a cached token
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post($this->baseUrl . '/v1/oauth2/token', [
            'grant_type' => 'client_credentials',
        ]);

        if (!$response->successful()) {
            Log::error('Failed to get PayPal access token', [
                'error' => $response->json(),
                'status_code' => $response->status(),
            ]);
            throw new \Exception('Failed to authenticate with PayPal');
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'];
        $expiresIn = $tokenData['expires_in'] ?? 3600;

        // Cache the token for slightly less than its expiration time
        Cache::put($cacheKey, $accessToken, $expiresIn - 300);

        return $accessToken;
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(string $payload, array $headers): bool
    {
        try {
            $transmissionId = $headers['paypal-transmission-id'][0] ?? '';
            $timestamp = $headers['paypal-transmission-time'][0] ?? '';
            $webhookId = $this->webhookId;
            $certUrl = $headers['paypal-cert-url'][0] ?? '';
            $authAlgo = $headers['paypal-auth-algo'][0] ?? '';
            $transmissionSig = $headers['paypal-transmission-sig'][0] ?? '';

            if (!$transmissionId || !$timestamp || !$webhookId || !$certUrl || !$authAlgo || !$transmissionSig) {
                Log::error('PayPal webhook missing required headers');
                return false;
            }

            // Verify certificate URL
            if (!filter_var($certUrl, FILTER_VALIDATE_URL) || 
                !str_contains($certUrl, 'api.paypal.com') && !str_contains($certUrl, 'api.sandbox.paypal.com')) {
                Log::error('PayPal webhook invalid certificate URL', ['cert_url' => $certUrl]);
                return false;
            }

            // Get certificate
            $certResponse = Http::get($certUrl);
            if (!$certResponse->successful()) {
                Log::error('Failed to fetch PayPal certificate', ['cert_url' => $certUrl]);
                return false;
            }

            $cert = $certResponse->body();

            // Create verification string
            $verificationString = $transmissionId . '|' . $timestamp . '|' . $webhookId . '|' . hash('sha256', $payload, false);

            // Verify signature
            $result = openssl_verify(
                $verificationString,
                base64_decode($transmissionSig),
                $cert,
                OPENSSL_ALGO_SHA256
            );

            return $result === 1;

        } catch (\Exception $e) {
            Log::error('PayPal webhook signature verification error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get user-friendly error message
     */
    private function getUserFriendlyError(array $error): string
    {
        $errorName = $error['name'] ?? '';
        $errorMessage = $error['message'] ?? 'Unknown error';
        
        return match($errorName) {
            'PAYMENT_DENIED' => 'Payment was denied. Please try a different payment method.',
            'PAYMENT_NOT_APPROVED_FOR_EXECUTION' => 'Payment was not approved. Please try again.',
            'PAYMENT_SOURCE_INFO_CANNOT_BE_VERIFIED' => 'Payment source information cannot be verified.',
            'PAYMENT_SOURCE_DECLINED_BY_PROCESSOR' => 'Payment was declined by the processor.',
            'PAYMENT_SOURCE_NOT_SUPPORTED' => 'This payment method is not supported.',
            'PAYMENT_SOURCE_ALREADY_EXISTS' => 'This payment method already exists.',
            'PAYMENT_SOURCE_BAD_REQUEST' => 'Invalid payment method information.',
            'PAYMENT_SOURCE_NOT_FOUND' => 'Payment method not found.',
            'PAYMENT_SOURCE_REQUIRED' => 'Payment method is required.',
            'PAYMENT_SOURCE_CANNOT_BE_USED' => 'This payment method cannot be used.',
            'PAYMENT_SOURCE_EXPIRED' => 'Payment method has expired.',
            'PAYMENT_SOURCE_INVALID' => 'Invalid payment method.',
            'PAYMENT_SOURCE_LIMIT_EXCEEDED' => 'Payment method limit exceeded.',
            'PAYMENT_SOURCE_NOT_ELIGIBLE' => 'Payment method is not eligible.',
            'PAYMENT_SOURCE_NOT_VERIFIED' => 'Payment method is not verified.',
            'PAYMENT_SOURCE_REQUIRES_ACTION' => 'Payment method requires additional action.',
            'PAYMENT_SOURCE_REQUIRES_CONSENT' => 'Payment method requires consent.',
            'PAYMENT_SOURCE_REQUIRES_CORRESPONDENCE' => 'Payment method requires correspondence.',
            'PAYMENT_SOURCE_REQUIRES_PHONE_VERIFICATION' => 'Payment method requires phone verification.',
            'PAYMENT_SOURCE_REQUIRES_REVIEW' => 'Payment method requires review.',
            'PAYMENT_SOURCE_REQUIRES_VERIFICATION' => 'Payment method requires verification.',
            'PAYMENT_SOURCE_REVOKED' => 'Payment method has been revoked.',
            'PAYMENT_SOURCE_SUSPENDED' => 'Payment method is suspended.',
            'PAYMENT_SOURCE_TEMPORARILY_UNAVAILABLE' => 'Payment method is temporarily unavailable.',
            'PAYMENT_SOURCE_UNUSABLE' => 'Payment method is unusable.',
            'PAYMENT_SOURCE_VERIFICATION_FAILED' => 'Payment method verification failed.',
            'PAYMENT_SOURCE_VERIFICATION_REQUIRED' => 'Payment method verification required.',
            'PAYMENT_SOURCE_VERIFICATION_TIMEOUT' => 'Payment method verification timeout.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED' => 'Payment method verification not supported.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR' => 'Payment method verification not supported by processor.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_CARD_TYPE' => 'Payment method verification not supported for this card type.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_COUNTRY' => 'Payment method verification not supported for this country.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_CURRENCY' => 'Payment method verification not supported for this currency.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_MERCHANT' => 'Payment method verification not supported for this merchant.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE' => 'Payment method verification not supported for this payment source.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_TYPE' => 'Payment method verification not supported for this payment source type.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_USAGE' => 'Payment method verification not supported for this payment source usage.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_USAGE_TYPE' => 'Payment method verification not supported for this payment source usage type.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_USAGE_TYPE_AND_PAYMENT_SOURCE' => 'Payment method verification not supported for this payment source usage type and payment source.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_USAGE_TYPE_AND_PAYMENT_SOURCE_TYPE' => 'Payment method verification not supported for this payment source usage type and payment source type.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_USAGE_TYPE_AND_PAYMENT_SOURCE_TYPE_AND_PAYMENT_SOURCE' => 'Payment method verification not supported for this payment source usage type and payment source type and payment source.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_USAGE_TYPE_AND_PAYMENT_SOURCE_TYPE_AND_PAYMENT_SOURCE_AND_PAYMENT_SOURCE_USAGE' => 'Payment method verification not supported for this payment source usage type and payment source type and payment source and payment source usage.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_USAGE_TYPE_AND_PAYMENT_SOURCE_TYPE_AND_PAYMENT_SOURCE_AND_PAYMENT_SOURCE_USAGE_AND_PAYMENT_SOURCE_USAGE_TYPE' => 'Payment method verification not supported for this payment source usage type and payment source type and payment source and payment source usage and payment source usage type.',
            'PAYMENT_SOURCE_VERIFICATION_UNSUPPORTED_BY_PROCESSOR_FOR_PAYMENT_SOURCE_USAGE_TYPE_AND_PAYMENT_SOURCE_TYPE_AND_PAYMENT_SOURCE_AND_PAYMENT_SOURCE_USAGE_AND_PAYMENT_SOURCE_USAGE_TYPE_AND_PAYMENT_SOURCE_USAGE_TYPE_AND_PAYMENT_SOURCE_USAGE_TYPE' => 'Payment method verification not supported for this payment source usage type and payment source type and payment source and payment source usage and payment source usage type and payment source usage type and payment source usage type.',
            default => $errorMessage,
        };
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . "/v1/billing/subscriptions/{$subscription->paypal_subscription_id}/cancel", [
                'reason' => 'User requested cancellation',
            ]);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('PayPal subscription cancellation failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'error' => $this->getUserFriendlyError($error),
                ];
            }

            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            Log::info('PayPal subscription cancelled', [
                'subscription_id' => $subscription->id,
            ]);

            return ['success' => true, 'message' => 'Subscription cancelled successfully'];

        } catch (\Exception $e) {
            Log::error('Error cancelling PayPal subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to cancel subscription. Please try again.',
            ];
        }
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $captureId, array $refundData = []): array
    {
        try {
            $refundPayload = array_merge([
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '0.00',
                ],
            ], $refundData);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ])->post($this->baseUrl . "/v2/payments/captures/{$captureId}/refund", $refundPayload);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('PayPal refund failed', [
                    'capture_id' => $captureId,
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'error' => $this->getUserFriendlyError($error),
                ];
            }

            $refund = $response->json();

            Log::info('PayPal refund created', [
                'capture_id' => $captureId,
                'refund_id' => $refund['id'],
                'status' => $refund['status'],
            ]);

            return [
                'success' => true,
                'refund_id' => $refund['id'],
                'status' => $refund['status'],
            ];

        } catch (\Exception $e) {
            Log::error('Error creating PayPal refund', [
                'capture_id' => $captureId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process refund. Please try again.',
            ];
        }
    }
} 