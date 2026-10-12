<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Return application health, database query latency, and system operational metrics.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'disconnected';
        $dbDriver = config('database.default');
        $dbLatencyMs = null;

        try {
            $dbStart = microtime(true);
            DB::select('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);
            $dbStatus = 'connected';
        } catch (Throwable $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }

        $cacheStatus = 'disconnected';
        $cacheDriver = config('cache.default');
        $cacheLatencyMs = null;

        try {
            $cacheStart = microtime(true);
            $cacheKey = 'health_ping_' . now()->timestamp;
            Cache::put($cacheKey, 'ok', 5);
            $cachedVal = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            if ($cachedVal === 'ok') {
                $cacheStatus = 'connected';
                $cacheLatencyMs = round((microtime(true) - $cacheStart) * 1000, 2);
            } else {
                $cacheStatus = 'degraded';
            }
        } catch (Throwable $e) {
            $cacheStatus = 'error: ' . $e->getMessage();
        }

        $isHealthy = ($dbStatus === 'connected');

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'app' => config('app.name', 'NextOmni Finance API'),
            'version' => '1.0.0',
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
            'database' => [
                'status' => $dbStatus,
                'driver' => $dbDriver,
                'latency_ms' => $dbLatencyMs,
            ],
            'cache' => [
                'status' => $cacheStatus,
                'driver' => $cacheDriver,
                'latency_ms' => $cacheLatencyMs,
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ],
        ], $isHealthy ? 200 : 503);
    }
}
