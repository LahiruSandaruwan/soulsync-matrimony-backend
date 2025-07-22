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
        Schema::create('user_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User who initiated
            $table->foreignId('matched_user_id')->constrained('users')->onDelete('cascade'); // User being matched
            
            // Match type and status
            $table->enum('match_type', ['ai_suggestion', 'search_result', 'mutual_interest', 'premium_suggestion'])
                  ->default('ai_suggestion');
            $table->enum('status', ['pending', 'liked', 'super_liked', 'disliked', 'blocked', 'mutual', 'expired'])
                  ->default('pending');
            
            // Interaction tracking
            $table->enum('user_action', ['none', 'liked', 'super_liked', 'disliked', 'blocked'])->default('none');
            $table->enum('matched_user_action', ['none', 'liked', 'super_liked', 'disliked', 'blocked'])->default('none');
            $table->timestamp('user_action_at')->nullable();
            $table->timestamp('matched_user_action_at')->nullable();
            
            // Matching scores
            $table->decimal('compatibility_score', 5, 2)->default(0.00); // Overall compatibility (0-100)
            $table->decimal('horoscope_score', 5, 2)->nullable(); // Horoscope compatibility (0-100)
            $table->decimal('preference_score', 5, 2)->default(0.00); // How well they match preferences
            $table->decimal('ai_score', 5, 2)->nullable(); // AI-generated compatibility score
            
            // Match details
            $table->json('matching_factors')->nullable(); // What factors contributed to the match
            $table->json('common_interests')->nullable(); // Shared hobbies/interests
            $table->json('compatibility_details')->nullable(); // Detailed compatibility breakdown
            
            // Communication
            $table->boolean('can_communicate')->default(false); // If they can chat (mutual like)
            $table->timestamp('communication_started_at')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable(); // References conversations table
            
            // Premium features
            $table->boolean('is_premium_match')->default(false); // Premium-only match
            $table->boolean('is_boosted')->default(false); // Boosted match (paid feature)
            $table->timestamp('boost_expires_at')->nullable();
            
            // Analytics
            $table->integer('profile_views')->default(0); // How many times viewed
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // When the match suggestion expires
            
            $table->timestamps();
            
            // Prevent duplicate matches
            $table->unique(['user_id', 'matched_user_id']);
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['matched_user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['compatibility_score']);
            $table->index(['match_type', 'created_at']);
            $table->index(['can_communicate']);
            $table->index(['expires_at']);
            $table->index(['is_premium_match']);
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_matches');
    }
};
