<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $categories = $this->categoryService->listForUser($request->user());

            return response()->json($categories);
        } catch (\Throwable $e) {
            Log::error('Error listing categories', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível listar as categorias.'], 500);
        }
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->create($request->user(), $request->validated());

            return response()->json($category, 201);
        } catch (\Throwable $e) {
            Log::error('Error creating category', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível criar a categoria.'], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->categoryService->delete($request->user(), $id);

            return response()->json(['message' => 'Categoria excluída com sucesso.']);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Log::error('Error deleting category', ['category_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível excluir a categoria.'], 500);
        }
    }
}
