<?php

namespace App\Repositories;

use App\Models\EmergencyFund;
use App\Models\Transaction;
use App\Repositories\Contracts\EmergencyFundRepositoryInterface;
use Illuminate\Support\Carbon;

class EmergencyFundRepository implements EmergencyFundRepositoryInterface
{
    public function __construct(
        private readonly EmergencyFund $model,
        private readonly Transaction $transaction,
    ) {}

    public function findByUser(int $userId): ?EmergencyFund
    {
        return $this->model->where('user_id', $userId)->first();
    }

    public function createOrUpdate(int $userId, array $data): EmergencyFund
    {
        return $this->model->updateOrCreate(
            ['user_id' => $userId],
            $data,
        );
    }

    public function getCurrentAmount(int $userId): float
    {
        return (float) $this->transaction
            ->where('user_id', $userId)
            ->where('type', 'emergency_fund')
            ->sum('amount');
    }

    public function getAverageMonthlyExpenses(int $userId, int $months = 3): float
    {
        $from = Carbon::now()->subMonths($months)->startOfMonth()->toDateString();

        $rows = $this->transaction
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->whereDate('date', '>=', $from)
            ->get(['amount', 'date']);

        if ($rows->isEmpty()) {
            return 0.0;
        }

        // Group by year-month in PHP for cross-DB compatibility
        $byMonth = $rows->groupBy(fn ($t) => Carbon::parse($t->date)->format('Y-m'));
        $sums    = $byMonth->map(fn ($group) => $group->sum('amount'));

        return (float) ($sums->sum() / $sums->count());
    }
}
