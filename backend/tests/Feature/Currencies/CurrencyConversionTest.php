<?php

namespace Tests\Feature\Currencies;

use App\Models\Account;
use App\Models\Institution;
use App\Models\User;
use Database\Seeders\ExchangeRateSeeder;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Institution $mbb;
    protected Institution $cimb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            InstitutionSeeder::class,
            ExchangeRateSeeder::class,
        ]);

        $this->user = User::factory()->create([
            'base_currency' => 'MYR',
        ]);
        $this->token = $this->user->createToken('test_token')->plainTextToken;

        $this->mbb = Institution::where('code', 'MBB')->firstOrFail();
        $this->cimb = Institution::where('code', 'CIMB')->firstOrFail();
    }

    public function test_user_can_retrieve_exchange_rates_for_base_currency(): void
    {
        $response = $this->getJson('/api/v1/currencies/rates?base=MYR');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'base_currency',
                'rates' => [
                    '*' => ['base_currency', 'target_currency', 'rate', 'effective_date'],
                ],
            ])
            ->assertJson([
                'base_currency' => 'MYR',
            ]);

        $this->assertGreaterThanOrEqual(4, count($response->json('rates')));
    }

    public function test_api_performs_direct_conversion_with_precision(): void
    {
        // 100 USD to MYR (Rate: 4.720000 => 472.00 MYR)
        $response = $this->postJson('/api/v1/currencies/convert', [
            'amount' => 100.00,
            'from_currency' => 'USD',
            'to_currency' => 'MYR',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'from_currency' => 'USD',
                'to_currency' => 'MYR',
                'rate' => '4.720000',
                'original_amount' => '100.00',
                'converted_amount' => '472.00',
                'converted_formatted' => 'RM 472.00',
            ]);
    }

    public function test_system_resolves_cross_currency_triangulation(): void
    {
        // Convert EUR to SGD
        $response = $this->postJson('/api/v1/currencies/convert', [
            'amount' => 50.00,
            'from_currency' => 'EUR',
            'to_currency' => 'SGD',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'from_currency' => 'EUR',
                'to_currency' => 'SGD',
            ]);

        $this->assertGreaterThan(0, (float) $response->json('converted_amount'));
    }

    public function test_multi_currency_accounts_are_consolidated_into_user_base_currency(): void
    {
        // 1. Maybank account in MYR: RM 10,000.00
        Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $this->mbb->id,
            'name' => 'Maybank MYR Savings',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '10000.0000',
            'current_balance' => '10000.0000',
        ]);

        // 2. Foreign account in SGD: SGD 2,000.00 (Rate: SGD -> MYR is 3.520000 => RM 7,040.00)
        Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $this->cimb->id,
            'name' => 'CIMB Singapore SGD Account',
            'account_type' => 'bank',
            'currency' => 'SGD',
            'opening_balance' => '2000.0000',
            'current_balance' => '2000.0000',
        ]);

        // 3. Foreign investment in USD: USD 1,000.00 (Rate: USD -> MYR is 4.720000 => RM 4,720.00)
        Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $this->mbb->id,
            'name' => 'US Brokerage USD Account',
            'account_type' => 'investment',
            'currency' => 'USD',
            'opening_balance' => '1000.0000',
            'current_balance' => '1000.0000',
        ]);

        // Expected Consolidated Net Worth in MYR:
        // 10,000.00 + 7,040.00 + 4,720.00 = RM 21,760.00
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/accounts');

        $response->assertStatus(200)
            ->assertJson([
                'summary' => [
                    'net_worth' => '21760.00',
                    'net_worth_formatted' => 'RM 21,760.00',
                    'currency' => 'MYR',
                ],
            ]);
    }

    public function test_same_currency_conversion_short_circuits_without_rate(): void
    {
        $response = $this->postJson('/api/v1/currencies/convert', [
            'amount' => 500.00,
            'from_currency' => 'MYR',
            'to_currency' => 'MYR',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'from_currency' => 'MYR',
                'to_currency' => 'MYR',
                'rate' => '1.000000',
                'converted_amount' => '500.00',
            ]);
    }
}
