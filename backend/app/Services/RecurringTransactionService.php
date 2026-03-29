<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\RecurringTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RecurringTransactionService
{
    public function __construct(
        private readonly RecurringTransactionRepositoryInterface $recurringRepository,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function list(User $user): Collection
    {
        return $this->recurringRepository->listForUser($user->id);
    }

    public function find(User $user, int $id): RecurringTransaction
    {
        $rt = $this->recurringRepository->findForUser($id, $user->id);

        if (!$rt) {
            throw new NotFoundHttpException('Recorrência não encontrada.');
        }

        return $rt;
    }

    public function create(User $user, array $data): RecurringTransaction
    {
        $rt = $this->recurringRepository->create(array_merge($data, [
            'user_id' => $user->id,
        ]));

        return $rt->load('category:id,name,type');
    }

    public function update(User $user, int $id, array $data): RecurringTransaction
    {
        $rt = $this->find($user, $id);
        return $this->recurringRepository->update($rt, $data);
    }

    public function delete(User $user, int $id): void
    {
        $rt = $this->find($user, $id);
        $this->recurringRepository->delete($rt);
    }

    public function generateDue(): int
    {
        $due   = $this->recurringRepository->findDueForGeneration();
        $count = 0;

        foreach ($due as $rt) {
            $date = $this->resolveTransactionDate($rt);

            Transaction::create([
                'user_id'      => $rt->user_id,
                'category_id'  => $rt->category_id,
                'type'         => $rt->type,
                'amount'       => $rt->amount,
                'description'  => $rt->description,
                'date'         => $date->toDateString(),
                'is_recurring' => true,
                'recurring_id' => $rt->id,
            ]);

            $rt->update(['last_generated_at' => now()]);

            $this->activityLog->transactionCreated($rt->user_id, $rt->id, $rt->type);

            $count++;
        }

        return $count;
    }

    private function resolveTransactionDate(RecurringTransaction $rt): Carbon
    {
        $start = Carbon::parse($rt->start_date);
        $now   = Carbon::now();

        if ($rt->frequency === 'monthly') {
            $day         = $start->day;
            $daysInMonth = $now->daysInMonth;
            $day         = min($day, $daysInMonth);

            return $now->copy()->day($day);
        }

        // yearly: same month/day, current year
        return $start->copy()->year($now->year);
    }
}
