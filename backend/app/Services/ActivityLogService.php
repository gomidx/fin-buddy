<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ActivityLogService
{
    public function log(string $event, int $userId, array $context = []): void
    {
        Log::channel('activity')->info($event, [
            'user_id' => $userId,
            ...$context,
        ]);
    }

    public function login(int $userId): void
    {
        $this->log('user.login', $userId);
    }

    public function logout(int $userId): void
    {
        $this->log('user.logout', $userId);
    }

    public function transactionCreated(int $userId, int $transactionId, string $type): void
    {
        $this->log('transaction.created', $userId, [
            'transaction_id' => $transactionId,
            'type'           => $type,
        ]);
    }

    public function transactionUpdated(int $userId, int $transactionId): void
    {
        $this->log('transaction.updated', $userId, ['transaction_id' => $transactionId]);
    }

    public function transactionDeleted(int $userId, int $transactionId): void
    {
        $this->log('transaction.deleted', $userId, ['transaction_id' => $transactionId]);
    }

    public function goalUpdated(int $userId, int $goalId): void
    {
        $this->log('goal.updated', $userId, ['goal_id' => $goalId]);
    }

    public function profileUpdated(int $userId): void
    {
        $this->log('profile.updated', $userId);
    }
}
