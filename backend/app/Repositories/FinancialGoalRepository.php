<?php

namespace App\Repositories;

use App\Models\FinancialGoal;
use App\Repositories\Contracts\FinancialGoalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FinancialGoalRepository implements FinancialGoalRepositoryInterface
{
    public function listForUser(int $userId): Collection
    {
        return FinancialGoal::where('user_id', $userId)->orderBy('target_date')->get();
    }

    public function findForUser(int $id, int $userId): ?FinancialGoal
    {
        return FinancialGoal::where('id', $id)->where('user_id', $userId)->first();
    }

    public function create(array $data): FinancialGoal
    {
        return FinancialGoal::create($data);
    }

    public function update(FinancialGoal $goal, array $data): FinancialGoal
    {
        $goal->update($data);
        return $goal->fresh();
    }

    public function delete(FinancialGoal $goal): bool
    {
        return $goal->delete();
    }
}
