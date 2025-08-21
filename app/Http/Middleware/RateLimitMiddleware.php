<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $key = null, int $maxAttempts = 60, int $decayMinutes = 1): ResponseCode
    {
        // Generate rate limit key
        $identifier = $this->resolveRequestSignature($request, $key);
        
        // Check if rate limit exceeded
        if (RateLimiter::tooManyAttempts($identifier, $maxAttempts)) {
            return $this->buildRateLimitResponse($identifier, $maxAttempts);
        }

        // Increment the rate limiter
        RateLimiter::hit($identifier, $decayMinutes * 60);

        // Execute the request
        $response = $next($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders(
            $response,
            $maxAttempts,
            RateLimiter::remaining($identifier, $maxAttempts),
            RateLimiter::availableAt($identifier)
        );
    }

    /**
     * Resolve the rate limit key for the request.
     */
    protected function resolveRequestSignature(Request $request, ?string $key): string
    {
        if ($key) {
            return $key . '|' . $request->ip();
        }

        // Default: use route name + user ID/IP
        $routeKey = $request->route() ? $request->route()->getName() : $request->path();
        $userKey = $request->user() ? $request->user()->id : $request->ip();

        return sprintf('%s|%s', $routeKey, $userKey);
    }

    /**
     * Build the rate limit exceeded response.
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts): ResponseCode
    {
        $retryAfter = RateLimiter::availableAt($key);
        
        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'error' => 'Rate limit exceeded',
            'retry_after' => $retryAfter - now()->timestamp,
        ], 429, [
            'Retry-After' => $retryAfter - now()->timestamp,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Add rate limiting headers to the response.
     */
    protected function addRateLimitHeaders($response, int $maxAttempts, int $remaining, int $retryAfter): ResponseCode
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
        ];

        if ($remaining === 0) {
            $headers['Retry-After'] = $retryAfter - now()->timestamp;
        }

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }
}