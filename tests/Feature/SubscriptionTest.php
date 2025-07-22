<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Subscription;
use App\Models\ExchangeRate;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Cache;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test exchange rate
        ExchangeRate::create([
            'from_currency' => 'USD',
            'to_currency' => 'LKR',
            'rate' => 300.0,
            'is_active' => true,
            'effective_date' => now(),
        ]);
    }

    /**
     * Test user can view subscription plans
     */
    public function test_user_can_view_subscription_plans()
    {
        $user = User::factory()->create(['country_code' => 'LK']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription/plans');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'plans' => [
                             '*' => [
                                 'plan_type',
                                 'name',
                                 'price_usd',
                                 'price_local',
                                 'currency',
                                 'features',
                             ]
                         ],
                         'available_payment_methods',
                         'currency_info',
                     ]
                 ]);
    }

    /**
     * Test unauthenticated user can view subscription plans
     */
    public function test_unauthenticated_user_can_view_subscription_plans()
    {
        $response = $this->getJson('/api/v1/subscription/plans');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'plans',
                         'currency_info',
                     ]
                 ]);
    }

    /**
     * Test user can subscribe to a plan
     */
    public function test_user_can_subscribe_to_plan()
    {
        $user = User::factory()->create(['country_code' => 'US']);
        Sanctum::actingAs($user);

        $subscriptionData = [
            'plan_type' => 'premium',
            'payment_method' => 'stripe',
            'duration_months' => 1,
            'payment_token' => 'test_payment_token',
            'auto_renewal' => true,
        ];

        $response = $this->postJson('/api/v1/subscription/subscribe', $subscriptionData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'subscription' => [
                             'id',
                             'plan_type',
                             'status',
                             'amount_usd',
                             'starts_at',
                             'expires_at',
                         ],
                         'payment',
                     ]
                 ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_type' => 'premium',
            'status' => 'active',
        ]);
    }

    /**
     * Test subscription creation fails with invalid payment method
     */
    public function test_subscription_fails_with_invalid_payment_method()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $subscriptionData = [
            'plan_type' => 'premium',
            'payment_method' => 'invalid_method',
            'payment_token' => 'test_token',
        ];

        $response = $this->postJson('/api/v1/subscription/subscribe', $subscriptionData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['payment_method']);
    }

    /**
     * Test subscription creation fails with invalid plan type
     */
    public function test_subscription_fails_with_invalid_plan_type()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $subscriptionData = [
            'plan_type' => 'invalid_plan',
            'payment_method' => 'stripe',
            'payment_token' => 'test_token',
        ];

        $response = $this->postJson('/api/v1/subscription/subscribe', $subscriptionData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['plan_type']);
    }

    /**
     * Test user can view their subscription status
     */
    public function test_user_can_view_subscription_status()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'plan_type' => 'premium',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription/status');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'has_subscription',
                         'subscription' => [
                             'id',
                             'plan_type',
                             'status',
                             'expires_at',
                         ],
                         'features',
                         'usage',
                     ]
                 ]);
    }

    /**
     * Test user without subscription sees free plan status
     */
    public function test_user_without_subscription_sees_free_plan()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription/status');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'has_subscription' => false,
                         'current_plan' => 'free',
                     ]
                 ]);
    }

    /**
     * Test user can cancel their subscription
     */
    public function test_user_can_cancel_subscription()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'auto_renewal' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/subscription/cancel', [
            'reason' => 'Testing cancellation',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Subscription cancelled successfully',
                 ]);

        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    /**
     * Test user cannot cancel non-existent subscription
     */
    public function test_user_cannot_cancel_non_existent_subscription()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/subscription/cancel', [
            'reason' => 'Testing cancellation',
        ]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'No active subscription found',
                 ]);
    }

    /**
     * Test user can reactivate cancelled subscription
     */
    public function test_user_can_reactivate_cancelled_subscription()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'cancelled',
            'expires_at' => now()->addDays(10), // Still within valid period
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/subscription/reactivate', [
            'payment_token' => 'test_payment_token',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Subscription reactivated successfully',
                 ]);

        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
    }

    /**
     * Test subscription history retrieval
     */
    public function test_user_can_view_subscription_history()
    {
        $user = User::factory()->create();
        
        // Create multiple subscriptions
        Subscription::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription/history');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'data' => [
                             '*' => [
                                 'id',
                                 'plan_type',
                                 'status',
                                 'amount_usd',
                                 'created_at',
                             ]
                         ]
                     ]
                 ]);
    }

    /**
     * Test payment verification
     */
    public function test_payment_verification()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_gateway_id' => 'test_transaction_123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/subscription/verify-payment', [
            'transaction_id' => 'test_transaction_123',
            'payment_method' => 'stripe',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                 ]);
    }

    /**
     * Test currency conversion for Sri Lankan users
     */
    public function test_currency_conversion_for_sri_lankan_users()
    {
        $user = User::factory()->create(['country_code' => 'LK']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription/plans');

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'currency' => 'LKR'
                 ]);

        $plans = $response->json('data.plans');
        foreach ($plans as $plan) {
            $this->assertArrayHasKey('price_local', $plan);
            $this->assertGreaterThan($plan['price_usd'], $plan['price_local']); // LKR should be higher value
        }
    }

    /**
     * Test multi-month discount calculation
     */
    public function test_multi_month_discount_calculation()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Test 12-month subscription for discount
        $subscriptionData = [
            'plan_type' => 'premium',
            'payment_method' => 'stripe',
            'duration_months' => 12,
            'payment_token' => 'test_payment_token',
        ];

        $response = $this->postJson('/api/v1/subscription/subscribe', $subscriptionData);

        $response->assertStatus(201);

        $subscription = Subscription::where('user_id', $user->id)->first();
        
        // Verify discount was applied
        $basePrice = 9.99 * 12; // Premium plan base price * 12 months
        $discountedPrice = $basePrice * 0.8; // 20% discount for 12 months
        
        $this->assertEquals($discountedPrice, $subscription->amount_usd);
    }

    /**
     * Test subscription expiry handling
     */
    public function test_subscription_expiry_handling()
    {
        $user = User::factory()->create(['is_premium' => true]);
        
        // Create expired subscription
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription/status');

        $response->assertStatus(200);
        
        // Check if user premium status is updated
        $user->refresh();
        $this->assertFalse($user->is_premium);
    }

    /**
     * Test auto-renewal functionality
     */
    public function test_auto_renewal_functionality()
    {
        $user = User::factory()->create();
        
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'auto_renewal' => true,
            'expires_at' => now()->addDay(),
            'payment_method' => 'stripe',
        ]);

        Sanctum::actingAs($user);

        // Simulate auto-renewal check
        $response = $this->postJson('/api/v1/subscription/process-renewals');

        $response->assertStatus(200);
        
        // Verify subscription was renewed
        $subscription->refresh();
        $this->assertGreaterThan(now()->addDay(), $subscription->expires_at);
    }

    /**
     * Test subscription upgrade
     */
    public function test_subscription_upgrade()
    {
        $user = User::factory()->create();
        
        // Create basic subscription
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_type' => 'basic',
            'status' => 'active',
            'expires_at' => now()->addDays(20),
        ]);

        Sanctum::actingAs($user);

        $upgradeData = [
            'plan_type' => 'premium',
            'payment_method' => 'stripe',
            'payment_token' => 'test_upgrade_token',
        ];

        $response = $this->postJson('/api/v1/subscription/upgrade', $upgradeData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Subscription upgraded successfully',
                 ]);

        // Verify old subscription is cancelled
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);

        // Verify new subscription exists
        $newSubscription = Subscription::where('user_id', $user->id)
                                      ->where('plan_type', 'premium')
                                      ->where('status', 'active')
                                      ->first();
        $this->assertNotNull($newSubscription);
    }

    /**
     * Test subscription downgrade
     */
    public function test_subscription_downgrade()
    {
        $user = User::factory()->create();
        
        // Create premium subscription
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_type' => 'premium',
            'status' => 'active',
            'expires_at' => now()->addDays(20),
        ]);

        Sanctum::actingAs($user);

        $downgradeData = [
            'plan_type' => 'basic',
            'effective_date' => 'end_of_period', // Downgrade at end of current period
        ];

        $response = $this->postJson('/api/v1/subscription/downgrade', $downgradeData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Subscription will be downgraded at the end of the current period',
                 ]);

        // Verify subscription is marked for downgrade
        $subscription->refresh();
        $this->assertEquals('basic', $subscription->pending_plan_change);
    }

    /**
     * Test free trial activation
     */
    public function test_free_trial_activation()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/subscription/start-trial', [
            'plan_type' => 'premium',
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Free trial started successfully',
                 ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_type' => 'premium',
            'status' => 'trial',
        ]);

        $user->refresh();
        $this->assertTrue($user->is_premium);
    }

    /**
     * Test user cannot start multiple trials
     */
    public function test_user_cannot_start_multiple_trials()
    {
        $user = User::factory()->create();
        
        // Create existing trial
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'trial',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/subscription/start-trial', [
            'plan_type' => 'premium',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'You have already used your free trial',
                 ]);
    }

    /**
     * Test subscription feature access
     */
    public function test_subscription_feature_access()
    {
        $user = User::factory()->create();
        
        // Create premium subscription
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_type' => 'premium',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription/features');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'plan_type',
                         'features' => [
                             'unlimited_likes',
                             'see_who_viewed_profile',
                             'access_private_photos',
                             'advanced_filters',
                         ]
                     ]
                 ]);
    }
} 