<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\StoreInvestmentTransactionRequest;
use App\Http\Requests\Investment\UpdateInvestmentTransactionRequest;
use App\Services\InvestmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvestmentTransactionController extends Controller
{
    public function __construct(private readonly InvestmentService $investmentService) {}

    public function index(Request $request, int $investment): JsonResponse
    {
        try {
            $transactions = $this->investmentService->listTransactions($request->user(), $investment);

            return response()->json($transactions);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error listing investment transactions', ['investment_id' => $investment, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível listar as movimentações.'], 500);
        }
    }

    public function store(StoreInvestmentTransactionRequest $request, int $investment): JsonResponse
    {
        try {
            $transaction = $this->investmentService->createTransaction($request->user(), $investment, $request->validated());

            return response()->json($transaction, 201);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error creating investment transaction', ['investment_id' => $investment, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível registrar a movimentação.'], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $transaction = $this->investmentService->findTransaction($request->user(), $id);

            return response()->json($transaction);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error fetching investment transaction', ['transaction_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar a movimentação.'], 500);
        }
    }

    public function update(UpdateInvestmentTransactionRequest $request, int $id): JsonResponse
    {
        try {
            $transaction = $this->investmentService->updateTransaction($request->user(), $id, $request->validated());

            return response()->json($transaction);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error updating investment transaction', ['transaction_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível atualizar a movimentação.'], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->investmentService->deleteTransaction($request->user(), $id);

            return response()->json(['message' => 'Movimentação excluída com sucesso.']);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error deleting investment transaction', ['transaction_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível excluir a movimentação.'], 500);
        }
    }
}
