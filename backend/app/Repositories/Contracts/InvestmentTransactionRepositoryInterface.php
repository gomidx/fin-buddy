<?php

namespace App\Repositories\Contracts;

use App\Models\InvestmentTransaction;
use Illuminate\Database\Eloquent\Collection;

interface InvestmentTransactionRepositoryInterface
{
    public function listForInvestment(int $investmentId): Collection;
    public function findById(int $id): ?InvestmentTransaction;
    public function create(array $data): InvestmentTransaction;
    public function update(InvestmentTransaction $it, array $data): InvestmentTransaction;
    public function delete(InvestmentTransaction $it): bool;
    public function getNetAllocatedInPeriod(int $userId, string $from, string $to): float;
}
