<?php

namespace App\Services\Analytics;

use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Compute comprehensive cash flow metrics and category breakdowns for a given period.
     */
    public function getCashFlow(User $user, ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfMonth();
        $currency = $user->base_currency ?? 'MYR';

        $transactions = Transaction::where('user_id', $user->id)
            ->where('status', 'posted')
            ->whereBetween('transacted_at', [$start, $end])
            ->with('category')
            ->orderBy('transacted_at')
            ->get();

        $incomeMoney = Money::zero($currency);
        $expenseMoney = Money::zero($currency);
        $categoryTotals = [];
        $dailyTotals = [];

        foreach ($transactions as $txn) {
            $amountMoney = Money::of($txn->amount, $txn->currency);

            if ($txn->type === 'income') {
                $incomeMoney = $incomeMoney->add($amountMoney);
            } elseif ($txn->type === 'expense') {
                $expenseMoney = $expenseMoney->add($amountMoney);

                // Category Breakdown
                $categoryName = $txn->category?->name ?? 'Uncategorized';
                $categoryColor = $txn->category?->color ?? '#64748b';
                $categoryIcon = $txn->category?->icon ?? 'heroicons:tag';

                if (! isset($categoryTotals[$categoryName])) {
                    $categoryTotals[$categoryName] = [
                        'name' => $categoryName,
                        'color' => $categoryColor,
                        'icon' => $categoryIcon,
                        'amount_money' => Money::zero($currency),
                    ];
                }
                $categoryTotals[$categoryName]['amount_money'] = $categoryTotals[$categoryName]['amount_money']->add($amountMoney);

                // Daily Trend
                $dateKey = $txn->transacted_at->toDateString();
                if (! isset($dailyTotals[$dateKey])) {
                    $dailyTotals[$dateKey] = Money::zero($currency);
                }
                $dailyTotals[$dateKey] = $dailyTotals[$dateKey]->add($amountMoney);
            }
        }

        $netCashFlowMoney = $incomeMoney->subtract($expenseMoney);

        // Savings Rate calculation
        $incomeFloat = (float) $incomeMoney->toDecimal();
        $expenseFloat = (float) $expenseMoney->toDecimal();
        $netFloat = (float) $netCashFlowMoney->toDecimal();
        $savingsRate = $incomeFloat > 0 ? round(($netFloat / $incomeFloat) * 100, 1) : 0;

        // Format category breakdown with percentages
        $categoriesFormatted = [];
        foreach ($categoryTotals as $cat) {
            $catAmountFloat = (float) $cat['amount_money']->toDecimal();
            $percentage = $expenseFloat > 0 ? round(($catAmountFloat / $expenseFloat) * 100, 1) : 0;

            $categoriesFormatted[] = [
                'name' => $cat['name'],
                'color' => $cat['color'],
                'icon' => $cat['icon'],
                'amount' => $cat['amount_money']->toDecimal(),
                'amount_formatted' => $cat['amount_money']->format(),
                'percentage' => $percentage,
            ];
        }

        // Sort categories by highest spend
        usort($categoriesFormatted, fn ($a, $b) => $b['percentage'] <=> $a['percentage']);

        // Format daily trend
        $dailyFormatted = [];
        foreach ($dailyTotals as $date => $money) {
            $dailyFormatted[] = [
                'date' => $date,
                'amount' => $money->toDecimal(),
                'amount_formatted' => $money->format(),
            ];
        }

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'summary' => [
                'total_income' => $incomeMoney->toDecimal(),
                'total_income_formatted' => $incomeMoney->format(),
                'total_expense' => $expenseMoney->toDecimal(),
                'total_expense_formatted' => $expenseMoney->format(),
                'net_cash_flow' => $netCashFlowMoney->toDecimal(),
                'net_cash_flow_formatted' => $netCashFlowMoney->format(),
                'savings_rate' => $savingsRate,
                'currency' => $currency,
            ],
            'categories' => $categoriesFormatted,
            'daily_trend' => $dailyFormatted,
        ];
    }
}
