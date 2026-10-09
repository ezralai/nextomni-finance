<?php

namespace App\Repositories\Eloquent;

use App\Models\Budget;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BudgetRepository implements BudgetRepositoryInterface
{
    public function getForUser(string $userId, array $filters = []): Collection
    {
        $query = Budget::where('user_id', $userId)
            ->with('category.children')
            ->orderBy('start_date', 'desc');

        if (! empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        if (! empty($filters['date'])) {
            $query->where('start_date', '<=', $filters['date'])
                ->where('end_date', '>=', $filters['date']);
        }

        return $query->get();
    }

    public function findById(string $id, string $userId): ?Budget
    {
        return Budget::where('user_id', $userId)
            ->where('id', $id)
            ->with('category.children')
            ->first();
    }

    public function create(array $data): Budget
    {
        return Budget::create($data);
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update($data);
        return $budget->fresh('category.children');
    }

    public function delete(Budget $budget): bool
    {
        return (bool) $budget->delete();
    }

    public function findExisting(string $userId, string $categoryId, string $startDate, string $endDate): ?Budget
    {
        return Budget::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->first();
    }
}
