<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PremiumMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error' => 'Unauthenticated'
            ], 401);
        }

        // Check if user has active premium subscription
        if (!$user->is_premium_active) {
            return response()->json([
                'success' => false,
                'message' => 'Premium subscription required',
                'error' => 'This feature requires an active premium subscription',
                'upgrade_required' => true,
                'subscription_info' => [
                    'current_plan' => 'free',
                    'has_premium' => false,
                    'premium_expires_at' => null,
                ]
            ], 403);
        }

        // Check if premium subscription has expired
        if ($user->premium_expires_at && $user->premium_expires_at->isPast()) {
            // Update user's premium status
            $user->update([
                'is_premium' => false,
                'premium_expires_at' => null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Premium subscription has expired',
                'error' => 'Your premium subscription has expired. Please renew to access this feature.',
                'upgrade_required' => true,
                'subscription_info' => [
                    'current_plan' => 'free',
                    'has_premium' => false,
                    'premium_expires_at' => null,
                    'expired_at' => $user->premium_expires_at,
                ]
            ], 403);
        }

        return $next($request);
    }
}
