<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_routes_require_authentication(): void
    {
        $routes = [
            ['GET',  '/api/profile'],
            ['PUT',  '/api/profile'],
            ['GET',  '/api/categories'],
            ['GET',  '/api/transactions'],
            ['GET',  '/api/dashboard'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401, "Route {$method} {$uri} should require authentication.");
        }
    }

    public function test_user_cannot_access_another_users_profile(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $tokenA = $userA->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/profile');

        // Should return userA's data, not userB's
        $response->assertStatus(200)
            ->assertJsonPath('id', $userA->id);

        $this->assertNotEquals($userB->id, $response->json('id'));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer token-invalido')
            ->getJson('/api/profile')
            ->assertStatus(401);
    }
}
