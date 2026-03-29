<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\InvestmentRepositoryInterface;
use App\Repositories\Contracts\InvestmentTransactionRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        private readonly EmergencyFundService $emergencyFundService,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly InvestmentRepositoryInterface $investmentRepository,
        private readonly InvestmentTransactionRepositoryInterface $investmentTransactionRepository,
    ) {}

    public static function cacheKey(int $userId): string
    {
        return "dashboard:{$userId}";
    }

    public function getSummary(User $user): array
    {
        return Cache::remember(self::cacheKey($user->id), 300, fn () => $this->buildSummary($user));
    }

    // -------------------------------------------------------------------------
    // Summary builder
    // -------------------------------------------------------------------------

    private function buildSummary(User $user): array
    {
        $now   = Carbon::now();
        $year  = (int) $now->format('Y');
        $month = (int) $now->format('n');

        $currentMonth      = $this->buildCurrentMonth($user->id, $year, $month);
        $expensesByCategory = $this->buildExpensesByCategory($user->id, $year, $month);
        $monthlyTotals     = $this->buildMonthlyTotals($user->id);
        $financialEvolution = $this->buildFinancialEvolution($user->id);
        $totalInvested     = round($this->investmentRepository->getGrandTotal($user->id), 2);
        $emergencyFund     = $this->emergencyFundService->getStatus($user);
        $insight           = $this->buildInsight(
            $currentMonth['income'],
            $currentMonth['free_balance'],
            $currentMonth['allocation_rate'],
        );

        return [
            'current_month'         => $currentMonth,
            'emergency_fund'        => $emergencyFund,
            'expenses_by_category'  => $expensesByCategory,
            'monthly_totals'        => $monthlyTotals,
            'financial_evolution'   => $financialEvolution,
            'total_invested'        => $totalInvested,
            'insight'               => $insight,
        ];
    }

    // -------------------------------------------------------------------------
    // Section builders
    // -------------------------------------------------------------------------

    private function buildCurrentMonth(int $userId, int $year, int $month): array
    {
        $income   = round($this->transactionRepository->sumByTypeInMonth($userId, 'income', $year, $month), 2);
        $expenses = round($this->transactionRepository->sumByTypeInMonth($userId, 'expense', $year, $month), 2);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth   = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $efAllocated          = $this->transactionRepository->sumByTypeInMonth($userId, 'emergency_fund', $year, $month);
        $investmentAllocated  = $this->investmentTransactionRepository->getNetAllocatedInPeriod($userId, $startOfMonth, $endOfMonth);

        $allocations    = round($efAllocated + $investmentAllocated, 2);
        $freeBalance    = round($income - $expenses - $allocations, 2);
        $allocationRate = $income > 0 ? round(($allocations / $income) * 100, 1) : 0.0;

        return [
            'income'          => $income,
            'expenses'        => $expenses,
            'allocations'     => $allocations,
            'free_balance'    => $freeBalance,
            'allocation_rate' => $allocationRate,
        ];
    }

    private function buildExpensesByCategory(int $userId, int $year, int $month): array
    {
        $transactions = $this->transactionRepository->getWithCategoryByTypeInMonth($userId, 'expense', $year, $month);

        $grouped = [];
        foreach ($transactions as $t) {
            $catName = $t->category->name ?? 'Outros';
            $catId   = $t->category_id ?? 'outros';
            if (!isset($grouped[$catId])) {
                $grouped[$catId] = ['category' => $catName, 'amount' => 0.0];
            }
            $grouped[$catId]['amount'] += (float) $t->amount;
        }

        foreach ($grouped as &$item) {
            $item['amount'] = round($item['amount'], 2);
        }
        unset($item);

        usort($grouped, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return array_values($grouped);
    }

    private function buildMonthlyTotals(int $userId): array
    {
        $labels       = $this->buildMonthLabels(5);
        $fromDate     = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();
        $transactions = $this->transactionRepository->getByTypesFromDate($userId, ['income', 'expense'], $fromDate);

        return array_map(function (string $label) use ($transactions): array {
            $income   = 0.0;
            $expenses = 0.0;
            foreach ($transactions as $t) {
                if (Carbon::parse($t->date)->format('Y-m') !== $label) {
                    continue;
                }
                if ($t->type === 'income') {
                    $income += (float) $t->amount;
                } elseif ($t->type === 'expense') {
                    $expenses += (float) $t->amount;
                }
            }
            return ['month' => $label, 'income' => round($income, 2), 'expenses' => round($expenses, 2)];
        }, $labels);
    }

    private function buildFinancialEvolution(int $userId): array
    {
        $labels       = $this->buildMonthLabels(11);
        $fromDate     = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
        $transactions = $this->transactionRepository->getByTypesFromDate($userId, ['income', 'expense'], $fromDate);

        return array_map(function (string $label) use ($transactions): array {
            $income   = 0.0;
            $expenses = 0.0;
            foreach ($transactions as $t) {
                if (Carbon::parse($t->date)->format('Y-m') !== $label) {
                    continue;
                }
                if ($t->type === 'income') {
                    $income += (float) $t->amount;
                } elseif ($t->type === 'expense') {
                    $expenses += (float) $t->amount;
                }
            }
            return ['month' => $label, 'balance' => round($income - $expenses, 2)];
        }, $labels);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildMonthLabels(int $monthsBack): array
    {
        $labels = [];
        for ($i = $monthsBack; $i >= 0; $i--) {
            $labels[] = Carbon::now()->subMonths($i)->format('Y-m');
        }
        return $labels;
    }

    private function buildInsight(float $income, float $freeBalance, float $allocationRate): string
    {
        if ($income <= 0) {
            return 'Registre suas receitas para começar a acompanhar sua saúde financeira.';
        }

        if ($freeBalance < 0) {
            return 'Atenção! Suas despesas e alocações superaram suas receitas este mês.';
        }

        if ($allocationRate >= 20) {
            return "Ótimo trabalho! Você alocou {$allocationRate}% da sua renda em reserva e investimentos este mês.";
        }

        if ($allocationRate > 0) {
            return "Você alocou {$allocationRate}% da sua renda este mês. Tente chegar a 20% em reserva e investimentos.";
        }

        return 'Você ainda não alocou nada em reserva ou investimentos este mês. Que tal começar agora?';
    }
}
