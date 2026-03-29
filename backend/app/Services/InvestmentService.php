<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\User;
use App\Repositories\Contracts\InvestmentRepositoryInterface;
use App\Repositories\Contracts\InvestmentTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvestmentService
{
    public function __construct(
        private readonly InvestmentRepositoryInterface $investmentRepository,
        private readonly InvestmentTransactionRepositoryInterface $transactionRepository,
        private readonly ActivityLogService $activityLog,
    ) {}

    // -------------------------------------------------------------------------
    // Investments
    // -------------------------------------------------------------------------

    public function list(User $user): array
    {
        $investments = $this->investmentRepository->listForUser($user->id);

        return [
            'investments'    => $investments->map(fn ($inv) => $this->formatInvestment($inv)),
            'totals_by_type' => $this->investmentRepository->getTotalByType($user->id),
            'grand_total'    => $this->investmentRepository->getGrandTotal($user->id),
        ];
    }

    public function find(User $user, int $id): Investment
    {
        $investment = $this->investmentRepository->findForUser($id, $user->id);

        if (!$investment) {
            throw new NotFoundHttpException('Investimento não encontrado.');
        }

        return $investment;
    }

    public function create(User $user, array $data): array
    {
        $investment = $this->investmentRepository->create(array_merge($data, [
            'user_id' => $user->id,
        ]));

        $this->activityLog->transactionCreated($user->id, $investment->id, 'investment');

        return $this->formatInvestment($investment);
    }

    public function update(User $user, int $id, array $data): array
    {
        $investment = $this->find($user, $id);
        $updated    = $this->investmentRepository->update($investment, $data);

        return $this->formatInvestment($updated);
    }

    public function delete(User $user, int $id): void
    {
        $investment = $this->find($user, $id);
        $this->investmentRepository->delete($investment);
    }

    // -------------------------------------------------------------------------
    // Investment transactions
    // -------------------------------------------------------------------------

    public function listTransactions(User $user, int $investmentId): Collection
    {
        $this->find($user, $investmentId); // authorization check
        return $this->transactionRepository->listForInvestment($investmentId);
    }

    public function createTransaction(User $user, int $investmentId, array $data): InvestmentTransaction
    {
        $this->find($user, $investmentId); // authorization check

        return $this->transactionRepository->create(array_merge($data, [
            'investment_id' => $investmentId,
        ]));
    }

    public function findTransaction(User $user, int $transactionId): InvestmentTransaction
    {
        $transaction = $this->transactionRepository->findById($transactionId);

        if (!$transaction) {
            throw new NotFoundHttpException('Movimentação não encontrada.');
        }

        // Authorization: verify through the parent investment
        $this->find($user, $transaction->investment_id);

        return $transaction;
    }

    public function updateTransaction(User $user, int $transactionId, array $data): InvestmentTransaction
    {
        $transaction = $this->findTransaction($user, $transactionId);
        return $this->transactionRepository->update($transaction, $data);
    }

    public function deleteTransaction(User $user, int $transactionId): void
    {
        $transaction = $this->findTransaction($user, $transactionId);
        $this->transactionRepository->delete($transaction);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function formatInvestment(Investment $investment): array
    {
        return array_merge($investment->toArray(), [
            'total_invested' => $investment->totalInvested(),
        ]);
    }
}
