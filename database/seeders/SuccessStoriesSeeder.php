<?php

namespace Database\Seeders;

use App\Models\SuccessStory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuccessStoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get test users from LiveProfilesSeeder
        $amara = User::where('email', 'amara.test@soulsync.com')->first();
        $kasun = User::where('email', 'kasun.test@soulsync.com')->first();
        $priya = User::where('email', 'priya.test@soulsync.com')->first();
        $nuwan = User::where('email', 'nuwan.test@soulsync.com')->first();
        $dilini = User::where('email', 'dilini.test@soulsync.com')->first();
        $testUser = User::where('email', 'test@soulsync.com')->first();
        $admin = User::where('email', 'admin@soulsync.com')->first();

        // Skip if required users don't exist
        if (!$amara || !$kasun) {
            $this->command->warn('Test users not found. Please run LiveProfilesSeeder first.');
            return;
        }

        $adminId = $admin?->id;

        // Story 1: Featured & Approved - Amara & Kasun
        SuccessStory::firstOrCreate(
            ['title' => 'From First Message to Forever'],
            [
                'couple_user1_id' => $amara->id,
                'couple_user2_id' => $kasun->id,
                'description' => "When I first joined SoulSync, I wasn't sure if online matrimony was right for me. But the moment Kasun sent me that first message, something felt different. His profile caught my eye - not just because of his achievements, but the genuine warmth in his words about family and values.\n\nWe talked for hours every day, sharing dreams, fears, and hopes for the future. When we finally met in person at Galle Face Green, it felt like meeting an old friend. The chemistry was undeniable.\n\nOur families met two months later, and everyone felt the same connection we did. Now, as we plan our wedding, we're grateful every day for taking that leap of faith on SoulSync.",
                'how_they_met' => "Kasun sent a thoughtful message complimenting my career achievements and asking about my love for traditional dancing. We connected instantly over our shared values and Sri Lankan heritage.",
                'story_location' => 'Colombo, Sri Lanka',
                'marriage_date' => '2025-10-15',
                'status' => SuccessStory::STATUS_APPROVED,
                'featured' => true,
                'featured_at' => now(),
                'approved_by' => $adminId,
                'approved_at' => now()->subDays(30),
                'view_count' => 342,
                'share_count' => 28,
            ]
        );

        // Story 2: Approved (Not Featured) - Priya & Nuwan
        if ($priya && $nuwan) {
            SuccessStory::firstOrCreate(
                ['title' => 'A Match Made in Heaven'],
                [
                    'couple_user1_id' => $priya->id,
                    'couple_user2_id' => $nuwan->id,
                    'description' => "My parents were skeptical about online matrimony, but I convinced them to let me try SoulSync. What drew me to Nuwan was his honest profile - no exaggerations, just a sincere man looking for a life partner.\n\nOur first video call lasted four hours! We discovered we both loved hiking, had similar career goals, and most importantly, shared the same vision for family life. His patience, kindness, and sense of humor won me over completely.\n\nAfter six months of getting to know each other and multiple family visits, we got engaged in a beautiful ceremony in Kandy. Our wedding is planned for next year, and we couldn't be happier.",
                    'how_they_met' => "We matched on SoulSync based on our compatible profiles. Nuwan's genuine approach and our shared interest in outdoor activities sparked an immediate connection.",
                    'story_location' => 'Kandy, Sri Lanka',
                    'marriage_date' => '2025-08-20',
                    'status' => SuccessStory::STATUS_APPROVED,
                    'featured' => false,
                    'approved_by' => $adminId,
                    'approved_at' => now()->subDays(15),
                    'view_count' => 156,
                    'share_count' => 12,
                ]
            );
        }

        // Story 3: Pending Approval - Dilini (no partner tagged)
        if ($dilini) {
            SuccessStory::firstOrCreate(
                ['title' => 'Our Beautiful Journey Begins'],
                [
                    'couple_user1_id' => $dilini->id,
                    'couple_user2_id' => null,
                    'description' => "I never thought I'd find love online, but SoulSync proved me wrong. After a year of searching, I finally found someone who understands me completely.\n\nWe connected over our shared love for music and traditional values. Every conversation brought us closer, and when we finally met, it was magical. Our families approved instantly, seeing how happy we make each other.\n\nWe're now engaged and planning our wedding for next spring. Thank you, SoulSync, for bringing us together!",
                    'how_they_met' => "Through SoulSync's matching system - our profiles aligned perfectly in terms of values, interests, and life goals.",
                    'story_location' => 'Negombo, Sri Lanka',
                    'marriage_date' => null,
                    'status' => SuccessStory::STATUS_PENDING,
                    'featured' => false,
                    'view_count' => 0,
                    'share_count' => 0,
                ]
            );
        }

        // Story 4: Featured & Approved - Test User & Amara (different pairing)
        if ($testUser) {
            SuccessStory::firstOrCreate(
                ['title' => 'Soulmates Found on SoulSync'],
                [
                    'couple_user1_id' => $testUser->id,
                    'couple_user2_id' => $priya?->id,
                    'description' => "SoulSync changed my life in ways I never imagined. After years of unsuccessful attempts at finding the right partner, I decided to give online matrimony one last try.\n\nFrom the very first conversation, there was something special. We talked about everything - our careers, our dreams, our fears. The platform's verification system gave my family confidence that this was legitimate.\n\nThree months of daily conversations led to our first meeting. Six months later, I proposed at sunset on Galle Fort. Now we're married and building our dream life together in our new home.\n\nTo anyone hesitant about online matrimony - take the chance. Your soulmate might be just a message away.",
                    'how_they_met' => "The SoulSync algorithm matched us based on our detailed profiles. Our first conversation about travel and food turned into a three-hour video call!",
                    'story_location' => 'Galle, Sri Lanka',
                    'marriage_date' => '2025-12-01',
                    'status' => SuccessStory::STATUS_APPROVED,
                    'featured' => true,
                    'featured_at' => now()->subDays(7),
                    'approved_by' => $adminId,
                    'approved_at' => now()->subDays(20),
                    'view_count' => 489,
                    'share_count' => 45,
                ]
            );
        }

        $this->command->info('Success stories seeded successfully!');
        $this->command->info('- Total stories: ' . SuccessStory::count());
        $this->command->info('- Approved: ' . SuccessStory::approved()->count());
        $this->command->info('- Featured: ' . SuccessStory::featured()->count());
        $this->command->info('- Pending: ' . SuccessStory::pending()->count());
    }
}
