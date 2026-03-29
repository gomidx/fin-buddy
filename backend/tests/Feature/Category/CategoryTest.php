<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->token = $this->user->createToken('api-token')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function test_user_can_list_system_and_own_categories(): void
    {
        // System categories (user_id = null)
        Category::factory()->count(3)->create(['user_id' => null, 'type' => 'expense']);

        // User's own categories
        Category::factory()->count(2)->create(['user_id' => $this->user->id, 'type' => 'income']);

        // Another user's categories (must not appear)
        $other = User::factory()->create();
        Category::factory()->count(2)->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->auth())->getJson('/api/categories');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json());
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/categories')->assertStatus(401);
    }

    public function test_list_does_not_expose_other_users_categories(): void
    {
        $other = User::factory()->create();
        Category::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->auth())->getJson('/api/categories');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function test_user_can_create_custom_category(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/categories', [
            'name' => 'Academia',
            'type' => 'expense',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Academia')
            ->assertJsonPath('type', 'expense')
            ->assertJsonPath('user_id', $this->user->id);

        $this->assertDatabaseHas('categories', [
            'name'    => 'Academia',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_create_fails_with_invalid_type(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/categories', [
            'name' => 'Teste',
            'type' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_fails_with_duplicate_name_for_same_user(): void
    {
        Category::factory()->create(['user_id' => $this->user->id, 'name' => 'Academia', 'type' => 'expense']);

        $response = $this->withHeaders($this->auth())->postJson('/api/categories', [
            'name' => 'Academia',
            'type' => 'expense',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_two_users_can_have_categories_with_same_name(): void
    {
        $other = User::factory()->create();
        Category::factory()->create(['user_id' => $other->id, 'name' => 'Academia', 'type' => 'expense']);

        $response = $this->withHeaders($this->auth())->postJson('/api/categories', [
            'name' => 'Academia',
            'type' => 'expense',
        ]);

        $response->assertStatus(201);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/categories', ['name' => 'Teste', 'type' => 'expense'])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function test_user_can_delete_own_category_without_transactions(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->auth())->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Categoria excluída com sucesso.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_transactions(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = $this->withHeaders($this->auth())->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_recurring_transactions(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);

        RecurringTransaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = $this->withHeaders($this->auth())->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(409);
    }

    public function test_cannot_delete_system_category(): void
    {
        $system = Category::factory()->create(['user_id' => null]);

        $response = $this->withHeaders($this->auth())->deleteJson("/api/categories/{$system->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('categories', ['id' => $system->id]);
    }

    public function test_cannot_delete_another_users_category(): void
    {
        $other    = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->auth())->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_delete_nonexistent_category_returns_404(): void
    {
        $this->withHeaders($this->auth())
            ->deleteJson('/api/categories/99999')
            ->assertStatus(404);
    }

    public function test_delete_requires_authentication(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $this->deleteJson("/api/categories/{$category->id}")->assertStatus(401);
    }
}
