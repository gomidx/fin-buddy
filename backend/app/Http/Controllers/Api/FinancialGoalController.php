<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialGoal\StoreFinancialGoalRequest;
use App\Http\Requests\FinancialGoal\UpdateFinancialGoalRequest;
use App\Services\FinancialGoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FinancialGoalController extends Controller
{
    public function __construct(private readonly FinancialGoalService $goalService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $goals = $this->goalService->list($request->user());

            $formatted = $goals->map(fn ($goal) => array_merge($goal->toArray(), [
                'progress_percentage' => $goal->progressPercentage(),
            ]));

            return response()->json($formatted);
        } catch (\Throwable $e) {
            Log::error('Error listing financial goals', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível listar as metas.'], 500);
        }
    }

    public function store(StoreFinancialGoalRequest $request): JsonResponse
    {
        try {
            $goal = $this->goalService->create($request->user(), $request->validated());

            return response()->json($goal, 201);
        } catch (\Throwable $e) {
            Log::error('Error creating financial goal', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível criar a meta.'], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $goal = $this->goalService->find($request->user(), $id);

            return response()->json($goal);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error fetching financial goal', ['goal_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar a meta.'], 500);
        }
    }

    public function update(UpdateFinancialGoalRequest $request, int $id): JsonResponse
    {
        try {
            $goal = $this->goalService->update($request->user(), $id, $request->validated());

            return response()->json($goal);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error updating financial goal', ['goal_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível atualizar a meta.'], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->goalService->delete($request->user(), $id);

            return response()->json(['message' => 'Meta excluída com sucesso.']);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error deleting financial goal', ['goal_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível excluir a meta.'], 500);
        }
    }
}
