<?php

namespace App\Repositories\Eloquent;

use App\Models\RecurringTransaction;
use App\Repositories\Contracts\RecurringTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class RecurringTransactionRepository implements RecurringTransactionRepositoryInterface
{
    public function getForUser(string $userId, array $filters = []): Collection
    {
        $query = RecurringTransaction::where('user_id', $userId)
            ->with(['account.institution', 'category'])
            ->orderBy('next_due_date', 'asc');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        return $query->get();
    }

    public function findById(string $id, string $userId): ?RecurringTransaction
    {
        return RecurringTransaction::where('user_id', $userId)
            ->where('id', $id)
            ->with(['account.institution', 'category'])
            ->first();
    }

    public function getDueRecurringTransactions(string $date): Collection
    {
        // Global system processing bypasses tenant scope to iterate across all active users
        return RecurringTransaction::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('auto_post', true)
            ->whereDate('next_due_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date);
            })
            ->with(['user', 'account', 'category'])
            ->get();
    }

    public function create(array $data): RecurringTransaction
    {
        return RecurringTransaction::create($data);
    }

    public function update(RecurringTransaction $recurring, array $data): RecurringTransaction
    {
        $recurring->update($data);
        return $recurring->fresh(['account.institution', 'category']);
    }

    public function delete(RecurringTransaction $recurring): bool
    {
        return $recurring->delete();
    }
}
