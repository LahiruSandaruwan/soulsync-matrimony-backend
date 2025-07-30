<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Subscription details
            $table->enum('plan_type', ['basic', 'premium', 'platinum', 'premium_monthly', 'premium_quarterly', 'premium_annual'])->default('basic');
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled', 'failed', 'refunded'])
                  ->default('pending');
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semi_annual', 'annual'])
                  ->default('monthly');
            
            // Pricing
            $table->decimal('amount_usd', 10, 2); // Base amount in USD
            $table->decimal('amount_local', 12, 2); // Amount in local currency
            $table->string('local_currency', 3)->default('USD'); // Currency code (LKR, USD, etc.)
            $table->decimal('exchange_rate', 10, 4)->default(1.0000); // Exchange rate at time of purchase
            $table->decimal('discount_amount', 10, 2)->default(0.00); // Discount applied
            $table->string('discount_code')->nullable(); // Promo code used
            
            // Payment details
            $table->enum('payment_method', ['stripe', 'paypal', 'payhere', 'webxpay', 'bank_transfer'])
                  ->nullable();
            $table->string('payment_gateway_id')->nullable(); // Gateway transaction ID
            $table->string('payment_gateway_subscription_id')->nullable(); // Recurring subscription ID
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'])
                  ->default('pending');
            $table->json('payment_details')->nullable(); // Gateway response data
            
            // Subscription period
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable(); // For trial periods
            $table->boolean('is_trial')->default(false);
            
            // Renewal settings
            $table->boolean('auto_renewal')->default(true);
            $table->timestamp('next_billing_date')->nullable();
            $table->integer('billing_attempts')->default(0); // Failed billing attempts
            $table->timestamp('last_billing_attempt')->nullable();
            
            // Cancellation
            $table->timestamp('cancelled_at')->nullable();
            $table->enum('cancellation_reason', [
                'user_request', 'payment_failed', 'fraud', 'refund', 'admin_action'
            ])->nullable();
            $table->text('cancellation_note')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Features access
            $table->json('features_included')->nullable(); // Array of features
            $table->json('usage_limits')->nullable(); // Daily/monthly limits
            $table->json('current_usage')->nullable(); // Current usage stats
            
            // Invoice details
            $table->string('invoice_number')->unique()->nullable();
            $table->json('billing_address')->nullable(); // Customer billing address
            $table->json('tax_details')->nullable(); // Tax information
            $table->decimal('tax_amount', 8, 2)->default(0.00);
            
            // Referral and affiliate
            $table->foreignId('referred_by')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('referral_commission', 8, 2)->default(0.00);
            $table->boolean('commission_paid')->default(false);
            
            // Analytics
            $table->integer('days_used')->default(0);
            $table->json('usage_analytics')->nullable(); // Detailed usage stats
            $table->decimal('lifetime_value', 12, 2)->default(0.00); // Customer LTV
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['plan_type', 'status']);
            $table->index(['payment_status']);
            $table->index(['auto_renewal', 'next_billing_date']);
            $table->index(['payment_gateway_id']);
            $table->index(['invoice_number']);
            $table->index(['starts_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
