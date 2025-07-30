<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_status' => 'approved'
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function user_can_verify_payment()
    {
        $paymentData = [
            'transaction_id' => 'test_payment_123',
            'amount' => 29.99,
            'currency' => 'USD',
            'payment_method' => 'stripe',
            'status' => 'succeeded'
        ];
        
        $response = $this->postJson('/api/v1/subscription/payment/verify', $paymentData);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function payment_verification_requires_valid_data()
    {
        $response = $this->postJson('/api/v1/subscription/payment/verify', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['transaction_id', 'payment_method']);
    }

    /** @test */
    public function stripe_webhook_processes_successful_payment()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'amount' => 2999,
                    'currency' => 'usd',
                    'subscription' => 'sub_test_123',
                    'metadata' => [
                        'user_id' => (string) $this->user->id,
                        'plan_id' => 'premium_monthly'
                    ]
                ]
            ]
        ];
        
        $response = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function stripe_webhook_processes_failed_payment()
    {
        $webhookData = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'amount' => 2999,
                    'currency' => 'usd',
                    'subscription' => 'sub_test_123',
                    'metadata' => [
                        'user_id' => (string) $this->user->id,
                        'plan_id' => 'premium_monthly'
                    ]
                ]
            ]
        ];
        
        $response = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function paypal_webhook_processes_successful_payment()
    {
        $webhookData = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'test_payment_123',
                'amount' => [
                    'value' => '29.99',
                    'currency_code' => 'USD'
                ],
                'custom_id' => $this->user->id
            ]
        ];
        
        $response = $this->postJson('/api/webhooks/paypal', $webhookData);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function payhere_webhook_processes_successful_payment()
    {
        $webhookData = [
            'merchant_id' => 'test_merchant',
            'order_id' => 'test_order_123',
            'payhere_amount' => '2999.00',
            'payhere_currency' => 'LKR',
            'status_code' => '2',
            'md5sig' => 'test_signature',
            'custom_1' => json_encode([
                'user_id' => $this->user->id,
                'plan_type' => 'premium_monthly'
            ])
        ];
        
        $response = $this->postJson('/api/webhooks/payhere', $webhookData);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function webxpay_webhook_processes_successful_payment()
    {
        $webhookData = [
            'transaction_id' => 'test_txn_123',
            'amount' => '2999.00',
            'currency' => 'LKR',
            'status' => 'SUCCESS',
            'merchant_reference' => json_encode([
                'user_id' => $this->user->id,
                'plan_type' => 'premium_monthly'
            ])
        ];
        
        $response = $this->postJson('/api/webhooks/webxpay', $webhookData);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function webhook_health_check_returns_status()
    {
        $response = $this->getJson('/api/webhooks/health');
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'healthy'
                ]);
    }

    /** @test */
    public function test_webhook_returns_success()
    {
        $testData = [
            'test' => true,
            'timestamp' => now()->toISOString()
        ];
        
        $response = $this->postJson('/api/webhooks/test', $testData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Test webhook received successfully'
                ]);
    }

    /** @test */
    public function webhook_with_invalid_signature_is_rejected()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'amount' => 2999,
                    'currency' => 'usd'
                ]
            ]
        ];
        
        $response = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'invalid_signature'
        ]);
        
        $response->assertStatus(400);
    }

    /** @test */
    public function webhook_with_missing_data_is_rejected()
    {
        $response = $this->postJson('/api/webhooks/stripe', []);
        
        $response->assertStatus(400);
    }

    /** @test */
    public function subscription_activation_after_successful_payment()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_type' => 'premium_monthly',
            'status' => 'pending',
            'amount_usd' => 29.99,
            'payment_gateway_id' => 'test_payment_123'
        ]);
        
        $paymentData = [
            'transaction_id' => 'test_payment_123',
            'amount' => 29.99,
            'currency' => 'USD',
            'payment_method' => 'stripe',
            'status' => 'succeeded',
            'subscription_id' => $subscription->id
        ];
        
        $response = $this->postJson('/api/v1/subscription/payment/verify', $paymentData);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active'
        ]);
    }

    /** @test */
    public function payment_failure_handling()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_type' => 'premium_monthly',
            'status' => 'pending',
            'amount_usd' => 29.99,
            'payment_gateway_id' => 'test_payment_123'
        ]);
        
        $paymentData = [
            'transaction_id' => 'test_payment_123',
            'amount' => 29.99,
            'currency' => 'USD',
            'payment_method' => 'stripe',
            'status' => 'failed',
            'subscription_id' => $subscription->id
        ];
        
        $response = $this->postJson('/api/v1/subscription/payment/verify', $paymentData);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'failed'
        ]);
    }

    /** @test */
    public function payment_method_validation()
    {
        $paymentData = [
            'transaction_id' => 'test_payment_123',
            'amount' => 29.99,
            'currency' => 'USD',
            'payment_method' => 'invalid_method',
            'status' => 'succeeded'
        ];
        
        $response = $this->postJson('/api/v1/subscription/payment/verify', $paymentData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['payment_method']);
    }

    /** @test */
    public function currency_validation()
    {
        $paymentData = [
            'transaction_id' => 'test_payment_123',
            'amount' => 29.99,
            'currency' => 'INVALID',
            'payment_method' => 'stripe',
            'status' => 'succeeded'
        ];
        
        $response = $this->postJson('/api/v1/subscription/payment/verify', $paymentData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['currency']);
    }

    /** @test */
    public function amount_validation()
    {
        $paymentData = [
            'transaction_id' => 'test_payment_123',
            'amount' => -10,
            'currency' => 'USD',
            'payment_method' => 'stripe',
            'status' => 'succeeded'
        ];
        
        $response = $this->postJson('/api/v1/subscription/payment/verify', $paymentData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function refund_processing()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_type' => 'premium_monthly',
            'status' => 'active',
            'amount_usd' => 29.99,
            'payment_gateway_id' => 'test_payment_123'
        ]);
        
        $refundData = [
            'payment_id' => 'test_payment_123',
            'refund_id' => 'test_refund_123',
            'amount' => 29.99,
            'currency' => 'USD',
            'reason' => 'customer_requested'
        ];
        
        $response = $this->postJson('/api/v1/subscription/payment/refund', $refundData);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'refunded'
        ]);
    }
}
