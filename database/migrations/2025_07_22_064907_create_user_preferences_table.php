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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Basic preferences
            $table->integer('min_age')->default(18);
            $table->integer('max_age')->default(50);
            $table->integer('min_height_cm')->nullable();
            $table->integer('max_height_cm')->nullable();
            $table->json('preferred_genders')->nullable(); // ['male', 'female', 'other']
            
            // Location preferences
            $table->json('preferred_countries')->nullable(); // Array of country codes
            $table->json('preferred_states')->nullable(); // Array of states
            $table->json('preferred_cities')->nullable(); // Array of cities
            $table->integer('max_distance_km')->nullable(); // Distance radius
            $table->boolean('willing_to_relocate')->default(false);
            
            // Cultural & Religious preferences
            $table->json('preferred_religions')->nullable(); // Array of religions
            $table->json('preferred_castes')->nullable(); // Array of castes
            $table->json('preferred_mother_tongues')->nullable(); // Array of languages
            $table->json('preferred_religiousness')->nullable(); // Array of religiousness levels
            
            // Education & Career preferences
            $table->json('preferred_education_levels')->nullable(); // Array of education levels
            $table->json('preferred_occupations')->nullable(); // Array of occupations
            $table->decimal('min_income_usd', 12, 2)->nullable();
            $table->decimal('max_income_usd', 12, 2)->nullable();
            $table->json('preferred_working_status')->nullable(); // Array of working statuses
            
            // Physical preferences
            $table->json('preferred_body_types')->nullable(); // Array of body types
            $table->json('preferred_complexions')->nullable(); // Array of complexions
            $table->json('preferred_blood_groups')->nullable(); // Array of blood groups
            $table->boolean('accept_physically_challenged')->default(true);
            
            // Lifestyle preferences
            $table->json('preferred_diets')->nullable(); // Array of diet preferences
            $table->json('preferred_smoking_habits')->nullable(); // Array of smoking preferences
            $table->json('preferred_drinking_habits')->nullable(); // Array of drinking preferences
            
            // Matrimonial preferences
            $table->json('preferred_marital_status')->nullable(); // Array of marital statuses
            $table->boolean('accept_with_children')->default(true);
            $table->integer('max_children_count')->nullable();
            
            // Family preferences
            $table->json('preferred_family_types')->nullable(); // Array of family types
            $table->json('preferred_family_status')->nullable(); // Array of family statuses
            
            // Horoscope preferences (for compatibility)
            $table->boolean('require_horoscope_match')->default(false);
            $table->decimal('min_horoscope_score', 3, 1)->nullable(); // Minimum compatibility score
            $table->json('preferred_zodiac_signs')->nullable(); // Array of zodiac signs
            $table->json('preferred_stars')->nullable(); // Array of nakshatra/stars
            
            // Matching behavior
            $table->boolean('auto_accept_matches')->default(false);
            $table->boolean('show_me_on_search')->default(true);
            $table->boolean('hide_profile_from_premium')->default(false);
            $table->integer('preferred_distance_km')->default(50);
            
            // Premium preferences
            $table->boolean('show_only_verified_profiles')->default(false);
            $table->boolean('show_only_premium_profiles')->default(false);
            $table->boolean('priority_to_recent_profiles')->default(false);
            
            // Notification preferences
            $table->boolean('email_new_matches')->default(true);
            $table->boolean('email_profile_views')->default(true);
            $table->boolean('email_messages')->default(true);
            $table->boolean('push_new_matches')->default(true);
            $table->boolean('push_profile_views')->default(false);
            $table->boolean('push_messages')->default(true);
            
            $table->timestamps();
            
            // Indexes for matching performance
            $table->index(['user_id']);
            $table->index(['min_age', 'max_age']);
            $table->index(['min_height_cm', 'max_height_cm']);
            $table->index(['max_distance_km']);
            $table->index(['min_income_usd', 'max_income_usd']);
            $table->index(['require_horoscope_match']);
            $table->index(['show_me_on_search']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
