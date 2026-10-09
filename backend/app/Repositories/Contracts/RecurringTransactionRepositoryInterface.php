<?php

namespace App\Repositories\Contracts;

use App\Models\RecurringTransaction;
use Illuminate\Database\Eloquent\Collection;

interface RecurringTransactionRepositoryInterface
{
    /**
     * Retrieve all recurring transactions for a user.
     */
    public function getForUser(string $userId, array $filters = []): Collection;

    /**
     * Find recurring transaction by ID for a user.
     */
    public function findById(string $id, string $userId): ?RecurringTransaction;

    /**
     * Retrieve all active recurring transactions due on or before a given date across users (for background command).
     */
    public function getDueRecurringTransactions(string $date): Collection;

    /**
     * Create a recurring transaction record.
     */
    public function create(array $data): RecurringTransaction;

    /**
     * Update an existing recurring transaction record.
     */
    public function update(RecurringTransaction $recurring, array $data): RecurringTransaction;

    /**
     * Delete a recurring transaction record.
     */
    public function delete(RecurringTransaction $recurring): bool;
}
