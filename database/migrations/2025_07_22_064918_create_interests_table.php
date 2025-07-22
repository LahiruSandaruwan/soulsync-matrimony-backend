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
        Schema::create('interests', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Interest name (e.g., "Cricket", "Cooking")
            $table->string('slug')->unique(); // URL-friendly version
            $table->text('description')->nullable(); // Detailed description
            $table->string('category', 50); // Category (sports, music, travel, etc.)
            $table->string('icon')->nullable(); // Icon class or image path
            $table->string('color', 7)->nullable(); // Hex color for UI
            
            // Popularity and usage
            $table->integer('user_count')->default(0); // How many users have this interest
            $table->integer('popularity_score')->default(0); // Overall popularity ranking
            $table->boolean('is_trending')->default(false); // Currently trending
            
            // Management
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false); // Show prominently
            $table->integer('sort_order')->default(0); // Display order
            
            // Localization
            $table->json('translations')->nullable(); // Multi-language support
            $table->string('language', 5)->default('en'); // Primary language
            
            // Matching weights for algorithm
            $table->decimal('matching_weight', 3, 2)->default(1.00); // Importance in matching (0-10)
            $table->json('related_interests')->nullable(); // Array of related interest IDs
            $table->json('synonyms')->nullable(); // Alternative names/keywords
            
            // Analytics
            $table->integer('search_count')->default(0); // How often searched
            $table->json('demographics')->nullable(); // Age/gender distribution
            $table->timestamp('last_used_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['category', 'is_active']);
            $table->index(['is_active', 'sort_order']);
            $table->index(['is_featured', 'popularity_score']);
            $table->index(['user_count']);
            $table->index(['is_trending']);
            $table->fullText(['name', 'description']); // For search functionality
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interests');
    }
};
