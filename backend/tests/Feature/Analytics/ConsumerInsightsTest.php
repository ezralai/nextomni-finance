<?php

namespace Tests\Feature\Analytics;

use App\Models\Account;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumerInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Account $account;
    protected TransactionCategory $diningCategory;
    protected TransactionCategory $transportCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'base_currency' => 'MYR',
        ]);
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $institution = Institution::create([
            'code' => 'MBB',
            'name' => 'Maybank',
            'type' => 'bank',
            'country' => 'MYS',
            'supported_currencies' => ['MYR'],
            'is_active' => true,
        ]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $institution->id,
            'name' => 'Primary Maybank',
            'account_type' => 'bank',
            'currency' => 'MYR',
            'opening_balance' => '5000.00',
            'current_balance' => '5000.00',
            'is_active' => true,
        ]);

        $this->diningCategory = TransactionCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Dining Out & Mamak',
            'slug' => 'dining-out-mamak',
            'type' => 'expense',
            'icon' => 'heroicons:utensils',
            'color' => '#f97316',
        ]);

        $this->transportCategory = TransactionCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Petrol & Tolls',
            'slug' => 'petrol-tolls',
            'type' => 'expense',
            'icon' => 'heroicons:truck',
            'color' => '#0ea5e9',
        ]);
    }

    public function test_can_fetch_malaysian_consumer_insights(): void
    {
        // Populate 90 days sample transactions
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->diningCategory->id,
            'type' => 'expense',
            'amount' => '45.00',
            'currency' => 'MYR',
            'description' => 'Mamak Teh Tarik & Roti Canai',
            'transacted_at' => Carbon::now()->subDays(5),
            'status' => 'posted',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->transportCategory->id,
            'type' => 'expense',
            'amount' => '80.00',
            'currency' => 'MYR',
            'description' => 'Petronas Primax 95 Fuel',
            'transacted_at' => Carbon::now()->subDays(2),
            'status' => 'posted',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/analytics/malaysian-insights');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.consent_granted', true)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'consent_granted',
                    'state_code',
                    'income_tier',
                    'user_persona' => [
                        'id',
                        'title',
                        'badge',
                        'description',
                        'traits',
                        'confidence',
                    ],
                    'payday_dynamics' => [
                        'detected_cycle',
                        'surge_velocity_ratio',
                        'recommendation',
                    ],
                    'demographic_benchmark' => [
                        'cohort_label',
                        'categories',
                    ],
                ],
            ]);
    }

    public function test_can_update_pdpa_consent_preferences(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/analytics/malaysian-insights/consent', [
                'is_opted_in' => false,
                'state_code' => 'MY-14', // KL
                'income_tier' => 'T20',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_opted_in', false)
            ->assertJsonPath('data.state_code', 'MY-14')
            ->assertJsonPath('data.income_tier', 'T20');

        // Subsequent get request should reflect disabled consent
        $getRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/analytics/malaysian-insights');

        $getRes->assertStatus(200)
            ->assertJsonPath('data.consent_granted', false);
    }
}
