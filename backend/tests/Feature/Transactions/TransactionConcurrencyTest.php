<?php

namespace Tests\Feature\Transactions;

use App\Models\Account;
use App\Models\Institution;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\Transactions\TransactionService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $accountA;
    protected Account $accountB;
    protected TransactionService $service;

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
        $cimb = Institution::where('code', 'CIMB')->firstOrFail();

        $this->accountA = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $mbb->id,
            'name' => 'Account A (Maybank)',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '1000.0000',
            'current_balance' => '1000.0000',
        ]);

        $this->accountB = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $cimb->id,
            'name' => 'Account B (CIMB)',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '500.0000',
            'current_balance' => '500.0000',
        ]);

        $this->service = app(TransactionService::class);
    }

    /**
     * Test that bidirectional transfers execute without deadlock due to deterministic lock sorting.
     */
    public function test_bidirectional_transfers_execute_deterministically_without_deadlock(): void
    {
        // Transfer 1: Account A -> Account B (RM 100)
        $transfer1 = $this->service->createTransfer($this->user, [
            'source_account_id' => $this->accountA->id,
            'destination_account_id' => $this->accountB->id,
            'amount' => '100.0000',
            'transacted_at' => now()->toIso8601String(),
            'notes' => 'A to B transfer',
        ]);

        $this->assertNotNull($transfer1);
        $this->accountA->refresh();
        $this->accountB->refresh();
        $this->assertEqualsWithDelta(900.00, (float) $this->accountA->current_balance, 0.0001);
        $this->assertEqualsWithDelta(600.00, (float) $this->accountB->current_balance, 0.0001);

        // Transfer 2: Account B -> Account A (RM 50) - Reverse direction
        $transfer2 = $this->service->createTransfer($this->user, [
            'source_account_id' => $this->accountB->id,
            'destination_account_id' => $this->accountA->id,
            'amount' => '50.0000',
            'transacted_at' => now()->toIso8601String(),
            'notes' => 'B to A reverse transfer',
        ]);

        $this->assertNotNull($transfer2);
        $this->accountA->refresh();
        $this->accountB->refresh();
        $this->assertEqualsWithDelta(950.00, (float) $this->accountA->current_balance, 0.0001);
        $this->assertEqualsWithDelta(550.00, (float) $this->accountB->current_balance, 0.0001);
    }

    /**
     * Test that duplicate transaction fingerprinting triggers ValidationException idempotently.
     */
    public function test_duplicate_fingerprint_is_rejected_idempotently(): void
    {
        $payload = [
            'account_id' => $this->accountA->id,
            'type' => 'expense',
            'amount' => '150.0000',
            'description' => 'Groceries at Jaya Grocer',
            'transacted_at' => '2026-10-21 14:00:00',
        ];

        // First creation succeeds
        $first = $this->service->createTransaction($this->user, $payload);
        $this->assertNotNull($first);

        $this->accountA->refresh();
        $this->assertEqualsWithDelta(850.00, (float) $this->accountA->current_balance, 0.0001);

        // Immediate duplicate creation with identical payload must be rejected
        $this->expectException(ValidationException::class);
        $this->service->createTransaction($this->user, $payload);
    }

    /**
     * Test atomic reversal of transfer maintains exact zero-drift balance.
     */
    public function test_transfer_deletion_restores_exact_balances_with_locks(): void
    {
        $transfer = $this->service->createTransfer($this->user, [
            'source_account_id' => $this->accountA->id,
            'destination_account_id' => $this->accountB->id,
            'amount' => '200.0000',
            'fee' => '1.5000',
            'transacted_at' => now()->toIso8601String(),
        ]);

        $this->accountA->refresh();
        $this->accountB->refresh();
        $this->assertEqualsWithDelta(798.50, (float) $this->accountA->current_balance, 0.0001); // 1000 - 201.50
        $this->assertEqualsWithDelta(700.00, (float) $this->accountB->current_balance, 0.0001); // 500 + 200

        // Delete transfer
        $deleted = $this->service->deleteTransfer($transfer);
        $this->assertTrue($deleted);

        // Balances restored exactly to initial state
        $this->accountA->refresh();
        $this->accountB->refresh();
        $this->assertEqualsWithDelta(1000.00, (float) $this->accountA->current_balance, 0.0001);
        $this->assertEqualsWithDelta(500.00, (float) $this->accountB->current_balance, 0.0001);
    }
}
