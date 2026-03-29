<?php

namespace App\Repositories;

use App\Models\RecurringTransaction;
use App\Repositories\Contracts\RecurringTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RecurringTransactionRepository implements RecurringTransactionRepositoryInterface
{
    public function __construct(private readonly RecurringTransaction $model) {}

    public function listForUser(int $userId): Collection
    {
        return $this->model
            ->with('category:id,name,type')
            ->where('user_id', $userId)
            ->orderBy('description')
            ->get();
    }

    public function findForUser(int $id, int $userId): ?RecurringTransaction
    {
        return $this->model
            ->with('category:id,name,type')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data): RecurringTransaction
    {
        return $this->model->create($data);
    }

    public function update(RecurringTransaction $rt, array $data): RecurringTransaction
    {
        $rt->update($data);
        return $rt->fresh('category');
    }

    public function delete(RecurringTransaction $rt): bool
    {
        return (bool) $rt->delete();
    }

    public function findDueForGeneration(): Collection
    {
        $today         = Carbon::today()->toDateString();
        $startOfMonth  = Carbon::now()->startOfMonth()->toDateTimeString();
        $startOfYear   = Carbon::now()->startOfYear()->toDateTimeString();

        return $this->model
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->where(function ($query) use ($today, $startOfMonth, $startOfYear) {
                $query->where(function ($q) use ($today) {
                    // Never generated and start_date is in the past or today
                    $q->whereNull('last_generated_at')
                      ->whereDate('start_date', '<=', $today);
                })->orWhere(function ($q) use ($today, $startOfMonth) {
                    // Monthly: not yet generated this month
                    $q->where('frequency', 'monthly')
                      ->whereNotNull('last_generated_at')
                      ->where('last_generated_at', '<', $startOfMonth)
                      ->whereDate('start_date', '<=', $today);
                })->orWhere(function ($q) use ($today, $startOfYear) {
                    // Yearly: not yet generated this year
                    $q->where('frequency', 'yearly')
                      ->whereNotNull('last_generated_at')
                      ->where('last_generated_at', '<', $startOfYear)
                      ->whereDate('start_date', '<=', $today);
                });
            })
            ->get();
    }
}
