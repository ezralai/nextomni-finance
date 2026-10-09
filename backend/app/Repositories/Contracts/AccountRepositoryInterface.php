<?php

namespace App\Repositories\Contracts;

use App\Models\Account;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;

interface AccountRepositoryInterface
{
    /**
     * Retrieve all active accounts for a user with institution relations.
     *
     * @return Collection<int, Account>
     */
    public function getForUser(string $userId, array $filters = []): Collection;

    /**
     * Find an account by ID scoped to the given user.
     */
    public function findById(string $id, string $userId): ?Account;

    /**
     * Create a new financial account.
     */
    public function create(array $data): Account;

    /**
     * Update an existing financial account.
     */
    public function update(Account $account, array $data): Account;

    /**
     * Delete an account.
     */
    public function delete(Account $account): bool;

    /**
     * Calculate total net balance across all accounts for the given user.
     */
    public function calculateNetWorth(string $userId, string $baseCurrency = 'MYR'): Money;
}
