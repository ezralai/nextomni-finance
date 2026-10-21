<?php

namespace App\Services\Budgets;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public function __construct(
        protected BudgetRepositoryInterface $budgetRepository
    ) {}

    /**
     * Create a new budget limit for a category and period.
     */
    public function createBudget(User $user, array $data): Budget
    {
        return DB::transaction(function () use ($user, $data) {
            $currency = $data['currency'] ?? $user->base_currency ?? 'MYR';
            $amountMoney = Money::of($data['amount'], $currency);

            if ($amountMoney->isZero() || $amountMoney->isNegative()) {
                throw ValidationException::withMessages([
                    'amount' => ['Budget amount must be strictly greater than 0.'],
                ]);
            }

            $startDate = Carbon::parse($data['start_date'])->startOfDay()->toDateString();
            $endDate = Carbon::parse($data['end_date'])->endOfDay()->toDateString();

            if ($endDate < $startDate) {
                throw ValidationException::withMessages([
                    'end_date' => ['Budget end date must not precede start date.'],
                ]);
            }

            // Check if budget for same category and period already exists
            $existing = $this->budgetRepository->findExisting(
                $user->id,
                $data['category_id'],
                $startDate,
                $endDate
            );

            if ($existing) {
                throw ValidationException::withMessages([
                    'category_id' => ['A budget for this category and date range is already active.'],
                ]);
            }

            return $this->budgetRepository->create([
                'user_id' => $user->id,
                'category_id' => $data['category_id'],
                'amount' => $amountMoney->getAmount(),
                'currency' => $currency,
                'period' => $data['period'] ?? 'monthly',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rollover_enabled' => (bool) ($data['rollover_enabled'] ?? false),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Update an existing budget.
     */
    public function updateBudget(Budget $budget, array $data): Budget
    {
        return DB::transaction(function () use ($budget, $data) {
            $payload = [];

            if (isset($data['amount'])) {
                $amountMoney = Money::of($data['amount'], $budget->currency);
                if ($amountMoney->isZero() || $amountMoney->isNegative()) {
                    throw ValidationException::withMessages([
                        'amount' => ['Budget amount must be strictly greater than 0.'],
                    ]);
                }
                $payload['amount'] = $amountMoney->getAmount();
            }

            if (isset($data['notes'])) {
                $payload['notes'] = $data['notes'];
            }

            if (isset($data['rollover_enabled'])) {
                $payload['rollover_enabled'] = (bool) $data['rollover_enabled'];
            }

            return $this->budgetRepository->update($budget, $payload);
        });
    }

    /**
     * Remove a budget.
     */
    public function deleteBudget(Budget $budget): bool
    {
        return $this->budgetRepository->delete($budget);
    }

    /**
     * Retrieve user budgets with real-time spending progress and threshold metrics.
     */
    public function getBudgetsProgress(User $user, array $filters = []): array
    {
        $date = $filters['date'] ?? Carbon::now()->toDateString();
        $filters['date'] = $date;

        $budgets = $this->budgetRepository->getForUser($user->id, $filters);
        $baseCurrency = $user->base_currency ?? 'MYR';

        $totalBudgetedMoney = Money::zero($baseCurrency);
        $totalSpentMoney = Money::zero($baseCurrency);

        $results = [];

        // Preload child categories in 1 query if not already eager-loaded
        $unloadedCategoryIds = $budgets->filter(function ($b) {
            return $b->category && ! $b->category->relationLoaded('children');
        })->pluck('category_id')->filter()->unique()->all();

        $childCategoriesMap = ! empty($unloadedCategoryIds)
            ? TransactionCategory::whereIn('parent_id', $unloadedCategoryIds)->where('is_active', true)->get(['id', 'parent_id'])->groupBy('parent_id')
            : collect();

        foreach ($budgets as $budget) {
            // Collect category and all of its subcategories for rollup aggregation
            $category = $budget->category;
            $categoryIds = [$budget->category_id];

            if ($category) {
                $childIds = $category->relationLoaded('children')
                    ? $category->children->pluck('id')->all()
                    : (isset($childCategoriesMap[$category->id]) ? $childCategoriesMap[$category->id]->pluck('id')->all() : []);

                $categoryIds = array_merge($categoryIds, $childIds);
            }

            // Sum up posted expenses in this window
            $spentTotal = Transaction::where('user_id', $user->id)
                ->where('type', 'expense')
                ->where('status', 'posted')
                ->whereIn('category_id', $categoryIds)
                ->whereBetween('transacted_at', [
                    Carbon::parse($budget->start_date)->startOfDay(),
                    Carbon::parse($budget->end_date)->endOfDay(),
                ])
                ->sum('amount') ?? '0.0000';

            $budgetMoney = Money::of($budget->amount, $budget->currency);
            $spentMoney = Money::of($spentTotal, $budget->currency);
            $remainingMoney = $budgetMoney->subtract($spentMoney);

            $spentFloat = (float) $spentMoney->toDecimal();
            $budgetFloat = (float) $budgetMoney->toDecimal();
            $percentUsed = $budgetFloat > 0 ? round(($spentFloat / $budgetFloat) * 100, 1) : 0;

            $status = 'healthy';
            if ($percentUsed >= 100) {
                $status = 'exceeded';
            } elseif ($percentUsed >= 80) {
                $status = 'warning';
            }

            $totalBudgetedMoney = $totalBudgetedMoney->add($budgetMoney);
            $totalSpentMoney = $totalSpentMoney->add($spentMoney);

            $results[] = [
                'budget' => $budget,
                'category' => $category,
                'amount' => $budgetMoney->toDecimal(),
                'amount_formatted' => $budgetMoney->format(),
                'spent' => $spentMoney->toDecimal(),
                'spent_formatted' => $spentMoney->format(),
                'remaining' => $remainingMoney->toDecimal(),
                'remaining_formatted' => $remainingMoney->format(),
                'percent_used' => $percentUsed,
                'is_over_budget' => $spentMoney->getAmount() > $budgetMoney->getAmount(),
                'status' => $status,
            ];
        }

        $totalRemainingMoney = $totalBudgetedMoney->subtract($totalSpentMoney);
        $overallBudgetFloat = (float) $totalBudgetedMoney->toDecimal();
        $overallSpentFloat = (float) $totalSpentMoney->toDecimal();
        $overallPercent = $overallBudgetFloat > 0 ? round(($overallSpentFloat / $overallBudgetFloat) * 100, 1) : 0;

        return [
            'budgets' => $results,
            'summary' => [
                'total_budgeted' => $totalBudgetedMoney->toDecimal(),
                'total_budgeted_formatted' => $totalBudgetedMoney->format(),
                'total_spent' => $totalSpentMoney->toDecimal(),
                'total_spent_formatted' => $totalSpentMoney->format(),
                'total_remaining' => $totalRemainingMoney->toDecimal(),
                'total_remaining_formatted' => $totalRemainingMoney->format(),
                'overall_percent_used' => $overallPercent,
                'currency' => $baseCurrency,
            ],
        ];
    }
}
