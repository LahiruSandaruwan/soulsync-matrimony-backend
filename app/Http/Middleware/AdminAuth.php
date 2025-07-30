<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
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

        // Check if user has admin or moderator role
        if (!$user->is_admin && !$user->is_moderator) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Check if moderator is trying to access user management routes
        if ($user->is_moderator && !$user->is_admin) {
            $path = $request->path();
            if (str_contains($path, 'admin/users') && !str_contains($path, 'analytics')) {
                return response()->json(['error' => 'Forbidden - Moderators cannot manage users'], 403);
            }
        }

        // Add user to request for channel authorization
        $request->merge(['user' => $user]);

        return $next($request);
    }
} 