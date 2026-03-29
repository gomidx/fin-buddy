<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function updateProfile(User $user, array $data): User
    {
        $payload = array_filter([
            'name'     => $data['name'] ?? null,
            'email'    => $data['email'] ?? null,
            'currency' => $data['currency'] ?? null,
        ], fn($value) => !is_null($value));

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        if (!empty($payload)) {
            $this->userRepository->update($user->id, $payload);
        }

        $this->activityLog->profileUpdated($user->id);

        return $user->fresh();
    }
}
