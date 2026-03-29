<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'João Silva',
            'email'                 => 'joao@example.com',
            'password'              => 'senha12345',
            'password_confirmation' => 'senha12345',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'token'])
            ->assertJsonPath('user.email', 'joao@example.com');

        $this->assertDatabaseHas('users', ['email' => 'joao@example.com']);
    }

    public function test_password_is_hashed_in_database(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'João Silva',
            'email'                 => 'joao@example.com',
            'password'              => 'senha12345',
            'password_confirmation' => 'senha12345',
        ]);

        $user = User::where('email', 'joao@example.com')->first();

        $this->assertNotEquals('senha12345', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('senha12345', $user->password));
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'joao@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Outro João',
            'email'                 => 'joao@example.com',
            'password'              => 'senha12345',
            'password_confirmation' => 'senha12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'João',
            'email'                 => 'nao-e-email',
            'password'              => 'senha12345',
            'password_confirmation' => 'senha12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_with_password_too_short(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'João',
            'email'                 => 'joao@example.com',
            'password'              => '1234',
            'password_confirmation' => '1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_fails_when_passwords_do_not_match(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'João',
            'email'                 => 'joao@example.com',
            'password'              => 'senha12345',
            'password_confirmation' => 'outrasenha',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_response_does_not_expose_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'João Silva',
            'email'                 => 'joao@example.com',
            'password'              => 'senha12345',
            'password_confirmation' => 'senha12345',
        ]);

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }
}
