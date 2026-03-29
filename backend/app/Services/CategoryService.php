<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {}

    public function listForUser(User $user): Collection
    {
        return $this->categoryRepository->allForUser($user->id);
    }

    public function create(User $user, array $data): Category
    {
        return $this->categoryRepository->create($user->id, $data);
    }

    public function delete(User $user, int $categoryId): void
    {
        $category = $this->categoryRepository->findUserCategory($categoryId, $user->id);

        if (!$category) {
            throw new NotFoundHttpException('Categoria não encontrada.');
        }

        if ($this->categoryRepository->hasTransactions($category)) {
            throw new ConflictHttpException('Não é possível excluir uma categoria que possui transações vinculadas.');
        }

        $this->categoryRepository->delete($category);
    }
}
