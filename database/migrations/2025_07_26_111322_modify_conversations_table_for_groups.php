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
        Schema::table('conversations', function (Blueprint $table) {
            // Make user_two_id nullable to support group conversations
            $table->foreignId('user_two_id')->nullable()->change();
            
            // Add name column for group conversations
            $table->string('name')->nullable()->after('user_two_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('user_two_id')->nullable(false)->change();
            $table->dropColumn('name');
        });
    }
}; 