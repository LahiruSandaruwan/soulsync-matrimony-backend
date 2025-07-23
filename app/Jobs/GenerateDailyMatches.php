<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GenerateDailyMatches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 300; // 5 minutes
    public $backoff = [300, 900]; // Retry after 5min, 15min

    protected ?User $user;
    protected bool $forAllUsers;

    /**
     * Create a new job instance.
     */
    public function __construct(?User $user = null, bool $forAllUsers = false)
    {
        $this->user = $user;
        $this->forAllUsers = $forAllUsers;
        $this->onQueue('matching');
    }

    /**
     * Execute the job.
     */
    public function handle(MatchingService $matchingService): void
    {
        try {
            if ($this->forAllUsers) {
                $this->generateMatchesForAllUsers($matchingService);
            } elseif ($this->user) {
                $this->generateMatchesForUser($this->user, $matchingService);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate daily matches', [
                'user_id' => $this->user?->id,
                'for_all_users' => $this->forAllUsers,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate matches for all eligible users.
     */
    private function generateMatchesForAllUsers(MatchingService $matchingService): void
    {
        $batchSize = 100;
        $processedCount = 0;
        $failedCount = 0;

        Log::info('Starting daily match generation for all users');

        // Get eligible users for match generation
        User::query()
            ->where('status', 'active')
            ->where('profile_status', 'approved')
            ->whereHas('profile', function ($query) {
                $query->where('profile_completion_percentage', '>=', 50);
            })
            ->chunk($batchSize, function ($users) use ($matchingService, &$processedCount, &$failedCount) {
                foreach ($users as $user) {
                    try {
                        // Check if matches already generated today
                        $cacheKey = "daily_matches_{$user->id}_" . now()->format('Y-m-d');
                        
                        if (!Cache::has($cacheKey)) {
                            $this->generateMatchesForUser($user, $matchingService);
                            $processedCount++;
                        }

                        // Small delay to prevent overwhelming the system
                        usleep(100000); // 100ms delay

                    } catch (\Exception $e) {
                        $failedCount++;
                        Log::warning('Failed to generate matches for user', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

        Log::info('Daily match generation completed', [
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'total_time' => $this->getExecutionTime()
        ]);
    }

    /**
     * Generate matches for a specific user.
     */
    private function generateMatchesForUser(User $user, MatchingService $matchingService): void
    {
        $startTime = microtime(true);

        // Determine match limit based on subscription
        $matchLimit = $user->is_premium_active ? 
            config('app.premium_daily_matches', 50) : 
            config('app.free_daily_matches', 5);

        try {
            // Generate daily matches
            $matches = $matchingService->generateDailyMatches($user, $matchLimit);

            Log::info('Daily matches generated successfully', [
                'user_id' => $user->id,
                'matches_count' => $matches->count(),
                'is_premium' => $user->is_premium_active,
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);

            // Dispatch notification jobs for new matches
            if ($matches->isNotEmpty()) {
                foreach ($matches->take(3) as $match) { // Notify about top 3 matches
                    SendMatchNotification::dispatch($user, $match)
                        ->delay(now()->addMinutes(rand(5, 30))); // Stagger notifications
                }
            }

            // Update user's last match generation timestamp
            $user->update(['last_matches_generated_at' => now()]);

        } catch (\Exception $e) {
            Log::error('Failed to generate matches for specific user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
            
            throw $e;
        }
    }

    /**
     * Get execution time for performance monitoring.
     */
    private function getExecutionTime(): string
    {
        return round((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms';
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateDailyMatches job failed permanently', [
            'user_id' => $this->user?->id,
            'for_all_users' => $this->forAllUsers,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could send admin notification about matching system failure
        if ($this->forAllUsers) {
            // This is critical - send admin alert
            Log::critical('Daily match generation failed for all users', [
                'error' => $exception->getMessage(),
                'job_attempts' => $this->attempts()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = ['matching', 'daily-matches'];
        
        if ($this->user) {
            $tags[] = "user:{$this->user->id}";
        }
        
        if ($this->forAllUsers) {
            $tags[] = 'bulk-generation';
        }
        
        return $tags;
    }
}
