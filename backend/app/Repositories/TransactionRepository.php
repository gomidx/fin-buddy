<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private readonly Transaction $model) {}

    public function paginateForUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model
            ->with('category:id,name,type')
            ->forUser($userId)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query->inMonth((int) $filters['year'], (int) $filters['month']);
        } elseif (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
            if (!empty($filters['date_to'])) {
                $query->whereDate('date', '<=', $filters['date_to']);
            }
        }

        return $query->paginate(15);
    }

    public function findForUser(int $id, int $userId): ?Transaction
    {
        return $this->model
            ->with('category:id,name,type')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data): Transaction
    {
        return $this->model->create($data);
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update($data);
        return $transaction->fresh('category');
    }

    public function delete(Transaction $transaction): bool
    {
        return (bool) $transaction->delete();
    }

    public function suggestCategory(int $userId, string $description): ?int
    {
        $result = $this->model
            ->forUser($userId)
            ->where('description', 'like', '%' . $description . '%')
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->first();

        return $result?->category_id;
    }

    public function sumByTypeInMonth(int $userId, string $type, int $year, int $month): float
    {
        return (float) $this->model
            ->forUser($userId)
            ->inMonth($year, $month)
            ->ofType($type)
            ->sum('amount');
    }

    public function getWithCategoryByTypeInMonth(int $userId, string $type, int $year, int $month): Collection
    {
        return $this->model
            ->with('category')
            ->forUser($userId)
            ->inMonth($year, $month)
            ->ofType($type)
            ->get();
    }

    public function getByTypesFromDate(int $userId, array $types, string $fromDate): Collection
    {
        return $this->model
            ->forUser($userId)
            ->where('date', '>=', $fromDate)
            ->whereIn('type', $types)
            ->get();
    }
}
