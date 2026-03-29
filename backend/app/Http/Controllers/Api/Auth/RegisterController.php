<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());
    
            return response()->json([
                'message' => 'Conta criada com sucesso.',
                'user'    => $result['user'],
                'token'   => $result['token'],
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('Error on register',
                [
                    'request' => $request->toArray(),
                    'error' => $exception->getMessage()
                ]
            );

            return response()->json([
                'message' => 'Ops, ocorreu um erro ao criar sua conta.'
            ]);
        }
    }
}
