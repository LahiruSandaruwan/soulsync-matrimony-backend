<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyMatches;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDailyMatchesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'matches:generate-daily 
                            {--user-id= : Generate matches for specific user ID}
                            {--limit=10 : Number of matches to generate per user}
                            {--chunk-size=100 : Process users in chunks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily matches for all eligible users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily match generation...');
        
        $userIdOption = $this->option('user-id');
        $limit = (int) $this->option('limit');
        $chunkSize = (int) $this->option('chunk-size');

        try {
            if ($userIdOption) {
                $this->generateForSpecificUser($userIdOption, $limit);
            } else {
                $this->generateForAllUsers($chunkSize);
            }

        } catch (\Exception $e) {
            $this->error('Failed to generate daily matches: ' . $e->getMessage());
            Log::error('Daily match generation command failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Generate matches for a specific user.
     */
    private function generateForSpecificUser(int $userId, int $limit): void
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return;
        }

        $this->info("Generating {$limit} matches for user: {$user->first_name} {$user->last_name} (ID: {$userId})");

        GenerateDailyMatches::dispatch($user);

        $this->info('Match generation job dispatched successfully.');
    }

    /**
     * Generate matches for all eligible users.
     */
    private function generateForAllUsers(int $chunkSize): void
    {
        $this->info('Dispatching daily match generation job for all users...');

        // Dispatch job to process all users
        GenerateDailyMatches::dispatch(null, true);

        $this->info('Daily match generation job dispatched successfully for all users.');
        $this->comment('The job will process users in the background queue.');
        $this->comment('Monitor queue with: php artisan queue:work');
    }
}
