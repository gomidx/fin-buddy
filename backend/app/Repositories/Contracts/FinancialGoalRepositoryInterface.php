<?php

namespace App\Repositories\Contracts;

use App\Models\FinancialGoal;
use Illuminate\Database\Eloquent\Collection;

interface FinancialGoalRepositoryInterface
{
    public function listForUser(int $userId): Collection;
    public function findForUser(int $id, int $userId): ?FinancialGoal;
    public function create(array $data): FinancialGoal;
    public function update(FinancialGoal $goal, array $data): FinancialGoal;
    public function delete(FinancialGoal $goal): bool;
}
