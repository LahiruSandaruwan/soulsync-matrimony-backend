<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware (runs on every request)
        $middleware->prepend(\App\Http\Middleware\ForceHttps::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // API middleware group
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'premium' => \App\Http\Middleware\PremiumMiddleware::class,
            'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'auth.rate.limit' => \App\Http\Middleware\AuthRateLimitMiddleware::class,
            'admin' => \App\Http\Middleware\AdminAuth::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'force.https' => \App\Http\Middleware\ForceHttps::class,
        ]);

        // Trust proxies for load balancers
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Report to Sentry (if configured)
        $exceptions->reportable(function (Throwable $e) {
            if (app()->bound('sentry') && config('sentry.dsn')) {
                app('sentry')->captureException($e);
            }
        });

        // Don't report these exceptions
        $exceptions->dontReport([
            AuthenticationException::class,
            ValidationException::class,
        ]);

        // Custom rendering for API requests
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = 500;
                $message = 'An unexpected error occurred.';

                if ($e instanceof ValidationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => $e->errors(),
                    ], 422);
                }

                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated.',
                    ], 401);
                }

                if ($e instanceof ModelNotFoundException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource not found.',
                    ], 404);
                }

                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Endpoint not found.',
                    ], 404);
                }

                if ($e instanceof HttpException) {
                    $status = $e->getStatusCode();
                    $message = $e->getMessage() ?: 'HTTP error occurred.';
                }

                // Log the actual error for debugging
                if ($status === 500) {
                    \Log::error('API Error', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'url' => $request->fullUrl(),
                        'method' => $request->method(),
                        'user_id' => $request->user()?->id,
                    ]);
                }

                $response = [
                    'success' => false,
                    'message' => $message,
                ];

                // Only include debug info in non-production
                if (config('app.debug')) {
                    $response['debug'] = [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ];
                }

                return response()->json($response, $status);
            }
        });
    })->create();
