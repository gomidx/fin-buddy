<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): array
    {
        $user  = User::factory()->create(['currency' => 'BRL']);
        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        ['user' => $user, 'token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
    }

    public function test_user_can_update_name(): void
    {
        ['user' => $user, 'token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/profile', ['name' => 'Novo Nome']);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Novo Nome');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Novo Nome']);
    }

    public function test_user_can_update_email(): void
    {
        ['user' => $user, 'token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/profile', ['email' => 'novo@example.com']);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'novo@example.com');
    }

    public function test_user_can_update_currency(): void
    {
        ['user' => $user, 'token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/profile', ['currency' => 'USD']);

        $response->assertStatus(200)
            ->assertJsonPath('user.currency', 'USD');
    }

    public function test_user_can_update_password(): void
    {
        ['user' => $user, 'token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/profile', [
                'password'              => 'novasenha123',
                'password_confirmation' => 'novasenha123',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('novasenha123', $user->password));
    }

    public function test_update_fails_with_invalid_email(): void
    {
        ['token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/profile', ['email' => 'nao-e-email']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'outro@example.com']);
        ['token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/profile', ['email' => 'outro@example.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_fails_with_invalid_currency(): void
    {
        ['token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/profile', ['currency' => 'INVALIDO']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_profile_response_does_not_expose_password(): void
    {
        ['token' => $token] = $this->actingAsUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json());
    }
}
