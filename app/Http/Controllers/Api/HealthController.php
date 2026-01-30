<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Basic health check for load balancers.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Comprehensive health check with service status.
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'services' => [],
        ];

        $allHealthy = true;

        // Check Database
        $health['services']['database'] = $this->checkDatabase();
        if ($health['services']['database']['status'] !== 'healthy') {
            $allHealthy = false;
        }

        // Check Redis/Cache
        $health['services']['cache'] = $this->checkCache();
        if ($health['services']['cache']['status'] !== 'healthy') {
            $allHealthy = false;
        }

        // Check Queue
        $health['services']['queue'] = $this->checkQueue();
        if ($health['services']['queue']['status'] !== 'healthy') {
            $allHealthy = false;
        }

        // Check Storage
        $health['services']['storage'] = $this->checkStorage();
        if ($health['services']['storage']['status'] !== 'healthy') {
            $allHealthy = false;
        }

        $health['status'] = $allHealthy ? 'healthy' : 'degraded';

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => config('app.debug') ? $e->getMessage() : 'Connection failed',
            ];
        }
    }

    /**
     * Check cache/Redis connectivity.
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . uniqid();

            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            $latency = round((microtime(true) - $start) * 1000, 2);

            if ($value !== 'test') {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Cache read/write mismatch',
                ];
            }

            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => config('app.debug') ? $e->getMessage() : 'Connection failed',
            ];
        }
    }

    /**
     * Check queue connectivity.
     */
    private function checkQueue(): array
    {
        try {
            $driver = config('queue.default');

            // For sync driver, always healthy
            if ($driver === 'sync') {
                return [
                    'status' => 'healthy',
                    'driver' => $driver,
                    'note' => 'Sync driver - jobs run immediately',
                ];
            }

            // For Redis driver, check Redis connection
            if ($driver === 'redis') {
                $start = microtime(true);
                Redis::ping();
                $latency = round((microtime(true) - $start) * 1000, 2);

                return [
                    'status' => 'healthy',
                    'latency_ms' => $latency,
                    'driver' => $driver,
                ];
            }

            // For database driver, check jobs table
            if ($driver === 'database') {
                DB::table('jobs')->count();
                return [
                    'status' => 'healthy',
                    'driver' => $driver,
                ];
            }

            return [
                'status' => 'healthy',
                'driver' => $driver,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => config('app.debug') ? $e->getMessage() : 'Connection failed',
            ];
        }
    }

    /**
     * Check storage accessibility.
     */
    private function checkStorage(): array
    {
        try {
            $storagePath = storage_path('app');

            if (!is_writable($storagePath)) {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Storage directory not writable',
                ];
            }

            // Test write
            $testFile = $storagePath . '/health_check_' . uniqid();
            file_put_contents($testFile, 'test');
            $content = file_get_contents($testFile);
            unlink($testFile);

            if ($content !== 'test') {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Storage read/write mismatch',
                ];
            }

            return [
                'status' => 'healthy',
                'driver' => config('filesystems.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => config('app.debug') ? $e->getMessage() : 'Storage check failed',
            ];
        }
    }

    /**
     * Readiness check for Kubernetes/orchestration.
     */
    public function ready(): JsonResponse
    {
        // Check if app is ready to receive traffic
        try {
            DB::select('SELECT 1');
            return response()->json(['ready' => true]);
        } catch (\Exception $e) {
            return response()->json(['ready' => false], 503);
        }
    }

    /**
     * Liveness check for Kubernetes/orchestration.
     */
    public function live(): JsonResponse
    {
        // App is alive if this endpoint responds
        return response()->json(['alive' => true]);
    }
}
