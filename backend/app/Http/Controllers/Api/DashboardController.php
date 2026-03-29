<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json($this->dashboardService->getSummary($request->user()));
        } catch (\Throwable $e) {
            Log::error('Error fetching dashboard', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar o dashboard.'], 500);
        }
    }
}
