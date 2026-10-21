<?php

namespace Tests\Feature\Analytics;

use App\Models\Account;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MlIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Account $account;
    protected TransactionCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'base_currency' => 'MYR',
        ]);
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $institution = Institution::create([
            'code' => 'MBB',
            'name' => 'Maybank',
            'type' => 'bank',
            'country' => 'MYS',
            'supported_currencies' => ['MYR'],
            'is_active' => true,
        ]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $institution->id,
            'name' => 'Savings Account',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '5000.0000',
            'current_balance' => '5000.0000',
            'is_active' => true,
        ]);

        $this->category = TransactionCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Groceries & Provisions',
            'slug' => 'groceries',
            'type' => 'expense',
            'icon' => 'shopping-cart',
            'color' => '#10b981',
        ]);
    }

    /**
     * Test empty state returns valid structured envelope.
     */
    public function test_ml_intelligence_endpoint_returns_empty_when_no_transactions(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/analytics/ml-intelligence');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'empty')
            ->assertJsonPath('data.records_analyzed', 0);
    }

    /**
     * Test ML intelligence execution with populated transactions.
     */
    public function test_ml_intelligence_computes_analytics_and_forecast(): void
    {
        $now = Carbon::now();

        // Seed 15 historical expense transactions
        for ($i = 0; $i < 15; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 45.00 + ($i * 5),
                'currency' => 'MYR',
                'transacted_at' => $now->copy()->subDays($i * 2),
                'description' => "Jaya Grocer #{$i}",
                'status' => 'posted',
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/analytics/ml-intelligence?refresh=1');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'records_analyzed',
                    'summary',
                    'anomalies',
                    'forecast',
                    'recurring',
                ],
            ]);

        $this->assertGreaterThanOrEqual(15, $response->json('data.records_analyzed'));

        $forecast = $response->json('data.forecast');
        $this->assertIsArray($forecast);
        $this->assertNotEmpty($forecast);
        $firstStep = $forecast[0];
        $this->assertArrayHasKey('month', $firstStep);
        $this->assertArrayHasKey('predicted_expense', $firstStep);
        $this->assertArrayHasKey('confidence_level', $firstStep);
        $this->assertGreaterThan(0, (float) $firstStep['predicted_expense']);
        $this->assertGreaterThan(0, (float) $firstStep['confidence_level']);
    }

    /**
     * Test ML intelligence recurring pattern detection.
     */
    public function test_ml_intelligence_detects_recurring_expenses(): void
    {
        $now = Carbon::now();

        // Seed 4 monthly Spotify recurring transactions
        for ($i = 0; $i < 4; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => '24.9000',
                'currency' => 'MYR',
                'transacted_at' => $now->copy()->subDays($i * 30),
                'description' => 'Spotify Premium Family',
                'status' => 'posted',
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/analytics/ml-intelligence?refresh=1');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $recurring = $response->json('data.recurring');
        $this->assertIsArray($recurring);
        $this->assertNotEmpty($recurring);

        $item = $recurring[0];
        $this->assertStringContainsString('Spotify', $item['merchant']);
        $this->assertEqualsWithDelta(24.90, (float) $item['average_amount'], 0.01);
        $this->assertArrayHasKey('cadence', $item);
        $this->assertArrayHasKey('next_expected_date', $item);
    }
}
