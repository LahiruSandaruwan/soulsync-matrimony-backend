<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Notification settings (configure in production)
        // Horizon::routeMailNotificationsTo(config('mail.from.address'));

        // Slack notifications for long wait times
        if (config('logging.channels.slack.url')) {
            Horizon::routeSlackNotificationsTo(
                config('logging.channels.slack.url'),
                '#horizon-alerts'
            );
        }

        // Night mode by default
        Horizon::night();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // In local environment, allow access
            if (app()->environment('local')) {
                return true;
            }

            // Must be authenticated
            if (!$user) {
                return false;
            }

            // Check if user has admin role (using Spatie Permission)
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole(['super-admin', 'admin']);
            }

            // Fallback: check email list for authorized users
            $authorizedEmails = array_filter([
                config('horizon.authorized_emails', []),
                env('HORIZON_ADMIN_EMAIL'),
            ]);

            return in_array($user->email, $authorizedEmails);
        });
    }
}
