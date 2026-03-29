<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyFund\DepositEmergencyFundRequest;
use App\Http\Requests\EmergencyFund\UpdateEmergencyFundRequest;
use App\Services\EmergencyFundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmergencyFundController extends Controller
{
    public function __construct(private readonly EmergencyFundService $fundService) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json($this->fundService->getStatus($request->user()));
        } catch (\Throwable $e) {
            Log::error('Error fetching emergency fund status', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar a reserva de emergência.'], 500);
        }
    }

    public function update(UpdateEmergencyFundRequest $request): JsonResponse
    {
        try {
            $status = $this->fundService->updateGoal($request->user(), $request->validated());

            return response()->json($status);
        } catch (\Throwable $e) {
            Log::error('Error updating emergency fund goal', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível atualizar a reserva de emergência.'], 500);
        }
    }

    public function deposit(DepositEmergencyFundRequest $request): JsonResponse
    {
        try {
            $transaction = $this->fundService->deposit($request->user(), $request->validated());

            return response()->json($transaction, 201);
        } catch (\Throwable $e) {
            Log::error('Error depositing to emergency fund', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível registrar o depósito.'], 500);
        }
    }
}
