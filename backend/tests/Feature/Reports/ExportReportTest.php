<?php

namespace Tests\Feature\Reports;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportReportTest extends TestCase
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
            'name' => 'Maybank',
            'code' => 'MBB',
            'type' => 'bank',
            'country' => 'MYS',
            'is_active' => true,
        ]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $institution->id,
            'name' => 'Maybank Premier Savings',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'current_balance' => '5000.0000',
            'available_balance' => '5000.0000',
            'is_active' => true,
        ]);

        $this->category = TransactionCategory::create([
            'name' => 'Salary',
            'slug' => 'salary',
            'type' => 'income',
            'icon' => 'briefcase',
            'color' => '#10b981',
            'is_system' => true,
        ]);
    }

    public function test_user_can_stream_csv_transaction_export_with_formula_sanitization(): void
    {
        // Add normal transaction
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'income',
            'amount' => '6500.0000',
            'currency' => 'MYR',
            'status' => 'posted',
            'description' => 'October Salary',
            'notes' => 'Monthly compensation',
            'transacted_at' => now(),
            'deduplication_hash' => hash('sha256', 'oct-salary'),
        ]);

        // Add potentially dangerous formula injection description (CWE-1236)
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => '120.0000',
            'currency' => 'MYR',
            'status' => 'posted',
            'description' => '=SUM(A1:A10)',
            'notes' => '+cmd|/C calc!A0',
            'transacted_at' => now(),
            'deduplication_hash' => hash('sha256', 'malicious-formula'),
        ]);

        $response = $this->actingAs($this->user)
            ->get('/api/v1/reports/transactions/csv');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // Check header row presence
        $this->assertStringContainsString('Transaction ID', $content);
        $this->assertStringContainsString('Date & Time', $content);
        $this->assertStringContainsString('Account Type', $content);
        $this->assertStringContainsString('October Salary', $content);
        $this->assertStringContainsString('Maybank Premier Savings', $content);

        // Check formula sanitization prepends single quote (')
        $this->assertStringContainsString("'=SUM(A1:A10)", $content);
        $this->assertStringContainsString("'+cmd|/C calc!A0", $content);
    }

    public function test_user_can_export_structured_json_statement_with_accurate_summary(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'income',
            'amount' => '8000.0000',
            'currency' => 'MYR',
            'status' => 'posted',
            'description' => 'Consulting Dividend',
            'transacted_at' => now(),
            'deduplication_hash' => hash('sha256', 'dividend-1'),
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => '2500.5000',
            'currency' => 'MYR',
            'status' => 'posted',
            'description' => 'Office Rent',
            'transacted_at' => now(),
            'deduplication_hash' => hash('sha256', 'rent-1'),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/transactions/json');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'statement_metadata' => [
                    'platform',
                    'specification',
                    'generated_at',
                    'user' => ['id', 'name', 'email', 'base_currency'],
                    'total_records',
                ],
                'financial_summary' => [
                    'total_inflows',
                    'total_inflows_formatted',
                    'total_outflows',
                    'total_outflows_formatted',
                    'net_cash_flow',
                    'net_cash_flow_formatted',
                    'currency',
                ],
                'transactions' => [
                    '*' => [
                        'id',
                        'transacted_at',
                        'type',
                        'amount',
                        'currency',
                        'status',
                        'description',
                        'account',
                        'category',
                    ],
                ],
            ]);

        $data = $response->json();
        $this->assertEquals(2, $data['statement_metadata']['total_records']);
        $this->assertEquals('8000.00', $data['financial_summary']['total_inflows']);
        $this->assertEquals('2500.50', $data['financial_summary']['total_outflows']);
        $this->assertEquals('5499.50', $data['financial_summary']['net_cash_flow']);
    }

    public function test_tenant_isolation_prevents_viewing_other_users_transactions(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::create([
            'user_id' => $otherUser->id,
            'institution_id' => $this->account->institution_id,
            'name' => 'Foreign Account',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'current_balance' => '1000.0000',
            'available_balance' => '1000.0000',
            'is_active' => true,
        ]);

        Transaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'category_id' => $this->category->id,
            'type' => 'income',
            'amount' => '99999.0000',
            'currency' => 'MYR',
            'status' => 'posted',
            'description' => 'Other User Private Inflow',
            'transacted_at' => now(),
            'deduplication_hash' => hash('sha256', 'other-user-private'),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/transactions/json');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(0, $data['statement_metadata']['total_records']);
        $this->assertEmpty($data['transactions']);
    }

    public function test_financial_operations_record_audit_trail_events(): void
    {
        // 1. Create transaction via API
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'income',
            'amount' => '3200.00',
            'description' => 'Consulting Payment',
            'transacted_at' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $txnId = $response->json('transaction.id');

        // Verify audit log created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'auditable_type' => Transaction::class,
            'auditable_id' => $txnId,
            'event' => 'transaction.created',
        ]);

        // 2. Delete transaction via API
        $delResponse = $this->actingAs($this->user)->deleteJson("/api/v1/transactions/{$txnId}");
        $delResponse->assertStatus(200);

        // Verify audit log for deletion created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'auditable_type' => Transaction::class,
            'auditable_id' => $txnId,
            'event' => 'transaction.deleted',
        ]);
    }
}
