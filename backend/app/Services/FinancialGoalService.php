<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\FinancialGoalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FinancialGoalService
{
    public function __construct(
        private readonly FinancialGoalRepositoryInterface $goalRepository
    ) {}

    public function list(User $user): Collection
    {
        return $this->goalRepository->listForUser($user->id);
    }

    public function create(User $user, array $data): array
    {
        $data['user_id']        = $user->id;
        $data['current_amount'] = $data['current_amount'] ?? 0;

        $goal = $this->goalRepository->create($data);

        return $this->format($goal);
    }

    public function find(User $user, int $id): array
    {
        $goal = $this->goalRepository->findForUser($id, $user->id);

        if (! $goal) {
            throw new NotFoundHttpException('Meta financeira não encontrada.');
        }

        return $this->format($goal);
    }

    public function update(User $user, int $id, array $data): array
    {
        $goal = $this->goalRepository->findForUser($id, $user->id);

        if (! $goal) {
            throw new NotFoundHttpException('Meta financeira não encontrada.');
        }

        $goal = $this->goalRepository->update($goal, $data);

        return $this->format($goal);
    }

    public function delete(User $user, int $id): void
    {
        $goal = $this->goalRepository->findForUser($id, $user->id);

        if (! $goal) {
            throw new NotFoundHttpException('Meta financeira não encontrada.');
        }

        $this->goalRepository->delete($goal);
    }

    private function format(\App\Models\FinancialGoal $goal): array
    {
        return array_merge($goal->toArray(), [
            'progress_percentage' => $goal->progressPercentage(),
        ]);
    }
}
