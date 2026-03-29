<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InsightController extends Controller
{
    public function __construct(private readonly InsightService $insightService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json($this->insightService->getInsights($request->user()));
        } catch (\Throwable $e) {
            Log::error('Error fetching insights', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar os insights.'], 500);
        }
    }
}
