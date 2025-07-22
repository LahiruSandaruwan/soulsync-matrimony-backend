<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * Handle Stripe webhooks
     */
    public function stripe(Request $request): JsonResponse
    {
        Log::info('Stripe webhook received', ['payload' => $request->all()]);

        try {
            // Verify Stripe signature
            $signature = $request->header('Stripe-Signature');
            $payload = $request->getContent();
            
            if (!$this->verifyStripeSignature($payload, $signature)) {
                Log::warning('Invalid Stripe webhook signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $event = json_decode($payload, true);

            if (!$event || !isset($event['type'])) {
                return response()->json(['error' => 'Invalid event'], 400);
            }

            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handleStripePaymentSuccess($event['data']['object']);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handleStripePaymentFailed($event['data']['object']);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleStripeInvoicePaid($event['data']['object']);
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $this->handleStripeSubscriptionUpdated($event['data']['object']);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleStripeSubscriptionCancelled($event['data']['object']);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['type' => $event['type']]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Stripe webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle PayPal webhooks
     */
    public function paypal(Request $request): JsonResponse
    {
        Log::info('PayPal webhook received', ['payload' => $request->all()]);

        try {
            // Verify PayPal webhook signature
            if (!$this->verifyPayPalSignature($request)) {
                Log::warning('Invalid PayPal webhook signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $event = $request->all();

            if (!isset($event['event_type'])) {
                return response()->json(['error' => 'Invalid event'], 400);
            }

            switch ($event['event_type']) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->handlePayPalPaymentCompleted($event['resource']);
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.FAILED':
                    $this->handlePayPalPaymentFailed($event['resource']);
                    break;

                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    $this->handlePayPalSubscriptionActivated($event['resource']);
                    break;

                case 'BILLING.SUBSCRIPTION.CANCELLED':
                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                    $this->handlePayPalSubscriptionCancelled($event['resource']);
                    break;

                default:
                    Log::info('Unhandled PayPal webhook event', ['type' => $event['event_type']]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('PayPal webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle PayHere webhooks
     */
    public function payhere(Request $request): JsonResponse
    {
        Log::info('PayHere webhook received', ['payload' => $request->all()]);

        try {
            // Verify PayHere signature
            if (!$this->verifyPayHereSignature($request)) {
                Log::warning('Invalid PayHere webhook signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $status = $request->input('status_code');
            $orderId = $request->input('order_id');
            $paymentId = $request->input('payment_id');

            switch ($status) {
                case '2': // Success
                    $this->handlePayHerePaymentSuccess([
                        'order_id' => $orderId,
                        'payment_id' => $paymentId,
                        'amount' => $request->input('payhere_amount'),
                        'currency' => $request->input('payhere_currency'),
                        'card_holder_name' => $request->input('card_holder_name'),
                        'card_no' => $request->input('card_no'),
                    ]);
                    break;

                case '0': // Pending
                    $this->handlePayHerePaymentPending($orderId, $paymentId);
                    break;

                case '-1': // Canceled
                case '-2': // Failed
                case '-3': // Chargedback
                    $this->handlePayHerePaymentFailed($orderId, $paymentId, $status);
                    break;

                default:
                    Log::info('Unhandled PayHere status', ['status' => $status]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('PayHere webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle WebXPay webhooks
     */
    public function webxpay(Request $request): JsonResponse
    {
        Log::info('WebXPay webhook received', ['payload' => $request->all()]);

        try {
            // Verify WebXPay signature
            if (!$this->verifyWebXPaySignature($request)) {
                Log::warning('Invalid WebXPay webhook signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $status = $request->input('status');
            $transactionId = $request->input('transaction_id');

            switch (strtolower($status)) {
                case 'success':
                case 'completed':
                    $this->handleWebXPayPaymentSuccess([
                        'transaction_id' => $transactionId,
                        'amount' => $request->input('amount'),
                        'currency' => $request->input('currency'),
                        'reference' => $request->input('reference'),
                    ]);
                    break;

                case 'failed':
                case 'cancelled':
                    $this->handleWebXPayPaymentFailed($transactionId, $status);
                    break;

                case 'pending':
                    $this->handleWebXPayPaymentPending($transactionId);
                    break;

                default:
                    Log::info('Unhandled WebXPay status', ['status' => $status]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('WebXPay webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Stripe signature verification
     */
    private function verifyStripeSignature($payload, $signature): bool
    {
        $secret = env('STRIPE_WEBHOOK_SECRET');
        if (!$secret) return true; // Skip verification if no secret configured

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
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
     * PayPal signature verification
     */
    private function verifyPayPalSignature(Request $request): bool
    {
        // TODO: Implement PayPal webhook signature verification
        // For now, just validate that required fields are present
        return $request->has(['event_type', 'resource']);
    }

    /**
     * PayHere signature verification
     */
    private function verifyPayHereSignature(Request $request): bool
    {
        $secret = env('PAYHERE_SECRET');
        if (!$secret) return true;

        $orderId = $request->input('order_id');
        $paymentId = $request->input('payment_id');
        $amount = $request->input('payhere_amount');
        $currency = $request->input('payhere_currency');
        $statusCode = $request->input('status_code');
        $md5sig = $request->input('md5sig');

        $localMd5sig = strtoupper(
            md5(
                env('PAYHERE_MERCHANT_ID') . 
                $orderId . 
                $amount . 
                $currency . 
                $statusCode . 
                strtoupper(md5($secret))
            )
        );

        return hash_equals($localMd5sig, strtoupper($md5sig));
    }

    /**
     * WebXPay signature verification
     */
    private function verifyWebXPaySignature(Request $request): bool
    {
        // TODO: Implement WebXPay specific signature verification
        return $request->has(['transaction_id', 'status']);
    }

    /**
     * Handle Stripe payment success
     */
    private function handleStripePaymentSuccess($paymentIntent): void
    {
        $subscriptionId = $paymentIntent['metadata']['subscription_id'] ?? null;
        
        if ($subscriptionId) {
            $subscription = Subscription::where('payment_gateway_id', $subscriptionId)->first();
            
            if ($subscription && $subscription->status === 'pending') {
                $subscription->update([
                    'status' => 'active',
                    'payment_status' => 'paid',
                    'starts_at' => now(),
                ]);

                $this->activateUserPremium($subscription->user);
                $this->sendSubscriptionNotification($subscription->user, 'activated');
            }
        }
    }

    /**
     * Handle Stripe payment failure
     */
    private function handleStripePaymentFailed($paymentIntent): void
    {
        $subscriptionId = $paymentIntent['metadata']['subscription_id'] ?? null;
        
        if ($subscriptionId) {
            $subscription = Subscription::where('payment_gateway_id', $subscriptionId)->first();
            
            if ($subscription) {
                $subscription->update([
                    'status' => 'failed',
                    'payment_status' => 'failed',
                ]);

                $this->sendSubscriptionNotification($subscription->user, 'failed');
            }
        }
    }

    /**
     * Handle PayHere payment success
     */
    private function handlePayHerePaymentSuccess($paymentData): void
    {
        $subscription = Subscription::where('payment_gateway_id', $paymentData['order_id'])->first();
        
        if ($subscription && $subscription->status === 'pending') {
            $subscription->update([
                'status' => 'active',
                'payment_status' => 'paid',
                'starts_at' => now(),
                'payment_details' => json_encode($paymentData),
            ]);

            $this->activateUserPremium($subscription->user);
            $this->sendSubscriptionNotification($subscription->user, 'activated');
        }
    }

    /**
     * Handle PayHere payment failure
     */
    private function handlePayHerePaymentFailed($orderId, $paymentId, $statusCode): void
    {
        $subscription = Subscription::where('payment_gateway_id', $orderId)->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => 'failed',
                'payment_status' => 'failed',
                'failure_reason' => $this->getPayHereFailureReason($statusCode),
            ]);

            $this->sendSubscriptionNotification($subscription->user, 'failed');
        }
    }

    /**
     * Handle PayHere payment pending
     */
    private function handlePayHerePaymentPending($orderId, $paymentId): void
    {
        $subscription = Subscription::where('payment_gateway_id', $orderId)->first();
        
        if ($subscription) {
            $subscription->update([
                'payment_status' => 'pending',
            ]);
        }
    }

    /**
     * Handle WebXPay payment success
     */
    private function handleWebXPayPaymentSuccess($paymentData): void
    {
        $subscription = Subscription::where('payment_gateway_id', $paymentData['transaction_id'])->first();
        
        if ($subscription && $subscription->status === 'pending') {
            $subscription->update([
                'status' => 'active',
                'payment_status' => 'paid',
                'starts_at' => now(),
                'payment_details' => json_encode($paymentData),
            ]);

            $this->activateUserPremium($subscription->user);
            $this->sendSubscriptionNotification($subscription->user, 'activated');
        }
    }

    /**
     * Handle WebXPay payment failure
     */
    private function handleWebXPayPaymentFailed($transactionId, $status): void
    {
        $subscription = Subscription::where('payment_gateway_id', $transactionId)->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => 'failed',
                'payment_status' => 'failed',
                'failure_reason' => $status,
            ]);

            $this->sendSubscriptionNotification($subscription->user, 'failed');
        }
    }

    /**
     * Handle WebXPay payment pending
     */
    private function handleWebXPayPaymentPending($transactionId): void
    {
        $subscription = Subscription::where('payment_gateway_id', $transactionId)->first();
        
        if ($subscription) {
            $subscription->update([
                'payment_status' => 'pending',
            ]);
        }
    }

    /**
     * PayPal specific handlers
     */
    private function handlePayPalPaymentCompleted($resource): void
    {
        // Similar to other payment success handlers
        // Implementation depends on PayPal's specific webhook structure
    }

    private function handlePayPalPaymentFailed($resource): void
    {
        // Similar to other payment failure handlers
    }

    private function handlePayPalSubscriptionActivated($resource): void
    {
        // Handle PayPal subscription activation
    }

    private function handlePayPalSubscriptionCancelled($resource): void
    {
        // Handle PayPal subscription cancellation
    }

    /**
     * Stripe subscription handlers
     */
    private function handleStripeSubscriptionUpdated($subscription): void
    {
        // Handle subscription updates from Stripe
    }

    private function handleStripeSubscriptionCancelled($subscription): void
    {
        // Handle subscription cancellation from Stripe
    }

    private function handleStripeInvoicePaid($invoice): void
    {
        // Handle recurring payment success
    }

    /**
     * Activate user premium status
     */
    private function activateUserPremium(User $user): void
    {
        $subscription = $user->activeSubscription;
        
        if ($subscription) {
            $user->update([
                'is_premium' => true,
                'premium_expires_at' => $subscription->expires_at,
            ]);

            // Assign premium role if using roles
            if (!$user->hasRole('premium-user')) {
                $user->assignRole('premium-user');
            }
        }
    }

    /**
     * Send subscription notification to user
     */
    private function sendSubscriptionNotification(User $user, string $type): void
    {
        $titles = [
            'activated' => 'Subscription Activated! 🎉',
            'failed' => 'Payment Failed',
            'cancelled' => 'Subscription Cancelled',
            'expired' => 'Subscription Expired',
        ];

        $messages = [
            'activated' => 'Your premium subscription is now active. Enjoy all premium features!',
            'failed' => 'Your payment could not be processed. Please try again or contact support.',
            'cancelled' => 'Your subscription has been cancelled. You can resubscribe anytime.',
            'expired' => 'Your premium subscription has expired. Renew to continue enjoying premium features.',
        ];

        Notification::create([
            'user_id' => $user->id,
            'type' => 'subscription',
            'title' => $titles[$type] ?? 'Subscription Update',
            'body' => $messages[$type] ?? 'Your subscription status has been updated.',
            'data' => json_encode([
                'subscription_type' => $type,
                'timestamp' => now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Get PayHere failure reason
     */
    private function getPayHereFailureReason($statusCode): string
    {
        $reasons = [
            '-1' => 'Payment cancelled by user',
            '-2' => 'Payment failed',
            '-3' => 'Payment chargedback',
        ];

        return $reasons[$statusCode] ?? 'Unknown failure reason';
    }

    /**
     * Log webhook for debugging
     */
    private function logWebhook(string $gateway, array $data): void
    {
        Log::info("Webhook received from {$gateway}", [
            'gateway' => $gateway,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
