<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PayHerePaymentService
{
    private string $merchantId;
    private string $merchantSecret;
    private string $appId;
    private string $appSecret;
    private string $baseUrl;
    private bool $sandbox;

    public function __construct()
    {
        $this->merchantId = config('services.payhere.merchant_id');
        $this->merchantSecret = config('services.payhere.merchant_secret');
        $this->appId = config('services.payhere.app_id');
        $this->appSecret = config('services.payhere.app_secret');
        $this->sandbox = config('services.payhere.sandbox', false);
        $this->baseUrl = $this->sandbox 
            ? 'https://sandbox.payhere.lk' 
            : 'https://www.payhere.lk';
    }

    /**
     * Process a payment using PayHere
     */
    public function processPayment(array $pricing, string $token, User $user, array $metadata = []): array
    {
        try {
            // Generate unique order ID
            $orderId = 'SS_' . $user->id . '_' . time();

            // Create payment data
            $paymentData = [
                'merchant_id' => $this->merchantId,
                'return_url' => config('app.frontend_url') . '/payment/success',
                'cancel_url' => config('app.frontend_url') . '/payment/cancel',
                'notify_url' => config('app.url') . '/api/webhooks/payhere',
                'order_id' => $orderId,
                'items' => 'SoulSync Subscription',
                'currency' => 'LKR',
                'amount' => number_format($pricing['amount_local'], 2, '.', ''),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'address' => $user->current_city ?? '',
                'city' => $user->current_city ?? '',
                'country' => 'Sri Lanka',
                'delivery_address' => $user->current_city ?? '',
                'delivery_city' => $user->current_city ?? '',
                'delivery_country' => 'Sri Lanka',
                'custom_1' => $user->id,
                'custom_2' => json_encode($metadata),
            ];

            // Generate hash
            $paymentData['hash'] = $this->generateHash($paymentData);

            // Create payment URL for redirection
            $paymentUrl = $this->createPaymentUrl($paymentData);

            return [
                'success' => true,
                'payment_url' => $paymentUrl,
                'order_id' => $orderId,
                'amount' => $pricing['amount_local'],
                'currency' => 'LKR',
                'status' => 'pending_payment',
            ];

        } catch (\Exception $e) {
            Log::error('PayHere payment processing error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'amount' => $pricing['amount_local'],
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create recurring subscription with PayHere
     */
    public function createRecurringPayment(User $user, array $pricing, string $interval, array $metadata = []): array
    {
        try {
            $subscriptionId = 'SUB_' . $user->id . '_' . time();

            $recurringData = [
                'merchant_id' => $this->merchantId,
                'return_url' => config('app.frontend_url') . '/subscription/success',
                'cancel_url' => config('app.frontend_url') . '/subscription/cancel',
                'notify_url' => config('app.url') . '/api/webhooks/payhere',
                'subscription_id' => $subscriptionId,
                'items' => 'SoulSync Subscription - ' . ucfirst($interval),
                'currency' => 'LKR',
                'amount' => number_format($pricing['amount_local'], 2, '.', ''),
                'recurrence' => $this->mapIntervalToPayHere($interval),
                'duration' => $this->getDurationForInterval($interval),
                'startup_fee' => '0.00',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'address' => $user->current_city ?? '',
                'city' => $user->current_city ?? '',
                'country' => 'Sri Lanka',
                'custom_1' => $user->id,
                'custom_2' => json_encode($metadata),
            ];

            $recurringData['hash'] = $this->generateRecurringHash($recurringData);

            $paymentUrl = $this->createRecurringPaymentUrl($recurringData);

            return [
                'success' => true,
                'payment_url' => $paymentUrl,
                'subscription_id' => $subscriptionId,
                'amount' => $pricing['amount_local'],
                'currency' => 'LKR',
                'recurrence' => $recurringData['recurrence'],
                'status' => 'pending_subscription',
            ];

        } catch (\Exception $e) {
            Log::error('PayHere recurring payment error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'amount' => $pricing['amount_local'],
            ]);

            return [
                'success' => false,
                'error' => 'Recurring payment setup failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel a recurring subscription
     */
    public function cancelRecurringPayment(string $subscriptionId): array
    {
        try {
            // PayHere API for canceling subscriptions
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->appId . ':' . $this->appSecret),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/merchant/v1/subscription/cancel', [
                'subscription_id' => $subscriptionId,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'subscription_id' => $subscriptionId,
                    'status' => 'cancelled',
                    'message' => $result['message'] ?? 'Subscription cancelled successfully',
                ];
            } else {
                throw new \Exception('PayHere API error: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('PayHere subscription cancellation error', [
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
     * Verify webhook signature
     */
    public function verifyWebhookSignature(array $data): bool
    {
        if (!isset($data['md5sig'])) {
            return false;
        }

        $orderId = $data['order_id'] ?? '';
        $paymentId = $data['payment_id'] ?? '';
        $amount = $data['payhere_amount'] ?? '';
        $currency = $data['payhere_currency'] ?? '';
        $statusCode = $data['status_code'] ?? '';

        $localMd5sig = strtoupper(
            md5(
                $this->merchantId . 
                $orderId . 
                $amount . 
                $currency . 
                $statusCode . 
                strtoupper(md5($this->merchantSecret))
            )
        );

        return hash_equals($localMd5sig, strtoupper($data['md5sig']));
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $orderId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->appId . ':' . $this->appSecret),
            ])->get($this->baseUrl . '/merchant/v1/payment/' . $orderId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            } else {
                throw new \Exception('PayHere API error: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('PayHere payment status check error', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment status check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process refund (if supported)
     */
    public function refund(string $paymentId, float $amount): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->appId . ':' . $this->appSecret),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/merchant/v1/payment/refund', [
                'payment_id' => $paymentId,
                'amount' => number_format($amount, 2, '.', ''),
                'reason' => 'Customer request',
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'refund_id' => $result['refund_id'] ?? null,
                    'amount' => $amount,
                    'status' => $result['status'] ?? 'pending',
                ];
            } else {
                throw new \Exception('PayHere refund API error: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('PayHere refund error', [
                'payment_id' => $paymentId,
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
     * Generate payment hash
     */
    private function generateHash(array $data): string
    {
        $hashedSecret = strtoupper(md5($this->merchantSecret));
        
        $amountFormatted = number_format($data['amount'], 2, '.', '');
        
        $hashString = $data['merchant_id'] . 
                     $data['order_id'] . 
                     $amountFormatted . 
                     $data['currency'] . 
                     $hashedSecret;

        return strtoupper(md5($hashString));
    }

    /**
     * Generate recurring payment hash
     */
    private function generateRecurringHash(array $data): string
    {
        $hashedSecret = strtoupper(md5($this->merchantSecret));
        
        $amountFormatted = number_format($data['amount'], 2, '.', '');
        
        $hashString = $data['merchant_id'] . 
                     $data['subscription_id'] . 
                     $amountFormatted . 
                     $data['currency'] . 
                     $data['recurrence'] . 
                     $data['duration'] . 
                     $hashedSecret;

        return strtoupper(md5($hashString));
    }

    /**
     * Create payment URL
     */
    private function createPaymentUrl(array $data): string
    {
        $queryString = http_build_query($data);
        return $this->baseUrl . '/pay?' . $queryString;
    }

    /**
     * Create recurring payment URL
     */
    private function createRecurringPaymentUrl(array $data): string
    {
        $queryString = http_build_query($data);
        return $this->baseUrl . '/subscribe?' . $queryString;
    }

    /**
     * Map subscription interval to PayHere format
     */
    private function mapIntervalToPayHere(string $interval): string
    {
        $mapping = [
            'monthly' => '1 Month',
            'quarterly' => '3 Month',
            'semi-annually' => '6 Month',
            'annually' => '1 Year',
        ];

        return $mapping[$interval] ?? '1 Month';
    }

    /**
     * Get duration for interval (PayHere requires this)
     */
    private function getDurationForInterval(string $interval): string
    {
        // PayHere duration options
        $mapping = [
            'monthly' => 'Forever',
            'quarterly' => 'Forever',
            'semi-annually' => 'Forever',
            'annually' => 'Forever',
        ];

        return $mapping[$interval] ?? 'Forever';
    }

    /**
     * Get supported payment methods
     */
    public function getSupportedPaymentMethods(): array
    {
        return [
            'visa' => ['name' => 'Visa Cards', 'type' => 'card'],
            'mastercard' => ['name' => 'Mastercard', 'type' => 'card'],
            'amex' => ['name' => 'American Express', 'type' => 'card'],
            'bank_transfer' => ['name' => 'Bank Transfer', 'type' => 'bank'],
            'ezcash' => ['name' => 'eZCash', 'type' => 'wallet'],
            'mcash' => ['name' => 'mCash', 'type' => 'wallet'],
        ];
    }

    /**
     * Validate PayHere configuration
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty($this->merchantId)) {
            $errors[] = 'PayHere Merchant ID is not configured';
        }

        if (empty($this->merchantSecret)) {
            $errors[] = 'PayHere Merchant Secret is not configured';
        }

        if (empty($this->appId)) {
            $errors[] = 'PayHere App ID is not configured';
        }

        if (empty($this->appSecret)) {
            $errors[] = 'PayHere App Secret is not configured';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sandbox' => $this->sandbox,
        ];
    }

    /**
     * Format amount for PayHere (always 2 decimal places)
     */
    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Log payment attempt for debugging
     */
    private function logPaymentAttempt(array $data, User $user): void
    {
        Log::info('PayHere payment attempt', [
            'user_id' => $user->id,
            'order_id' => $data['order_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'sandbox' => $this->sandbox,
        ]);
    }
} 