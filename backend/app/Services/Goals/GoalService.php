<?php

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\User;
use App\Repositories\Contracts\GoalRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoalService
{
    public function __construct(
        protected GoalRepositoryInterface $goalRepository,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Retrieve user's goals decorated with progress metrics and pacing math.
     */
    public function getGoalsWithProgress(User $user, array $filters = []): array
    {
        $goals = $this->goalRepository->getForUser($user->id, $filters);
        $today = Carbon::today();

        $items = [];
        $totalTargetMoney = Money::zero($user->base_currency ?? 'MYR');
        $totalSavedMoney = Money::zero($user->base_currency ?? 'MYR');

        foreach ($goals as $goal) {
            $currency = $goal->currency ?? $user->base_currency ?? 'MYR';
            $targetMoney = Money::of($goal->target_amount, $currency);
            $currentMoney = Money::of($goal->current_amount, $currency);

            // Accumulate if in user base currency
            if ($currency === ($user->base_currency ?? 'MYR')) {
                $totalTargetMoney = $totalTargetMoney->add($targetMoney);
                $totalSavedMoney = $totalSavedMoney->add($currentMoney);
            }

            // Calculate progress percentage
            $percent = 0.0;
            if (! $targetMoney->isZero()) {
                $ratio = bcdiv($currentMoney->getAmount(), $targetMoney->getAmount(), 4);
                $percent = (float) bcmul($ratio, '100', 2);
                $percent = min(100.0, max(0.0, $percent));
            }

            // Calculate remaining amount
            $remaining = $targetMoney->isGreaterThan($currentMoney)
                ? $targetMoney->subtract($currentMoney)
                : Money::zero($currency);

            // Calculate pacing math
            $daysRemaining = null;
            $monthsRemaining = null;
            $monthlyRequired = Money::zero($currency);

            if ($goal->target_date) {
                $targetDate = Carbon::parse($goal->target_date);
                $daysRemaining = (int) $today->diffInDays($targetDate, false);

                if ($daysRemaining > 0) {
                    $monthsRemaining = max(1, (int) ceil($daysRemaining / 30.44));
                    if (! $remaining->isZero()) {
                        $monthlyAmount = bcdiv($remaining->getAmount(), (string) $monthsRemaining, 4);
                        $monthlyRequired = Money::of($monthlyAmount, $currency);
                    }
                } elseif ($daysRemaining === 0 && ! $remaining->isZero()) {
                    $monthlyRequired = $remaining;
                }
            }

            $isAchieved = $currentMoney->isGreaterThanOrEqualTo($targetMoney) && ! $targetMoney->isZero();

            $items[] = [
                'goal' => $goal,
                'target_amount' => $targetMoney->toDecimal(),
                'target_amount_formatted' => $targetMoney->format(),
                'current_amount' => $currentMoney->toDecimal(),
                'current_amount_formatted' => $currentMoney->format(),
                'remaining_amount' => $remaining->toDecimal(),
                'remaining_amount_formatted' => $remaining->format(),
                'percent_achieved' => $percent,
                'days_remaining' => $daysRemaining,
                'months_remaining' => $monthsRemaining,
                'monthly_required' => $monthlyRequired->toDecimal(),
                'monthly_required_formatted' => $monthlyRequired->format(),
                'is_achieved' => $isAchieved,
            ];
        }

        $overallPercent = 0.0;
        if (! $totalTargetMoney->isZero()) {
            $ratio = bcdiv($totalSavedMoney->getAmount(), $totalTargetMoney->getAmount(), 4);
            $overallPercent = (float) bcmul($ratio, '100', 2);
            $overallPercent = min(100.0, max(0.0, $overallPercent));
        }

        return [
            'goals' => $items,
            'summary' => [
                'total_goals' => count($items),
                'total_target' => $totalTargetMoney->toDecimal(),
                'total_target_formatted' => $totalTargetMoney->format(),
                'total_saved' => $totalSavedMoney->toDecimal(),
                'total_saved_formatted' => $totalSavedMoney->format(),
                'overall_percent' => $overallPercent,
                'currency' => $user->base_currency ?? 'MYR',
            ],
        ];
    }

    /**
     * Create a new financial goal.
     */
    public function createGoal(User $user, array $data): Goal
    {
        $currency = $data['currency'] ?? $user->base_currency ?? 'MYR';
        $targetMoney = Money::of($data['target_amount'], $currency);

        if ($targetMoney->isZero() || $targetMoney->isNegative()) {
            throw ValidationException::withMessages([
                'target_amount' => ['Target amount must be strictly greater than 0.'],
            ]);
        }

        $currentMoney = isset($data['current_amount'])
            ? Money::of($data['current_amount'], $currency)
            : Money::zero($currency);

        $goal = $this->goalRepository->create([
            'user_id' => $user->id,
            'account_id' => $data['account_id'] ?? null,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'target_amount' => $targetMoney->toDecimal(),
            'current_amount' => $currentMoney->toDecimal(),
            'currency' => $currency,
            'target_date' => ! empty($data['target_date']) ? Carbon::parse($data['target_date'])->toDateString() : null,
            'color' => $data['color'] ?? '#10b981',
            'icon' => $data['icon'] ?? 'target',
            'status' => $data['status'] ?? 'in_progress',
        ]);

        $this->auditLogService->log($user, 'goal.created', $goal, null, $goal->toArray());

        return $goal;
    }

    /**
     * Update an existing goal.
     */
    public function updateGoal(Goal $goal, array $data): Goal
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['target_amount'])) {
            $money = Money::of($data['target_amount'], $goal->currency);
            if ($money->isZero() || $money->isNegative()) {
                throw ValidationException::withMessages([
                    'target_amount' => ['Target amount must be strictly greater than 0.'],
                ]);
            }
            $updateData['target_amount'] = $money->toDecimal();
        }

        if (isset($data['current_amount'])) {
            $money = Money::of($data['current_amount'], $goal->currency);
            $updateData['current_amount'] = $money->toDecimal();
        }

        if (array_key_exists('account_id', $data)) {
            $updateData['account_id'] = $data['account_id'];
        }

        if (array_key_exists('target_date', $data)) {
            $updateData['target_date'] = ! empty($data['target_date']) ? Carbon::parse($data['target_date'])->toDateString() : null;
        }

        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }

        if (isset($data['icon'])) {
            $updateData['icon'] = $data['icon'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        $oldValues = $goal->toArray();
        $updated = $this->goalRepository->update($goal, $updateData);

        if ($goal->user) {
            $this->auditLogService->log($goal->user, 'goal.updated', $updated, $oldValues, $updated->toArray());
        }

        return $updated;
    }

    /**
     * Delete a goal.
     */
    public function deleteGoal(Goal $goal): bool
    {
        $oldValues = $goal->toArray();
        $user = $goal->user;

        $deleted = $this->goalRepository->delete($goal);

        if ($deleted && $user) {
            $this->auditLogService->log($user, 'goal.deleted', $goal, $oldValues, null);
        }

        return $deleted;
    }

    /**
     * Record a milestone contribution toward the savings goal with atomic concurrency locking.
     */
    public function contributeToGoal(Goal $goal, string|float $amount): Goal
    {
        return DB::transaction(function () use ($goal, $amount) {
            $lockedGoal = Goal::where('id', $goal->id)
                ->lockForUpdate()
                ->findOrFail($goal->id);

            $oldValues = $lockedGoal->toArray();

            $contribution = Money::of($amount, $lockedGoal->currency);
            if ($contribution->isZero() || $contribution->isNegative()) {
                throw ValidationException::withMessages([
                    'amount' => ['Contribution amount must be strictly greater than 0.'],
                ]);
            }

            $current = Money::of($lockedGoal->current_amount, $lockedGoal->currency);
            $newCurrent = $current->add($contribution);

            $updateData = [
                'current_amount' => $newCurrent->toDecimal(),
            ];

            $target = Money::of($lockedGoal->target_amount, $lockedGoal->currency);
            if ($newCurrent->isGreaterThanOrEqualTo($target)) {
                $updateData['status'] = 'achieved';
            }

            $updatedGoal = $this->goalRepository->update($lockedGoal, $updateData);

            if ($lockedGoal->user) {
                $this->auditLogService->log(
                    $lockedGoal->user,
                    'goal.contributed',
                    $updatedGoal,
                    $oldValues,
                    array_merge($updatedGoal->toArray(), ['contribution_amount' => $contribution->toDecimal()])
                );
            }

            return $updatedGoal;
        });
    }
}
