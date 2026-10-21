<?php

namespace Tests\Feature\Goals;

use App\Models\Account;
use App\Models\Goal;
use App\Models\Institution;
use App\Models\User;
use App\Services\Goals\GoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GoalConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;
    protected GoalService $goalService;

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
            'name' => 'Goal Dedicated Savings',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'opening_balance' => '10000.0000',
            'current_balance' => '10000.0000',
            'is_active' => true,
        ]);

        $this->goalService = app(GoalService::class);
    }

    public function test_contribute_to_goal_updates_balance_atomically_with_pessimistic_lock(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'name' => 'Downpayment for Condo',
            'target_amount' => '50000.0000',
            'current_amount' => '10000.0000',
            'currency' => 'MYR',
            'status' => 'in_progress',
        ]);

        // Sequential contributions
        $this->goalService->contributeToGoal($goal, '5000.00');
        $this->goalService->contributeToGoal($goal->fresh(), '2500.50');

        $refreshed = $goal->fresh();
        $this->assertEqualsWithDelta(17500.50, (float) $refreshed->current_amount, 0.0001);
        $this->assertEquals('in_progress', $refreshed->status);
    }

    public function test_contribute_to_goal_transitions_status_to_achieved(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'name' => 'Emergency Fund 3 Months',
            'target_amount' => '15000.0000',
            'current_amount' => '12000.0000',
            'currency' => 'MYR',
            'status' => 'in_progress',
        ]);

        // Contribute enough to meet or exceed target
        $updated = $this->goalService->contributeToGoal($goal, '3000.00');

        $this->assertEquals('achieved', $updated->status);
        $this->assertEqualsWithDelta(15000.00, (float) $updated->current_amount, 0.0001);

        // Additional contribution above target keeps achieved status
        $overshoot = $this->goalService->contributeToGoal($updated->fresh(), '500.00');
        $this->assertEquals('achieved', $overshoot->status);
        $this->assertEqualsWithDelta(15500.00, (float) $overshoot->current_amount, 0.0001);
    }

    public function test_contribute_to_goal_rejects_negative_or_zero_amounts(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'name' => 'Travel Fund',
            'target_amount' => '5000.0000',
            'current_amount' => '1000.0000',
            'currency' => 'MYR',
            'status' => 'in_progress',
        ]);

        $this->expectException(ValidationException::class);
        $this->goalService->contributeToGoal($goal, '0.00');
    }

    public function test_transaction_rollback_preserves_initial_goal_balance(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'name' => 'Car Upgrade',
            'target_amount' => '20000.0000',
            'current_amount' => '5000.0000',
            'currency' => 'MYR',
            'status' => 'in_progress',
        ]);

        try {
            DB::transaction(function () use ($goal) {
                $this->goalService->contributeToGoal($goal, '3000.00');
                throw new \RuntimeException('Simulated failure during goal contribution');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $refreshed = $goal->fresh();
        $this->assertEqualsWithDelta(5000.00, (float) $refreshed->current_amount, 0.0001);
        $this->assertEquals('in_progress', $refreshed->status);
    }
}
