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
        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('viewed_user_id')->constrained('users')->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->string('browser')->nullable();
            $table->string('platform')->nullable(); // ios, android, windows, etc
            $table->string('referrer')->nullable(); // where they came from
            $table->boolean('is_anonymous')->default(false); // guest view
            $table->integer('duration_seconds')->nullable(); // time spent viewing
            $table->json('sections_viewed')->nullable(); // which profile sections were viewed
            $table->boolean('profile_contacted')->default(false); // if they sent message/interest
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index(['viewer_id', 'viewed_user_id']);
            $table->index(['viewed_user_id', 'created_at']);
            $table->index(['is_anonymous']);
            $table->index(['created_at']);
            $table->unique(['viewer_id', 'viewed_user_id', 'created_at']); // Prevent duplicate views in same second
        });

        // Add profile view counters to users table
        Schema::table('users', function (Blueprint $table) {
            $table->integer('total_profile_views')->default(0)->after('last_seen_at');
            $table->integer('unique_profile_views')->default(0)->after('total_profile_views');
            $table->timestamp('last_profile_view_at')->nullable()->after('unique_profile_views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_profile_views', 'unique_profile_views', 'last_profile_view_at']);
        });
        
        Schema::dropIfExists('profile_views');
    }
}; 