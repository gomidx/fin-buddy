<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private readonly Category $model) {}

    public function allForUser(int $userId): Collection
    {
        return $this->model->forUser($userId)->orderBy('type')->orderBy('name')->get();
    }

    public function findUserCategory(int $id, int $userId): ?Category
    {
        return $this->model
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(int $userId, array $data): Category
    {
        return $this->model->create([
            'user_id' => $userId,
            'name'    => $data['name'],
            'type'    => $data['type'],
        ]);
    }

    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }

    public function hasTransactions(Category $category): bool
    {
        return $category->transactions()->exists()
            || $category->recurringTransactions()->exists();
    }
}
