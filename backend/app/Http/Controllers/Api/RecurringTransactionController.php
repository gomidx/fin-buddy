<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecurringTransaction\StoreRecurringTransactionRequest;
use App\Http\Requests\RecurringTransaction\UpdateRecurringTransactionRequest;
use App\Services\RecurringTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RecurringTransactionController extends Controller
{
    public function __construct(private readonly RecurringTransactionService $recurringService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json($this->recurringService->list($request->user()));
        } catch (\Throwable $e) {
            Log::error('Error listing recurring transactions', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível listar as recorrências.'], 500);
        }
    }

    public function store(StoreRecurringTransactionRequest $request): JsonResponse
    {
        try {
            $rt = $this->recurringService->create($request->user(), $request->validated());

            return response()->json($rt, 201);
        } catch (\Throwable $e) {
            Log::error('Error creating recurring transaction', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível criar a recorrência.'], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $rt = $this->recurringService->find($request->user(), $id);

            return response()->json($rt);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error fetching recurring transaction', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar a recorrência.'], 500);
        }
    }

    public function update(UpdateRecurringTransactionRequest $request, int $id): JsonResponse
    {
        try {
            $rt = $this->recurringService->update($request->user(), $id, $request->validated());

            return response()->json($rt);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error updating recurring transaction', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível atualizar a recorrência.'], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->recurringService->delete($request->user(), $id);

            return response()->json(['message' => 'Recorrência excluída com sucesso.']);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error deleting recurring transaction', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível excluir a recorrência.'], 500);
        }
    }
}
