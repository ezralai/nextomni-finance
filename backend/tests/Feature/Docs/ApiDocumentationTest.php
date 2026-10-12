<?php

namespace Tests\Feature\Docs;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    /**
     * Test interactive Scalar UI renders with HTTP 200 and required elements.
     */
    public function test_api_docs_ui_returns_ok_html(): void
    {
        $response = $this->get('/docs');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('NextOmni Finance API', $response->getContent());
        $this->assertStringContainsString('@scalar/api-reference', $response->getContent());
        $this->assertStringContainsString('/api/v1/openapi.json', $response->getContent());
    }

    /**
     * Test OpenAPI 3.1 JSON specification contains required tags, paths, and metadata.
     */
    public function test_openapi_json_returns_valid_spec(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'openapi',
                'info' => ['title', 'version', 'description'],
                'servers',
                'tags',
                'components' => [
                    'securitySchemes' => [
                        'SanctumBearer',
                    ],
                ],
                'paths',
            ])
            ->assertJson([
                'openapi' => '3.1.0',
                'info' => [
                    'title' => 'NextOmni Finance API',
                    'version' => '1.0.0',
                ],
            ]);

        $paths = $response->json('paths');
        $this->assertArrayHasKey('/health', $paths);
        $this->assertArrayHasKey('/auth/login', $paths);
        $this->assertArrayHasKey('/auth/register', $paths);
        $this->assertArrayHasKey('/accounts', $paths);
        $this->assertArrayHasKey('/transactions', $paths);
        $this->assertArrayHasKey('/transfers', $paths);
        $this->assertArrayHasKey('/budgets', $paths);
        $this->assertArrayHasKey('/recurring-transactions', $paths);
        $this->assertArrayHasKey('/goals', $paths);
        $this->assertArrayHasKey('/analytics/cash-flow', $paths);
        $this->assertArrayHasKey('/reports/transactions/csv', $paths);
        $this->assertArrayHasKey('/imports/statements', $paths);
    }
}
