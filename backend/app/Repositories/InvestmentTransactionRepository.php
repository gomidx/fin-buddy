<?php

namespace App\Repositories;

use App\Models\InvestmentTransaction;
use App\Repositories\Contracts\InvestmentTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class InvestmentTransactionRepository implements InvestmentTransactionRepositoryInterface
{
    public function __construct(private readonly InvestmentTransaction $model) {}

    public function listForInvestment(int $investmentId): Collection
    {
        return $this->model
            ->where('investment_id', $investmentId)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function findById(int $id): ?InvestmentTransaction
    {
        return $this->model->find($id);
    }

    public function create(array $data): InvestmentTransaction
    {
        return $this->model->create($data);
    }

    public function update(InvestmentTransaction $it, array $data): InvestmentTransaction
    {
        $it->update($data);
        return $it->fresh();
    }

    public function delete(InvestmentTransaction $it): bool
    {
        return (bool) $it->delete();
    }

    public function getNetAllocatedInPeriod(int $userId, string $from, string $to): float
    {
        return (float) $this->model
            ->whereHas('investment', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('type', ['buy', 'sell'])
            ->whereBetween('date', [$from, $to])
            ->selectRaw("SUM(CASE WHEN type = 'buy' THEN amount WHEN type = 'sell' THEN -amount ELSE 0 END) as net")
            ->value('net') ?? 0.0;
    }
}
