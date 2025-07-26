<?php

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\ApiErrorException;

class StripePaymentService
{
    private string $secretKey;
    private string $webhookSecret;
    private string $currency;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        $this->webhookSecret = config('services.stripe.webhook_secret');
        $this->currency = config('services.stripe.currency', 'usd');
        
        Stripe::setApiKey($this->secretKey);
    }

    /**
     * Create a payment intent for subscription
     */
    public function createPaymentIntent(User $user, array $subscriptionData): array
    {
        try {
            // Validate subscription data
            $validator = Validator::make($subscriptionData, [
                'plan_id' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|size:3',
                'payment_method_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                throw new \InvalidArgumentException('Invalid subscription data: ' . $validator->errors()->first());
            }

            // Create or get Stripe customer
            $customer = $this->getOrCreateCustomer($user);

            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) ($subscriptionData['amount'] * 100), // Convert to cents
                'currency' => $subscriptionData['currency'],
                'customer' => $customer->id,
                'payment_method' => $subscriptionData['payment_method_id'],
                'confirmation_method' => 'manual',
                'confirm' => true,
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $subscriptionData['plan_id'],
                    'subscription_type' => $subscriptionData['subscription_type'] ?? 'monthly',
                ],
                'description' => "Subscription payment for {$user->email}",
                'receipt_email' => $user->email,
            ]);

            Log::info('Stripe payment intent created', [
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $subscriptionData['amount'],
                'currency' => $subscriptionData['currency'],
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'status' => $paymentIntent->status,
                'amount' => $subscriptionData['amount'],
                'currency' => $subscriptionData['currency'],
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error creating payment intent', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'error' => $this->getUserFriendlyError($e),
                'error_code' => $e->getStripeCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Error creating Stripe payment intent', [
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
     * Create a subscription
     */
    public function createSubscription(User $user, array $subscriptionData): array
    {
        try {
            // Validate subscription data
            $validator = Validator::make($subscriptionData, [
                'plan_id' => 'required|string',
                'payment_method_id' => 'required|string',
                'trial_period_days' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                throw new \InvalidArgumentException('Invalid subscription data: ' . $validator->errors()->first());
            }

            // Create or get Stripe customer
            $customer = $this->getOrCreateCustomer($user);

            // Attach payment method to customer
            $customer->attachPaymentMethod($subscriptionData['payment_method_id']);

            // Set as default payment method
            $customer->invoice_settings->default_payment_method = $subscriptionData['payment_method_id'];
            $customer->save();

            // Create subscription
            $subscriptionData = [
                'customer' => $customer->id,
                'items' => [
                    ['price' => $subscriptionData['plan_id']],
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription',
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'user_id' => $user->id,
                    'subscription_type' => $subscriptionData['subscription_type'] ?? 'monthly',
                ],
            ];

            // Add trial period if specified
            if (isset($subscriptionData['trial_period_days']) && $subscriptionData['trial_period_days'] > 0) {
                $subscriptionData['trial_period_days'] = $subscriptionData['trial_period_days'];
            }

            $subscription = StripeSubscription::create($subscriptionData);

            Log::info('Stripe subscription created', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $subscriptionData['plan_id'],
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error creating subscription', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'error' => $this->getUserFriendlyError($e),
                'error_code' => $e->getStripeCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Error creating Stripe subscription', [
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
            $signature = $request->header('Stripe-Signature');

            if (!$signature) {
                Log::error('Stripe webhook missing signature header');
                return ['success' => false, 'error' => 'Missing signature header'];
            }

            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);

            Log::info('Stripe webhook received', [
                'event_type' => $event->type,
                'event_id' => $event->id,
            ]);

            // Process the event
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    return $this->handlePaymentIntentSucceeded($event->data->object);
                
                case 'payment_intent.payment_failed':
                    return $this->handlePaymentIntentFailed($event->data->object);
                
                case 'invoice.payment_succeeded':
                    return $this->handleInvoicePaymentSucceeded($event->data->object);
                
                case 'invoice.payment_failed':
                    return $this->handleInvoicePaymentFailed($event->data->object);
                
                case 'customer.subscription.created':
                    return $this->handleSubscriptionCreated($event->data->object);
                
                case 'customer.subscription.updated':
                    return $this->handleSubscriptionUpdated($event->data->object);
                
                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionDeleted($event->data->object);
                
                default:
                    Log::info('Unhandled Stripe webhook event', ['event_type' => $event->type]);
                    return ['success' => true, 'message' => 'Event ignored'];
            }

        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Invalid signature'];
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing error', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Webhook processing failed'];
        }
    }

    /**
     * Handle successful payment intent
     */
    private function handlePaymentIntentSucceeded($paymentIntent): array
    {
        try {
            $userId = $paymentIntent->metadata->user_id ?? null;
            $planId = $paymentIntent->metadata->plan_id ?? null;

            if (!$userId || !$planId) {
                Log::error('Stripe payment intent missing metadata', [
                    'payment_intent_id' => $paymentIntent->id,
                ]);
                return ['success' => false, 'error' => 'Missing metadata'];
            }

            $user = User::find($userId);
            if (!$user) {
                Log::error('User not found for Stripe payment', [
                    'user_id' => $userId,
                    'payment_intent_id' => $paymentIntent->id,
                ]);
                return ['success' => false, 'error' => 'User not found'];
            }

            // Update user subscription
            $subscription = $user->subscriptions()->where('stripe_subscription_id', $paymentIntent->subscription)->first();
            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'paid_at' => now(),
                ]);
            }

            Log::info('Stripe payment intent succeeded', [
                'user_id' => $userId,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount / 100,
            ]);

            return ['success' => true, 'message' => 'Payment processed successfully'];

        } catch (\Exception $e) {
            Log::error('Error handling Stripe payment intent succeeded', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }

    /**
     * Handle failed payment intent
     */
    private function handlePaymentIntentFailed($paymentIntent): array
    {
        try {
            $userId = $paymentIntent->metadata->user_id ?? null;

            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    // Update subscription status
                    $subscription = $user->subscriptions()->where('stripe_subscription_id', $paymentIntent->subscription)->first();
                    if ($subscription) {
                        $subscription->update([
                            'status' => 'failed',
                            'stripe_payment_intent_id' => $paymentIntent->id,
                        ]);
                    }
                }
            }

            Log::warning('Stripe payment intent failed', [
                'payment_intent_id' => $paymentIntent->id,
                'user_id' => $userId,
                'error' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
            ]);

            return ['success' => true, 'message' => 'Payment failure recorded'];

        } catch (\Exception $e) {
            Log::error('Error handling Stripe payment intent failed', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Payment failure processing failed'];
        }
    }

    /**
     * Handle successful invoice payment
     */
    private function handleInvoicePaymentSucceeded($invoice): array
    {
        try {
            $subscriptionId = $invoice->subscription;
            $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'last_payment_at' => now(),
                    'next_billing_date' => now()->addDays(30), // Adjust based on billing cycle
                ]);

                Log::info('Stripe invoice payment succeeded', [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);
            }

            return ['success' => true, 'message' => 'Invoice payment processed'];

        } catch (\Exception $e) {
            Log::error('Error handling Stripe invoice payment succeeded', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Invoice processing failed'];
        }
    }

    /**
     * Handle failed invoice payment
     */
    private function handleInvoicePaymentFailed($invoice): array
    {
        try {
            $subscriptionId = $invoice->subscription;
            $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'past_due',
                    'last_payment_failed_at' => now(),
                ]);

                Log::warning('Stripe invoice payment failed', [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);
            }

            return ['success' => true, 'message' => 'Invoice failure recorded'];

        } catch (\Exception $e) {
            Log::error('Error handling Stripe invoice payment failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Invoice failure processing failed'];
        }
    }

    /**
     * Handle subscription created
     */
    private function handleSubscriptionCreated($stripeSubscription): array
    {
        try {
            $userId = $stripeSubscription->metadata->user_id ?? null;
            $user = User::find($userId);

            if ($user) {
                Subscription::create([
                    'user_id' => $user->id,
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'plan_id' => $stripeSubscription->items->data[0]->price->id,
                    'status' => $stripeSubscription->status,
                    'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                    'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                    'trial_start' => $stripeSubscription->trial_start ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_start) : null,
                    'trial_end' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                ]);

                Log::info('Stripe subscription created', [
                    'user_id' => $userId,
                    'subscription_id' => $stripeSubscription->id,
                ]);
            }

            return ['success' => true, 'message' => 'Subscription created'];

        } catch (\Exception $e) {
            Log::error('Error handling Stripe subscription created', [
                'subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription creation failed'];
        }
    }

    /**
     * Handle subscription updated
     */
    private function handleSubscriptionUpdated($stripeSubscription): array
    {
        try {
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => $stripeSubscription->status,
                    'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                    'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                ]);

                Log::info('Stripe subscription updated', [
                    'subscription_id' => $subscription->id,
                    'status' => $stripeSubscription->status,
                ]);
            }

            return ['success' => true, 'message' => 'Subscription updated'];

        } catch (\Exception $e) {
            Log::error('Error handling Stripe subscription updated', [
                'subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription update failed'];
        }
    }

    /**
     * Handle subscription deleted
     */
    private function handleSubscriptionDeleted($stripeSubscription): array
    {
        try {
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

                Log::info('Stripe subscription cancelled', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            return ['success' => true, 'message' => 'Subscription cancelled'];

        } catch (\Exception $e) {
            Log::error('Error handling Stripe subscription deleted', [
                'subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Subscription cancellation failed'];
        }
    }

    /**
     * Get or create Stripe customer
     */
    private function getOrCreateCustomer(User $user): Customer
    {
        // Check if user already has a Stripe customer ID
        if ($user->stripe_customer_id) {
            try {
                return Customer::retrieve($user->stripe_customer_id);
            } catch (ApiErrorException $e) {
                // Customer not found, create new one
                Log::warning('Stripe customer not found, creating new one', [
                    'user_id' => $user->id,
                    'stripe_customer_id' => $user->stripe_customer_id,
                ]);
            }
        }

        // Create new customer
        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
            'phone' => $user->phone,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        // Update user with Stripe customer ID
        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Get user-friendly error message
     */
    private function getUserFriendlyError(ApiErrorException $e): string
    {
        $errorCode = $e->getStripeCode();
        
        return match($errorCode) {
            'card_declined' => 'Your card was declined. Please try a different payment method.',
            'insufficient_funds' => 'Your card has insufficient funds.',
            'expired_card' => 'Your card has expired. Please update your payment method.',
            'incorrect_cvc' => 'The security code (CVC) is incorrect.',
            'processing_error' => 'An error occurred while processing your card. Please try again.',
            'rate_limit' => 'Too many requests made to the API too quickly.',
            'invalid_request_error' => 'Invalid parameters were supplied to Stripe\'s API.',
            'authentication_error' => 'Authentication with Stripe\'s API failed.',
            'api_connection_error' => 'Network communication with Stripe failed.',
            'api_error' => 'An error occurred internally with Stripe\'s API.',
            default => 'Payment processing failed. Please try again.',
        };
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription): array
    {
        try {
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
            $stripeSubscription->cancel();

            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            Log::info('Stripe subscription cancelled', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
            ]);

            return ['success' => true, 'message' => 'Subscription cancelled successfully'];

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error cancelling subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $this->getUserFriendlyError($e),
            ];
        } catch (\Exception $e) {
            Log::error('Error cancelling Stripe subscription', [
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
    public function refundPayment(string $paymentIntentId, int $amount = null): array
    {
        try {
            $refundData = ['payment_intent' => $paymentIntentId];
            
            if ($amount) {
                $refundData['amount'] = $amount;
            }

            $refund = \Stripe\Refund::create($refundData);

            Log::info('Stripe refund created', [
                'payment_intent_id' => $paymentIntentId,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100,
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error creating refund', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $this->getUserFriendlyError($e),
            ];
        } catch (\Exception $e) {
            Log::error('Error creating Stripe refund', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process refund. Please try again.',
            ];
        }
    }
} 