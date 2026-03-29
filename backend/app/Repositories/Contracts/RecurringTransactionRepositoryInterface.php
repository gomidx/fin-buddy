<?php

namespace App\Repositories\Contracts;

use App\Models\RecurringTransaction;
use Illuminate\Database\Eloquent\Collection;

interface RecurringTransactionRepositoryInterface
{
    public function listForUser(int $userId): Collection;
    public function findForUser(int $id, int $userId): ?RecurringTransaction;
    public function create(array $data): RecurringTransaction;
    public function update(RecurringTransaction $rt, array $data): RecurringTransaction;
    public function delete(RecurringTransaction $rt): bool;
    public function findDueForGeneration(): Collection;
}
