<?php

namespace App\Repositories\Eloquent;

use App\Models\Goal;
use App\Repositories\Contracts\GoalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class GoalRepository implements GoalRepositoryInterface
{
    public function getForUser(string $userId, array $filters = []): Collection
    {
        $query = Goal::where('user_id', $userId)
            ->with(['account.institution'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function findById(string $id, string $userId): ?Goal
    {
        return Goal::where('user_id', $userId)
            ->where('id', $id)
            ->with(['account.institution'])
            ->first();
    }

    public function create(array $data): Goal
    {
        return Goal::create($data);
    }

    public function update(Goal $goal, array $data): Goal
    {
        $goal->update($data);
        return $goal->fresh(['account.institution']);
    }

    public function delete(Goal $goal): bool
    {
        return $goal->delete();
    }
}
