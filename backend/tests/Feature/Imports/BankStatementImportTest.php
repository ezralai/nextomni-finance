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

        $this->assertTrue(in_array($response->status(), [404, 422]));
    }

    public function test_import_automatically_classifies_merchant_categories(): void
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);

        $csvContent = implode("\n", [
            'Date,Description,Amount,Type',
            '2026-10-01,Starbucks Reserve Mid Valley,25.00,expense',
            '2026-10-02,GrabFood Delivery Order,42.50,expense',
            '2026-10-03,Petronas Mesra Station,80.00,expense',
            '2026-10-04,Netflix Premium Monthly,54.90,expense',
            '2026-10-05,Unifi Home Broadband,139.00,expense',
            '2026-10-06,Monthly Salary Transfer,8500.00,income',
            '2026-10-07,Generic Unmatched Reference,100.00,expense',
        ]);

        $file = UploadedFile::fake()->createWithContent('merchant_categorized.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/statements', [
            'account_id' => $this->account->id,
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_rows', 7)
            ->assertJsonPath('data.imported_count', 7)
            ->assertJsonPath('data.status', 'completed');

        // Verify Starbucks resolved to restaurants-cafes
        $starbucksCat = \App\Models\TransactionCategory::where('slug', 'restaurants-cafes')->first();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Starbucks Reserve Mid Valley',
            'category_id' => $starbucksCat->id,
        ]);

        // Verify GrabFood resolved to food-delivery
        $grabCat = \App\Models\TransactionCategory::where('slug', 'food-delivery')->first();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'GrabFood Delivery Order',
            'category_id' => $grabCat->id,
        ]);

        // Verify Petronas resolved to fuel-petrol
        $fuelCat = \App\Models\TransactionCategory::where('slug', 'fuel-petrol')->first();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Petronas Mesra Station',
            'category_id' => $fuelCat->id,
        ]);

        // Verify Netflix resolved to entertainment-subscriptions
        $subCat = \App\Models\TransactionCategory::where('slug', 'entertainment-subscriptions')->first();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Netflix Premium Monthly',
            'category_id' => $subCat->id,
        ]);

        // Verify Unifi resolved to home-broadband
        $broadbandCat = \App\Models\TransactionCategory::where('slug', 'home-broadband')->first();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Unifi Home Broadband',
            'category_id' => $broadbandCat->id,
        ]);

        // Verify Monthly Salary resolved to salary-wages
        $salaryCat = \App\Models\TransactionCategory::where('slug', 'salary-wages')->first();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Monthly Salary Transfer',
            'category_id' => $salaryCat->id,
        ]);

        // Verify generic unmatched description has null category_id without error
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Generic Unmatched Reference',
            'category_id' => null,
        ]);
    }

    public function test_import_reconciles_explicit_category_column_from_csv(): void
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);

        $csvContent = implode("\n", [
            'Date,Description,Amount,Type,Category',
            '2026-10-10,Custom Gym Membership,150.00,expense,Fitness & Sports',
            '2026-10-11,Gadget Accessory,99.00,expense,shopping-gadgets',
        ]);

        $file = UploadedFile::fake()->createWithContent('explicit_categories.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/statements', [
            'account_id' => $this->account->id,
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported_count', 2)
            ->assertJsonPath('data.status', 'completed');

        $gymCat = \App\Models\TransactionCategory::where('slug', 'fitness-sports')->first();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Custom Gym Membership',
            'category_id' => $gymCat->id,
        ]);

        $gadgetCat = \App\Models\TransactionCategory::where('slug', 'shopping-gadgets')->first();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Gadget Accessory',
            'category_id' => $gadgetCat->id,
        ]);
    }
}
