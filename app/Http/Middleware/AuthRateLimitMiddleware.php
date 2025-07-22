<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $action = 'login'): Response
    {
        // Get rate limiting configuration for the action
        $config = $this->getAuthRateLimitConfig($action);
        
        // Generate rate limiting keys
        $keys = $this->generateRateLimitKeys($request, $action);
        
        // Check rate limits
        $rateLimitResult = $this->checkRateLimits($keys, $config);
        
        if ($rateLimitResult['blocked']) {
            // Log suspicious activity
            $this->logSuspiciousActivity($request, $action, $rateLimitResult);
            
            return $this->buildRateLimitResponse($rateLimitResult, $config);
        }
        
        // Increment rate limit counters
        $this->incrementRateLimitCounters($keys, $config);
        
        // Continue with the request
        $response = $next($request);
        
        // Log failed authentication attempts
        if ($this->isFailedAuthResponse($response, $action)) {
            $this->logFailedAttempt($request, $action);
            
            // Increase penalties for failed attempts
            $this->applyFailurePenalties($keys, $config);
        } else if ($this->isSuccessfulAuthResponse($response, $action)) {
            // Clear some rate limits on successful auth
            $this->clearSuccessfulAuthLimits($keys, $action);
        }
        
        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $keys, $config);
    }

    /**
     * Get rate limiting configuration for different auth actions
     */
    protected function getAuthRateLimitConfig(string $action): array
    {
        $configs = [
            'login' => [
                'ip_max_attempts' => 10,
                'ip_window_minutes' => 15,
                'email_max_attempts' => 5,
                'email_window_minutes' => 15,
                'global_max_attempts' => 100,
                'global_window_minutes' => 5,
                'lockout_duration' => 30, // minutes
            ],
            'register' => [
                'ip_max_attempts' => 5,
                'ip_window_minutes' => 60,
                'email_max_attempts' => 3,
                'email_window_minutes' => 60,
                'global_max_attempts' => 50,
                'global_window_minutes' => 10,
                'lockout_duration' => 60,
            ],
            'forgot-password' => [
                'ip_max_attempts' => 5,
                'ip_window_minutes' => 60,
                'email_max_attempts' => 3,
                'email_window_minutes' => 60,
                'global_max_attempts' => 20,
                'global_window_minutes' => 10,
                'lockout_duration' => 120,
            ],
            'reset-password' => [
                'ip_max_attempts' => 5,
                'ip_window_minutes' => 30,
                'email_max_attempts' => 3,
                'email_window_minutes' => 30,
                'global_max_attempts' => 20,
                'global_window_minutes' => 10,
                'lockout_duration' => 60,
            ],
            'verify-email' => [
                'ip_max_attempts' => 5,
                'ip_window_minutes' => 60,
                'email_max_attempts' => 3,
                'email_window_minutes' => 60,
                'global_max_attempts' => 30,
                'global_window_minutes' => 10,
                'lockout_duration' => 30,
            ],
            'change-password' => [
                'ip_max_attempts' => 5,
                'ip_window_minutes' => 30,
                'email_max_attempts' => 3,
                'email_window_minutes' => 30,
                'global_max_attempts' => 20,
                'global_window_minutes' => 10,
                'lockout_duration' => 30,
            ],
        ];

        return $configs[$action] ?? $configs['login'];
    }

    /**
     * Generate rate limiting keys for different scopes
     */
    protected function generateRateLimitKeys(Request $request, string $action): array
    {
        $ip = $request->ip();
        $email = $request->input('email', '');
        $userAgent = $request->userAgent();
        
        // Create a device fingerprint
        $deviceFingerprint = md5($ip . $userAgent);
        
        return [
            'ip' => "auth:{$action}:ip:{$ip}",
            'email' => $email ? "auth:{$action}:email:" . Str::lower($email) : null,
            'device' => "auth:{$action}:device:{$deviceFingerprint}",
            'global' => "auth:{$action}:global",
            'suspicious_ip' => "auth:suspicious:ip:{$ip}",
            'suspicious_email' => $email ? "auth:suspicious:email:" . Str::lower($email) : null,
        ];
    }

    /**
     * Check all rate limits
     */
    protected function checkRateLimits(array $keys, array $config): array
    {
        $result = [
            'blocked' => false,
            'reason' => '',
            'retry_after' => 0,
            'violated_limits' => [],
        ];

        // Check IP rate limit
        if (RateLimiter::tooManyAttempts($keys['ip'], $config['ip_max_attempts'])) {
            $result['blocked'] = true;
            $result['reason'] = 'IP rate limit exceeded';
            $result['retry_after'] = max($result['retry_after'], RateLimiter::availableIn($keys['ip']));
            $result['violated_limits'][] = 'ip';
        }

        // Check email rate limit
        if ($keys['email'] && RateLimiter::tooManyAttempts($keys['email'], $config['email_max_attempts'])) {
            $result['blocked'] = true;
            $result['reason'] = 'Email rate limit exceeded';
            $result['retry_after'] = max($result['retry_after'], RateLimiter::availableIn($keys['email']));
            $result['violated_limits'][] = 'email';
        }

        // Check global rate limit
        if (RateLimiter::tooManyAttempts($keys['global'], $config['global_max_attempts'])) {
            $result['blocked'] = true;
            $result['reason'] = 'Global rate limit exceeded';
            $result['retry_after'] = max($result['retry_after'], RateLimiter::availableIn($keys['global']));
            $result['violated_limits'][] = 'global';
        }

        // Check if IP is flagged as suspicious
        if (Cache::has($keys['suspicious_ip'])) {
            $result['blocked'] = true;
            $result['reason'] = 'IP temporarily blocked due to suspicious activity';
            $result['retry_after'] = max($result['retry_after'], Cache::get($keys['suspicious_ip'] . ':ttl', 3600));
            $result['violated_limits'][] = 'suspicious_ip';
        }

        // Check if email is flagged as suspicious
        if ($keys['suspicious_email'] && Cache::has($keys['suspicious_email'])) {
            $result['blocked'] = true;
            $result['reason'] = 'Email temporarily blocked due to suspicious activity';
            $result['retry_after'] = max($result['retry_after'], Cache::get($keys['suspicious_email'] . ':ttl', 3600));
            $result['violated_limits'][] = 'suspicious_email';
        }

        return $result;
    }

    /**
     * Increment rate limit counters
     */
    protected function incrementRateLimitCounters(array $keys, array $config): void
    {
        // Increment IP counter
        RateLimiter::hit($keys['ip'], $config['ip_window_minutes'] * 60);

        // Increment email counter
        if ($keys['email']) {
            RateLimiter::hit($keys['email'], $config['email_window_minutes'] * 60);
        }

        // Increment device counter
        RateLimiter::hit($keys['device'], $config['ip_window_minutes'] * 60);

        // Increment global counter
        RateLimiter::hit($keys['global'], $config['global_window_minutes'] * 60);
    }

    /**
     * Check if response indicates failed authentication
     */
    protected function isFailedAuthResponse(Response $response, string $action): bool
    {
        if ($response->getStatusCode() !== 422 && $response->getStatusCode() !== 401) {
            return false;
        }

        $content = json_decode($response->getContent(), true);
        
        return isset($content['success']) && $content['success'] === false;
    }

    /**
     * Check if response indicates successful authentication
     */
    protected function isSuccessfulAuthResponse(Response $response, string $action): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $content = json_decode($response->getContent(), true);
        
        return isset($content['success']) && $content['success'] === true;
    }

    /**
     * Apply penalties for failed authentication attempts
     */
    protected function applyFailurePenalties(array $keys, array $config): void
    {
        // Increase rate limit counters for failures
        RateLimiter::hit($keys['ip'] . ':failures', $config['ip_window_minutes'] * 60);
        
        if ($keys['email']) {
            RateLimiter::hit($keys['email'] . ':failures', $config['email_window_minutes'] * 60);
        }

        // Check if we should flag as suspicious
        $ipFailures = RateLimiter::attempts($keys['ip'] . ':failures');
        $emailFailures = $keys['email'] ? RateLimiter::attempts($keys['email'] . ':failures') : 0;

        // Flag IP as suspicious after multiple failures
        if ($ipFailures >= 5) {
            $duration = min($config['lockout_duration'] * $ipFailures, 1440); // Max 24 hours
            Cache::put($keys['suspicious_ip'], true, $duration * 60);
            Cache::put($keys['suspicious_ip'] . ':ttl', $duration * 60, $duration * 60);
        }

        // Flag email as suspicious after multiple failures
        if ($emailFailures >= 3 && $keys['email']) {
            $duration = min($config['lockout_duration'] * $emailFailures, 720); // Max 12 hours
            Cache::put($keys['suspicious_email'], true, $duration * 60);
            Cache::put($keys['suspicious_email'] . ':ttl', $duration * 60, $duration * 60);
        }
    }

    /**
     * Clear some rate limits on successful authentication
     */
    protected function clearSuccessfulAuthLimits(array $keys, string $action): void
    {
        // Clear failure counters on successful login
        if ($action === 'login') {
            RateLimiter::clear($keys['ip'] . ':failures');
            if ($keys['email']) {
                RateLimiter::clear($keys['email'] . ':failures');
            }
        }
    }

    /**
     * Log suspicious authentication activity
     */
    protected function logSuspiciousActivity(Request $request, string $action, array $rateLimitResult): void
    {
        Log::warning('Authentication rate limit exceeded', [
            'action' => $action,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'email' => $request->input('email'),
            'reason' => $rateLimitResult['reason'],
            'violated_limits' => $rateLimitResult['violated_limits'],
            'retry_after' => $rateLimitResult['retry_after'],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log failed authentication attempts
     */
    protected function logFailedAttempt(Request $request, string $action): void
    {
        Log::info('Failed authentication attempt', [
            'action' => $action,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'email' => $request->input('email'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildRateLimitResponse(array $rateLimitResult, array $config): Response
    {
        $retryAfter = $rateLimitResult['retry_after'];
        
        $message = match ($rateLimitResult['reason']) {
            'IP rate limit exceeded' => "Too many authentication attempts from your IP address. Please try again in " . gmdate('H:i:s', $retryAfter) . ".",
            'Email rate limit exceeded' => "Too many authentication attempts for this email address. Please try again later.",
            'Global rate limit exceeded' => "System is experiencing high authentication traffic. Please try again shortly.",
            'IP temporarily blocked due to suspicious activity' => "Your IP address has been temporarily blocked due to suspicious activity. Please try again later.",
            'Email temporarily blocked due to suspicious activity' => "This email address has been temporarily blocked due to suspicious activity. Please contact support if this persists.",
            default => "Rate limit exceeded. Please try again later."
        };

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => 'Rate Limit Exceeded',
            'retry_after' => $retryAfter,
            'security_notice' => 'Multiple failed attempts detected. For security, access has been temporarily limited.',
            'support_contact' => 'If you believe this is an error, please contact support.',
        ], 429)->withHeaders([
            'X-RateLimit-Limit' => $config['ip_max_attempts'],
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            'Retry-After' => $retryAfter,
        ]);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, array $keys, array $config): Response
    {
        $remaining = max(0, $config['ip_max_attempts'] - RateLimiter::attempts($keys['ip']));
        
        $response->headers->set('X-Auth-RateLimit-Limit', $config['ip_max_attempts']);
        $response->headers->set('X-Auth-RateLimit-Remaining', $remaining);
        
        if ($remaining <= 0) {
            $response->headers->set('X-Auth-RateLimit-Reset', now()->addMinutes($config['ip_window_minutes'])->timestamp);
        }

        return $response;
    }

    /**
     * Clear rate limits for a specific identifier (admin function)
     */
    public static function clearAuthRateLimit(string $identifier, string $type = 'ip'): void
    {
        $actions = ['login', 'register', 'forgot-password', 'reset-password', 'verify-email', 'change-password'];
        
        foreach ($actions as $action) {
            $key = "auth:{$action}:{$type}:{$identifier}";
            RateLimiter::clear($key);
            RateLimiter::clear($key . ':failures');
            
            if ($type === 'ip') {
                Cache::forget("auth:suspicious:ip:{$identifier}");
                Cache::forget("auth:suspicious:ip:{$identifier}:ttl");
            } elseif ($type === 'email') {
                Cache::forget("auth:suspicious:email:{$identifier}");
                Cache::forget("auth:suspicious:email:{$identifier}:ttl");
            }
        }
    }

    /**
     * Get rate limit status for debugging
     */
    public static function getAuthRateStatus(string $identifier, string $type = 'ip'): array
    {
        $actions = ['login', 'register', 'forgot-password', 'reset-password'];
        $status = [];
        
        foreach ($actions as $action) {
            $key = "auth:{$action}:{$type}:{$identifier}";
            $status[$action] = [
                'attempts' => RateLimiter::attempts($key),
                'failures' => RateLimiter::attempts($key . ':failures'),
                'available_in' => RateLimiter::availableIn($key),
            ];
        }
        
        return $status;
    }
} 