<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PremiumAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via Sanctum
        if (!Auth::guard('sanctum')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::guard('sanctum')->user();

        // Check if user has active subscription
        if (!$user->hasActiveSubscription()) {
            return response()->json(['error' => 'Premium subscription required'], 403);
        }

        // Add user to request for channel authorization
        $request->merge(['user' => $user]);

        return $next($request);
    }
} 