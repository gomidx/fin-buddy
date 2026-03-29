<?php

namespace App\Services;

use App\Models\FinNotification;
use App\Models\FinancialGoal;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    // Janelas de tempo para geração de notificações
    private const RECURRING_DAYS_AHEAD  = 3;   // avisa com 3 dias de antecedência
    private const GOAL_DAYS_AHEAD       = 30;  // avisa quando prazo < 30 dias
    private const GOAL_MIN_PROGRESS     = 0.7; // só avisa se progresso < 70%
    private const NO_ACTIVITY_DAYS      = 7;   // avisa se sem transações há 7 dias

    // ──────────────────────────────────────────────
    // Leitura
    // ──────────────────────────────────────────────

    public function listForUser(int $userId): Collection
    {
        return FinNotification::where('user_id', $userId)
            ->orderBy('is_read')
            ->orderByDesc('created_at')
            ->get();
    }

    public function unreadCount(int $userId): int
    {
        return FinNotification::where('user_id', $userId)->where('is_read', false)->count();
    }

    // ──────────────────────────────────────────────
    // Marcação como lida
    // ──────────────────────────────────────────────

    public function markAsRead(int $id, int $userId): bool
    {
        $notification = FinNotification::where('id', $id)->where('user_id', $userId)->first();

        if (!$notification || $notification->is_read) {
            return false;
        }

        $notification->update(['is_read' => true, 'read_at' => Carbon::now()]);

        return true;
    }

    public function markAllAsRead(int $userId): int
    {
        return FinNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => Carbon::now()]);
    }

    // ──────────────────────────────────────────────
    // Geração automática
    // ──────────────────────────────────────────────

    /**
     * Gera notificações para todos os usuários.
     * Chamado pelo scheduler diariamente.
     */
    public function generateForAllUsers(): array
    {
        $results = ['recurring' => 0, 'goals' => 0, 'no_activity' => 0];

        User::each(function (User $user) use (&$results) {
            $results['recurring']    += $this->generateRecurringDue($user);
            $results['goals']        += $this->generateGoalReminders($user);
            $results['no_activity']  += $this->generateNoActivityReminder($user);
        });

        return $results;
    }

    /**
     * Notifica sobre despesas recorrentes com vencimento em até 3 dias.
     */
    public function generateRecurringDue(User $user): int
    {
        $cutoff = Carbon::today()->addDays(self::RECURRING_DAYS_AHEAD);
        $count  = 0;

        $recurrings = RecurringTransaction::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', Carbon::today());
            })
            ->get();

        foreach ($recurrings as $rt) {
            if (!$this->isDueWithin($rt, $cutoff)) {
                continue;
            }

            // Evita duplicata: apenas uma notificação do tipo por recorrência por dia
            $exists = FinNotification::where('user_id', $user->id)
                ->where('type', 'recurring_due')
                ->where('message', 'like', "%#{$rt->id}%")
                ->whereDate('created_at', Carbon::today())
                ->exists();

            if ($exists) {
                continue;
            }

            FinNotification::create([
                'user_id' => $user->id,
                'type'    => 'recurring_due',
                'title'   => 'Despesa recorrente próxima',
                'message' => "A recorrência \"{$rt->description}\" vence em breve. Verifique se foi registrada. (#{$rt->id})",
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Notifica sobre metas com prazo próximo (< 30 dias) e progresso < 70%.
     */
    public function generateGoalReminders(User $user): int
    {
        $count = 0;

        $goals = FinancialGoal::where('user_id', $user->id)
            ->whereNotNull('target_date')
            ->where('target_date', '>=', Carbon::today())
            ->where('target_date', '<=', Carbon::today()->addDays(self::GOAL_DAYS_AHEAD))
            ->get();

        foreach ($goals as $goal) {
            $progress = $goal->target_amount > 0
                ? ($goal->current_amount / $goal->target_amount)
                : 1.0;

            if ($progress >= self::GOAL_MIN_PROGRESS) {
                continue;
            }

            // Evita duplicata: apenas uma notificação por meta por mês
            $exists = FinNotification::where('user_id', $user->id)
                ->where('type', 'goal_reminder')
                ->where('message', 'like', "%#{$goal->id}%")
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->exists();

            if ($exists) {
                continue;
            }

            $daysLeft = (int) Carbon::today()->diffInDays($goal->target_date);
            $pct      = round($progress * 100, 0);

            FinNotification::create([
                'user_id' => $user->id,
                'type'    => 'goal_reminder',
                'title'   => 'Meta com prazo próximo',
                'message' => "A meta \"{$goal->name}\" vence em {$daysLeft} dias e está {$pct}% concluída. (#{$goal->id})",
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Notifica se o usuário não registrou nenhuma transação nos últimos 7 dias.
     */
    public function generateNoActivityReminder(User $user): int
    {
        $since = Carbon::today()->subDays(self::NO_ACTIVITY_DAYS);

        $hasActivity = Transaction::where('user_id', $user->id)
            ->where('date', '>=', $since->toDateString())
            ->exists();

        if ($hasActivity) {
            return 0;
        }

        // Evita duplicata: apenas uma notificação desse tipo por semana
        $exists = FinNotification::where('user_id', $user->id)
            ->where('type', 'no_activity')
            ->where('created_at', '>=', $since)
            ->exists();

        if ($exists) {
            return 0;
        }

        FinNotification::create([
            'user_id' => $user->id,
            'type'    => 'no_activity',
            'title'   => 'Lembrete de registro',
            'message' => 'Você não registrou nenhuma transação nos últimos 7 dias. Mantenha seu histórico atualizado!',
        ]);

        return 1;
    }

    // ──────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────

    /**
     * Verifica se a recorrência está prestes a vencer (dentro da janela de corte).
     */
    private function isDueWithin(RecurringTransaction $rt, Carbon $cutoff): bool
    {
        $lastGenerated = $rt->last_generated_at ? Carbon::parse($rt->last_generated_at) : null;
        $start         = Carbon::parse($rt->start_date);
        $today         = Carbon::today();

        if ($rt->frequency === 'monthly') {
            // Próxima ocorrência: dia do mês igual ao dia de start_date
            $nextDue = Carbon::today()->day($start->day)->startOfDay();
            if ($nextDue->lessThan($today)) {
                $nextDue->addMonth();
            }

            // Só notifica se ainda não gerou para este mês
            if ($lastGenerated && $lastGenerated->gte(Carbon::now()->startOfMonth())) {
                return false;
            }

            return $nextDue->lte($cutoff);
        }

        if ($rt->frequency === 'yearly') {
            $nextDue = Carbon::today()->month($start->month)->day($start->day)->startOfDay();
            if ($nextDue->lessThan($today)) {
                $nextDue->addYear();
            }

            if ($lastGenerated && $lastGenerated->gte(Carbon::now()->startOfYear())) {
                return false;
            }

            return $nextDue->lte($cutoff);
        }

        return false;
    }
}
