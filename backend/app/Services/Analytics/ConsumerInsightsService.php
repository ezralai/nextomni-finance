<?php

namespace App\Services\Analytics;

use App\Models\Transaction;
use App\Models\User;
use App\Models\UserInsightsConsent;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ConsumerInsightsService
{
    // DOSM HIES Category Consumption Baskets (% of total monthly expenditure) [Mean, Std]
    protected const DOSM_BENCHMARK_BASKETS = [
        'B40' => [
            'food_and_beverage' => [29.8, 4.5],
            'groceries' => [18.5, 3.8],
            'housing_and_utilities' => [23.2, 3.2],
            'transportation' => [11.2, 2.9],
            'health_and_wellness' => [3.5, 1.2],
            'shopping' => [8.8, 2.5],
            'savings' => [5.0, 3.0],
        ],
        'M40' => [
            'food_and_beverage' => [22.4, 4.0],
            'groceries' => [15.2, 3.0],
            'housing_and_utilities' => [23.5, 3.5],
            'transportation' => [14.6, 3.1],
            'health_and_wellness' => [4.8, 1.5],
            'shopping' => [12.5, 3.2],
            'savings' => [16.0, 5.0],
        ],
        'T20' => [
            'food_and_beverage' => [15.6, 3.5],
            'groceries' => [11.0, 2.5],
            'housing_and_utilities' => [22.8, 3.8],
            'transportation' => [15.2, 3.4],
            'health_and_wellness' => [5.5, 1.8],
            'shopping' => [16.5, 4.2],
            'savings' => [28.0, 6.5],
        ],
    ];

    // State cost-of-living multiplier (Selangor / National Baseline = 1.0)
    protected const STATE_COST_MULTIPLIERS = [
        'MY-14' => 1.15, // KL
        'MY-10' => 1.05, // Selangor
        'MY-07' => 1.02, // Penang
        'MY-01' => 0.98, // Johor
        'MY-08' => 0.88, // Perak
        'MY-02' => 0.82, // Kedah
        'MY-03' => 0.78, // Kelantan
        'MY-11' => 0.80, // Terengganu
        'MY-12' => 0.94, // Sabah
        'MY-13' => 0.95, // Sarawak
    ];

    public function getConsent(User $user): UserInsightsConsent
    {
        return UserInsightsConsent::firstOrCreate(
            ['user_id' => $user->id],
            [
                'is_opted_in' => true,
                'state_code' => 'MY-10',
                'income_tier' => 'M40',
                'consented_at' => Carbon::now(),
            ]
        );
    }

    public function updateConsent(User $user, array $data): UserInsightsConsent
    {
        $consent = $this->getConsent($user);
        $consent->update([
            'is_opted_in' => $data['is_opted_in'] ?? $consent->is_opted_in,
            'state_code' => $data['state_code'] ?? $consent->state_code,
            'income_tier' => $data['income_tier'] ?? $consent->income_tier,
            'consented_at' => Carbon::now(),
        ]);

        Cache::forget("malaysian_insights_{$user->id}");

        return $consent;
    }

    /**
     * Get Malaysian consumer insights, persona classification, and DOSM benchmark comparison.
     */
    public function getInsights(User $user): array
    {
        $cacheKey = "malaysian_insights_{$user->id}";

        return Cache::remember($cacheKey, 3600 * 12, function () use ($user) {
            $consent = $this->getConsent($user);

            if (! $consent->is_opted_in) {
                return [
                    'consent_granted' => false,
                    'message' => 'Insights are paused because PDPA analytics consent is disabled. Enable opt-in to view peer benchmarks.',
                ];
            }

            $endDate = Carbon::now();
            $startDate = Carbon::now()->subDays(90)->startOfDay();
            $currency = $user->base_currency ?? 'MYR';

            $transactions = Transaction::where('user_id', $user->id)
                ->where('status', 'posted')
                ->whereBetween('transacted_at', [$startDate, $endDate])
                ->with('category')
                ->get();

            $totalIncome = 0.0;
            $totalExpense = 0.0;
            $categorySums = [
                'food_and_beverage' => 0.0,
                'groceries' => 0.0,
                'housing_and_utilities' => 0.0,
                'transportation' => 0.0,
                'health_and_wellness' => 0.0,
                'shopping' => 0.0,
                'savings' => 0.0,
            ];

            $postPaydaySpend = 0.0;
            $midMonthSpend = 0.0;
            $weekendSpend = 0.0;

            foreach ($transactions as $txn) {
                $amount = (float) $txn->amount;
                $date = Carbon::parse($txn->transacted_at);
                $day = (int) $date->day;

                if ($txn->type === 'income') {
                    $totalIncome += $amount;
                } elseif ($txn->type === 'expense') {
                    $totalExpense += $amount;

                    if ($date->isWeekend()) {
                        $weekendSpend += $amount;
                    }

                    // Payday windows (Day 26-31 & 1-5 = post-payday; 6-20 = mid-month)
                    if ($day >= 26 || $day <= 5) {
                        $postPaydaySpend += $amount;
                    } elseif ($day >= 6 && $day <= 20) {
                        $midMonthSpend += $amount;
                    }

                    $catName = strtolower($txn->category?->name ?? 'uncategorized');
                    $this->accumulateCategory($catName, $amount, $categorySums);
                }
            }

            // Monthly equivalents over 90 days (div by 3)
            $monthlyIncome = round($totalIncome / 3.0, 2);
            $monthlyExpense = round($totalExpense / 3.0, 2);
            $netSavings = max(0.0, $monthlyIncome - $monthlyExpense);
            $categorySums['savings'] = $netSavings;

            $persona = $this->classifyPersona($categorySums, $monthlyExpense, $monthlyIncome, $weekendSpend);
            $payday = $this->calculatePaydayVelocity($postPaydaySpend, $midMonthSpend);
            $benchmarks = $this->compareWithDOSM(
                $categorySums,
                $monthlyIncome,
                $monthlyExpense,
                $consent->state_code,
                $consent->income_tier
            );

            return [
                'consent_granted' => true,
                'state_code' => $consent->state_code,
                'income_tier' => $consent->income_tier,
                'currency' => $currency,
                'monthly_income_avg' => $monthlyIncome,
                'monthly_expense_avg' => $monthlyExpense,
                'user_persona' => $persona,
                'payday_dynamics' => $payday,
                'demographic_benchmark' => $benchmarks,
            ];
        });
    }

    protected function accumulateCategory(string $catName, float $amount, array &$categorySums): void
    {
        if (str_contains($catName, 'food') || str_contains($catName, 'dining') || str_contains($catName, 'restaurant') || str_contains($catName, 'cafe') || str_contains($catName, 'hawker')) {
            $categorySums['food_and_beverage'] += $amount / 3.0;
        } elseif (str_contains($catName, 'grocer') || str_contains($catName, 'supermarket') || str_contains($catName, 'speedmart')) {
            $categorySums['groceries'] += $amount / 3.0;
        } elseif (str_contains($catName, 'transport') || str_contains($catName, 'fuel') || str_contains($catName, 'petrol') || str_contains($catName, 'toll') || str_contains($catName, 'transit')) {
            $categorySums['transportation'] += $amount / 3.0;
        } elseif (str_contains($catName, 'utilit') || str_contains($catName, 'rent') || str_contains($catName, 'housing') || str_contains($catName, 'electric') || str_contains($catName, 'water')) {
            $categorySums['housing_and_utilities'] += $amount / 3.0;
        } elseif (str_contains($catName, 'health') || str_contains($catName, 'medical') || str_contains($catName, 'pharmacy')) {
            $categorySums['health_and_wellness'] += $amount / 3.0;
        } elseif (str_contains($catName, 'shop') || str_contains($catName, 'ecommerce') || str_contains($catName, 'retail')) {
            $categorySums['shopping'] += $amount / 3.0;
        } else {
            $categorySums['shopping'] += $amount / 3.0;
        }
    }

    protected function classifyPersona(array $cats, float $totalExpense, float $totalIncome, float $weekendSpend): array
    {
        $denom = $totalExpense > 0 ? $totalExpense : 1.0;
        $diningRatio = $cats['food_and_beverage'] / $denom;
        $transportRatio = $cats['transportation'] / $denom;
        $groceryRatio = $cats['groceries'] / $denom;
        $shoppingRatio = $cats['shopping'] / $denom;
        $savingsRate = $totalIncome > 0 ? ($cats['savings'] / $totalIncome) : 0.0;
        $weekendSkew = $denom > 0 ? ($weekendSpend / (3.0 * $denom)) : 0.0;

        $scores = [
            'frugal_builder' => $savingsRate * 2.5,
            'foodie_cafe_hopper' => ($diningRatio * 2.2) + ($weekendSkew * 0.8),
            'urban_commuter' => $transportRatio * 3.0,
            'family_anchor' => $groceryRatio * 2.8,
            'digital_native' => $shoppingRatio * 2.8,
        ];

        arsort($scores);
        $bestKey = array_key_first($scores);
        $score = $scores[$bestKey];
        $confidence = round(min(0.98, max(0.60, 0.50 + ($score * 0.4))), 2);

        $definitions = [
            'urban_commuter' => [
                'id' => 'urban_commuter',
                'title' => 'Urban Commuter',
                'badge' => '🚗 Commuter & Transit',
                'description' => 'Your spending is heavily driven by daily commuting, expressway tolls, fuel, and quick meals on the go.',
                'traits' => [
                    'Transport, Fuel & Tolls account for significant monthly cash outflow',
                    'Weekday morning spending spikes for fuel and breakfast',
                    'Active Touch \'n Go and RapidKL transit usage',
                ],
                'tip' => 'Consider automated Touch \'n Go RFID top-ups and cash-back fuel cards to save RM 30–RM 60 monthly.',
            ],
            'foodie_cafe_hopper' => [
                'id' => 'foodie_cafe_hopper',
                'title' => 'Foodie & Café Hopper',
                'badge' => '🍜 Foodie & Café Hopper',
                'description' => 'You love exploring Malaysian food culture, frequenting mamak hangouts, kopitiams, and artisan cafés.',
                'traits' => [
                    'Dining, hawker, and café expenses account for a high share of total outflow',
                    'High frequency of DuitNow QR micro-transactions',
                    'Pronounced weekend dining surges with friends & family',
                ],
                'tip' => 'Cap spontaneous late-night supper runs and pack lunch two days a week to redirect ~RM 200 into savings.',
            ],
            'family_anchor' => [
                'id' => 'family_anchor',
                'title' => 'Pragmatic Family Anchor',
                'badge' => '🏡 Pragmatic Family Anchor',
                'description' => 'Your financial priorities center on household sustenance, regular grocery runs, utilities, and dependable family care.',
                'traits' => [
                    'Supermarket and grocery spending (Lotus\'s, Giant, 99 Speedmart) is dominant',
                    'Stable utility and recurring bill payments (TNB, Unifi, Water)',
                    'Predictable, planned weekly expenditure rhythm',
                ],
                'tip' => 'Utilize supermarket loyalty points and bulk purchases on staples to capture 5–8% annual cash rebates.',
            ],
            'digital_native' => [
                'id' => 'digital_native',
                'title' => 'Digital Native & Deal Hunter',
                'badge' => '📦 Digital Native & Deal Hunter',
                'description' => 'You are a savvy modern spender who leverages e-commerce platforms, flash sales, and digital payment channels.',
                'traits' => [
                    'E-commerce (Shopee, Lazada, TikTok Shop) forms a notable share of expenses',
                    'High FPX and digital wallet utilization',
                    'Spending peaks around double-date campaign days (10.10, 11.11, payday sales)',
                ],
                'tip' => 'Implement a 24-hour cooling-off rule before checkout to eliminate impulse e-commerce purchases.',
            ],
            'frugal_builder' => [
                'id' => 'frugal_builder',
                'title' => 'Frugal Wealth Builder',
                'badge' => '🌱 Frugal Wealth Builder',
                'description' => 'You demonstrate disciplined financial restraint, maintaining a high savings surplus and minimizing lifestyle creep.',
                'traits' => [
                    'Consistently maintains a high savings rate well above demographic peers',
                    'Low discretionary outflow and controlled impulse spending',
                    'Steady capital accumulation for long-term independence',
                ],
                'tip' => 'Ensure surplus cash is working in high-yield instruments or diversified index funds rather than stagnant low-yield accounts.',
            ],
        ];

        $res = $definitions[$bestKey] ?? $definitions['frugal_builder'];
        $res['confidence'] = $confidence;

        return $res;
    }

    protected function calculatePaydayVelocity(float $postPaydayTotal, float $midMonthTotal): array
    {
        // 90 days = 3 cycles. Window days: post-payday ~10 days/mo = 30 days; mid-month ~15 days/mo = 45 days.
        $postDaily = round($postPaydayTotal / 30.0, 2);
        $midDaily = round($midMonthTotal / 45.0, 2);
        $ratio = round($postDaily / ($midDaily > 0 ? $midDaily : 1.0), 2);

        if ($ratio >= 1.8) {
            $rec = "Payday surge detected: daily spending is {$ratio}x higher in the first 5 days post-salary. Automate savings transfers on Day 1.";
        } elseif ($ratio >= 1.3) {
            $rec = "Moderate post-payday spending elevation ({$ratio}x). Spending tapers gracefully towards month-end.";
        } else {
            $rec = 'Balanced cash flow: your daily expenditure is steady throughout the calendar month.';
        }

        return [
            'detected_cycle' => 'MALAYSIAN_END_OF_MONTH',
            'surge_velocity_ratio' => $ratio,
            'post_payday_daily_avg' => $postDaily,
            'mid_month_daily_avg' => $midDaily,
            'recommendation' => $rec,
        ];
    }

    protected function compareWithDOSM(array $cats, float $monthlyIncome, float $monthlyExpense, string $stateCode, string $tier): array
    {
        $basket = self::DOSM_BENCHMARK_BASKETS[$tier] ?? self::DOSM_BENCHMARK_BASKETS['M40'];
        $mult = self::STATE_COST_MULTIPLIERS[$stateCode] ?? 1.0;
        $denom = $monthlyIncome > 0 ? $monthlyIncome : ($monthlyExpense > 0 ? $monthlyExpense : 1.0);

        $labels = [
            'food_and_beverage' => 'Food & Dining Out',
            'groceries' => 'Groceries & Essentials',
            'housing_and_utilities' => 'Housing & Utilities',
            'transportation' => 'Transportation & Fuel',
            'health_and_wellness' => 'Health & Personal Care',
            'shopping' => 'Shopping & Discretionary',
            'savings' => 'Net Monthly Savings',
        ];

        $categories = [];

        foreach ($basket as $catKey => [$meanPct, $stdPct]) {
            $userAmt = $cats[$catKey] ?? 0.0;
            $userPct = round(($userAmt / $denom) * 100, 1);
            $peerPct = round($catKey === 'savings' ? $meanPct : ($meanPct * $mult), 1);
            $zScore = round(($userPct - $peerPct) / ($stdPct > 0 ? $stdPct : 1.0), 2);

            if ($catKey === 'savings') {
                $status = $userPct >= $peerPct ? 'optimal' : ($zScore >= -1.0 ? 'healthy' : 'warning');
                $advice = $userPct >= $peerPct
                    ? "Savings rate ({$userPct}%) exceeds peer average ({$peerPct}%)."
                    : "Savings rate ({$userPct}%) is below peer average ({$peerPct}%).";
            } else {
                $status = $zScore > 1.96 ? 'warning' : ($zScore > 1.0 ? 'elevated' : 'healthy');
                $excess = round((($userPct - $peerPct) / 100) * $denom, 2);
                $advice = $status === 'warning'
                    ? "High outlier: {$userPct}% vs peer avg {$peerPct}%. Potential savings: ~RM " . number_format($excess, 2) . "/mo."
                    : ($status === 'elevated' ? "Moderately higher than peer average ({$userPct}% vs {$peerPct}%)." : "Within healthy demographic range ({$userPct}% vs {$peerPct}%).");
            }

            $categories[] = [
                'key' => $catKey,
                'name' => $labels[$catKey] ?? ucwords(str_replace('_', ' ', $catKey)),
                'user_amount' => round($userAmt, 2),
                'user_pct' => $userPct,
                'peer_pct' => $peerPct,
                'z_score' => $zScore,
                'status' => $status,
                'advice' => $advice,
            ];
        }

        return [
            'cohort_label' => "{$stateCode} / {$tier} (DOSM Baseline)",
            'categories' => $categories,
        ];
    }
}
