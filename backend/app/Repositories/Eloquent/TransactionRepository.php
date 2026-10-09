<?php

namespace App\Repositories\Eloquent;

use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function getForUser(string $userId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Transaction::where('user_id', $userId)
            ->with(['account.institution', 'category'])
            ->orderBy('transacted_at', 'desc')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('transacted_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('transacted_at', '<=', $filters['end_date']);
        }

        if (! empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', $searchTerm)
                    ->orWhere('notes', 'like', $searchTerm)
                    ->orWhere('reference_number', 'like', $searchTerm);
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?Transaction
    {
        return Transaction::with(['account.institution', 'category'])->find($id);
    }

    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update($data);
        return $transaction->fresh(['account.institution', 'category']);
    }

    public function delete(Transaction $transaction): bool
    {
        return (bool) $transaction->delete();
    }

    public function findByDeduplicationHash(string $userId, string $hash): ?Transaction
    {
        return Transaction::where('user_id', $userId)
            ->where('deduplication_hash', $hash)
            ->first();
    }

    public function getTotalsForPeriod(string $userId, string $startDate, string $endDate): array
    {
        $transactions = Transaction::where('user_id', $userId)
            ->whereBetween('transacted_at', [$startDate, $endDate])
            ->where('status', 'posted')
            ->get();

        $income = Money::zero();
        $expense = Money::zero();

        foreach ($transactions as $txn) {
            $money = Money::of($txn->amount, $txn->currency);
            if ($txn->type === 'income') {
                $income = $income->add($money);
            } elseif ($txn->type === 'expense') {
                $expense = $expense->add($money);
            }
        }

        return [
            'income' => $income->toDecimal(),
            'expense' => $expense->toDecimal(),
            'net' => $income->subtract($expense)->toDecimal(),
        ];
    }
}
