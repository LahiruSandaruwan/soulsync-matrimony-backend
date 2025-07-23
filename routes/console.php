<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled Tasks
Schedule::command('matches:generate-daily')
    ->dailyAt('06:00')
    ->timezone('UTC')
    ->description('Generate daily matches for all users')
    ->emailOutputOnFailure(config('mail.admin_email', 'admin@soulsync.com'));

Schedule::command('rates:update')
    ->hourly()
    ->description('Update exchange rates every hour')
    ->runInBackground()
    ->withoutOverlapping(5); // Prevent overlapping runs with 5 minute timeout

Schedule::command('rates:update --currency=LKR')
    ->everyFifteenMinutes()
    ->description('Update LKR rates more frequently')
    ->runInBackground();

// Queue worker monitoring (restart if stopped)
Schedule::command('queue:restart')
    ->dailyAt('02:00')
    ->description('Restart queue workers daily');

// Clean up old exchange rates
Schedule::call(function () {
    \App\Models\ExchangeRate::where('expires_at', '<', now())
        ->where('is_active', true)
        ->update(['is_active' => false]);
})->hourly()->description('Deactivate expired exchange rates');

// Clean up old notifications
Schedule::call(function () {
    \App\Models\Notification::where('expires_at', '<', now())
        ->whereNull('deleted_at')
        ->delete();
})->daily()->description('Clean up expired notifications');

// Update user last activity status
Schedule::call(function () {
    \App\Models\User::where('last_active_at', '<', now()->subDays(30))
        ->where('status', 'active')
        ->update(['status' => 'inactive']);
})->weekly()->description('Update inactive user status');

// Generate weekly analytics
Schedule::call(function () {
    // This could dispatch a job to generate weekly analytics
    \Illuminate\Support\Facades\Log::info('Weekly analytics generation triggered');
})->weeklyOn(1, '08:00') // Every Monday at 8 AM
  ->description('Generate weekly analytics');
