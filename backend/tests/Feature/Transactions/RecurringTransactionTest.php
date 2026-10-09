<?php

namespace Tests\Feature\Transactions;

use App\Models\Account;
use App\Models\Institution;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecurringTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;
    protected TransactionCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'base_currency' => 'MYR',
        ]);

        $institution = Institution::create([
            'name' => 'CIMB Bank',
            'code' => 'CIMB',
            'type' => 'bank',
            'country' => 'MYS',
            'is_active' => true,
        ]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $institution->id,
            'name' => 'CIMB Current Account',
            'account_type' => 'checking',
            'currency' => 'MYR',
            'current_balance' => '10000.0000',
            'available_balance' => '10000.0000',
            'is_active' => true,
        ]);

        $this->category = TransactionCategory::create([
            'name' => 'Internet & Utilities',
            'slug' => 'utilities',
            'type' => 'expense',
            'icon' => 'wifi',
            'color' => '#0ea5e9',
            'is_system' => true,
        ]);
    }

    public function test_user_can_create_recurring_transaction(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/recurring-transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'description' => 'Unifi Home Fibre 300Mbps',
            'amount' => '149.00',
            'frequency' => 'monthly',
            'start_date' => '2026-10-15',
            'auto_post' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'recurring_transaction' => [
                    'id',
                    'description',
                    'amount',
                    'amount_formatted',
                    'frequency',
                    'start_date',
                    'next_due_date',
                    'auto_post',
                ],
            ]);

        $this->assertDatabaseHas('recurring_transactions', [
            'user_id' => $this->user->id,
            'description' => 'Unifi Home Fibre 300Mbps',
            'frequency' => 'monthly',
        ]);

        $created = RecurringTransaction::where('user_id', $this->user->id)->first();
        $this->assertNotNull($created);
        $this->assertEquals('149.00', \App\Support\Money::of($created->amount, $created->currency)->toDecimal());
        $this->assertEquals('2026-10-15', $created->next_due_date->toDateString());
    }

    public function test_user_can_list_and_filter_recurring_transactions(): void
    {
        RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'description' => 'Netflix Premium 4K',
            'amount' => '55.0000',
            'currency' => 'MYR',
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-11-01',
            'auto_post' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/recurring-transactions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Netflix Premium 4K');
    }

    public function test_user_can_update_recurring_transaction(): void
    {
        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'description' => 'Gym Membership',
            'amount' => '180.0000',
            'currency' => 'MYR',
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-11-01',
            'auto_post' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/recurring-transactions/{$recurring->id}", [
            'amount' => '195.00',
            'frequency' => 'monthly',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('recurring_transaction.amount', '195.00');

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $recurring->id,
            'amount' => '195.0000',
        ]);
    }

    public function test_user_can_delete_recurring_transaction(): void
    {
        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'description' => 'Obsolete Magazine Subscription',
            'amount' => '25.0000',
            'currency' => 'MYR',
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-11-01',
            'auto_post' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/recurring-transactions/{$recurring->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('recurring_transactions', [
            'id' => $recurring->id,
        ]);
    }

    public function test_tenant_isolation_prevents_unauthorized_access(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::create([
            'user_id' => $otherUser->id,
            'institution_id' => $this->account->institution_id,
            'name' => 'Other Account',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'current_balance' => '500.0000',
            'available_balance' => '500.0000',
            'is_active' => true,
        ]);

        $otherRecurring = RecurringTransaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'type' => 'expense',
            'description' => 'Confidential Executive Retainer',
            'amount' => '5000.0000',
            'currency' => 'MYR',
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-11-01',
            'auto_post' => true,
            'is_active' => true,
        ]);

        // Attempt to view
        $viewResponse = $this->actingAs($this->user)->getJson("/api/v1/recurring-transactions/{$otherRecurring->id}");
        $viewResponse->assertStatus(404);

        // Attempt to update
        $updateResponse = $this->actingAs($this->user)->putJson("/api/v1/recurring-transactions/{$otherRecurring->id}", [
            'amount' => '1.00',
        ]);
        $updateResponse->assertStatus(404);
    }

    public function test_process_recurring_transactions_command_posts_ledger_entries_and_advances_due_date(): void
    {
        $today = Carbon::today()->toDateString();

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'description' => 'AWS Cloud Infrastructure Hosting',
            'amount' => '450.0000',
            'currency' => 'MYR',
            'frequency' => 'monthly',
            'start_date' => $today,
            'next_due_date' => $today,
            'auto_post' => true,
            'is_active' => true,
        ]);

        // Run artisan command
        Artisan::call('finance:process-recurring', [
            '--date' => $today,
        ]);

        // Check transaction posted into ledger
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'description' => '[Recurring] AWS Cloud Infrastructure Hosting',
        ]);

        // Check account balance deducted: 10000.00 - 450.00 = 9550.00
        $this->account->refresh();
        $this->assertEquals('9550.00', \App\Support\Money::of($this->account->current_balance, 'MYR')->toDecimal());

        // Check recurring transaction next_due_date advanced to next month
        $recurring->refresh();
        $expectedNextDue = Carbon::parse($today)->addMonthsNoOverflow(1)->toDateString();
        $this->assertEquals($expectedNextDue, $recurring->next_due_date->toDateString());
        $this->assertNotNull($recurring->last_posted_at);

        // Check audit log recorded for the posted transaction
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'auditable_type' => Transaction::class,
            'event' => 'transaction.created',
        ]);
    }

    public function test_recurring_transaction_expires_when_end_date_reached(): void
    {
        $today = Carbon::today()->toDateString();

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'description' => 'Temporary 1-Month Software Pilot',
            'amount' => '100.0000',
            'currency' => 'MYR',
            'frequency' => 'monthly',
            'start_date' => $today,
            'end_date' => $today, // ends today
            'next_due_date' => $today,
            'auto_post' => true,
            'is_active' => true,
        ]);

        // Run command
        Artisan::call('finance:process-recurring', [
            '--date' => $today,
        ]);

        $recurring->refresh();
        // Since next occurrence is next month which is > end_date, is_active is now false
        $this->assertFalse($recurring->is_active);
    }
}
