<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha12345')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'senha12345',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token'])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correta')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'errada',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'naoexiste@example.com',
            'password' => 'qualquercoisa',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_response_does_not_expose_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha12345')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'senha12345',
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logout realizado com sucesso.');

        // Token must be removed from the database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
