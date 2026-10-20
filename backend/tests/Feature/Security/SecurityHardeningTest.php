<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\Reports\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Account $userAccount;
    protected Account $otherAccount;
    protected TransactionCategory $globalCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['base_currency' => 'MYR']);
        $this->otherUser = User::factory()->create(['base_currency' => 'MYR']);

        $institution = Institution::create([
            'name' => 'Maybank',
            'code' => 'MBB',
            'country' => 'MYS',
            'is_active' => true,
        ]);

        $this->userAccount = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $institution->id,
            'name' => 'User Main Account',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'current_balance' => 5000.00,
        ]);

        $this->otherAccount = Account::create([
            'user_id' => $this->otherUser->id,
            'institution_id' => $institution->id,
            'name' => 'Other Tenant Account',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'current_balance' => 9000.00,
        ]);

        $this->globalCategory = TransactionCategory::create([
            'name' => 'Groceries & Food',
            'slug' => 'groceries-food',
            'type' => 'expense',
            'is_active' => true,
            'user_id' => null, // Global
        ]);
    }

    /**
     * Test 1: Defense-in-depth HTTP security headers are attached to API responses.
     */
    public function test_api_responses_contain_owasp_security_headers(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertTrue($response->headers->has('Permissions-Policy'));
    }

    /**
     * Test 2: Stock market endpoints reject malicious ticker symbols with 422.
     */
    public function test_stock_market_rejects_malformed_or_injection_symbols(): void
    {
        $maliciousSymbols = [
            'AAPL;DROP TABLE',
            'alert(1)',
            '1155 KL',
            'INV@LID!',
            'VERY_LONG_SYMBOL_THAT_EXCEEDS_MAX_LENGTH_1234567890',
        ];

        foreach ($maliciousSymbols as $symbol) {
            $quoteResponse = $this->getJson('/api/v1/markets/quote/' . urlencode($symbol));
            $quoteResponse->assertStatus(422)
                ->assertJson(['status' => 'error']);

            $chartResponse = $this->getJson('/api/v1/markets/chart/' . urlencode($symbol));
            $chartResponse->assertStatus(422)
                ->assertJson(['status' => 'error']);
        }
    }

    /**
     * Test 3: Valid stock symbols (Bursa 4-digit, .KL, US, indices) pass validation.
     */
    public function test_valid_stock_symbols_pass_validation(): void
    {
        $validSymbols = ['1155', '1155.KL', 'AAPL', 'BRK-B', '^KLSE'];

        foreach ($validSymbols as $symbol) {
            $response = $this->getJson("/api/v1/markets/quote/{$symbol}");
            $this->assertNotEquals(422, $response->status());
        }
    }

    /**
     * Test 4: Tenant isolation - User cannot record transaction against another tenant's account.
     */
    public function test_user_cannot_create_transaction_for_another_tenant_account(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->otherAccount->id, // Belongs to otherUser
            'category_id' => $this->globalCategory->id,
            'type' => 'expense',
            'amount' => 150.00,
            'description' => 'Unauthorized debit attempt',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /**
     * Test 5: Tenant isolation - User cannot execute transfer involving another tenant's account.
     */
    public function test_user_cannot_transfer_to_or_from_another_tenant_account(): void
    {
        // Second account for user
        $userSecondAccount = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $this->userAccount->institution_id,
            'name' => 'User E-Wallet',
            'account_type' => 'e_wallet',
            'currency' => 'MYR',
            'current_balance' => 200.00,
        ]);

        // Attempt transfer where destination belongs to other tenant
        $response = $this->actingAs($this->user)->postJson('/api/v1/transfers', [
            'source_account_id' => $userSecondAccount->id,
            'destination_account_id' => $this->otherAccount->id,
            'amount' => 50.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_account_id']);
    }

    /**
     * Test 6: Tenant isolation - User cannot use a private category owned by another user.
     */
    public function test_user_cannot_use_another_users_private_custom_category(): void
    {
        $otherUserCategory = TransactionCategory::create([
            'name' => 'Other Tenant Secret Category',
            'slug' => 'other-tenant-secret',
            'type' => 'expense',
            'is_active' => true,
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->userAccount->id,
            'category_id' => $otherUserCategory->id,
            'type' => 'expense',
            'amount' => 75.00,
            'description' => 'Expense with invalid category',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    /**
     * Test 7: CSV formula injection characters (=, +, -, @, |, %) are escaped with single quote.
     */
    public function test_csv_export_neutralizes_formula_injection_attempts(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->userAccount->id,
            'category_id' => $this->globalCategory->id,
            'type' => 'expense',
            'amount' => '100.00',
            'currency' => 'MYR',
            'description' => '=cmd|\' /C calc\'!A0',
            'notes' => '@SUM(1+1)*cmd',
            'transacted_at' => now(),
            'status' => 'posted',
        ]);

        $exportService = app(ExportService::class);
        $streamedResponse = $exportService->exportTransactionsCsv($this->user);

        ob_start();
        $streamedResponse->sendContent();
        $csvContent = ob_get_clean();

        // Formula prefix triggers should be escaped by prefixing single quote
        $this->assertStringContainsString("'=cmd", $csvContent);
        $this->assertStringContainsString("'@SUM", $csvContent);
        $this->assertStringNotContainsString("\n=cmd", $csvContent);
    }
}
