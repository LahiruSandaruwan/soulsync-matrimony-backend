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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3); // Base currency (USD)
            $table->string('to_currency', 3); // Target currency (LKR, EUR, etc.)
            $table->decimal('rate', 12, 6); // Exchange rate (1 USD = X target currency)
            $table->decimal('inverse_rate', 12, 6)->default(0); // Inverse rate (1 target = X USD)
            
            // Rate metadata
            $table->string('source', 50)->default('api'); // Source of rate (api, manual, bank)
            $table->string('provider', 100)->nullable(); // Rate provider (exchangerate-api.com, etc.)
            $table->decimal('bid_rate', 12, 6)->nullable(); // Buy rate
            $table->decimal('ask_rate', 12, 6)->nullable(); // Sell rate
            $table->decimal('mid_rate', 12, 6)->nullable(); // Mid-market rate
            
            // Validity and caching
            $table->timestamp('effective_date'); // When this rate becomes effective
            $table->timestamp('expires_at')->nullable(); // When this rate expires
            $table->boolean('is_active')->default(true);
            $table->boolean('is_cached')->default(true); // For performance
            
            // Quality and reliability
            $table->decimal('volatility', 5, 4)->nullable(); // Rate volatility indicator
            $table->integer('confidence_score')->default(100); // Reliability score (0-100)
            $table->timestamp('last_updated_at')->nullable(); // When rate was last fetched
            $table->integer('update_frequency_minutes')->default(60); // How often to update
            
            // Historical tracking
            $table->decimal('previous_rate', 12, 6)->nullable(); // Previous rate for comparison
            $table->decimal('change_amount', 12, 6)->nullable(); // Change from previous
            $table->decimal('change_percentage', 8, 4)->nullable(); // Percentage change
            $table->enum('trend', ['up', 'down', 'stable'])->nullable();
            
            // Usage tracking
            $table->integer('usage_count')->default(0); // How many times used
            $table->timestamp('last_used_at')->nullable();
            
            // API response data
            $table->json('api_response')->nullable(); // Full API response for debugging
            $table->string('api_request_id')->nullable(); // API request tracking
            
            $table->timestamps();
            
            // Ensure unique currency pairs for active rates
            $table->unique(['from_currency', 'to_currency', 'effective_date']);
            
            // Indexes for performance
            $table->index(['from_currency', 'to_currency', 'is_active']);
            $table->index(['effective_date', 'expires_at']);
            $table->index(['is_active', 'last_updated_at']);
            $table->index(['to_currency', 'is_active']); // For LKR rates specifically
            $table->index(['update_frequency_minutes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
