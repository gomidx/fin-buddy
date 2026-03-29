<?php

namespace App\Repositories;

use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Repositories\Contracts\InvestmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class InvestmentRepository implements InvestmentRepositoryInterface
{
    public function __construct(
        private readonly Investment $model,
        private readonly InvestmentTransaction $transactionModel,
    ) {}

    public function listForUser(int $userId): Collection
    {
        return $this->model
            ->with('investmentTransactions')
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();
    }

    public function findForUser(int $id, int $userId): ?Investment
    {
        return $this->model
            ->with('investmentTransactions')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data): Investment
    {
        return $this->model->create($data);
    }

    public function update(Investment $investment, array $data): Investment
    {
        $investment->update($data);
        return $investment->fresh('investmentTransactions');
    }

    public function delete(Investment $investment): bool
    {
        return (bool) $investment->delete();
    }

    public function getTotalByType(int $userId): array
    {
        $investments = $this->listForUser($userId);
        $totals      = [];

        foreach ($investments as $investment) {
            $type = $investment->type;
            $totals[$type] = ($totals[$type] ?? 0.0) + $investment->totalInvested();
        }

        return $totals;
    }

    public function getGrandTotal(int $userId): float
    {
        return (float) array_sum($this->getTotalByType($userId));
    }
}
