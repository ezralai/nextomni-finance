<?php

namespace Tests\Feature\Goals;

use App\Models\Account;
use App\Models\Goal;
use App\Models\Institution;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
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
            'name' => 'Public Bank',
            'code' => 'PBB',
            'type' => 'bank',
            'country' => 'MYS',
            'is_active' => true,
        ]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $institution->id,
            'name' => 'High-Yield Savings PB',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'current_balance' => '25000.0000',
            'available_balance' => '25000.0000',
            'is_active' => true,
        ]);
    }

    public function test_user_can_create_goal(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/goals', [
            'name' => 'Emergency Fund 6 Months',
            'description' => 'Essential living expenses reserve in high-yield account',
            'target_amount' => '30000.00',
            'current_amount' => '10000.00',
            'currency' => 'MYR',
            'target_date' => '2027-12-31',
            'account_id' => $this->account->id,
            'color' => '#10b981',
            'icon' => 'shield-check',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'goal' => [
                    'id',
                    'name',
                    'target_amount',
                    'target_amount_formatted',
                    'current_amount',
                    'current_amount_formatted',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('goals', [
            'user_id' => $this->user->id,
            'name' => 'Emergency Fund 6 Months',
            'status' => 'in_progress',
        ]);

        $goal = Goal::where('user_id', $this->user->id)->first();
        $this->assertEquals('30000.00', \App\Support\Money::of($goal->target_amount, 'MYR')->toDecimal());
        $this->assertEquals('10000.00', \App\Support\Money::of($goal->current_amount, 'MYR')->toDecimal());
    }

    public function test_user_can_list_goals_with_progress_and_monthly_pacing(): void
    {
        // 6 months from today: 180 days -> ceil(180 / 30.44) = 6 months
        $targetDate = Carbon::today()->addMonths(6)->toDateString();

        Goal::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'name' => 'House Down Payment Fund',
            'target_amount' => '12000.0000',
            'current_amount' => '3000.0000',
            'currency' => 'MYR',
            'target_date' => $targetDate,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/goals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'goals' => [
                    '*' => [
                        'goal',
                        'target_amount',
                        'current_amount',
                        'remaining_amount',
                        'percent_achieved',
                        'days_remaining',
                        'months_remaining',
                        'monthly_required',
                        'is_achieved',
                    ],
                ],
                'summary' => [
                    'total_goals',
                    'total_target',
                    'total_saved',
                    'overall_percent',
                ],
            ]);

        $goalData = $response->json('goals.0');
        $this->assertEquals(25.0, $goalData['percent_achieved']);
        $this->assertEquals('9000.00', $goalData['remaining_amount']);
        $this->assertEquals('1500.00', $goalData['monthly_required']);
        $this->assertFalse($goalData['is_achieved']);
    }

    public function test_user_can_contribute_to_goal_and_auto_mark_achieved(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'name' => 'Tokyo Holiday Fund',
            'target_amount' => '5000.0000',
            'current_amount' => '4500.0000',
            'currency' => 'MYR',
            'status' => 'in_progress',
        ]);

        // Contribute RM 600 -> Total RM 5,100 (surpasses 5,000 target)
        $response = $this->actingAs($this->user)->postJson("/api/v1/goals/{$goal->id}/contribute", [
            'amount' => '600.00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('goal.current_amount', '5100.00')
            ->assertJsonPath('goal.status', 'achieved');

        $goal->refresh();
        $this->assertEquals('achieved', $goal->status);
        $this->assertEquals('5100.00', \App\Support\Money::of($goal->current_amount, 'MYR')->toDecimal());
    }

    public function test_user_can_update_and_delete_goal(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'name' => 'New MacBook Pro M4',
            'target_amount' => '10000.0000',
            'current_amount' => '2000.0000',
            'currency' => 'MYR',
            'status' => 'in_progress',
        ]);

        // Update
        $updateResponse = $this->actingAs($this->user)->putJson("/api/v1/goals/{$goal->id}", [
            'name' => 'MacBook Pro Max M4',
            'target_amount' => '14000.00',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('goal.name', 'MacBook Pro Max M4')
            ->assertJsonPath('goal.target_amount', '14000.00');

        // Delete
        $delResponse = $this->actingAs($this->user)->deleteJson("/api/v1/goals/{$goal->id}");
        $delResponse->assertStatus(200);

        $this->assertDatabaseMissing('goals', ['id' => $goal->id]);
    }

    public function test_tenant_isolation_prevents_unauthorized_access(): void
    {
        $otherUser = User::factory()->create();
        $otherGoal = Goal::create([
            'user_id' => $otherUser->id,
            'name' => 'Confidential Investment Syndicate',
            'target_amount' => '100000.0000',
            'current_amount' => '50000.0000',
            'currency' => 'MYR',
            'status' => 'in_progress',
        ]);

        // Try viewing
        $viewResponse = $this->actingAs($this->user)->getJson("/api/v1/goals/{$otherGoal->id}");
        $viewResponse->assertStatus(404);

        // Try contributing
        $contributeResponse = $this->actingAs($this->user)->postJson("/api/v1/goals/{$otherGoal->id}/contribute", [
            'amount' => '500.00',
        ]);
        $contributeResponse->assertStatus(404);

        // Try deleting
        $delResponse = $this->actingAs($this->user)->deleteJson("/api/v1/goals/{$otherGoal->id}");
        $delResponse->assertStatus(404);
    }
}
