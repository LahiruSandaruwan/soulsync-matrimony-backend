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
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->json('filters'); // Search criteria stored as JSON
            $table->boolean('is_alert_enabled')->default(false);
            $table->integer('alert_frequency')->default(24); // hours
            $table->timestamp('last_alert_sent')->nullable();
            $table->integer('result_count')->default(0);
            $table->timestamp('last_executed')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['is_alert_enabled', 'last_alert_sent']);
        });

        Schema::create('search_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_search_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('new_results_count');
            $table->json('sample_results'); // Store sample of new results
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['saved_search_id', 'sent']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_alerts');
        Schema::dropIfExists('saved_searches');
    }
}; 