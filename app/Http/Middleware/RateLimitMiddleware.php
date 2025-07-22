<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $key = 'api', int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        // Get the rate limiting key
        $rateLimitKey = $this->getRateLimitKey($request, $key);

        // Check if the request should be rate limited
        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return $this->buildRateLimitResponse($rateLimitKey, $maxAttempts, $decayMinutes);
        }

        // Increment the rate limiter
        RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

        // Continue with the request
        $response = $next($request);

        // Add rate limit headers to the response
        return $this->addRateLimitHeaders(
            $response,
            $maxAttempts,
            RateLimiter::retriesLeft($rateLimitKey, $maxAttempts),
            RateLimiter::availableIn($rateLimitKey)
        );
    }

    /**
     * Generate the rate limiting key for the request
     */
    protected function getRateLimitKey(Request $request, string $key): string
    {
        $user = $request->user();
        
        // If user is authenticated, use user ID
        if ($user) {
            return $key . ':user:' . $user->id;
        }
        
        // For unauthenticated requests, use IP address
        return $key . ':ip:' . $request->ip();
    }

    /**
     * Build the rate limit exceeded response
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts, int $decayMinutes): Response
    {
        $retryAfter = RateLimiter::availableIn($key);
        
        $message = "Rate limit exceeded. Too many requests. Please try again in {$retryAfter} seconds.";
        
        // Check if this is a premium user for better messaging
        if (Str::contains($key, 'user:')) {
            $userId = Str::afterLast($key, 'user:');
            $user = \App\Models\User::find($userId);
            
            if ($user && $user->is_premium_active) {
                $message = "Rate limit exceeded. As a premium user, you have higher limits, but they have been reached. Please try again in {$retryAfter} seconds.";
            }
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => 'Too Many Requests',
            'retry_after' => $retryAfter,
            'limit_info' => [
                'max_attempts' => $maxAttempts,
                'window_minutes' => $decayMinutes,
                'current_attempts' => $maxAttempts,
                'remaining_attempts' => 0,
            ]
        ], 429)->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            'Retry-After' => $retryAfter,
        ]);
    }

    /**
     * Add rate limit headers to the response
     */
    protected function addRateLimitHeaders(Response $response, int $maxAttempts, int $remainingAttempts, int $retryAfter): Response
    {
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remainingAttempts));
        
        if ($remainingAttempts <= 0) {
            $response->headers->set('X-RateLimit-Reset', now()->addSeconds($retryAfter)->timestamp);
        }

        return $response;
    }

    /**
     * Get rate limit configuration for different endpoints
     */
    public static function getEndpointLimits(): array
    {
        return [
            // General API limits
            'api' => ['max' => 100, 'minutes' => 1],
            'api_premium' => ['max' => 200, 'minutes' => 1],
            
            // Search and browse limits
            'search' => ['max' => 30, 'minutes' => 1],
            'search_premium' => ['max' => 60, 'minutes' => 1],
            'browse' => ['max' => 50, 'minutes' => 1],
            
            // Messaging limits
            'messages' => ['max' => 20, 'minutes' => 1],
            'messages_premium' => ['max' => 50, 'minutes' => 1],
            
            // Photo upload limits
            'photo_upload' => ['max' => 5, 'minutes' => 5],
            'photo_upload_premium' => ['max' => 10, 'minutes' => 5],
            
            // Match actions
            'matches' => ['max' => 50, 'minutes' => 5],
            'matches_premium' => ['max' => 100, 'minutes' => 5],
            'super_likes' => ['max' => 5, 'minutes' => 60 * 24], // 5 per day
            'super_likes_premium' => ['max' => 20, 'minutes' => 60 * 24], // 20 per day
            
            // Profile updates
            'profile_update' => ['max' => 10, 'minutes' => 5],
            
            // Subscription related
            'subscription' => ['max' => 5, 'minutes' => 5],
            
            // Report and moderation
            'reports' => ['max' => 5, 'minutes' => 60], // 5 reports per hour
            
            // Admin actions
            'admin' => ['max' => 200, 'minutes' => 1],
        ];
    }

    /**
     * Create middleware with dynamic configuration
     */
    public static function withLimits(string $key, ?int $maxAttempts = null, ?int $decayMinutes = null): string
    {
        $limits = self::getEndpointLimits();
        
        // Get default limits for the key
        $defaultLimits = $limits[$key] ?? $limits['api'];
        
        $max = $maxAttempts ?? $defaultLimits['max'];
        $minutes = $decayMinutes ?? $defaultLimits['minutes'];
        
        return "rate.limit:{$key},{$max},{$minutes}";
    }

    /**
     * Apply premium rate limits if user is premium
     */
    protected function applyPremiumLimits(Request $request, string $key, int &$maxAttempts, int &$decayMinutes): void
    {
        $user = $request->user();
        
        if ($user && $user->is_premium_active) {
            $limits = self::getEndpointLimits();
            $premiumKey = $key . '_premium';
            
            if (isset($limits[$premiumKey])) {
                $maxAttempts = $limits[$premiumKey]['max'];
                $decayMinutes = $limits[$premiumKey]['minutes'];
            } else {
                // Default premium boost: 2x the limits
                $maxAttempts = $maxAttempts * 2;
            }
        }
    }

    /**
     * Handle middleware with premium user consideration
     */
    public function handleWithPremium(Request $request, Closure $next, string $key = 'api', int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        // Apply premium limits if applicable
        $this->applyPremiumLimits($request, $key, $maxAttempts, $decayMinutes);
        
        return $this->handle($request, $next, $key, $maxAttempts, $decayMinutes);
    }

    /**
     * Clear rate limit for a specific key (useful for testing or admin actions)
     */
    public static function clearRateLimit(string $key): void
    {
        RateLimiter::clear($key);
    }

    /**
     * Get current rate limit status for a key
     */
    public static function getRateLimitStatus(string $key): array
    {
        return [
            'attempts' => RateLimiter::attempts($key),
            'retries_left' => RateLimiter::retriesLeft($key, 60),
            'available_in' => RateLimiter::availableIn($key),
        ];
    }

    /**
     * Check if rate limit is exceeded for a key
     */
    public static function isRateLimited(string $key, int $maxAttempts = 60): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }
} 