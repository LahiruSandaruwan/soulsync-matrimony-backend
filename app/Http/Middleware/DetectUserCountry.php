<?php

namespace App\Http\Middleware;

use App\Services\GeolocationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectUserCountry
{
    public function __construct(
        private GeolocationService $geolocationService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get IP from various sources (handles proxies)
        $ip = $request->header('X-Forwarded-For')
            ?? $request->header('X-Real-IP')
            ?? $request->ip();

        // If X-Forwarded-For contains multiple IPs, get the first one
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // Detect country from IP
        $location = $this->geolocationService->detectCountry($ip);

        // If user is authenticated and has a country set, prefer that
        if ($request->user() && $request->user()->country_code) {
            $userCountry = $request->user()->country_code;
            $location['country_code'] = $userCountry;
            $location['currency'] = $this->geolocationService->getCurrencyForCountry($userCountry);
            $location['currency_symbol'] = $this->geolocationService->getCurrencySymbol($location['currency']);
            $location['source'] = 'user_profile';
        }

        // Store location data in request for later use
        $request->attributes->set('user_location', $location);
        $request->attributes->set('user_country', $location['country_code']);
        $request->attributes->set('user_currency', $location['currency']);

        return $next($request);
    }
}
