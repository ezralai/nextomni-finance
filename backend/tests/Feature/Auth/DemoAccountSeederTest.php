<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InstitutionSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(\Database\Seeders\ExchangeRateSeeder::class);
    }

    public function test_demo_account_seeder_populates_portfolio_and_authenticates(): void
    {
        // 1. Run seeder
        $this->seed(DemoAccountSeeder::class);

        // 2. Assert Demo User exists
        $user = User::where('email', 'demo@nextomni.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Demo Investor', $user->name);
        $this->assertEquals('MYR', $user->base_currency);

        // 3. Assert Multi-Asset Accounts
        $accounts = Account::withoutGlobalScopes()->where('user_id', $user->id)->get();
        $this->assertCount(6, $accounts);
        $this->assertTrue($accounts->contains('name', 'Maybank Premier Savings'));
        $this->assertTrue($accounts->contains('name', 'CIMB Visa Infinite'));
        $this->assertTrue($accounts->contains('name', "Touch 'n Go eWallet"));
        $this->assertTrue($accounts->contains('name', 'GrabPay Wallet'));
        $this->assertTrue($accounts->contains('name', 'Wise SGD Savings'));
        $this->assertTrue($accounts->contains('name', 'Interactive Brokers USD'));

        // 4. Assert Transactions, Budgets, Recurring Bills, Goals
        $this->assertGreaterThan(0, Transaction::withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertGreaterThan(0, Budget::withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertGreaterThan(0, RecurringTransaction::withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertEquals(2, Goal::withoutGlobalScopes()->where('user_id', $user->id)->count());

        // 5. Test Sanctum Authentication with demo credentials
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@nextomni.com',
            'password' => 'NextOmniDemo2026!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'base_currency'],
                'token',
            ]);

        $token = $loginResponse->json('token');

        // 6. Test querying accounts with bearer token
        $accountsResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/accounts');

        $accountsResponse->assertStatus(200);
        $accountsList = $accountsResponse->json('accounts');
        $this->assertCount(6, $accountsList);
        $this->assertNotNull($accountsResponse->json('summary.net_worth'));
    }

    public function test_demo_account_seeder_is_idempotent_on_consecutive_runs(): void
    {
        $this->seed(DemoAccountSeeder::class);
        $initialUserCount = User::count();
        $initialAccountCount = Account::withoutGlobalScopes()->count();
        $initialGoalCount = Goal::withoutGlobalScopes()->count();

        // Run seeder a second time
        $this->seed(DemoAccountSeeder::class);

        $this->assertEquals($initialUserCount, User::count());
        $this->assertEquals($initialAccountCount, Account::withoutGlobalScopes()->count());
        $this->assertEquals($initialGoalCount, Goal::withoutGlobalScopes()->count());
    }
}
