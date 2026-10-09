<?php

namespace App\Repositories\Contracts;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TransactionRepositoryInterface
{
    /**
     * Retrieve paginated transactions for a user with optional filtering.
     *
     * @param array{
     *   account_id?: string,
     *   category_id?: string,
     *   type?: string,
     *   status?: string,
     *   start_date?: string,
     *   end_date?: string,
     *   search?: string
     * } $filters
     */
    public function getForUser(string $userId, array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * Find a transaction by UUID.
     */
    public function findById(string $id): ?Transaction;

    /**
     * Create a new transaction record.
     */
    public function create(array $data): Transaction;

    /**
     * Update an existing transaction record.
     */
    public function update(Transaction $transaction, array $data): Transaction;

    /**
     * Delete a transaction record.
     */
    public function delete(Transaction $transaction): bool;

    /**
     * Find existing transaction by user and deduplication hash.
     */
    public function findByDeduplicationHash(string $userId, string $hash): ?Transaction;

    /**
     * Compute total income and total expense for a given date range.
     *
     * @return array{income: string, expense: string, net: string}
     */
    public function getTotalsForPeriod(string $userId, string $startDate, string $endDate): array;
}
