<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $planTypes = ['basic', 'premium', 'platinum'];
        $statuses = ['pending', 'active', 'expired', 'cancelled', 'failed', 'refunded'];
        $billingCycles = ['monthly', 'quarterly', 'semi_annual', 'annual'];
        $paymentMethods = ['stripe', 'paypal', 'payhere', 'webxpay', 'bank_transfer'];
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];

        return [
            'user_id' => User::factory(),
            'plan_type' => $this->faker->randomElement($planTypes),
            'status' => $this->faker->randomElement($statuses),
            'billing_cycle' => $this->faker->randomElement($billingCycles),
            'amount_usd' => $this->faker->randomFloat(2, 5, 100),
            'amount_local' => $this->faker->randomFloat(2, 1000, 30000),
            'local_currency' => 'LKR',
            'exchange_rate' => $this->faker->randomFloat(4, 300, 350),
            'discount_amount' => $this->faker->randomFloat(2, 0, 10),
            'discount_code' => null,
            'payment_method' => $this->faker->randomElement($paymentMethods),
            'payment_gateway_id' => $this->faker->uuid(),
            'payment_gateway_subscription_id' => $this->faker->uuid(),
            'payment_status' => $this->faker->randomElement($paymentStatuses),
            'payment_details' => null,
            'starts_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 year'),
            'trial_ends_at' => null,
            'is_trial' => false,
            'auto_renewal' => $this->faker->boolean(80),
            'next_billing_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'billing_attempts' => 0,
            'last_billing_attempt' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'cancellation_note' => null,
            'cancelled_by' => null,
            'features_included' => null,
            'usage_limits' => null,
            'current_usage' => null,
            'invoice_number' => null,
            'billing_address' => null,
            'tax_details' => null,
            'tax_amount' => 0.00,
            'referred_by' => null,
            'referral_commission' => 0.00,
            'commission_paid' => false,
            'days_used' => 0,
            'usage_analytics' => null,
            'lifetime_value' => 0.00,
        ];
    }
} 