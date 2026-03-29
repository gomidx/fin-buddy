<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\EmergencyFundRepositoryInterface;
use App\Services\DashboardService;
use App\Services\InsightService;
use Illuminate\Support\Facades\Cache;

class EmergencyFundService
{
    private const SYSTEM_CATEGORY_NAME = 'Reserva de Emergência';

    public function __construct(
        private readonly EmergencyFundRepositoryInterface $fundRepository,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function getStatus(User $user): array
    {
        $fund    = $this->fundRepository->findByUser($user->id);
        $current = $this->fundRepository->getCurrentAmount($user->id);
        $avgExp  = $this->fundRepository->getAverageMonthlyExpenses($user->id);

        if (!$fund) {
            return [
                'has_goal'                  => false,
                'target_months'             => null,
                'target_amount'             => null,
                'current_amount'            => $current,
                'percentage'                => 0.0,
                'months_covered'            => $avgExp > 0 ? round($current / $avgExp, 1) : 0.0,
                'average_monthly_expenses'  => round($avgExp, 2),
                'status'                    => $this->resolveStatus($current, $avgExp),
            ];
        }

        $target     = (float) $fund->target_amount;
        $percentage = $target > 0 ? min(100, round(($current / $target) * 100, 1)) : 0.0;
        $covered    = $avgExp > 0 ? round($current / $avgExp, 1) : 0.0;

        return [
            'has_goal'                  => true,
            'target_months'             => $fund->target_months,
            'target_amount'             => $target,
            'current_amount'            => $current,
            'percentage'                => $percentage,
            'months_covered'            => $covered,
            'average_monthly_expenses'  => round($avgExp, 2),
            'status'                    => $this->resolveStatus($current, $avgExp),
        ];
    }

    public function updateGoal(User $user, array $data): array
    {
        $avgExp = $this->fundRepository->getAverageMonthlyExpenses($user->id);

        // Auto-calculate target_amount if not provided or derive from months
        $targetAmount = isset($data['target_amount']) && $data['target_amount'] !== null
            ? (float) $data['target_amount']
            : ($avgExp > 0 ? round($avgExp * $data['target_months'], 2) : 0.0);

        $this->fundRepository->createOrUpdate($user->id, [
            'target_months' => $data['target_months'],
            'target_amount' => $targetAmount,
        ]);

        $this->activityLog->goalUpdated($user->id, $user->id);

        Cache::forget(DashboardService::cacheKey($user->id));

        return $this->getStatus($user);
    }

    public function deposit(User $user, array $data): Transaction
    {
        $category = Category::whereNull('user_id')
            ->where('name', self::SYSTEM_CATEGORY_NAME)
            ->where('type', 'expense')
            ->firstOrFail();

        $transaction = Transaction::create([
            'user_id'      => $user->id,
            'category_id'  => $category->id,
            'type'         => 'emergency_fund',
            'amount'       => $data['amount'],
            'description'  => $data['description'] ?? 'Depósito na reserva de emergência',
            'date'         => $data['date'],
            'is_recurring' => false,
        ]);

        $this->activityLog->transactionCreated($user->id, $transaction->id, 'emergency_fund');

        Cache::forget(DashboardService::cacheKey($user->id));
        Cache::forget(InsightService::cacheKey($user->id));

        return $transaction;
    }

    private function resolveStatus(float $currentAmount, float $avgMonthlyExpenses): string
    {
        if ($avgMonthlyExpenses <= 0) {
            return 'not_configured';
        }

        $covered = $currentAmount / $avgMonthlyExpenses;

        if ($covered < 3) {
            return 'risk';
        }

        if ($covered < 6) {
            return 'attention';
        }

        return 'safe';
    }
}
