<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\StoreInvestmentRequest;
use App\Http\Requests\Investment\UpdateInvestmentRequest;
use App\Services\InvestmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvestmentController extends Controller
{
    public function __construct(private readonly InvestmentService $investmentService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json($this->investmentService->list($request->user()));
        } catch (\Throwable $e) {
            Log::error('Error listing investments', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível listar os investimentos.'], 500);
        }
    }

    public function store(StoreInvestmentRequest $request): JsonResponse
    {
        try {
            $investment = $this->investmentService->create($request->user(), $request->validated());

            return response()->json($investment, 201);
        } catch (\Throwable $e) {
            Log::error('Error creating investment', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível criar o investimento.'], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $investment = $this->investmentService->find($request->user(), $id);

            return response()->json(array_merge($investment->toArray(), [
                'total_invested' => $investment->totalInvested(),
            ]));
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error fetching investment', ['investment_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar o investimento.'], 500);
        }
    }

    public function update(UpdateInvestmentRequest $request, int $id): JsonResponse
    {
        try {
            $investment = $this->investmentService->update($request->user(), $id, $request->validated());

            return response()->json($investment);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error updating investment', ['investment_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível atualizar o investimento.'], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->investmentService->delete($request->user(), $id);

            return response()->json(['message' => 'Investimento excluído com sucesso.']);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Error deleting investment', ['investment_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível excluir o investimento.'], 500);
        }
    }
}
