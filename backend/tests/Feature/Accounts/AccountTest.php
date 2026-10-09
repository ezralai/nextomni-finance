<?php

namespace Tests\Feature\Accounts;

use App\Models\Account;
use App\Models\Institution;
use App\Models\User;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InstitutionSeeder::class);
        $this->institution = Institution::where('code', 'MBB')->firstOrFail();

        $this->user = User::factory()->create([
            'base_currency' => 'MYR',
        ]);
        $this->token = $this->user->createToken('test_token')->plainTextToken;
    }

    public function test_user_can_list_supported_institutions(): void
    {
        $response = $this->getJson('/api/v1/institutions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code', 'country', 'is_active'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(8, count($response->json('data')));
    }

    public function test_authenticated_user_can_create_account_with_precision_balance(): void
    {
        $payload = [
            'institution_id' => $this->institution->id,
            'name' => 'Maybank Premier Savings',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => 12500.50,
            'account_number_masked' => '••••5678',
            'notes' => 'Primary salary savings account',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/accounts', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'account' => [
                    'id',
                    'user_id',
                    'institution_id',
                    'name',
                    'account_type',
                    'currency',
                    'opening_balance',
                    'current_balance',
                    'current_balance_formatted',
                    'institution' => ['name', 'code'],
                ],
            ])
            ->assertJson([
                'account' => [
                    'name' => 'Maybank Premier Savings',
                    'account_type' => 'bank',
                    'currency' => 'MYR',
                    'opening_balance' => '12500.50',
                    'current_balance' => '12500.50',
                    'current_balance_formatted' => 'RM 12,500.50',
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $this->user->id,
            'name' => 'Maybank Premier Savings',
            'opening_balance' => '12500.5000',
            'current_balance' => '12500.5000',
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $response->json('account.id'));
    }

    public function test_account_overview_aggregates_net_worth_with_liabilities_accurately(): void
    {
        $cimb = Institution::where('code', 'CIMB')->firstOrFail();
        $tng = Institution::where('code', 'TNG')->firstOrFail();

        // 1. Bank asset: +RM 10,000.00
        $this->withHeader('Authorization', 'Bearer ' . $this->token)->postJson('/api/v1/accounts', [
            'institution_id' => $this->institution->id,
            'name' => 'Maybank Savings',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => 10000.00,
        ]);

        // 2. E-wallet asset: +RM 850.50
        $this->withHeader('Authorization', 'Bearer ' . $this->token)->postJson('/api/v1/accounts', [
            'institution_id' => $tng->id,
            'name' => 'Touch n Go eWallet',
            'account_type' => 'e_wallet',
            'currency' => 'MYR',
            'opening_balance' => 850.50,
        ]);

        // 3. Credit Card liability: -RM 2,350.25
        $this->withHeader('Authorization', 'Bearer ' . $this->token)->postJson('/api/v1/accounts', [
            'institution_id' => $cimb->id,
            'name' => 'CIMB Visa Signature',
            'account_type' => 'credit_card',
            'currency' => 'MYR',
            'opening_balance' => 2350.25,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/accounts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'accounts',
                'summary' => [
                    'net_worth',
                    'net_worth_formatted',
                    'currency',
                    'breakdown' => ['bank', 'e_wallet', 'credit_card', 'cash'],
                ],
            ]);

        // Net worth calculation: 10000.00 + 850.50 - 2350.25 = 8500.25
        $this->assertEquals('8500.25', $response->json('summary.net_worth'));
        $this->assertEquals('RM 8,500.25', $response->json('summary.net_worth_formatted'));
        $this->assertEquals('10000.00', $response->json('summary.breakdown.bank'));
        $this->assertEquals('8500.25', $response->json('summary.net_worth'));
    }

    public function test_user_cannot_access_another_users_account(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id,
            'institution_id' => $this->institution->id,
            'name' => 'Private Account of Other User',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '50000.0000',
            'current_balance' => '50000.0000',
        ]);

        // Attempt to view other user's account
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/accounts/' . $otherAccount->id);

        // TenantScope or Policy will return 404 or 403
        $this->assertContains($response->status(), [403, 404]);

        // Attempt to update other user's account
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/accounts/' . $otherAccount->id, [
                'name' => 'Compromised Name',
            ]);
        $this->assertContains($updateResponse->status(), [403, 404]);

        // Attempt to delete other user's account
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/accounts/' . $otherAccount->id);
        $this->assertContains($deleteResponse->status(), [403, 404]);
    }

    public function test_user_can_update_own_account(): void
    {
        $account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $this->institution->id,
            'name' => 'Old Account Name',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '1000.0000',
            'current_balance' => '1000.0000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/accounts/' . $account->id, [
                'name' => 'Renamed Account',
                'account_number_masked' => '••••1234',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'account' => [
                    'name' => 'Renamed Account',
                    'account_number_masked' => '••••1234',
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Renamed Account',
        ]);
    }

    public function test_user_can_delete_own_account(): void
    {
        $account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $this->institution->id,
            'name' => 'To Be Deleted',
            'account_type' => 'cash',
            'currency' => 'MYR',
            'opening_balance' => '100.0000',
            'current_balance' => '100.0000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/accounts/' . $account->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account deleted successfully.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_account_creation_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/accounts', [
                'name' => '',
                'account_type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['institution_id', 'name', 'account_type', 'opening_balance']);
    }
}
