<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Return application health and database connection status.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'disconnected';
        $dbDriver = config('database.default');

        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (Throwable $e) {
            $dbStatus = 'error: ' . $e->getMessage();
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
            ],
        ], $isHealthy ? 200 : 503);
    }
}
