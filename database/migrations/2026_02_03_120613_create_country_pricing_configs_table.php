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
        Schema::create('country_pricing_configs', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->unique();
            $table->string('country_name', 100);
            $table->string('currency_code', 3);
            $table->string('currency_symbol', 10);

            // Base prices in local currency - Basic plan
            $table->decimal('basic_monthly', 10, 2);
            $table->decimal('basic_quarterly', 10, 2);
            $table->decimal('basic_yearly', 10, 2);

            // Premium plan prices
            $table->decimal('premium_monthly', 10, 2);
            $table->decimal('premium_quarterly', 10, 2);
            $table->decimal('premium_yearly', 10, 2);

            // Platinum plan prices
            $table->decimal('platinum_monthly', 10, 2);
            $table->decimal('platinum_quarterly', 10, 2);
            $table->decimal('platinum_yearly', 10, 2);

            // Duration discounts (percentage)
            $table->decimal('quarterly_discount', 5, 2)->default(10.00);
            $table->decimal('yearly_discount', 5, 2)->default(20.00);

            // Payment methods available (JSON array)
            $table->json('payment_methods')->nullable();

            // Tax configuration
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('tax_name', 50)->nullable();
            $table->boolean('tax_inclusive')->default(false);

            // Display settings
            $table->integer('display_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            // Metadata
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'display_order']);
            $table->index('currency_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_pricing_configs');
    }
};
