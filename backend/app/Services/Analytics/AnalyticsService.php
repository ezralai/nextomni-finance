<?php

namespace App\Services\Analytics;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Scopes\TenantScope;
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
                        $txnMoney = Money::of((string) $t->amount, $t->currency ?: $currency);
                        $anomalies[] = [
                            'id' => $t->id,
                            'description' => $t->description,
                            'amount' => $txnMoney->toDecimal(),
                            'amount_formatted' => $txnMoney->format(),
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

        // Multi-Dimensional Financial Health Score
        $healthScore = $this->computeFinancialHealthScore(
            $user,
            $incomeFloat,
            $expenseFloat,
            $liquidBalance,
            $monthlyBurnRate,
            $runwayMetrics,
            $savingsRate,
            $dailyTotals,
            $categoriesFormatted,
            $periodDays,
            $currency
        );

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
            'health_score' => $healthScore,
        ];
    }

    /**
     * Compute multi-dimensional Financial Health Score (0-100) and risk stability radar.
     *
     * Evaluates 5 balanced pillars:
     * 1. Liquidity Runway (25%)
     * 2. Savings Rate & Net Margin (25%)
     * 3. Fixed Liability Burden & Recurring Drag (20%)
     * 4. Daily Spending Volatility / Coefficient of Variation (15%)
     * 5. Category Diversification / Herfindahl-Hirschman Index (15%)
     */
    public function computeFinancialHealthScore(
        User $user,
        float $incomeFloat,
        float $expenseFloat,
        float $liquidBalance,
        float $monthlyBurnRate,
        array $runwayMetrics,
        float $savingsRate,
        array $dailyTotals,
        array $categoriesFormatted,
        int $periodDays,
        string $currency
    ): array {
        // --- 1. Pillar 1: Liquidity Runway (Weight: 25%) ---
        $runwayMonths = (float) ($runwayMetrics['runway_months'] ?? 0.0);
        if ($runwayMonths >= 6.0) {
            $liquidityScore = 100.0;
        } elseif ($runwayMonths >= 3.0) {
            $liquidityScore = round(75.0 + (($runwayMonths - 3.0) / 3.0) * 25.0, 1);
        } elseif ($runwayMonths >= 1.0) {
            $liquidityScore = round(40.0 + (($runwayMonths - 1.0) / 2.0) * 35.0, 1);
        } elseif ($runwayMonths > 0.0) {
            $liquidityScore = max(10.0, round($runwayMonths * 40.0, 1));
        } elseif ($liquidBalance == 0.0 && $monthlyBurnRate == 0.0) {
            $liquidityScore = 70.0; // Neutral cold start
        } else {
            $liquidityScore = 10.0;
        }

        $liquidityStatus = match (true) {
            $liquidityScore >= 80.0 => 'robust',
            $liquidityScore >= 60.0 => 'healthy',
            $liquidityScore >= 40.0 => 'caution',
            default => 'critical',
        };

        // --- 2. Pillar 2: Savings & Margin Retention (Weight: 25%) ---
        if ($incomeFloat > 0) {
            if ($savingsRate >= 20.0) {
                $savingsScore = 100.0;
            } elseif ($savingsRate >= 10.0) {
                $savingsScore = round(75.0 + (($savingsRate - 10.0) / 10.0) * 25.0, 1);
            } elseif ($savingsRate >= 0.0) {
                $savingsScore = round(50.0 + ($savingsRate / 10.0) * 25.0, 1);
            } else {
                // Negative savings rate (cash deficit)
                $savingsScore = max(0.0, round(50.0 + $savingsRate, 1));
            }
        } else {
            $savingsScore = $expenseFloat > 0
                ? max(10.0, round(50.0 - min(40.0, ($expenseFloat / max(1.0, $liquidBalance)) * 50.0), 1))
                : 70.0;
        }

        $savingsStatus = match (true) {
            $savingsScore >= 80.0 => 'exceptional',
            $savingsScore >= 60.0 => 'healthy',
            $savingsScore >= 40.0 => 'moderate',
            default => 'deficit',
        };

        // --- 3. Pillar 3: Fixed Liability & Recurring Drag (Weight: 20%) ---
        $recurringExpenses = RecurringTransaction::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('type', 'expense')
            ->get();

        $monthlyFixedObligations = 0.0;
        foreach ($recurringExpenses as $rec) {
            $amt = (float) $rec->amount;
            $monthlyFixedObligations += match ($rec->frequency) {
                'daily' => $amt * 30.42,
                'weekly' => $amt * 4.33,
                'biweekly' => $amt * 2.165,
                'quarterly' => $amt / 3.0,
                'yearly' => $amt / 12.0,
                default => $amt,
            };
        }

        $monthlyInflow = $incomeFloat > 0 ? ($incomeFloat / max(1, $periodDays) * 30.42) : $monthlyBurnRate;
        if ($monthlyFixedObligations > 0 && $monthlyInflow > 0) {
            $fixedRatio = round(($monthlyFixedObligations / $monthlyInflow) * 100, 1);
            if ($fixedRatio <= 25.0) {
                $debtScore = 100.0;
            } elseif ($fixedRatio <= 45.0) {
                $debtScore = round(70.0 + ((45.0 - $fixedRatio) / 20.0) * 30.0, 1);
            } elseif ($fixedRatio <= 70.0) {
                $debtScore = round(40.0 + ((70.0 - $fixedRatio) / 25.0) * 30.0, 1);
            } else {
                $debtScore = max(10.0, round(40.0 - ($fixedRatio - 70.0), 1));
            }
            $debtLabel = "{$fixedRatio}% fixed obligation ratio";
        } else {
            $debtScore = 95.0; // Minimal recurring liabilities
            $debtLabel = $monthlyFixedObligations > 0 ? "{$currency} " . number_format($monthlyFixedObligations, 2) . " / mo" : "Zero fixed liabilities recorded";
        }

        $debtStatus = match (true) {
            $debtScore >= 80.0 => 'optimal',
            $debtScore >= 60.0 => 'manageable',
            $debtScore >= 40.0 => 'elevated',
            default => 'critical',
        };

        // --- 4. Pillar 4: Daily Spending Volatility (Weight: 15%) ---
        $dailyValues = array_map(fn ($m) => (float) $m->toDecimal(), array_values($dailyTotals));
        $dailyCount = count($dailyValues);
        if ($dailyCount >= 2) {
            $dailyMean = array_sum($dailyValues) / $dailyCount;
            $varianceSum = 0.0;
            foreach ($dailyValues as $v) {
                $varianceSum += pow($v - $dailyMean, 2);
            }
            $dailyStd = sqrt($varianceSum / ($dailyCount - 1));
            $cv = $dailyMean > 0 ? round($dailyStd / $dailyMean, 2) : 0.0;

            if ($cv <= 0.5) {
                $volatilityScore = 100.0;
            } elseif ($cv <= 1.0) {
                $volatilityScore = round(75.0 + ((1.0 - $cv) / 0.5) * 25.0, 1);
            } elseif ($cv <= 1.8) {
                $volatilityScore = round(45.0 + ((1.8 - $cv) / 0.8) * 30.0, 1);
            } else {
                $volatilityScore = max(15.0, round(45.0 - ($cv - 1.8) * 15.0, 1));
            }
            $volatilityLabel = "Daily variance index (CV: {$cv})";
        } else {
            $volatilityScore = 85.0;
            $volatilityLabel = "Consistent daily baseline";
        }

        $volatilityStatus = match (true) {
            $volatilityScore >= 80.0 => 'stable',
            $volatilityScore >= 60.0 => 'moderate',
            default => 'volatile',
        };

        // --- 5. Pillar 5: Category Concentration (HHI) (Weight: 15%) ---
        $hhi = 0.0;
        if (! empty($categoriesFormatted) && $expenseFloat > 0) {
            foreach ($categoriesFormatted as $cat) {
                $pct = (float) ($cat['percentage'] ?? 0);
                $hhi += pow($pct, 2);
            }
            $hhi = round($hhi);

            if ($hhi <= 2500) {
                $concentrationScore = 100.0;
            } elseif ($hhi <= 4500) {
                $concentrationScore = round(70.0 + ((4500 - $hhi) / 2000.0) * 30.0, 1);
            } elseif ($hhi <= 7000) {
                $concentrationScore = round(40.0 + ((7000 - $hhi) / 2500.0) * 30.0, 1);
            } else {
                $concentrationScore = max(15.0, round(40.0 - (($hhi - 7000) / 3000.0) * 25.0, 1));
            }

            $concentrationType = $hhi <= 2500 ? 'Diversified' : ($hhi <= 4500 ? 'Moderate' : 'Concentrated');
            $concentrationLabel = "HHI: {$hhi} ({$concentrationType})";
        } else {
            $concentrationScore = 90.0;
            $concentrationLabel = "No category concentration risk";
        }

        $concentrationStatus = match (true) {
            $concentrationScore >= 80.0 => 'balanced',
            $concentrationScore >= 60.0 => 'moderate',
            default => 'concentrated',
        };

        // --- Composite Weighted Score Calculation ---
        $compositeScore = round(
            ($liquidityScore * 0.25) +
            ($savingsScore * 0.25) +
            ($debtScore * 0.20) +
            ($volatilityScore * 0.15) +
            ($concentrationScore * 0.15)
        );
        $compositeScore = max(0, min(100, (int) $compositeScore));

        // Grade and Tier
        [$grade, $tier, $tierColor] = match (true) {
            $compositeScore >= 85 => ['A', 'Prime (Exceptional)', 'emerald'],
            $compositeScore >= 70 => ['B', 'Healthy (Stable)', 'teal'],
            $compositeScore >= 55 => ['C', 'Fair (Moderate)', 'sky'],
            $compositeScore >= 40 => ['D', 'Vulnerable (At Risk)', 'amber'],
            default => ['F', 'Critical (Action Required)', 'rose'],
        };

        // Diagnostics Compilation
        $diagnostics = [];
        $recommendations = [];

        // Liquidity feedback
        if ($liquidityScore >= 80) {
            $diagnostics[] = [
                'pillar' => 'liquidity',
                'type' => 'positive',
                'message' => "Emergency liquidity reserve covers {$runwayMonths} months of expenditure.",
            ];
        } elseif ($liquidityScore < 50) {
            $diagnostics[] = [
                'pillar' => 'liquidity',
                'type' => 'critical',
                'message' => "High liquidity exposure: runway is under 3 months.",
            ];
            $recommendations[] = "Prioritize building an emergency buffer of at least 3 to 6 months of living expenses.";
        }

        // Savings feedback
        if ($savingsScore >= 75) {
            $diagnostics[] = [
                'pillar' => 'savings',
                'type' => 'positive',
                'message' => "Strong savings discipline with {$savingsRate}% net margin retention.",
            ];
        } elseif ($savingsRate < 0) {
            $diagnostics[] = [
                'pillar' => 'savings',
                'type' => 'critical',
                'message' => "Operating at cash flow deficit: expenditures exceed net income.",
            ];
            $recommendations[] = "Review non-essential category outlays to restore positive monthly cash retention.";
        }

        // Fixed obligation feedback
        if ($debtScore < 60) {
            $diagnostics[] = [
                'pillar' => 'debt_liability',
                'type' => 'warning',
                'message' => "Recurring liabilities consume an elevated share of income.",
            ];
            $recommendations[] = "Audit recurring bills and subscriptions to reduce ongoing fixed liabilities.";
        }

        // Concentration feedback
        if ($concentrationScore < 60 && ! empty($categoriesFormatted)) {
            $topCat = $categoriesFormatted[0];
            $diagnostics[] = [
                'pillar' => 'concentration',
                'type' => 'warning',
                'message' => "Expenditure heavily concentrated in {$topCat['name']} ({$topCat['percentage']}%).",
            ];
            $recommendations[] = "Establish monthly budget caps on your highest-spend categories.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "Maintain your current savings and liquidity cadence to preserve financial resilience.";
            $recommendations[] = "Consider setting milestone targets in your financial goals to compound idle surplus.";
        }

        return [
            'score' => $compositeScore,
            'grade' => $grade,
            'tier' => $tier,
            'tier_color' => $tierColor,
            'pillars' => [
                'liquidity' => [
                    'name' => 'Liquidity Runway',
                    'score' => $liquidityScore,
                    'weight' => 25,
                    'weighted_score' => round($liquidityScore * 0.25, 2),
                    'metric_label' => sprintf('%.1f mo runway (%s %s liquid)', $runwayMonths, $currency, number_format($liquidBalance, 2)),
                    'status' => $liquidityStatus,
                ],
                'savings' => [
                    'name' => 'Savings Rate',
                    'score' => $savingsScore,
                    'weight' => 25,
                    'weighted_score' => round($savingsScore * 0.25, 2),
                    'metric_label' => "{$savingsRate}% margin retention",
                    'status' => $savingsStatus,
                ],
                'debt_liability' => [
                    'name' => 'Fixed Obligations',
                    'score' => $debtScore,
                    'weight' => 20,
                    'weighted_score' => round($debtScore * 0.20, 2),
                    'metric_label' => $debtLabel,
                    'status' => $debtStatus,
                ],
                'volatility' => [
                    'name' => 'Spending Stability',
                    'score' => $volatilityScore,
                    'weight' => 15,
                    'weighted_score' => round($volatilityScore * 0.15, 2),
                    'metric_label' => $volatilityLabel,
                    'status' => $volatilityStatus,
                ],
                'concentration' => [
                    'name' => 'Category Diversification',
                    'score' => $concentrationScore,
                    'weight' => 15,
                    'weighted_score' => round($concentrationScore * 0.15, 2),
                    'metric_label' => $concentrationLabel,
                    'status' => $concentrationStatus,
                ],
            ],
            'diagnostics' => $diagnostics,
            'recommendations' => $recommendations,
        ];
    }
}
