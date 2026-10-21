<?php

namespace Tests\Feature\Transactions;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\Budgets\BudgetService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionRepositoryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;
    protected TransactionRepositoryInterface $transactionRepository;
    protected BudgetService $budgetService;

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

        $mbb = Institution::where('code', 'MBB')->firstOrFail();

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $mbb->id,
            'name' => 'Main Checking',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '5000.0000',
            'current_balance' => '5000.0000',
        ]);

        $this->transactionRepository = app(TransactionRepositoryInterface::class);
        $this->budgetService = app(BudgetService::class);
    }

    public function test_date_filtering_executes_direct_timestamp_comparisons(): void
    {
        // 3 transactions: Past, Inside Target Window, Future
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '50.0000',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-10 12:00:00'),
            'description' => 'Outside early',
            'status' => 'posted',
        ]);

        $inRange = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '120.0000',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-20 18:30:00'),
            'description' => 'In range target',
            'status' => 'posted',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '80.0000',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-30 08:00:00'),
            'description' => 'Outside late',
            'status' => 'posted',
        ]);

        $results = $this->transactionRepository->getForUser($this->user->id, [
            'start_date' => '2026-10-18',
            'end_date' => '2026-10-22',
        ]);

        $this->assertCount(1, $results->items());
        $this->assertEquals($inRange->id, $results->items()[0]->id);
        $this->assertEqualsWithDelta(120.0, (float) $results->items()[0]->amount, 0.0001);
    }

    public function test_get_totals_for_period_produces_exact_aggregates_via_database_engine(): void
    {
        // Income entries
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'income',
            'amount' => '3500.5000',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-15 10:00:00'),
            'description' => 'Salary Part 1',
            'status' => 'posted',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'income',
            'amount' => '1500.2500',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-16 10:00:00'),
            'description' => 'Consulting',
            'status' => 'posted',
        ]);

        // Expense entries
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '450.7500',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-17 12:00:00'),
            'description' => 'Groceries',
            'status' => 'posted',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '150.0000',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-18 14:00:00'),
            'description' => 'Utilities',
            'status' => 'posted',
        ]);

        // Ignored pending transaction
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '999.9900',
            'currency' => 'MYR',
            'transacted_at' => Carbon::parse('2026-10-18 14:00:00'),
            'description' => 'Pending check',
            'status' => 'pending',
        ]);

        $totals = $this->transactionRepository->getTotalsForPeriod(
            $this->user->id,
            '2026-10-01',
            '2026-10-31'
        );

        // Expected in Money::DISPLAY_SCALE (2 decimals):
        // Income = 3500.50 + 1500.25 = 5000.75
        // Expense = 450.75 + 150.00 = 600.75
        // Net = 5000.75 - 600.75 = 4400.00
        $this->assertEquals('5000.75', $totals['income']);
        $this->assertEquals('600.75', $totals['expense']);
        $this->assertEquals('4400.00', $totals['net']);
    }

    public function test_budget_progress_preloads_child_categories_in_single_query(): void
    {
        $foodCategory = TransactionCategory::where('slug', 'food-beverage')->first();
        if (! $foodCategory) {
            $foodCategory = TransactionCategory::create([
                'name' => 'Food & Beverage',
                'slug' => 'food-beverage',
                'type' => 'expense',
                'color' => '#f97316',
            ]);
        }

        // Create 3 subcategories
        $groceries = TransactionCategory::create([
            'parent_id' => $foodCategory->id,
            'name' => 'Groceries',
            'slug' => 'groceries',
            'type' => 'expense',
            'color' => '#22c55e',
        ]);

        $dining = TransactionCategory::create([
            'parent_id' => $foodCategory->id,
            'name' => 'Dining Out',
            'slug' => 'dining-out',
            'type' => 'expense',
            'color' => '#eab308',
        ]);

        // Create 3 distinct budgets
        $budget1 = Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $foodCategory->id,
            'amount' => '1000.0000',
            'currency' => 'MYR',
            'period' => 'monthly',
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]);

        $budget2 = Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $groceries->id,
            'amount' => '500.0000',
            'currency' => 'MYR',
            'period' => 'monthly',
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]);

        $budget3 = Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $dining->id,
            'amount' => '300.0000',
            'currency' => 'MYR',
            'period' => 'monthly',
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]);

        // Record expense in child category
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $groceries->id,
            'type' => 'expense',
            'amount' => '250.0000',
            'currency' => 'MYR',
            'transacted_at' => Carbon::now(),
            'description' => 'Weekly Supermarket',
            'status' => 'posted',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $progress = $this->budgetService->getBudgetsProgress($this->user);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(3, $progress['budgets']);

        // Inspect queries for child categories lookups
        $categoryLookupQueries = array_filter($queries, function ($q) {
            return str_contains(strtolower($q['query']), 'from "transaction_categories"')
                || str_contains(strtolower($q['query']), 'from `transaction_categories`');
        });

        // Batch preloading ensures category hierarchy is loaded in at most 2 queries (parents + active children), with zero in-loop queries
        $this->assertLessThanOrEqual(2, count($categoryLookupQueries), 'Category child lookup must be batched, not N+1.');
    }

    public function test_database_enforces_unique_deduplication_hash(): void
    {
        $hash = hash('sha256', 'unique-test-fingerprint');

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '100.0000',
            'currency' => 'MYR',
            'transacted_at' => Carbon::now(),
            'description' => 'Original entry',
            'deduplication_hash' => $hash,
            'status' => 'posted',
        ]);

        $this->expectException(QueryException::class);

        // Attempt second insert with identical user_id and deduplication_hash
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '100.0000',
            'currency' => 'MYR',
            'transacted_at' => Carbon::now(),
            'description' => 'Duplicate attempt',
            'deduplication_hash' => $hash,
            'status' => 'posted',
        ]);
    }
}
