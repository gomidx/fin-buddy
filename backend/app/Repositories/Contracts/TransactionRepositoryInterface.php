<?php

namespace App\Repositories\Contracts;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TransactionRepositoryInterface
{
    public function paginateForUser(int $userId, array $filters = []): LengthAwarePaginator;
    public function findForUser(int $id, int $userId): ?Transaction;
    public function create(array $data): Transaction;
    public function update(Transaction $transaction, array $data): Transaction;
    public function delete(Transaction $transaction): bool;
    public function suggestCategory(int $userId, string $description): ?int;
    public function sumByTypeInMonth(int $userId, string $type, int $year, int $month): float;
    public function getWithCategoryByTypeInMonth(int $userId, string $type, int $year, int $month): Collection;
    public function getByTypesFromDate(int $userId, array $types, string $fromDate): Collection;
}
