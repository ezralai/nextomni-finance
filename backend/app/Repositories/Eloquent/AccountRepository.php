<?php

namespace App\Repositories\Eloquent;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;

class AccountRepository implements AccountRepositoryInterface
{
    public function getForUser(string $userId, array $filters = []): Collection
    {
        $query = Account::where('user_id', $userId)
            ->with('institution')
            ->orderBy('created_at', 'desc');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (isset($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (isset($filters['currency'])) {
            $query->where('currency', strtoupper($filters['currency']));
        }

        return $query->get();
    }

    public function findById(string $id, string $userId): ?Account
    {
        return Account::where('user_id', $userId)
            ->where('id', $id)
            ->with('institution')
            ->first();
    }

    public function create(array $data): Account
    {
        return Account::create($data);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($data);
        return $account->fresh('institution');
    }

    public function delete(Account $account): bool
    {
        return (bool) $account->delete();
    }

    public function calculateNetWorth(string $userId, string $baseCurrency = 'MYR'): Money
    {
        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $total = Money::zero($baseCurrency);

        foreach ($accounts as $account) {
            $balance = Money::of($account->current_balance, $account->currency);
            // Liabilities (credit cards, loans) decrease net worth if positive balance represents debt
            if (in_array($account->account_type, ['credit_card', 'loan'])) {
                $total = $total->subtract($balance);
            } else {
                $total = $total->add($balance);
            }
        }

        return $total;
    }
}
