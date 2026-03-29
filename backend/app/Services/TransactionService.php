<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\DashboardService;
use App\Services\InsightService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->transactionRepository->paginateForUser($user->id, $filters);
    }

    public function find(User $user, int $id): Transaction
    {
        $transaction = $this->transactionRepository->findForUser($id, $user->id);

        if (!$transaction) {
            throw new NotFoundHttpException('Transação não encontrada.');
        }

        return $transaction;
    }

    public function create(User $user, array $data): Transaction
    {
        $transaction = $this->transactionRepository->create(array_merge($data, [
            'user_id'      => $user->id,
            'is_recurring' => false,
        ]));

        $this->activityLog->transactionCreated($user->id, $transaction->id, $transaction->type);
        $this->invalidateUserCache($user->id);

        return $transaction->load('category:id,name,type');
    }

    public function update(User $user, int $id, array $data): Transaction
    {
        $transaction = $this->find($user, $id);

        $updated = $this->transactionRepository->update($transaction, $data);

        $this->activityLog->transactionUpdated($user->id, $id);
        $this->invalidateUserCache($user->id);

        return $updated;
    }

    public function delete(User $user, int $id): void
    {
        $transaction = $this->find($user, $id);

        $this->transactionRepository->delete($transaction);

        $this->activityLog->transactionDeleted($user->id, $id);
        $this->invalidateUserCache($user->id);
    }

    private function invalidateUserCache(int $userId): void
    {
        Cache::forget(DashboardService::cacheKey($userId));
        Cache::forget(InsightService::cacheKey($userId));
    }

    public function suggestCategory(User $user, string $description): ?int
    {
        return $this->transactionRepository->suggestCategory($user->id, $description);
    }
}
