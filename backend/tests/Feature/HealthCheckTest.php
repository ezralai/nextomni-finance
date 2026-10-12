<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Test health check endpoint returns 200 and expected schema with latency metrics.
     */
    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'app',
                'version',
                'environment',
                'timestamp',
                'database' => [
                    'status',
                    'driver',
                    'latency_ms',
                ],
                'cache' => [
                    'status',
                    'driver',
                    'latency_ms',
                ],
                'system' => [
                    'php_version',
                    'laravel_version',
                    'memory_usage_mb',
                ],
            ])
            ->assertJson([
                'status' => 'ok',
                'database' => [
                    'status' => 'connected',
                ],
            ]);

        $responseData = $response->json();
        $this->assertIsNumeric($responseData['database']['latency_ms']);
        $this->assertGreaterThanOrEqual(0, $responseData['database']['latency_ms']);
        $this->assertIsNumeric($responseData['system']['memory_usage_mb']);
    }
}
