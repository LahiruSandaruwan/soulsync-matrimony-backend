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
        Schema::create('user_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('interest_id')->constrained()->onDelete('cascade');
            
            // Interest level and details
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('intermediate');
            $table->integer('years_experience')->nullable(); // How many years involved
            $table->enum('frequency', ['rarely', 'occasionally', 'regularly', 'daily'])->default('occasionally');
            
            // Personalization
            $table->text('description')->nullable(); // Personal description of interest
            $table->json('achievements')->nullable(); // Related achievements or certifications
            $table->boolean('is_priority')->default(false); // High priority interest for matching
            
            // Visibility
            $table->boolean('show_on_profile')->default(true);
            $table->boolean('use_for_matching')->default(true);
            
            // Social aspects
            $table->boolean('looking_for_partner')->default(false); // Want partner with same interest
            $table->boolean('willing_to_teach')->default(false); // Willing to teach this interest
            $table->boolean('want_to_learn_more')->default(false); // Want to improve in this area
            
            $table->timestamps();
            
            // Prevent duplicate user-interest combinations
            $table->unique(['user_id', 'interest_id']);
            
            // Indexes
            $table->index(['user_id', 'is_priority']);
            $table->index(['interest_id', 'level']);
            $table->index(['use_for_matching']);
            $table->index(['looking_for_partner']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_interests');
    }
};
