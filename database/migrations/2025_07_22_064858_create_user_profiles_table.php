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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Physical attributes
            $table->integer('height_cm')->nullable(); // Height in centimeters
            $table->decimal('weight_kg', 5, 2)->nullable(); // Weight in kg
            $table->enum('body_type', ['slim', 'average', 'athletic', 'heavy'])->nullable();
            $table->enum('complexion', ['very_fair', 'fair', 'wheatish', 'dark', 'very_dark'])->nullable();
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->boolean('physically_challenged')->default(false);
            $table->text('physical_challenge_details')->nullable();
            
            // Location
            $table->string('current_city')->nullable();
            $table->string('current_state')->nullable();
            $table->string('current_country')->nullable();
            $table->string('hometown_city')->nullable();
            $table->string('hometown_state')->nullable();
            $table->string('hometown_country')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Education & Career
            $table->string('education_level')->nullable(); // e.g., Bachelor's, Master's, PhD
            $table->string('education_field')->nullable(); // e.g., Engineering, Medicine
            $table->string('college_university')->nullable();
            $table->string('occupation')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('annual_income_usd', 12, 2)->nullable();
            $table->enum('working_status', ['employed', 'self_employed', 'business', 'not_working', 'student'])->nullable();
            
            // Cultural & Religious
            $table->string('religion')->nullable();
            $table->string('caste')->nullable();
            $table->string('sub_caste')->nullable();
            $table->string('mother_tongue')->nullable();
            $table->json('languages_known')->nullable(); // Array of languages
            $table->enum('religiousness', ['very_religious', 'religious', 'somewhat_religious', 'not_religious'])->nullable();
            
            // Family
            $table->enum('family_type', ['nuclear', 'joint'])->nullable();
            $table->enum('family_status', ['middle_class', 'upper_middle_class', 'rich', 'affluent'])->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->integer('brothers_count')->default(0);
            $table->integer('sisters_count')->default(0);
            $table->integer('brothers_married')->default(0);
            $table->integer('sisters_married')->default(0);
            $table->text('family_details')->nullable();
            
            // Lifestyle
            $table->enum('diet', ['vegetarian', 'non_vegetarian', 'vegan', 'jain', 'occasionally_non_veg'])->nullable();
            $table->enum('smoking', ['never', 'occasionally', 'regularly'])->nullable();
            $table->enum('drinking', ['never', 'occasionally', 'socially', 'regularly'])->nullable();
            $table->json('hobbies')->nullable(); // Array of hobbies
            $table->text('about_me')->nullable();
            $table->text('looking_for')->nullable();
            
            // Matrimonial specific
            $table->enum('marital_status', ['never_married', 'divorced', 'widowed', 'separated'])->nullable();
            $table->boolean('have_children')->default(false);
            $table->integer('children_count')->default(0);
            $table->enum('children_living_status', ['with_me', 'with_ex', 'independent'])->nullable();
            $table->boolean('willing_to_relocate')->default(false);
            $table->json('preferred_locations')->nullable(); // Array of preferred cities/countries
            
            // Verification
            $table->boolean('profile_verified')->default(false);
            $table->boolean('income_verified')->default(false);
            $table->boolean('education_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by')->nullable(); // Admin user who verified
            
            // Completion tracking
            $table->integer('profile_completion_percentage')->default(0);
            $table->timestamp('last_updated_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for search performance
            $table->index(['user_id']);
            $table->index(['current_city', 'current_country']);
            $table->index(['religion', 'caste']);
            $table->index(['education_level', 'occupation']);
            $table->index(['annual_income_usd']);
            $table->index(['marital_status', 'have_children']);
            $table->index(['diet', 'smoking', 'drinking']);
            $table->index(['height_cm']);
            $table->index(['profile_completion_percentage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
