<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Test health check endpoint returns 200 and expected schema.
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
                ],
            ])
            ->assertJson([
                'status' => 'ok',
                'database' => [
                    'status' => 'connected',
                ],
            ]);
    }
}
