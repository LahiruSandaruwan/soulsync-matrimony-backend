<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Redirect HTTP requests to HTTPS in production.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce in production
        if (config('app.env') !== 'production') {
            return $next($request);
        }

        // Skip if already HTTPS
        if ($request->secure()) {
            return $next($request);
        }

        // Skip for health check endpoints (load balancer checks)
        if ($request->is('health', 'api/v1/health')) {
            return $next($request);
        }

        // Check for proxy headers (common in load balancer setups)
        if ($request->header('X-Forwarded-Proto') === 'https') {
            return $next($request);
        }

        // Redirect to HTTPS
        return redirect()->secure($request->getRequestUri(), 301);
    }
}
