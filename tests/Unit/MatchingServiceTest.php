<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MatchingService;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPreference;
use App\Models\UserMatch;
use App\Models\Horoscope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class MatchingServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected MatchingService $matchingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matchingService = new MatchingService();
    }

    /**
     * Test basic compatibility score calculation
     */
    public function test_compatibility_score_calculation()
    {
        $user1 = User::factory()->create([
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
        ]);

        $user2 = User::factory()->create([
            'date_of_birth' => '1992-01-01',
            'gender' => 'female',
        ]);

        // Create profiles
        $profile1 = UserProfile::factory()->create([
            'user_id' => $user1->id,
            'height_cm' => 175,
            'education_level' => 'bachelors',
            'occupation' => 'engineer',
            'religion' => 'buddhist',
            'current_city' => 'Colombo',
        ]);

        $profile2 = UserProfile::factory()->create([
            'user_id' => $user2->id,
            'height_cm' => 165,
            'education_level' => 'bachelors',
            'occupation' => 'teacher',
            'religion' => 'buddhist',
            'current_city' => 'Colombo',
        ]);

        // Create preferences
        $preferences1 = UserPreference::factory()->create([
            'user_id' => $user1->id,
            'min_age' => 25,
            'max_age' => 35,
            'preferred_education_levels' => json_encode(['bachelors', 'masters']),
            'preferred_religions' => json_encode(['buddhist']),
        ]);

        $score = $this->matchingService->calculateCompatibilityScore($user1, $user2);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test age preference matching
     */
    public function test_age_preference_matching()
    {
        $user1 = User::factory()->create([
            'date_of_birth' => '1990-01-01', // Age 34 (assuming current year is 2024)
        ]);

        $user2 = User::factory()->create([
            'date_of_birth' => '1995-01-01', // Age 29
        ]);

        $preferences = UserPreference::factory()->create([
            'user_id' => $user1->id,
            'min_age' => 25,
            'max_age' => 35,
        ]);

        $score = $this->matchingService->calculateAgeCompatibility($user1, $user2);

        $this->assertGreaterThan(0, $score);
    }

    /**
     * Test age preference mismatch
     */
    public function test_age_preference_mismatch()
    {
        $user1 = User::factory()->create([
            'date_of_birth' => '1990-01-01', // Age 34
        ]);

        $user2 = User::factory()->create([
            'date_of_birth' => '2000-01-01', // Age 24
        ]);

        $preferences = UserPreference::factory()->create([
            'user_id' => $user1->id,
            'min_age' => 28,
            'max_age' => 35,
        ]);

        $score = $this->matchingService->calculateAgeCompatibility($user1, $user2);

        $this->assertEquals(0, $score);
    }

    /**
     * Test location preference matching
     */
    public function test_location_preference_matching()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $profile1 = UserProfile::factory()->create([
            'user_id' => $user1->id,
            'current_city' => 'Colombo',
            'current_country' => 'Sri Lanka',
        ]);

        $profile2 = UserProfile::factory()->create([
            'user_id' => $user2->id,
            'current_city' => 'Colombo',
            'current_country' => 'Sri Lanka',
        ]);

        $preferences = UserPreference::factory()->create([
            'user_id' => $user1->id,
            'preferred_countries' => json_encode(['Sri Lanka']),
            'preferred_cities' => json_encode(['Colombo', 'Kandy']),
        ]);

        $score = $this->matchingService->calculateLocationCompatibility($user1, $user2);

        $this->assertGreaterThan(0, $score);
    }

    /**
     * Test education compatibility
     */
    public function test_education_compatibility()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $profile1 = UserProfile::factory()->create([
            'user_id' => $user1->id,
            'education_level' => 'masters',
        ]);

        $profile2 = UserProfile::factory()->create([
            'user_id' => $user2->id,
            'education_level' => 'bachelors',
        ]);

        $preferences = UserPreference::factory()->create([
            'user_id' => $user1->id,
            'preferred_education_levels' => json_encode(['bachelors', 'masters', 'phd']),
        ]);

        $score = $this->matchingService->calculateEducationCompatibility($user1, $user2);

        $this->assertGreaterThan(0, $score);
    }

    /**
     * Test religion compatibility
     */
    public function test_religion_compatibility()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $profile1 = UserProfile::factory()->create([
            'user_id' => $user1->id,
            'religion' => 'buddhist',
        ]);

        $profile2 = UserProfile::factory()->create([
            'user_id' => $user2->id,
            'religion' => 'buddhist',
        ]);

        $preferences = UserPreference::factory()->create([
            'user_id' => $user1->id,
            'preferred_religions' => json_encode(['buddhist']),
        ]);

        $score = $this->matchingService->calculateReligionCompatibility($user1, $user2);

        $this->assertEquals(100, $score); // Perfect match
    }

    /**
     * Test horoscope compatibility
     */
    public function test_horoscope_compatibility()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $horoscope1 = Horoscope::factory()->create([
            'user_id' => $user1->id,
            'moon_sign' => 'aries',
            'nakshatra' => 'ashwini',
        ]);

        $horoscope2 = Horoscope::factory()->create([
            'user_id' => $user2->id,
            'moon_sign' => 'leo',
            'nakshatra' => 'magha',
        ]);

        $score = $this->matchingService->calculateHoroscopeCompatibility($user1, $user2);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test lifestyle compatibility
     */
    public function test_lifestyle_compatibility()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $profile1 = UserProfile::factory()->create([
            'user_id' => $user1->id,
            'smoking_habits' => 'never',
            'drinking_habits' => 'socially',
            'dietary_preferences' => 'vegetarian',
        ]);

        $profile2 = UserProfile::factory()->create([
            'user_id' => $user2->id,
            'smoking_habits' => 'never',
            'drinking_habits' => 'never',
            'dietary_preferences' => 'vegetarian',
        ]);

        $preferences = UserPreference::factory()->create([
            'user_id' => $user1->id,
            'preferred_smoking_habits' => json_encode(['never']),
            'preferred_drinking_habits' => json_encode(['never', 'socially']),
            'preferred_dietary_preferences' => json_encode(['vegetarian']),
        ]);

        $score = $this->matchingService->calculateLifestyleCompatibility($user1, $user2);

        $this->assertGreaterThan(0, $score);
    }

    /**
     * Test interest compatibility
     */
    public function test_interest_compatibility()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Mock interests
        $commonInterests = ['reading', 'travel', 'cooking'];
        $user1OnlyInterests = ['swimming'];
        $user2OnlyInterests = ['painting'];

        // Simulate common interests
        $score = $this->matchingService->calculateInterestCompatibility($user1, $user2);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test activity score calculation
     */
    public function test_activity_score_calculation()
    {
        $user = User::factory()->create([
            'last_active_at' => now()->subHours(2),
            'created_at' => now()->subDays(30),
        ]);

        $score = $this->matchingService->calculateActivityScore($user);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test generating daily matches for user
     */
    public function test_generate_daily_matches()
    {
        $user = User::factory()->create([
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        // Create profile and preferences
        UserProfile::factory()->create(['user_id' => $user->id]);
        UserPreference::factory()->create(['user_id' => $user->id]);

        // Create potential matches
        $potentialMatches = User::factory()->count(5)->create([
            'gender' => 'female',
            'status' => 'active',
        ]);

        foreach ($potentialMatches as $match) {
            UserProfile::factory()->create(['user_id' => $match->id]);
        }

        $matches = $this->matchingService->generateDailyMatches($user, 3);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $matches);
        $this->assertLessThanOrEqual(3, $matches->count());
        
        foreach ($matches as $match) {
            $this->assertArrayHasKey('user', $match);
            $this->assertArrayHasKey('compatibility_score', $match);
            $this->assertArrayHasKey('matching_factors', $match);
        }
    }

    /**
     * Test mutual match detection
     */
    public function test_mutual_match_detection()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create mutual likes
        UserMatch::factory()->create([
            'user_id' => $user1->id,
            'matched_user_id' => $user2->id,
            'user_action' => 'liked',
        ]);

        UserMatch::factory()->create([
            'user_id' => $user2->id,
            'matched_user_id' => $user1->id,
            'user_action' => 'liked',
        ]);

        $isMutual = $this->matchingService->checkMutualMatch($user1->id, $user2->id);

        $this->assertTrue($isMutual);
    }

    /**
     * Test one-sided match detection
     */
    public function test_one_sided_match_detection()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create one-sided like
        UserMatch::factory()->create([
            'user_id' => $user1->id,
            'matched_user_id' => $user2->id,
            'user_action' => 'liked',
        ]);

        $isMutual = $this->matchingService->checkMutualMatch($user1->id, $user2->id);

        $this->assertFalse($isMutual);
    }

    /**
     * Test match quality categorization
     */
    public function test_match_quality_categorization()
    {
        $scores = [95, 80, 65, 45, 20];
        $expectedQualities = ['excellent', 'very_good', 'good', 'fair', 'poor'];

        foreach ($scores as $index => $score) {
            $quality = $this->matchingService->getMatchQuality($score);
            $this->assertEquals($expectedQualities[$index], $quality);
        }
    }

    /**
     * Test filtering blocked users
     */
    public function test_filtering_blocked_users()
    {
        $user = User::factory()->create();
        $blockedUser = User::factory()->create();
        $normalUser = User::factory()->create();

        // Create block relationship
        UserMatch::factory()->create([
            'user_id' => $user->id,
            'matched_user_id' => $blockedUser->id,
            'user_action' => 'blocked',
        ]);

        $candidates = collect([$blockedUser, $normalUser]);
        $filtered = $this->matchingService->filterBlockedUsers($user, $candidates);

        $this->assertEquals(1, $filtered->count());
        $this->assertEquals($normalUser->id, $filtered->first()->id);
    }

    /**
     * Test preference deal breakers
     */
    public function test_preference_deal_breakers()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create([
            'date_of_birth' => '2005-01-01', // Too young
        ]);

        $preferences = UserPreference::factory()->create([
            'user_id' => $user->id,
            'min_age' => 25,
            'max_age' => 35,
            'deal_breakers' => json_encode(['age']),
        ]);

        $hasDealBreakers = $this->matchingService->checkDealBreakers($user, $targetUser);

        $this->assertTrue($hasDealBreakers);
    }

    /**
     * Test moon sign compatibility
     */
    public function test_moon_sign_compatibility()
    {
        // Test compatible moon signs
        $compatibleSigns = [
            ['aries', 'leo'],
            ['taurus', 'virgo'],
            ['gemini', 'libra'],
            ['cancer', 'scorpio'],
        ];

        foreach ($compatibleSigns as [$sign1, $sign2]) {
            $score = $this->matchingService->getMoonSignCompatibility($sign1, $sign2);
            $this->assertGreaterThan(70, $score); // Should be highly compatible
        }

        // Test incompatible moon signs
        $incompatibleSigns = [
            ['aries', 'cancer'],
            ['taurus', 'aquarius'],
        ];

        foreach ($incompatibleSigns as [$sign1, $sign2]) {
            $score = $this->matchingService->getMoonSignCompatibility($sign1, $sign2);
            $this->assertLessThan(50, $score); // Should be less compatible
        }
    }

    /**
     * Test premium user match boosting
     */
    public function test_premium_user_match_boosting()
    {
        $premiumUser = User::factory()->create(['is_premium' => true]);
        $regularUser = User::factory()->create(['is_premium' => false]);

        $baseScore = 75;
        $boostedScore = $this->matchingService->applyPremiumBoost($baseScore, $premiumUser);

        $this->assertGreaterThan($baseScore, $boostedScore);
        $this->assertLessThanOrEqual(100, $boostedScore);
    }

    /**
     * Test match score caching
     */
    public function test_match_score_caching()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UserProfile::factory()->create(['user_id' => $user1->id]);
        UserProfile::factory()->create(['user_id' => $user2->id]);
        UserPreference::factory()->create(['user_id' => $user1->id]);

        // Calculate score twice
        $score1 = $this->matchingService->calculateCompatibilityScore($user1, $user2);
        $score2 = $this->matchingService->calculateCompatibilityScore($user1, $user2);

        // Should return same cached result
        $this->assertEquals($score1, $score2);
    }

    /**
     * Test photo verification impact on matching
     */
    public function test_photo_verification_impact()
    {
        $verifiedUser = User::factory()->create(['verification_status' => 'verified']);
        $unverifiedUser = User::factory()->create(['verification_status' => 'pending']);

        $baseScore = 80;
        $verifiedScore = $this->matchingService->applyVerificationBoost($baseScore, $verifiedUser);
        $unverifiedScore = $this->matchingService->applyVerificationBoost($baseScore, $unverifiedUser);

        $this->assertGreaterThan($unverifiedScore, $verifiedScore);
    }

    /**
     * Test geographic distance calculation
     */
    public function test_geographic_distance_calculation()
    {
        // Colombo coordinates
        $lat1 = 6.9271;
        $lon1 = 79.8612;

        // Kandy coordinates
        $lat2 = 7.2906;
        $lon2 = 80.6337;

        $distance = $this->matchingService->calculateDistance($lat1, $lon1, $lat2, $lon2);

        $this->assertIsFloat($distance);
        $this->assertGreaterThan(0, $distance);
        $this->assertLessThan(200, $distance); // Should be less than 200km
    }
} 