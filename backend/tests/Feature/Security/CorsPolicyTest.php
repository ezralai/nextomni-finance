<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CorsPolicyTest extends TestCase
{
    /**
     * Test CORS headers for localhost development origin.
     */
    public function test_cors_allows_localhost_origins(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Test CORS headers dynamically allow Vercel preview deployments.
     */
    public function test_cors_allows_vercel_preview_deployments(): void
    {
        $vercelOrigin = 'https://nextomni-finance-preview-xyz123.vercel.app';

        $response = $this->withHeaders([
            'Origin' => $vercelOrigin,
        ])->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $vercelOrigin);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Test preflight OPTIONS request returns required CORS preflight headers and cache max-age.
     */
    public function test_preflight_options_request(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://nextomni-finance.vercel.app',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])->options('/api/v1/auth/login');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://nextomni-finance.vercel.app');
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Max-Age', '86400');
    }
}
