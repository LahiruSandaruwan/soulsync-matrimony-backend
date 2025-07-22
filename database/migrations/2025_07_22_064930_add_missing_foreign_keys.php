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
        // Add foreign key constraints after all tables are created
        
        // Add conversation_id foreign key to user_matches table
        Schema::table('user_matches', function (Blueprint $table) {
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
        });
        
        // Add subscription_id foreign key to coupon_usages table
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });
        
        // Add report_id foreign key to user_warnings table
        Schema::table('user_warnings', function (Blueprint $table) {
            $table->foreign('report_id')->references('id')->on('reports')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_warnings', function (Blueprint $table) {
            $table->dropForeign(['report_id']);
        });
        
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
        });
        
        Schema::table('user_matches', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });
    }
}; 