<?php

namespace Tests\Feature\Imports;

use App\Models\Account;
use App\Models\Institution;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankStatementImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;

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
    }

    public function test_user_can_import_bank_statement_csv_and_update_balance(): void
    {
        $csvContent = implode("\n", [
            'Date,Description,Amount,Type',
            '2026-10-01,Client Retainer Invoice,3500.00,income',
            '2026-10-02,Coworking Space Rental,750.00,expense',
        ]);

        $file = UploadedFile::fake()->createWithContent('october_statement.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/statements', [
            'account_id' => $this->account->id,
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.imported_count', 2)
            ->assertJsonPath('data.duplicate_count', 0)
            ->assertJsonPath('data.status', 'completed');

        // Check account balance updated: 5000 + 3500 - 750 = 7750
        $this->account->refresh();
        $this->assertEquals('7750.00', Money::of($this->account->current_balance, 'MYR')->toDecimal());

        // Check transactions inserted
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Client Retainer Invoice',
            'type' => 'income',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Coworking Space Rental',
            'type' => 'expense',
        ]);

        // Check batch recorded in statement_imports
        $this->assertDatabaseHas('statement_imports', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'filename' => 'october_statement.csv',
            'imported_count' => 2,
        ]);
    }

    public function test_subsequent_statement_import_skips_duplicates_idempotently(): void
    {
        $csvContent = implode("\n", [
            'Date,Description,Amount,Type',
            '2026-10-01,Client Retainer Invoice,3500.00,income',
            '2026-10-02,Coworking Space Rental,750.00,expense',
        ]);

        $file1 = UploadedFile::fake()->createWithContent('october_statement.csv', $csvContent);

        // First import
        $this->actingAs($this->user)->postJson('/api/v1/imports/statements', [
            'account_id' => $this->account->id,
            'file' => $file1,
        ]);

        $this->account->refresh();
        $this->assertEquals('7750.00', Money::of($this->account->current_balance, 'MYR')->toDecimal());

        // Second import with identical content
        $file2 = UploadedFile::fake()->createWithContent('october_statement_duplicate.csv', $csvContent);
        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/statements', [
            'account_id' => $this->account->id,
            'file' => $file2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.imported_count', 0)
            ->assertJsonPath('data.duplicate_count', 2);

        // Verify balance was NOT touched on duplicate import
        $this->account->refresh();
        $this->assertEquals('7750.00', Money::of($this->account->current_balance, 'MYR')->toDecimal());
    }

    public function test_import_supports_debit_credit_columns_and_malaysian_date_format(): void
    {
        $csvContent = implode("\n", [
            'Transaction Date,Payee,Withdrawal,Deposit',
            '05/10/2026,Specialty Coffee,18.50,',
            '06/10/2026,Consulting Dividend,,2400.00',
        ]);

        $file = UploadedFile::fake()->createWithContent('mbb_format.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/statements', [
            'account_id' => $this->account->id,
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported_count', 2)
            ->assertJsonPath('data.duplicate_count', 0);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Specialty Coffee',
            'type' => 'expense',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Consulting Dividend',
            'type' => 'income',
        ]);
    }

    public function test_tenant_isolation_prevents_uploading_to_other_users_account(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::create([
            'user_id' => $otherUser->id,
            'institution_id' => $this->account->institution_id,
            'name' => 'Private Account',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'current_balance' => '1000.0000',
            'available_balance' => '1000.0000',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->createWithContent('fake.csv', "Date,Description,Amount\n2026-10-01,Test,10.00\n");

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/statements', [
            'account_id' => $otherAccount->id,
            'file' => $file,
        ]);

        $response->assertStatus(404);
    }
}
