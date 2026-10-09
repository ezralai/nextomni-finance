<?php

namespace App\Repositories\Contracts;

use App\Models\Goal;
use Illuminate\Database\Eloquent\Collection;

interface GoalRepositoryInterface
{
    /**
     * Retrieve all goals for a user.
     */
    public function getForUser(string $userId, array $filters = []): Collection;

    /**
     * Find goal by ID.
     */
    public function findById(string $id, string $userId): ?Goal;

    /**
     * Create a new goal record.
     */
    public function create(array $data): Goal;

    /**
     * Update an existing goal record.
     */
    public function update(Goal $goal, array $data): Goal;

    /**
     * Delete a goal record.
     */
    public function delete(Goal $goal): bool;
}
