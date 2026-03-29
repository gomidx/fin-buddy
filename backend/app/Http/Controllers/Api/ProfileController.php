<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json($request->user());
        } catch (\Throwable $e) {
            Log::error('Error fetching profile', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar o perfil.'], 500);
        }
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->updateProfile($request->user(), $request->validated());

            return response()->json([
                'message' => 'Perfil atualizado com sucesso.',
                'user'    => $user,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating profile', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível atualizar o perfil.'], 500);
        }
    }
}
