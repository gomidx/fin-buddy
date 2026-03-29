<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function store(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated('email'),
                $request->validated('password'),
            );

            return response()->json([
                'message' => 'Login realizado com sucesso.',
                'user'    => $result['user'],
                'token'   => $result['token'],
            ]);
        } catch (AuthenticationException) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        } catch (\Throwable $e) {
            Log::error('Error on login', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, ocorreu um erro ao realizar o login.'], 500);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return response()->json(['message' => 'Logout realizado com sucesso.']);
        } catch (\Throwable $e) {
            Log::error('Error on logout', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, ocorreu um erro ao realizar o logout.'], 500);
        }
    }
}
