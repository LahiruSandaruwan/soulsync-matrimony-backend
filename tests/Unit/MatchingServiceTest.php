<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MatchingService;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPreference;
use App\Models\Interest;
use App\Models\UserMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class MatchingServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private MatchingService $matchingService;
    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->matchingService = new MatchingService();
        
        $this->user1 = User::factory()->create([
            'date_of_birth' => now()->subYears(28),
            'gender' => 'male',
            'country_code' => 'US',
            'latitude' => 40.7128,
            'longitude' => -74.0060, // New York
        ]);

        $this->user2 = User::factory()->create([
            'date_of_birth' => now()->subYears(26),
            'gender' => 'female',
            'country_code' => 'US',
            'latitude' => 40.7589,
            'longitude' => -73.9851, // Also New York (close)
        ]);

        // Create profiles
        UserProfile::factory()->create([
            'user_id' => $this->user1->id,
            'education_level' => 'bachelors',
            'religion' => 'christian',
            'smoking' => 'never',
            'drinking' => 'socially',
            'exercise_frequency' => 'weekly',
            'diet' => 'omnivore',
        ]);

        UserProfile::factory()->create([
            'user_id' => $this->user2->id,
            'education_level' => 'masters',
            'religion' => 'christian',
            'smoking' => 'never',
            'drinking' => 'never',
            'exercise_frequency' => 'daily',
            'diet' => 'vegetarian',
        ]);

        // Create preferences
        UserPreference::factory()->create([
            'user_id' => $this->user1->id,
            'min_age' => 22,
            'max_age' => 30,
            'preferred_genders' => ['female'],
            'max_distance' => 50,
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->user2->id,
            'min_age' => 25,
            'max_age' => 35,
            'preferred_genders' => ['male'],
            'max_distance' => 30,
        ]);
    }

    public function test_calculate_distance_returns_correct_value()
    {
        // Test distance between New York coordinates (should be close)
        $distance = $this->matchingService->calculateDistance(
            40.7128, -74.0060, // NYC
            40.7589, -73.9851  // Also NYC
        );

        $this->assertIsFloat($distance);
        $this->assertGreaterThan(0, $distance);
        $this->assertLessThan(10, $distance); // Should be less than 10km
    }

    public function test_calculate_distance_with_far_locations()
    {
        // Test distance between New York and Los Angeles
        $distance = $this->matchingService->calculateDistance(
            40.7128, -74.0060, // NYC
            34.0522, -118.2437 // LA
        );

        $this->assertGreaterThan(3000, $distance); // Should be over 3000km
    }

    public function test_apply_premium_boost_for_premium_user()
    {
        $this->user1->update([
            'is_premium' => true,
            'premium_expires_at' => now()->addMonth(),
        ]);

        $originalScore = 75;
        $boostedScore = $this->matchingService->applyPremiumBoost($originalScore, $this->user1);

        $this->assertGreaterThan($originalScore, $boostedScore);
        $this->assertEquals(90, $boostedScore); // 75 + 15 boost
    }

    public function test_apply_premium_boost_for_non_premium_user()
    {
        $this->user1->update(['is_premium' => false]);

        $originalScore = 75;
        $boostedScore = $this->matchingService->applyPremiumBoost($originalScore, $this->user1);

        $this->assertEquals($originalScore, $boostedScore); // No boost
    }

    public function test_apply_premium_boost_for_expired_premium()
    {
        $this->user1->update([
            'is_premium' => true,
            'premium_expires_at' => now()->subDay(), // Expired
        ]);

        $originalScore = 75;
        $boostedScore = $this->matchingService->applyPremiumBoost($originalScore, $this->user1);

        $this->assertEquals($originalScore, $boostedScore); // No boost for expired
    }

    public function test_apply_verification_boost()
    {
        $this->user1->update([
            'email_verified' => true,
            'phone_verified' => true,
            'photo_verified' => true,
            'id_verified' => true,
        ]);

        $originalScore = 50;
        $boostedScore = $this->matchingService->applyVerificationBoost($originalScore, $this->user1);

        $expectedBoost = 2 + 3 + 5 + 10; // Email + Phone + Photo + ID
        $this->assertEquals($originalScore + $expectedBoost, $boostedScore);
    }

    public function test_check_deal_breakers_age_compatibility()
    {
        // User2's age (26) should be within User1's preferences (22-30)
        $isCompatible = $this->matchingService->checkDealBreakers($this->user1, $this->user2);
        $this->assertTrue($isCompatible);

        // Test age outside range
        $this->user2->update(['date_of_birth' => now()->subYears(35)]); // Too old
        $isCompatible = $this->matchingService->checkDealBreakers($this->user1, $this->user2);
        $this->assertFalse($isCompatible);
    }

    public function test_check_deal_breakers_gender_compatibility()
    {
        // Should be compatible (male looking for female)
        $isCompatible = $this->matchingService->checkDealBreakers($this->user1, $this->user2);
        $this->assertTrue($isCompatible);

        // Change gender preferences
        $this->user1->preferences->update(['preferred_genders' => ['male']]);
        $isCompatible = $this->matchingService->checkDealBreakers($this->user1, $this->user2);
        $this->assertFalse($isCompatible);
    }

    public function test_check_deal_breakers_distance_compatibility()
    {
        // Users are close, should be compatible
        $isCompatible = $this->matchingService->checkDealBreakers($this->user1, $this->user2);
        $this->assertTrue($isCompatible);

        // Move user2 far away (Los Angeles)
        $this->user2->update([
            'latitude' => 34.0522,
            'longitude' => -118.2437,
        ]);

        $isCompatible = $this->matchingService->checkDealBreakers($this->user1, $this->user2);
        $this->assertFalse($isCompatible); // Should fail due to distance
    }

    public function test_calculate_age_compatibility()
    {
        $score = $this->matchingService->calculateAgeCompatibility($this->user1, $this->user2);
        $this->assertGreaterThan(0, $score); // Should return positive score

        // Test with age outside preferences
        $this->user2->update(['date_of_birth' => now()->subYears(18)]); // Too young
        $score = $this->matchingService->calculateAgeCompatibility($this->user1, $this->user2);
        $this->assertEquals(0, $score); // Should return 0
    }

    public function test_calculate_location_compatibility()
    {
        // Test same city (close coordinates)
        $score = $this->matchingService->calculateLocationCompatibility($this->user1, $this->user2);
        $this->assertEquals(100, $score); // Should be 100 for same city

        // Test nearby cities
        $this->user2->update([
            'latitude' => 40.7831,
            'longitude' => -73.9712, // About 30km away
        ]);
        $score = $this->matchingService->calculateLocationCompatibility($this->user1, $this->user2);
        $this->assertEquals(80, $score);

        // Test different countries
        $this->user2->update(['country_code' => 'CA']);
        $score = $this->matchingService->calculateLocationCompatibility($this->user1, $this->user2);
        $this->assertEquals(20, $score);
    }

    public function test_calculate_education_compatibility()
    {
        // Different education levels (bachelors vs masters = 1 level difference)
        $score = $this->matchingService->calculateEducationCompatibility($this->user1, $this->user2);
        $this->assertEquals(80, $score); // One level difference

        // Same education level
        $this->user2->profile->update(['education_level' => 'bachelors']);
        $score = $this->matchingService->calculateEducationCompatibility($this->user1, $this->user2);
        $this->assertEquals(100, $score);

        // Large education gap
        $this->user2->profile->update(['education_level' => 'high_school']);
        $score = $this->matchingService->calculateEducationCompatibility($this->user1, $this->user2);
        $this->assertEquals(60, $score); // Two levels difference
    }

    public function test_calculate_religion_compatibility()
    {
        // Same religion
        $score = $this->matchingService->calculateReligionCompatibility($this->user1, $this->user2);
        $this->assertEquals(100, $score);

        // Compatible religions
        $this->user2->profile->update(['religion' => 'catholic']);
        $score = $this->matchingService->calculateReligionCompatibility($this->user1, $this->user2);
        $this->assertEquals(70, $score);

        // Different religions
        $this->user2->profile->update(['religion' => 'muslim']);
        $score = $this->matchingService->calculateReligionCompatibility($this->user1, $this->user2);
        $this->assertEquals(20, $score);
    }

    public function test_calculate_lifestyle_compatibility()
    {
        $score = $this->matchingService->calculateLifestyleCompatibility($this->user1, $this->user2);
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);

        // Test with identical lifestyle
        $this->user2->profile->update([
            'smoking' => 'never',
            'drinking' => 'socially',
            'exercise_frequency' => 'weekly',
            'diet' => 'omnivore',
        ]);
        $score = $this->matchingService->calculateLifestyleCompatibility($this->user1, $this->user2);
        $this->assertEquals(100, $score);
    }

    public function test_calculate_interest_compatibility()
    {
        // Create interests
        $interest1 = Interest::factory()->create(['name' => 'Photography']);
        $interest2 = Interest::factory()->create(['name' => 'Travel']);
        $interest3 = Interest::factory()->create(['name' => 'Cooking']);

        // Both users have photography and travel
        $this->user1->interests()->attach([$interest1->id, $interest2->id]);
        $this->user2->interests()->attach([$interest1->id, $interest2->id, $interest3->id]);

        $score = $this->matchingService->calculateInterestCompatibility($this->user1, $this->user2);
        $this->assertGreaterThan(50, $score); // Should have good compatibility
    }

    public function test_calculate_interest_compatibility_with_no_common_interests()
    {
        $interest1 = Interest::factory()->create(['name' => 'Photography']);
        $interest2 = Interest::factory()->create(['name' => 'Travel']);

        $this->user1->interests()->attach([$interest1->id]);
        $this->user2->interests()->attach([$interest2->id]);

        $score = $this->matchingService->calculateInterestCompatibility($this->user1, $this->user2);
        $this->assertEquals(0, $score); // No common interests
    }

    public function test_calculate_compatibility_score()
    {
        // Create some interests for both users
        $interest = Interest::factory()->create(['name' => 'Reading']);
        $this->user1->interests()->attach([$interest->id]);
        $this->user2->interests()->attach([$interest->id]);

        $score = $this->matchingService->calculateCompatibilityScore($this->user1, $this->user2);
        
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_check_mutual_match()
    {
        // Create mutual likes
        UserMatch::create([
            'user_id' => $this->user1->id,
            'matched_user_id' => $this->user2->id,
            'user_action' => 'liked',
        ]);

        UserMatch::create([
            'user_id' => $this->user2->id,
            'matched_user_id' => $this->user1->id,
            'user_action' => 'liked',
        ]);

        $isMutual = $this->matchingService->checkMutualMatch($this->user1->id, $this->user2->id);
        $this->assertTrue($isMutual);
    }

    public function test_check_mutual_match_one_sided()
    {
        // Only user1 likes user2
        UserMatch::create([
            'user_id' => $this->user1->id,
            'matched_user_id' => $this->user2->id,
            'user_action' => 'liked',
        ]);

        $isMutual = $this->matchingService->checkMutualMatch($this->user1->id, $this->user2->id);
        $this->assertFalse($isMutual);
    }

    public function test_horoscope_compatibility()
    {
        // Test compatible signs
        $score = $this->matchingService->calculateHoroscopeCompatibility('aries', 'leo');
        $this->assertEquals(80, $score);

        // Test incompatible signs
        $score = $this->matchingService->calculateHoroscopeCompatibility('aries', 'cancer');
        $this->assertEquals(30, $score);

        // Test same sign
        $score = $this->matchingService->calculateHoroscopeCompatibility('aries', 'aries');
        $this->assertEquals(100, $score);
    }
}