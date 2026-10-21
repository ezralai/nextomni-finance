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
                    'month' => now()->addMonth()->format('Y-m'),
                    'predicted_expense' => round($avgExpense * 1.05, 2),
                    'confidence_level' => 0.75,
                ],
            ],
            'recurring' => [],
        ];
    }
}
