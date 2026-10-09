<?php

namespace Tests\Feature\Transactions;

use App\Models\Account;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Account $bankAccount;
    protected Account $cardAccount;
    protected Account $walletAccount;

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
        $cimb = Institution::where('code', 'CIMB')->firstOrFail();
        $tng = Institution::where('code', 'TNG')->firstOrFail();

        // Bank Account: RM 5,000.00
        $this->bankAccount = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $mbb->id,
            'name' => 'Maybank Savings',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '5000.0000',
            'current_balance' => '5000.0000',
        ]);

        // Credit Card: RM 1,200.00 (Outstanding Debt)
        $this->cardAccount = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $cimb->id,
            'name' => 'CIMB Visa Signature',
            'account_type' => 'credit_card',
            'currency' => 'MYR',
            'opening_balance' => '1200.0000',
            'current_balance' => '1200.0000',
        ]);

        // E-Wallet: RM 300.00
        $this->walletAccount = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $tng->id,
            'name' => 'Touch n Go eWallet',
            'account_type' => 'e_wallet',
            'currency' => 'MYR',
            'opening_balance' => '300.0000',
            'current_balance' => '300.0000',
        ]);
    }

    public function test_user_can_list_hierarchical_categories(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'categories' => [
                    '*' => ['id', 'name', 'slug', 'type', 'icon', 'color', 'children'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(6, count($response->json('categories')));
    }

    public function test_user_can_record_income_and_account_balance_increases(): void
    {
        $salaryCategory = TransactionCategory::where('slug', 'salary-wages')->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->bankAccount->id,
                'category_id' => $salaryCategory->id,
                'type' => 'income',
                'amount' => 8500.25,
                'description' => 'Monthly Salary Oct 2026',
                'transacted_at' => '2026-10-09 10:00:00',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Transaction recorded successfully.',
                'transaction' => [
                    'type' => 'income',
                    'amount' => '8500.25',
                    'amount_formatted' => 'RM 8,500.25',
                    'currency' => 'MYR',
                    'description' => 'Monthly Salary Oct 2026',
                ],
            ]);

        // Verify Account balance: 5000.0000 + 8500.2500 = 13500.2500
        $this->assertDatabaseHas('accounts', [
            'id' => $this->bankAccount->id,
            'current_balance' => '13500.2500',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'income',
            'amount' => '8500.2500',
        ]);
    }

    public function test_user_can_record_expense_and_account_balance_decreases(): void
    {
        $groceriesCategory = TransactionCategory::where('slug', 'groceries-supermarket')->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->bankAccount->id,
                'category_id' => $groceriesCategory->id,
                'type' => 'expense',
                'amount' => 155.75,
                'description' => 'Jaya Grocer Weekly Shopping',
                'transacted_at' => '2026-10-09 11:15:00',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'transaction' => [
                    'type' => 'expense',
                    'amount' => '155.75',
                    'amount_formatted' => 'RM 155.75',
                ],
            ]);

        // Verify Account balance: 5000.0000 - 155.7500 = 4844.2500
        $this->assertDatabaseHas('accounts', [
            'id' => $this->bankAccount->id,
            'current_balance' => '4844.2500',
        ]);
    }

    public function test_credit_card_expense_increases_liability_balance(): void
    {
        $fuelCategory = TransactionCategory::where('slug', 'fuel-petrol')->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->cardAccount->id,
                'category_id' => $fuelCategory->id,
                'type' => 'expense',
                'amount' => 95.00,
                'description' => 'Petronas RON97 Fuel',
                'transacted_at' => '2026-10-09 12:00:00',
            ]);

        $response->assertStatus(201);

        // For liability (credit card debt): 1200.0000 + 95.0000 = 1295.0000
        $this->assertDatabaseHas('accounts', [
            'id' => $this->cardAccount->id,
            'current_balance' => '1295.0000',
        ]);
    }

    public function test_duplicate_transaction_is_detected_and_rejected_via_idempotency_hash(): void
    {
        $payload = [
            'account_id' => $this->bankAccount->id,
            'type' => 'expense',
            'amount' => 50.00,
            'description' => 'Kopitiam Breakfast',
            'transacted_at' => '2026-10-09 08:30:00',
        ];

        // 1st request succeeds
        $first = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions', $payload);
        $first->assertStatus(201);

        // 2nd duplicate request on the same date, amount, description fails
        $second = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions', $payload);

        $second->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_user_can_execute_atomic_transfer_between_accounts(): void
    {
        // Transfer RM 200.00 from Bank (balance 5000.00) to eWallet (balance 300.00) with RM 1.00 fee
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transfers', [
                'source_account_id' => $this->bankAccount->id,
                'destination_account_id' => $this->walletAccount->id,
                'amount' => 200.00,
                'fee' => 1.00,
                'notes' => 'Top up TNG for highway tolls',
                'transacted_at' => '2026-10-09 14:00:00',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Transfer executed successfully.',
                'transfer' => [
                    'amount' => '200.00',
                    'fee' => '1.00',
                    'amount_formatted' => 'RM 200.00',
                    'fee_formatted' => 'RM 1.00',
                ],
            ]);

        // Source Bank balance: 5000.0000 - 201.0000 = 4799.0000
        $this->assertDatabaseHas('accounts', [
            'id' => $this->bankAccount->id,
            'current_balance' => '4799.0000',
        ]);

        // Destination eWallet balance: 300.0000 + 200.0000 = 500.0000
        $this->assertDatabaseHas('accounts', [
            'id' => $this->walletAccount->id,
            'current_balance' => '500.0000',
        ]);

        // Verify twin transactions created
        $this->assertDatabaseCount('transfers', 1);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_user_can_delete_transaction_and_balance_is_restored(): void
    {
        // First record expense of RM 120.00
        $postResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->bankAccount->id,
                'type' => 'expense',
                'amount' => 120.00,
                'description' => 'Dinner at Mamak',
            ]);

        $postResponse->assertStatus(201);
        $txnId = $postResponse->json('transaction.id');

        // Balance was 5000 - 120 = 4880.0000
        $this->assertDatabaseHas('accounts', [
            'id' => $this->bankAccount->id,
            'current_balance' => '4880.0000',
        ]);

        // Now delete transaction
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/transactions/' . $txnId);

        $deleteResponse->assertStatus(200)
            ->assertJson(['message' => 'Transaction deleted and account balance restored.']);

        // Balance restored back to 5000.0000
        $this->assertDatabaseHas('accounts', [
            'id' => $this->bankAccount->id,
            'current_balance' => '5000.0000',
        ]);

        $this->assertDatabaseMissing('transactions', ['id' => $txnId]);
    }

    public function test_user_cannot_access_or_manipulate_another_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('other_token')->plainTextToken;

        $otherAccount = Account::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id,
            'institution_id' => $this->bankAccount->institution_id,
            'name' => 'Other Bank',
            'account_type' => 'bank',
            'opening_balance' => '1000.0000',
            'current_balance' => '1000.0000',
        ]);

        $otherTxn = Transaction::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'type' => 'expense',
            'amount' => '50.0000',
            'currency' => 'MYR',
            'transacted_at' => now(),
            'description' => 'Secret transaction',
        ]);

        // User attempts to view other transaction
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/transactions/' . $otherTxn->id);

        $this->assertContains($response->status(), [403, 404]);

        // User attempts to delete other transaction
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/transactions/' . $otherTxn->id);

        $this->assertContains($deleteResponse->status(), [403, 404]);
    }
}
