<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $notifications = $this->notificationService->listForUser($request->user()->id);

            return response()->json($notifications);
        } catch (\Throwable $e) {
            Log::error('Error listing notifications', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível carregar as notificações.'], 500);
        }
    }

    public function unreadCount(Request $request): JsonResponse
    {
        try {
            return response()->json(['count' => $this->notificationService->unreadCount($request->user()->id)]);
        } catch (\Throwable $e) {
            Log::error('Error counting notifications', ['error' => $e->getMessage()]);

            return response()->json(['count' => 0]);
        }
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $marked = $this->notificationService->markAsRead($id, $request->user()->id);

            if (!$marked) {
                return response()->json(['message' => 'Notificação não encontrada ou já lida.'], 404);
            }

            return response()->json(['message' => 'Notificação marcada como lida.']);
        } catch (\Throwable $e) {
            Log::error('Error marking notification as read', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível marcar a notificação.'], 500);
        }
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $count = $this->notificationService->markAllAsRead($request->user()->id);

            return response()->json(['message' => "{$count} notificação(ões) marcada(s) como lida(s).", 'count' => $count]);
        } catch (\Throwable $e) {
            Log::error('Error marking all notifications as read', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ops, não foi possível marcar as notificações.'], 500);
        }
    }
}
