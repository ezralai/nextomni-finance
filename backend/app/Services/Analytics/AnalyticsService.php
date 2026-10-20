<?php

namespace App\Services\Analytics;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccountService;
use App\Support\Money;
use Carbon\Carbon;

class AnalyticsService
{
    public function __construct(
        protected ?AccountService $accountService = null
    ) {}

    /**
     * Compute comprehensive cash flow metrics, liquidity runway, merchant concentration,
     * behavioral spending patterns, and statistical anomaly detection.
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
        $merchantsMap = [];
        $categoryTransactions = [];

        $weekdayExpense = Money::zero($currency);
        $weekendExpense = Money::zero($currency);
        $weekdayDays = 0;
        $weekendDays = 0;

        // Count calendar days in period
        $periodDays = max(1, $start->diffInDays($end) + 1);

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

                // Store for statistical anomaly evaluation
                if (! isset($categoryTransactions[$categoryName])) {
                    $categoryTransactions[$categoryName] = [];
                }
                $categoryTransactions[$categoryName][] = $txn;

                // Daily Trend
                $dateKey = $txn->transacted_at->toDateString();
                if (! isset($dailyTotals[$dateKey])) {
                    $dailyTotals[$dateKey] = Money::zero($currency);
                }
                $dailyTotals[$dateKey] = $dailyTotals[$dateKey]->add($amountMoney);

                // Top Merchant Concentration
                $merchantName = trim($txn->counterparty ?: $txn->description);
                if (! $merchantName) {
                    $merchantName = 'General Merchant';
                }
                if (! isset($merchantsMap[$merchantName])) {
                    $merchantsMap[$merchantName] = [
                        'name' => $merchantName,
                        'count' => 0,
                        'amount_money' => Money::zero($currency),
                    ];
                }
                $merchantsMap[$merchantName]['count']++;
                $merchantsMap[$merchantName]['amount_money'] = $merchantsMap[$merchantName]['amount_money']->add($amountMoney);

                // Weekday vs Weekend Split
                if ($txn->transacted_at->isWeekend()) {
                    $weekendExpense = $weekendExpense->add($amountMoney);
                } else {
                    $weekdayExpense = $weekdayExpense->add($amountMoney);
                }
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

        // Top Merchants
        $topMerchants = [];
        foreach ($merchantsMap as $m) {
            $mAmountFloat = (float) $m['amount_money']->toDecimal();
            $mPercent = $expenseFloat > 0 ? round(($mAmountFloat / $expenseFloat) * 100, 1) : 0;
            $topMerchants[] = [
                'name' => $m['name'],
                'count' => $m['count'],
                'amount' => $m['amount_money']->toDecimal(),
                'amount_formatted' => $m['amount_money']->format(),
                'percentage' => $mPercent,
            ];
        }
        usort($topMerchants, fn ($a, $b) => (float) $b['amount'] <=> (float) $a['amount']);
        $topMerchants = array_slice($topMerchants, 0, 8);

        // Weekday vs Weekend Analysis
        $weekdayFloat = (float) $weekdayExpense->toDecimal();
        $weekendFloat = (float) $weekendExpense->toDecimal();
        $totalWeekendDays = 0;
        $totalWeekdayDays = 0;
        $currentCursor = $start->copy();
        while ($currentCursor->lte($end)) {
            if ($currentCursor->isWeekend()) {
                $totalWeekendDays++;
            } else {
                $totalWeekdayDays++;
            }
            $currentCursor->addDay();
        }

        $spendingPatterns = [
            'weekday_expense' => $weekdayExpense->toDecimal(),
            'weekday_formatted' => $weekdayExpense->format(),
            'weekday_percent' => $expenseFloat > 0 ? round(($weekdayFloat / $expenseFloat) * 100, 1) : 0,
            'weekday_daily_avg' => $totalWeekdayDays > 0 ? round($weekdayFloat / $totalWeekdayDays, 2) : 0,
            'weekend_expense' => $weekendExpense->toDecimal(),
            'weekend_formatted' => $weekendExpense->format(),
            'weekend_percent' => $expenseFloat > 0 ? round(($weekendFloat / $expenseFloat) * 100, 1) : 0,
            'weekend_daily_avg' => $totalWeekendDays > 0 ? round($weekendFloat / $totalWeekendDays, 2) : 0,
        ];

        // Liquidity Runway & Burn Rate
        $dailyBurnRate = $periodDays > 0 ? round($expenseFloat / $periodDays, 2) : 0;
        $monthlyBurnRate = round($dailyBurnRate * 30, 2);

        $liquidBalance = 0.0;
        if ($this->accountService) {
            $overview = $this->accountService->getAccountsOverview($user);
            $breakdown = $overview['breakdown'] ?? [];
            $liquidBalance = (float) ($breakdown['bank'] ?? 0)
                + (float) ($breakdown['e_wallet'] ?? 0)
                + (float) ($breakdown['cash'] ?? 0);
        } else {
            $accounts = Account::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereIn('account_type', ['bank', 'e_wallet', 'cash', 'checking', 'savings'])
                ->get();
            foreach ($accounts as $acc) {
                $liquidBalance += (float) ($acc->current_balance ?? 0);
            }
        }

        $runwayMonths = 0.0;
        if ($monthlyBurnRate > 0) {
            $runwayMonths = round($liquidBalance / $monthlyBurnRate, 1);
        } elseif ($liquidBalance > 0) {
            $runwayMonths = 999.0;
        }

        $healthTier = 'critical';
        if ($runwayMonths >= 12.0) {
            $healthTier = 'robust';
        } elseif ($runwayMonths >= 6.0) {
            $healthTier = 'healthy';
        } elseif ($runwayMonths >= 3.0) {
            $healthTier = 'caution';
        }

        $runwayMetrics = [
            'liquid_balance' => number_format($liquidBalance, 2, '.', ''),
            'liquid_balance_formatted' => Money::of((string) $liquidBalance, $currency)->format(),
            'daily_burn_rate' => number_format($dailyBurnRate, 2, '.', ''),
            'monthly_burn_rate' => number_format($monthlyBurnRate, 2, '.', ''),
            'runway_months' => $runwayMonths,
            'health_tier' => $healthTier,
            'health_label' => match ($healthTier) {
                'robust' => 'Robust (>12 mo)',
                'healthy' => 'Healthy (6-12 mo)',
                'caution' => 'Caution (3-6 mo)',
                default => 'Critical (<3 mo)',
            },
        ];

        // Statistical Spending Anomaly Radar
        $anomalies = [];
        foreach ($categoryTransactions as $catName => $txns) {
            if (count($txns) < 3) {
                continue;
            }

            $amounts = array_map(fn ($t) => (float) $t->amount, $txns);
            $count = count($amounts);
            $mean = array_sum($amounts) / $count;

            $variance = 0.0;
            foreach ($amounts as $amt) {
                $variance += pow($amt - $mean, 2);
            }
            $std = sqrt($variance / max(1, $count - 1));

            if ($std > 0) {
                foreach ($txns as $t) {
                    $amt = (float) $t->amount;
                    $zScore = ($amt - $mean) / $std;
                    $multiplier = round($amt / max(1, $mean), 1);

                    // Flag if z-score is high or transaction is more than 2.0x category average
                    if (($zScore >= 1.75 || ($amt >= $mean * 2.0 && $multiplier >= 2.0)) && $amt >= 50.0) {
                        $anomalies[] = [
                            'id' => $t->id,
                            'description' => $t->description,
                            'amount' => $amountMoney->toDecimal(),
                            'amount_formatted' => $amountMoney->format(),
                            'category' => $catName,
                            'date' => $t->transacted_at->toDateString(),
                            'z_score' => round($zScore, 1),
                            'multiplier' => $multiplier,
                            'severity' => ($zScore >= 2.5 || $multiplier >= 3.0) ? 'high' : 'medium',
                            'reason' => sprintf('%.1fx higher than your average %s spend', $multiplier, $catName),
                        ];
                    }
                }
            }
        }

        // Sort anomalies by highest multiplier
        usort($anomalies, fn ($a, $b) => $b['multiplier'] <=> $a['multiplier']);
        $anomalies = array_slice($anomalies, 0, 5);

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
            'runway' => $runwayMetrics,
            'top_merchants' => $topMerchants,
            'spending_patterns' => $spendingPatterns,
            'anomalies' => $anomalies,
        ];
    }
}
