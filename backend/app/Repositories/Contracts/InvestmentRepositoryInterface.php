<?php

namespace App\Repositories\Contracts;

use App\Models\Investment;
use Illuminate\Database\Eloquent\Collection;

interface InvestmentRepositoryInterface
{
    public function listForUser(int $userId): Collection;
    public function findForUser(int $id, int $userId): ?Investment;
    public function create(array $data): Investment;
    public function update(Investment $investment, array $data): Investment;
    public function delete(Investment $investment): bool;
    public function getTotalByType(int $userId): array;
    public function getGrandTotal(int $userId): float;
}
