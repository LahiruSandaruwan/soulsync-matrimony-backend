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
        Schema::create('horoscopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Birth details
            $table->date('birth_date'); // Already in users table, but kept for reference
            $table->time('birth_time')->nullable();
            $table->string('birth_place'); // City/place of birth
            $table->decimal('birth_latitude', 10, 8)->nullable();
            $table->decimal('birth_longitude', 11, 8)->nullable();
            $table->string('birth_timezone', 50)->nullable();
            
            // Zodiac information
            $table->string('zodiac_sign', 20)->nullable(); // Aries, Taurus, etc.
            $table->string('moon_sign', 20)->nullable(); // Rashi
            $table->string('sun_sign', 20)->nullable(); // Western zodiac
            $table->string('ascendant', 20)->nullable(); // Rising sign / Lagna
            
            // Vedic astrology (Nakshatra)
            $table->string('nakshatra', 30)->nullable(); // Birth star
            $table->string('nakshatra_pada', 5)->nullable(); // Quarter/pada (1-4)
            $table->string('nakshatra_lord', 20)->nullable(); // Ruling planet
            
            // Planetary positions
            $table->json('planetary_positions')->nullable(); // All 9 planets with degrees
            $table->json('house_positions')->nullable(); // 12 houses and their rulers
            $table->json('aspects')->nullable(); // Planetary aspects
            
            // Doshas and yogas
            $table->boolean('manglik')->default(false); // Mangal dosha
            $table->enum('manglik_severity', ['none', 'low', 'medium', 'high'])->default('none');
            $table->boolean('kaal_sarp_dosha')->default(false);
            $table->boolean('shani_sade_sati')->default(false);
            $table->json('yogas')->nullable(); // Beneficial yogas
            $table->json('doshas')->nullable(); // All doshas with severity
            
            // Compatibility factors
            $table->integer('guna_milan_score')->nullable(); // Total score out of 36
            $table->json('guna_breakdown')->nullable(); // 8 gunas with individual scores
            $table->json('compatibility_factors')->nullable(); // Various compatibility aspects
            
            // Panchang details
            $table->string('tithi', 30)->nullable(); // Lunar day
            $table->string('vara', 15)->nullable(); // Day of week
            $table->string('karana', 20)->nullable();
            $table->string('yoga', 30)->nullable(); // Panchang yoga
            
            // Gem and remedy suggestions
            $table->json('lucky_gems')->nullable(); // Recommended gemstones
            $table->json('lucky_colors')->nullable(); // Favorable colors
            $table->json('lucky_numbers')->nullable(); // Lucky numbers
            $table->json('lucky_days')->nullable(); // Favorable days
            $table->json('remedies')->nullable(); // Astrological remedies
            
            // Professional analysis
            $table->text('astrologer_notes')->nullable(); // Manual notes from astrologer
            $table->foreignId('analyzed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('analyzed_at')->nullable();
            $table->boolean('is_verified')->default(false); // Verified by professional astrologer
            
            // Privacy
            $table->boolean('is_public')->default(false); // Show horoscope details to matches
            $table->boolean('show_birth_time')->default(false); // Hide exact birth time
            $table->json('visibility_settings')->nullable(); // What to show/hide
            
            // Chart images
            $table->string('birth_chart_image')->nullable(); // Generated chart image
            $table->string('navamsa_chart_image')->nullable(); // D9 chart
            $table->json('chart_metadata')->nullable(); // Chart generation details
            
            $table->timestamps();
            
            // Indexes for compatibility matching
            $table->index(['user_id']);
            $table->index(['zodiac_sign']);
            $table->index(['moon_sign']);
            $table->index(['nakshatra']);
            $table->index(['manglik']);
            $table->index(['guna_milan_score']);
            $table->index(['is_verified']);
            $table->index(['is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horoscopes');
    }
};
