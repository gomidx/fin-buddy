<?php

namespace App\Repositories\Contracts;

use App\Models\EmergencyFund;

interface EmergencyFundRepositoryInterface
{
    public function findByUser(int $userId): ?EmergencyFund;
    public function createOrUpdate(int $userId, array $data): EmergencyFund;
    public function getCurrentAmount(int $userId): float;
    public function getAverageMonthlyExpenses(int $userId, int $months = 3): float;
}
