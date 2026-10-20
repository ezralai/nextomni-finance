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

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Account $account;
    protected TransactionCategory $groceries;
    protected TransactionCategory $utilities;

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
            'name' => 'Primary Checking',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '10000.00',
            'current_balance' => '10000.00',
            'is_active' => true,
        ]);

        $this->groceries = TransactionCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Groceries & Household',
            'slug' => 'groceries',
            'type' => 'expense',
            'color' => '#10b981',
            'icon' => 'shopping-cart',
            'is_system' => false,
        ]);

        $this->utilities = TransactionCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Utilities & Bills',
            'slug' => 'utilities',
            'type' => 'expense',
            'color' => '#3b82f6',
            'icon' => 'zap',
            'is_system' => false,
        ]);
    }

    public function test_cash_flow_analytics_returns_summary_and_categories(): void
    {
        // 1 Income of 5,000
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'income',
            'amount' => '5000.00',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-05 10:00:00'),
            'description' => 'Salary Inflow',
            'status' => 'posted',
        ]);

        // 2 Expenses of 500 and 1000
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->groceries->id,
            'type' => 'expense',
            'amount' => '500.00',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-06 12:00:00'),
            'description' => 'Jaya Grocer',
            'counterparty' => 'Jaya Grocer',
            'status' => 'posted',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->utilities->id,
            'type' => 'expense',
            'amount' => '1000.00',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-10 15:00:00'),
            'description' => 'Tenaga Nasional',
            'counterparty' => 'TNB',
            'status' => 'posted',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/analytics/cash-flow?start_date=2026-10-01&end_date=2026-10-31');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => ['start_date', 'end_date'],
                'summary' => [
                    'total_income',
                    'total_expense',
                    'net_cash_flow',
                    'savings_rate',
                    'currency',
                ],
                'categories',
                'daily_trend',
                'runway' => [
                    'liquid_balance',
                    'daily_burn_rate',
                    'monthly_burn_rate',
                    'runway_months',
                    'health_tier',
                ],
                'top_merchants',
                'spending_patterns',
                'anomalies',
            ]);

        $summary = $response->json('summary');
        $this->assertEquals('5000.00', $summary['total_income']);
        $this->assertEquals('1500.00', $summary['total_expense']);
        $this->assertEquals('3500.00', $summary['net_cash_flow']);
        $this->assertEquals(70.0, $summary['savings_rate']); // (3500 / 5000) * 100

        // Check top merchants ranking
        $merchants = $response->json('top_merchants');
        $this->assertNotEmpty($merchants);
        $this->assertEquals('Tenaga Nasional', $merchants[0]['name']);
        $this->assertEquals('1000.00', $merchants[0]['amount']);
    }

    public function test_runway_calculation_reflects_liquid_balance(): void
    {
        // Account balance is 10,000 MYR
        // Spend 1,000 MYR over 31 days -> daily burn ~32.26, monthly burn ~967.74
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '1000.00',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-15 12:00:00'),
            'description' => 'Living Expense',
            'status' => 'posted',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/analytics/cash-flow?start_date=2026-10-01&end_date=2026-10-31');

        $response->assertStatus(200);
        $runway = $response->json('runway');
        $this->assertGreaterThan(0, $runway['runway_months']);
        $this->assertEquals('healthy', $runway['health_tier']); // ~10.3 months is in the 6-12 month healthy bracket
    }

    public function test_statistical_anomaly_detection_flags_outlier_expenses(): void
    {
        // Seed 4 typical grocery expenses: 50, 55, 60, 45 (mean ~52.5, std ~6.5)
        foreach ([50.00, 55.00, 60.00, 45.00] as $amt) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->groceries->id,
                'type' => 'expense',
                'amount' => number_format($amt, 2, '.', ''),
                'currency' => 'MYR',
                'transacted_at' => Carbon::parse('2026-10-02 12:00:00'),
                'description' => 'Regular Grocery',
                'status' => 'posted',
            ]);
        }

        // Seed 1 massive outlier: 450.00 (Z-score > 2.0)
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->groceries->id,
            'type' => 'expense',
            'amount' => '450.00',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-18 19:00:00'),
            'description' => 'Bulk Seafood Party Feast',
            'status' => 'posted',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/analytics/cash-flow?start_date=2026-10-01&end_date=2026-10-31');

        $response->assertStatus(200);
        $anomalies = $response->json('anomalies');
        $this->assertNotEmpty($anomalies);
        $this->assertEquals('Bulk Seafood Party Feast', $anomalies[0]['description']);
        $this->assertEquals('450.00', $anomalies[0]['amount']);
        $this->assertGreaterThanOrEqual(1.75, $anomalies[0]['z_score']);
    }
}
