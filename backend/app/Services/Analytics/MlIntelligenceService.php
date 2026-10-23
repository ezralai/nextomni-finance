<?php

namespace App\Services\Analytics;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MlIntelligenceService
{
    /**
     * Compute or retrieve cached predictive ML intelligence for user transactions.
     */
    public function getIntelligence(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = "user_{$user->id}_ml_intelligence";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 1800, function () use ($user) {
            return $this->executeMlInference($user);
        });
    }

    /**
     * Prepare feature vectors and execute the decoupled Python ML pipeline.
     */
    protected function executeMlInference(User $user): array
    {
        // 1. Fetch user transactions (up to 200 recent records)
        $transactions = Transaction::where('user_id', $user->id)
            ->with('category')
            ->orderBy('transacted_at', 'desc')
            ->limit(200)
            ->get();

        if ($transactions->isEmpty()) {
            return [
                'status' => 'empty',
                'message' => 'No recorded transactions available for machine learning evaluation.',
                'records_analyzed' => 0,
                'anomalies' => [],
                'forecast' => [],
                'recurring' => [],
            ];
        }

        // 2. Format JSON payload for ML service
        $payload = [];
        foreach ($transactions as $txn) {
            $payload[] = [
                'id' => $txn->id,
                'transacted_at' => $txn->transacted_at?->toISOString() ?? now()->toISOString(),
                'amount' => (float) $txn->amount,
                'type' => $txn->type,
                'category' => $txn->category?->slug ?? 'uncategorized',
                'description' => $txn->description,
            ];
        }

        // 3. Locate Python executable & ML service
        $baseDir = base_path('..');
        $pythonBinary = realpath("{$baseDir}/ml/.venv/bin/python");
        $serviceScript = realpath("{$baseDir}/ml/src/service.py");

        if (! $pythonBinary || ! file_exists($pythonBinary) || ! $serviceScript || ! file_exists($serviceScript)) {
            Log::warning('Python ML environment not located, returning deterministic heuristics.');
            return $this->fallbackHeuristics($transactions);
        }

        try {
            $process = new Process([$pythonBinary, $serviceScript, '--stdin'], dirname($serviceScript));
            $process->setInput(json_encode($payload));
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('ML inference subprocess failed: ' . $process->getErrorOutput());
                return $this->fallbackHeuristics($transactions);
            }

            $output = json_decode($process->getOutput(), true);
            if (! is_array($output)) {
                return $this->fallbackHeuristics($transactions);
            }

            return $output;
        } catch (\Throwable $e) {
            Log::error('ML inference exception: ' . $e->getMessage());
            return $this->fallbackHeuristics($transactions);
        }
    }

    /**
     * Fallback heuristic prediction when ML service is offline.
     */
    protected function fallbackHeuristics($transactions): array
    {
        $expenses = $transactions->where('type', 'expense');
        $totalExpense = $expenses->sum('amount');
        $avgExpense = $expenses->count() > 0 ? $totalExpense / $expenses->count() : 0;

        $recurringFallback = [];
        $grouped = $expenses->groupBy('description');
        foreach ($grouped as $desc => $items) {
            if ($items->count() >= 3) {
                $amounts = $items->pluck('amount')->map(fn ($a) => (float) $a);
                $avg = $amounts->avg() ?? 0.0;
                $min = $amounts->min() ?? 0.0;
                $max = $amounts->max() ?? 0.0;
                $isFixed = ($max - $min) <= ($avg * 0.05);

                $latest = $items->sortByDesc('transacted_at')->first();
                $nextExpected = $latest && $latest->transacted_at
                    ? $latest->transacted_at->copy()->addMonth()->format('Y-m-d')
                    : now()->addMonth()->format('Y-m-d');

                $recurringFallback[] = [
                    'merchant' => (string) $desc,
                    'category' => $items->first()->category?->slug ?? 'general',
                    'cadence' => 'Monthly',
                    'cost_type' => $isFixed ? 'Fixed Amount' : 'Variable Metered',
                    'average_amount' => round($avg, 2),
                    'estimated_amount' => round($avg, 2),
                    'monthly_equivalent' => round($avg, 2),
                    'annual_projected' => round($avg * 12.0, 2),
                    'recurrence_count' => $items->count(),
                    'confidence' => $isFixed ? 0.85 : 0.70,
                    'last_billed_date' => $latest?->transacted_at?->format('Y-m-d') ?? '',
                    'next_expected_date' => $nextExpected,
                ];
            }
        }

        return [
            'status' => 'fallback_heuristic',
            'records_analyzed' => $transactions->count(),
            'summary' => [
                'total_income' => (float) $transactions->where('type', 'income')->sum('amount'),
                'total_expenses' => (float) $totalExpense,
            ],
            'anomalies' => [],
            'forecast' => [
                [
                    'month' => now()->addMonth(1)->format('Y-m'),
                    'predicted_expense' => round($avgExpense * 1.02, 2),
                    'confidence_level' => 0.75,
                    'method' => 'fallback_moving_average',
                    'lower_bound_80' => round(max(0, $avgExpense * 1.02 * 0.90), 2),
                    'upper_bound_80' => round($avgExpense * 1.02 * 1.10, 2),
                    'lower_bound_95' => round(max(0, $avgExpense * 1.02 * 0.85), 2),
                    'upper_bound_95' => round($avgExpense * 1.02 * 1.15, 2),
                    'margin_of_error_80' => round($avgExpense * 1.02 * 0.10, 2),
                    'margin_of_error_95' => round($avgExpense * 1.02 * 0.15, 2),
                ],
                [
                    'month' => now()->addMonths(2)->format('Y-m'),
                    'predicted_expense' => round($avgExpense * 1.04, 2),
                    'confidence_level' => 0.70,
                    'method' => 'fallback_moving_average',
                    'lower_bound_80' => round(max(0, $avgExpense * 1.04 * 0.88), 2),
                    'upper_bound_80' => round($avgExpense * 1.04 * 1.12, 2),
                    'lower_bound_95' => round(max(0, $avgExpense * 1.04 * 0.82), 2),
                    'upper_bound_95' => round($avgExpense * 1.04 * 1.18, 2),
                    'margin_of_error_80' => round($avgExpense * 1.04 * 0.12, 2),
                    'margin_of_error_95' => round($avgExpense * 1.04 * 0.18, 2),
                ],
                [
                    'month' => now()->addMonths(3)->format('Y-m'),
                    'predicted_expense' => round($avgExpense * 1.06, 2),
                    'confidence_level' => 0.65,
                    'method' => 'fallback_moving_average',
                    'lower_bound_80' => round(max(0, $avgExpense * 1.06 * 0.85), 2),
                    'upper_bound_80' => round($avgExpense * 1.06 * 1.15, 2),
                    'lower_bound_95' => round(max(0, $avgExpense * 1.06 * 0.78), 2),
                    'upper_bound_95' => round($avgExpense * 1.06 * 1.22, 2),
                    'margin_of_error_80' => round($avgExpense * 1.06 * 0.15, 2),
                    'margin_of_error_95' => round($avgExpense * 1.06 * 0.22, 2),
                ],
            ],
            'category_forecast' => [
                'forecast_period' => now()->addMonth(1)->format('Y-m'),
                'total_projected_expense' => round($totalExpense, 2),
                'categories_at_risk_count' => 0,
                'categories' => [],
            ],
            'recurring' => $recurringFallback,
            'spending_persona' => [
                'id' => 'frugal_builder',
                'title' => 'Frugal Wealth Builder',
                'badge' => '🌱 Frugal Wealth Builder',
                'icon' => 'TrendingUp',
                'description' => 'You demonstrate disciplined financial restraint, maintaining a high savings surplus and minimizing lifestyle creep.',
                'traits' => [
                    'Consistently maintains a savings rate of 35% or higher',
                    'Low discretionary outflow and controlled impulse spending',
                    'Steady capital accumulation for long-term independence',
                ],
                'financial_tip' => 'Ensure surplus cash is working in high-yield instruments or diversified index funds rather than stagnant low-yield accounts.',
                'confidence' => 0.70,
                'features' => [
                    'dining_ratio' => 0.0,
                    'groceries_ratio' => 0.0,
                    'transport_ratio' => 0.0,
                    'shopping_ratio' => 0.0,
                    'weekend_skew' => 0.0,
                    'savings_rate' => 0.35,
                    'total_income' => (float) $transactions->where('type', 'income')->sum('amount'),
                    'total_expense' => (float) $totalExpense,
                ],
            ],
        ];
    }
}
