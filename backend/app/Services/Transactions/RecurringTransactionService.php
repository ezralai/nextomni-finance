<?php

namespace App\Services\Transactions;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Repositories\Contracts\RecurringTransactionRepositoryInterface;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecurringTransactionService
{
    public function __construct(
        protected RecurringTransactionRepositoryInterface $recurringRepository,
        protected TransactionService $transactionService
    ) {}

    /**
     * Retrieve all recurring transactions for the user.
     */
    public function getRecurringTransactions(User $user, array $filters = []): Collection
    {
        return $this->recurringRepository->getForUser($user->id, $filters);
    }

    /**
     * Create a new recurring transaction schedule.
     */
    public function createRecurring(User $user, array $data): RecurringTransaction
    {
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);
        $currency = $account->currency ?? 'MYR';

        $money = Money::of($data['amount'], $currency);
        if ($money->isZero() || $money->isNegative()) {
            throw ValidationException::withMessages([
                'amount' => ['Recurring amount must be strictly greater than 0.'],
            ]);
        }

        $startDate = Carbon::parse($data['start_date']);
        $nextDueDate = isset($data['next_due_date'])
            ? Carbon::parse($data['next_due_date'])
            : $startDate->copy();

        return $this->recurringRepository->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $data['category_id'] ?? null,
            'type' => strtolower($data['type']),
            'description' => trim($data['description']),
            'notes' => $data['notes'] ?? null,
            'amount' => $money->toDecimal(),
            'currency' => $currency,
            'frequency' => strtolower($data['frequency']),
            'start_date' => $startDate->toDateString(),
            'end_date' => ! empty($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : null,
            'next_due_date' => $nextDueDate->toDateString(),
            'auto_post' => $data['auto_post'] ?? true,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing recurring transaction schedule.
     */
    public function updateRecurring(RecurringTransaction $recurring, array $data): RecurringTransaction
    {
        $updateData = [];

        if (isset($data['account_id'])) {
            $account = Account::where('user_id', $recurring->user_id)->findOrFail($data['account_id']);
            $updateData['account_id'] = $account->id;
            $updateData['currency'] = $account->currency;
        }

        if (isset($data['amount'])) {
            $currency = $updateData['currency'] ?? $recurring->currency;
            $money = Money::of($data['amount'], $currency);
            if ($money->isZero() || $money->isNegative()) {
                throw ValidationException::withMessages([
                    'amount' => ['Recurring amount must be strictly greater than 0.'],
                ]);
            }
            $updateData['amount'] = $money->toDecimal();
        }

        if (isset($data['category_id'])) {
            $updateData['category_id'] = $data['category_id'];
        }

        if (isset($data['type'])) {
            $updateData['type'] = strtolower($data['type']);
        }

        if (isset($data['description'])) {
            $updateData['description'] = trim($data['description']);
        }

        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'];
        }

        if (isset($data['frequency'])) {
            $updateData['frequency'] = strtolower($data['frequency']);
        }

        if (isset($data['start_date'])) {
            $updateData['start_date'] = Carbon::parse($data['start_date'])->toDateString();
        }

        if (array_key_exists('end_date', $data)) {
            $updateData['end_date'] = ! empty($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : null;
        }

        if (isset($data['next_due_date'])) {
            $updateData['next_due_date'] = Carbon::parse($data['next_due_date'])->toDateString();
        }

        if (isset($data['auto_post'])) {
            $updateData['auto_post'] = (bool) $data['auto_post'];
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }

        return $this->recurringRepository->update($recurring, $updateData);
    }

    /**
     * Delete a recurring transaction.
     */
    public function deleteRecurring(RecurringTransaction $recurring): bool
    {
        return $this->recurringRepository->delete($recurring);
    }

    /**
     * Compute next recurrence date avoiding monthly calendar overflow (e.g. Jan 31 -> Feb 28).
     */
    public function calculateNextDueDate(Carbon $currentDate, string $frequency): Carbon
    {
        $next = $currentDate->copy();

        return match (strtolower($frequency)) {
            'daily' => $next->addDay(),
            'weekly' => $next->addWeek(),
            'biweekly' => $next->addWeeks(2),
            'monthly' => $next->addMonthsNoOverflow(1),
            'quarterly' => $next->addMonthsNoOverflow(3),
            'yearly' => $next->addYearsNoOverflow(1),
            default => $next->addMonthsNoOverflow(1),
        };
    }

    /**
     * Process all due recurring transactions as of a target date.
     */
    public function processDueRecurringTransactions(?string $asOfDate = null): int
    {
        $targetDate = $asOfDate ? Carbon::parse($asOfDate)->toDateString() : Carbon::today()->toDateString();
        $dueItems = $this->recurringRepository->getDueRecurringTransactions($targetDate);

        $postedCount = 0;

        foreach ($dueItems as $recurring) {
            DB::transaction(function () use ($recurring, &$postedCount) {
                // Post transaction via TransactionService ensuring deduplication & audit trails
                $user = $recurring->user;
                if (! $user) {
                    return;
                }

                $this->transactionService->createTransaction($user, [
                    'account_id' => $recurring->account_id,
                    'category_id' => $recurring->category_id,
                    'type' => $recurring->type,
                    'amount' => $recurring->amount,
                    'description' => "[Recurring] {$recurring->description}",
                    'notes' => $recurring->notes,
                    'transacted_at' => $recurring->next_due_date->startOfDay()->toDateTimeString(),
                ]);

                // Calculate next recurrence date
                $nextDueDate = $this->calculateNextDueDate($recurring->next_due_date, $recurring->frequency);

                $updateData = [
                    'last_posted_at' => now(),
                    'next_due_date' => $nextDueDate->toDateString(),
                ];

                // Check if recurring series has expired
                if ($recurring->end_date && $nextDueDate->toDateString() > $recurring->end_date->toDateString()) {
                    $updateData['is_active'] = false;
                }

                $recurring->update($updateData);
                $postedCount++;
            });
        }

        return $postedCount;
    }
}
