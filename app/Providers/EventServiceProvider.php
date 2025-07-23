<?php

namespace App\Providers;

use App\Events\MatchFound;
use App\Events\MessageSent;
use App\Events\UserOnline;
use App\Listeners\SendMatchNotificationListener;
use App\Listeners\SendMessageNotificationListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Match Events
        MatchFound::class => [
            SendMatchNotificationListener::class,
        ],

        // Message Events
        MessageSent::class => [
            SendMessageNotificationListener::class,
        ],

        // User Events
        UserOnline::class => [
            // Could add listeners for user online status updates
        ],

        // Laravel Events
        'Illuminate\Auth\Events\Login' => [
            'App\Listeners\UpdateUserLastLogin',
        ],

        'Illuminate\Auth\Events\Logout' => [
            'App\Listeners\UpdateUserLastActivity',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
