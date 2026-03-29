<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface
{
    public function allForUser(int $userId): Collection;
    public function findUserCategory(int $id, int $userId): ?Category;
    public function create(int $userId, array $data): Category;
    public function delete(Category $category): bool;
    public function hasTransactions(Category $category): bool;
}
