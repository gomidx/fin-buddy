<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['type', 'category_id', 'month', 'year', 'date_from', 'date_to']);
            $transactions = $this->transactionService->list($request->user(), $filters);

            return response()->json($transactions);
        } catch (\Throwable $e) {
            Log::error('Error listing transactions', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível listar as transações.'], 500);
        }
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->create($request->user(), $request->validated());

            return response()->json($transaction, 201);
        } catch (\Throwable $e) {
            Log::error('Error creating transaction', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível registrar a transação.'], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $transaction = $this->transactionService->find($request->user(), $id);

            return response()->json($transaction);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error fetching transaction', ['transaction_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar a transação.'], 500);
        }
    }

    public function update(UpdateTransactionRequest $request, int $id): JsonResponse
    {
        try {
            $transaction = $this->transactionService->update($request->user(), $id, $request->validated());

            return response()->json($transaction);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error updating transaction', ['transaction_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível atualizar a transação.'], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->transactionService->delete($request->user(), $id);

            return response()->json(['message' => 'Transação excluída com sucesso.']);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error deleting transaction', ['transaction_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível excluir a transação.'], 500);
        }
    }

    public function suggestCategory(Request $request): JsonResponse
    {
        try {
            $description = trim($request->query('description', ''));

            if (strlen($description) < 2) {
                return response()->json(['category_id' => null]);
            }

            $categoryId = $this->transactionService->suggestCategory($request->user(), $description);

            return response()->json(['category_id' => $categoryId]);
        } catch (\Throwable $e) {
            Log::error('Error suggesting category', ['error' => $e->getMessage()]);

            return response()->json(['category_id' => null]);
        }
    }
}
