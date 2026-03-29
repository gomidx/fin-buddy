<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class InsightService
{
    private const ESSENTIAL_CATEGORIES = ['Moradia', 'Alimentação', 'Transporte', 'Saúde', 'Educação'];
    private const LEISURE_CATEGORIES   = ['Lazer', 'Compras', 'Pets', 'Outros'];

    public function __construct(
        private readonly EmergencyFundService $emergencyFundService,
    ) {}

    public static function cacheKey(int $userId): string
    {
        return "insights:{$userId}";
    }

    public function getInsights(User $user): array
    {
        return Cache::remember(self::cacheKey($user->id), 300, fn () => $this->buildInsights($user));
    }

    private function buildInsights(User $user): array
    {
        $now   = Carbon::now();
        $year  = (int) $now->format('Y');
        $month = (int) $now->format('n');

        // Transações do mês atual com categoria carregada
        $currentExpenses = Transaction::with('category')
            ->forUser($user->id)
            ->inMonth($year, $month)
            ->ofType('expense')
            ->get();

        $income = (float) Transaction::forUser($user->id)
            ->inMonth($year, $month)
            ->ofType('income')
            ->sum('amount');

        $expenses    = (float) $currentExpenses->sum('amount');
        $savedAmount = $income - $expenses;
        $savingsRate = $income > 0 ? ($savedAmount / $income) * 100 : 0.0;

        // Breakdown por categoria
        $categoryTotals   = [];
        $essentialTotal   = 0.0;
        $leisureTotal     = 0.0;

        foreach ($currentExpenses as $t) {
            $catName = $t->category->name ?? 'Outros';
            $categoryTotals[$catName] = ($categoryTotals[$catName] ?? 0.0) + (float) $t->amount;

            if (in_array($catName, self::ESSENTIAL_CATEGORIES)) {
                $essentialTotal += (float) $t->amount;
            } elseif (in_array($catName, self::LEISURE_CATEGORIES)) {
                $leisureTotal += (float) $t->amount;
            }
        }

        // Categoria com maior gasto
        arsort($categoryTotals);
        $topCategory = !empty($categoryTotals)
            ? ['name' => array_key_first($categoryTotals), 'amount' => (float) reset($categoryTotals)]
            : null;

        // Variação de gastos vs mês anterior
        $prev         = $now->copy()->subMonth();
        $prevExpenses = (float) Transaction::forUser($user->id)
            ->inMonth((int) $prev->format('Y'), (int) $prev->format('n'))
            ->ofType('expense')
            ->sum('amount');

        $expenseChangePct = $prevExpenses > 0
            ? (($expenses - $prevExpenses) / $prevExpenses) * 100
            : null;

        // Ratios
        $leisureRatio   = $income > 0 ? ($leisureTotal / $income) * 100 : 0.0;
        $essentialRatio = $income > 0 ? ($essentialTotal / $income) * 100 : 0.0;

        // Reserva de emergência
        $fundStatus = $this->emergencyFundService->getStatus($user);

        // Classificação de saúde
        $health = $this->classifyHealth($savingsRate);

        // Recomendações
        $recommendations = $this->buildRecommendations(
            $savingsRate,
            $leisureRatio,
            $fundStatus,
            $topCategory,
            $expenseChangePct,
            $income,
        );

        return [
            'health'  => $health,
            'metrics' => [
                'savings_rate'           => round($savingsRate, 1),
                'income'                 => round($income, 2),
                'expenses'               => round($expenses, 2),
                'saved_amount'           => round($savedAmount, 2),
                'essential_expenses'     => round($essentialTotal, 2),
                'leisure_expenses'       => round($leisureTotal, 2),
                'essential_ratio'        => round($essentialRatio, 1),
                'leisure_ratio'          => round($leisureRatio, 1),
                'top_category'           => $topCategory
                    ? ['name' => $topCategory['name'], 'amount' => round($topCategory['amount'], 2)]
                    : null,
                'expense_change_pct'     => $expenseChangePct !== null ? round($expenseChangePct, 1) : null,
                'emergency_fund_months'  => $fundStatus['months_covered'] ?? null,
                'emergency_fund_status'  => $fundStatus['status'] ?? null,
            ],
            'recommendations' => $recommendations,
        ];
    }

    private function classifyHealth(float $savingsRate): array
    {
        if ($savingsRate >= 20) {
            return ['status' => 'healthy', 'label' => 'Saudável', 'color' => 'success'];
        }

        if ($savingsRate >= 10) {
            return ['status' => 'attention', 'label' => 'Atenção', 'color' => 'warning'];
        }

        return ['status' => 'risk', 'label' => 'Risco financeiro', 'color' => 'danger'];
    }

    private function buildRecommendations(
        float $savingsRate,
        float $leisureRatio,
        array $fundStatus,
        ?array $topCategory,
        ?float $expenseChangePct,
        float $income,
    ): array {
        $recs = [];

        // Taxa de economia
        if ($income <= 0) {
            $recs[] = ['type' => 'info', 'message' => 'Registre suas receitas para acompanhar sua saúde financeira.'];

            return $recs;
        }

        if ($savingsRate >= 20) {
            $recs[] = ['type' => 'success', 'message' => sprintf(
                'Parabéns! Você economizou %.0f%% da renda este mês.',
                $savingsRate,
            )];
        } elseif ($savingsRate >= 10) {
            $recs[] = ['type' => 'warning', 'message' => sprintf(
                'Você economizou %.0f%% da renda. Seu objetivo é 20%%.',
                $savingsRate,
            )];
        } else {
            $recs[] = ['type' => 'danger', 'message' => 'Seus gastos estão altos. Tente economizar pelo menos 20% da renda.'];
        }

        // Gastos em lazer > 30% da renda
        if ($leisureRatio > 30) {
            $recs[] = ['type' => 'warning', 'message' => sprintf(
                'Você gastou %.0f%% da renda em lazer este mês.',
                $leisureRatio,
            )];
        }

        // Reserva de emergência
        $efStatus = $fundStatus['status'] ?? null;
        $efMonths = (float) ($fundStatus['months_covered'] ?? 0);

        if ($efStatus === 'risk') {
            $recs[] = ['type' => 'danger', 'message' => sprintf(
                'Sua reserva cobre apenas %.1f meses de despesas. Recomendado: 6 meses.',
                $efMonths,
            )];
        } elseif ($efStatus === 'attention') {
            $recs[] = ['type' => 'warning', 'message' => sprintf(
                'Sua reserva cobre %.1f meses de despesas. Recomendado: 6 meses.',
                $efMonths,
            )];
        } elseif ($efStatus === 'safe') {
            $recs[] = ['type' => 'success', 'message' => sprintf(
                'Sua reserva de emergência está saudável! Cobrindo %.1f meses.',
                $efMonths,
            )];
        }

        // Crescimento de gastos ≥ 25% vs mês anterior
        if ($expenseChangePct !== null && $expenseChangePct >= 25) {
            $recs[] = ['type' => 'warning', 'message' => sprintf(
                'Seus gastos aumentaram %.0f%% em relação ao mês anterior.',
                $expenseChangePct,
            )];
        }

        // Maior categoria de gasto
        if ($topCategory) {
            $recs[] = ['type' => 'info', 'message' => sprintf(
                'Maior gasto do mês: %s (R$ %s).',
                $topCategory['name'],
                number_format($topCategory['amount'], 2, ',', '.'),
            )];
        }

        return $recs;
    }
}
