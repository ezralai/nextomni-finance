<?php

namespace App\Repositories\Contracts;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Collection;

interface BudgetRepositoryInterface
{
    /**
     * Retrieve all active budgets for a user in a given period.
     */
    public function getForUser(string $userId, array $filters = []): Collection;

    /**
     * Find a budget by ID.
     */
    public function findById(string $id, string $userId): ?Budget;

    /**
     * Create a new budget record.
     */
    public function create(array $data): Budget;

    /**
     * Update an existing budget record.
     */
    public function update(Budget $budget, array $data): Budget;

    /**
     * Delete a budget record.
     */
    public function delete(Budget $budget): bool;

    /**
     * Find existing budget for user, category, and date range.
     */
    public function findExisting(string $userId, string $categoryId, string $startDate, string $endDate): ?Budget;
}
