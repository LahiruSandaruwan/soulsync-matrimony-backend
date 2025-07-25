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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add downgrade fields
            $table->enum('downgrade_to', ['basic', 'premium', 'platinum'])->nullable()->after('cancellation_note');
            $table->timestamp('downgrade_at')->nullable()->after('downgrade_to');
            
            // Update payment_method enum to include 'trial'
            $table->enum('payment_method', ['stripe', 'paypal', 'payhere', 'webxpay', 'bank_transfer', 'trial'])
                  ->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['downgrade_to', 'downgrade_at']);
            
            // Revert payment_method enum
            $table->enum('payment_method', ['stripe', 'paypal', 'payhere', 'webxpay', 'bank_transfer'])
                  ->nullable()->change();
        });
    }
};
