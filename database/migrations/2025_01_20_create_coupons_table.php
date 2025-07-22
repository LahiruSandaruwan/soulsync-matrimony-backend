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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'free_trial']);
            $table->decimal('value', 8, 2); // percentage or amount
            $table->decimal('minimum_amount', 8, 2)->nullable(); // minimum purchase amount
            $table->decimal('maximum_discount', 8, 2)->nullable(); // max discount for percentage coupons
            $table->json('applicable_plans')->nullable(); // which plans this coupon applies to
            $table->integer('usage_limit')->nullable(); // total usage limit
            $table->integer('usage_limit_per_user')->default(1); // per user limit
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['code', 'is_active']);
            $table->index(['is_active', 'starts_at', 'expires_at']);
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->decimal('original_amount', 8, 2);
            $table->decimal('discount_amount', 8, 2);
            $table->decimal('final_amount', 8, 2);
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable(); // Additional coupon usage details
            $table->timestamps();

            $table->index(['user_id', 'coupon_id']);
            $table->index('subscription_id');
        });
        
        // Add foreign key constraint for subscription_id after subscriptions table exists
        if (Schema::hasTable('subscriptions')) {
            Schema::table('coupon_usages', function (Blueprint $table) {
                $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
}; 