<?php

namespace Tests\Feature\Budgets;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Account $account;
    protected TransactionCategory $foodCategory;
    protected TransactionCategory $groceriesCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            InstitutionSeeder::class,
            CategorySeeder::class,
        ]);

        $this->user = User::factory()->create([
            'base_currency' => 'MYR',
        ]);
        $this->token = $this->user->createToken('test_token')->plainTextToken;

        $mbb = Institution::where('code', 'MBB')->firstOrFail();

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $mbb->id,
            'name' => 'Maybank Premier Savings',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '10000.0000',
            'current_balance' => '10000.0000',
        ]);

        $this->foodCategory = TransactionCategory::where('slug', 'food-dining')->firstOrFail();
        $this->groceriesCategory = TransactionCategory::where('slug', 'groceries-supermarket')->firstOrFail();
    }

    public function test_user_can_create_budget_and_list_with_progress(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/budgets', [
                'category_id' => $this->foodCategory->id,
                'amount' => 1500.00,
                'period' => 'monthly',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'notes' => 'Total food and grocery budget for October',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Budget limit established successfully.',
                'budget' => [
                    'amount' => '1500.00',
                    'amount_formatted' => 'RM 1,500.00',
                    'period' => 'monthly',
                ],
            ]);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id,
            'category_id' => $this->foodCategory->id,
            'amount' => '1500.0000',
        ]);

        // Fetch budget list
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/budgets?date=2026-10-15');

        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'budgets' => [
                    '*' => ['budget', 'category', 'amount', 'spent', 'remaining', 'percent_used', 'status'],
                ],
                'summary' => ['total_budgeted', 'total_spent', 'total_remaining', 'overall_percent_used'],
            ]);
    }

    public function test_budget_aggregates_child_category_transactions(): void
    {
        // 1. Create a parent Food & Dining budget of RM 1,000.00
        $this->withHeader('Authorization', 'Bearer ' . $this->token)->postJson('/api/v1/budgets', [
            'category_id' => $this->foodCategory->id,
            'amount' => 1000.00,
            'period' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        // 2. Record expense under child category (Groceries & Supermarket): RM 350.50
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->groceriesCategory->id,
            'type' => 'expense',
            'amount' => '350.5000',
            'currency' => 'MYR',
            'transacted_at' => '2026-10-09 10:00:00',
            'description' => 'Village Grocer Shopping',
            'status' => 'posted',
        ]);

        // 3. Record expense under child category (Hawker & Mamak): RM 50.00
        $hawker = TransactionCategory::where('slug', 'hawker-mamak')->firstOrFail();
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $hawker->id,
            'type' => 'expense',
            'amount' => '50.0000',
            'currency' => 'MYR',
            'transacted_at' => '2026-10-09 12:00:00',
            'description' => 'Mamak Roti Canai',
            'status' => 'posted',
        ]);

        // 4. Query budget progress
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/budgets?date=2026-10-09');

        $response->assertStatus(200);

        $budgetItem = $response->json('budgets.0');
        // Total spent: 350.50 + 50.00 = 400.50
        $this->assertEquals('400.50', $budgetItem['spent']);
        $this->assertEquals('599.50', $budgetItem['remaining']);
        // Percent used: 400.50 / 1000.00 = 40.1%
        $this->assertEquals(40.1, $budgetItem['percent_used']);
        $this->assertEquals('healthy', $budgetItem['status']);
    }

    public function test_budget_progress_detects_warning_and_exceeded_thresholds(): void
    {
        // Budget of RM 500.00
        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $this->foodCategory->id,
            'amount' => '500.0000',
            'currency' => 'MYR',
            'period' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        // Record expense of RM 550.00 (Over budget by RM 50)
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->foodCategory->id,
            'type' => 'expense',
            'amount' => '550.0000',
            'currency' => 'MYR',
            'transacted_at' => '2026-10-10 18:00:00',
            'description' => 'Expensive Dinner Party',
            'status' => 'posted',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/budgets?date=2026-10-10');

        $budgetItem = $response->json('budgets.0');
        $this->assertEquals('550.00', $budgetItem['spent']);
        $this->assertEquals('-50.00', $budgetItem['remaining']);
        $this->assertEquals(110.0, $budgetItem['percent_used']);
        $this->assertTrue($budgetItem['is_over_budget']);
        $this->assertEquals('exceeded', $budgetItem['status']);
    }

    public function test_user_can_update_and_delete_budget(): void
    {
        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $this->foodCategory->id,
            'amount' => '600.0000',
            'currency' => 'MYR',
            'period' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        // Update amount
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/budgets/' . $budget->id, [
                'amount' => 850.00,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'budget' => [
                    'amount' => '850.00',
                ],
            ]);

        // Delete budget
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/budgets/' . $budget->id);

        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_user_cannot_access_another_users_budget(): void
    {
        $otherUser = User::factory()->create();
        $otherBudget = Budget::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id,
            'category_id' => $this->foodCategory->id,
            'amount' => '2000.0000',
            'currency' => 'MYR',
            'period' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/budgets/' . $otherBudget->id);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_cash_flow_analytics_returns_inflows_outflows_and_savings_rate(): void
    {
        // 1. Income: RM 8,000.00
        $incomeCategory = TransactionCategory::where('slug', 'salary-wages')->firstOrFail();
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $incomeCategory->id,
            'type' => 'income',
            'amount' => '8000.0000',
            'currency' => 'MYR',
            'transacted_at' => '2026-10-01 09:00:00',
            'description' => 'Monthly Salary Oct',
            'status' => 'posted',
        ]);

        // 2. Expense: RM 2,000.00
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->foodCategory->id,
            'type' => 'expense',
            'amount' => '2000.0000',
            'currency' => 'MYR',
            'transacted_at' => '2026-10-05 14:00:00',
            'description' => 'Monthly Food Expenses',
            'status' => 'posted',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/analytics/cash-flow?start_date=2026-10-01&end_date=2026-10-31');

        $response->assertStatus(200)
            ->assertJson([
                'summary' => [
                    'total_income' => '8000.00',
                    'total_expense' => '2000.00',
                    'net_cash_flow' => '6000.00',
                    'savings_rate' => 75.0, // (6000 / 8000) * 100 = 75%
                ],
            ]);

        $this->assertNotEmpty($response->json('categories'));
        $this->assertNotEmpty($response->json('daily_trend'));
    }
}
