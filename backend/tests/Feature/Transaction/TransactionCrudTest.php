<?php

namespace Tests\Feature\Transaction;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user     = User::factory()->create();
        $this->token    = $this->user->createToken('api-token')->plainTextToken;
        $this->category = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_user_can_create_expense(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/transactions', [
            'type'        => 'expense',
            'amount'      => 150.50,
            'category_id' => $this->category->id,
            'description' => 'Supermercado',
            'date'        => '2026-03-10',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('type', 'expense')
            ->assertJsonPath('amount', '150.50')
            ->assertJsonPath('user_id', $this->user->id);

        $this->assertDatabaseHas('transactions', [
            'user_id'     => $this->user->id,
            'type'        => 'expense',
            'description' => 'Supermercado',
        ]);
    }

    public function test_user_can_create_income(): void
    {
        $incomeCategory = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'income']);

        $response = $this->withHeaders($this->auth())->postJson('/api/transactions', [
            'type'        => 'income',
            'amount'      => 5000,
            'category_id' => $incomeCategory->id,
            'description' => 'Salário',
            'date'        => '2026-03-05',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('type', 'income');
    }

    public function test_user_can_use_system_category(): void
    {
        $systemCategory = Category::factory()->create(['user_id' => null, 'type' => 'expense']);

        $response = $this->withHeaders($this->auth())->postJson('/api/transactions', [
            'type'        => 'expense',
            'amount'      => 50,
            'category_id' => $systemCategory->id,
            'date'        => '2026-03-10',
        ]);

        $response->assertStatus(201);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/transactions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'amount', 'category_id', 'date']);
    }

    public function test_create_fails_with_invalid_type(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/transactions', [
            'type'        => 'invalid',
            'amount'      => 100,
            'category_id' => $this->category->id,
            'date'        => '2026-03-10',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_create_fails_with_zero_amount(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/transactions', [
            'type'        => 'expense',
            'amount'      => 0,
            'category_id' => $this->category->id,
            'date'        => '2026-03-10',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_create_fails_with_other_users_category(): void
    {
        $other         = User::factory()->create();
        $otherCategory = Category::factory()->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->auth())->postJson('/api/transactions', [
            'type'        => 'expense',
            'amount'      => 100,
            'category_id' => $otherCategory->id,
            'date'        => '2026-03-10',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['category_id']);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/transactions', [])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_user_can_view_own_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->withHeaders($this->auth())->getJson("/api/transactions/{$transaction->id}");

        $response->assertStatus(200)->assertJsonPath('id', $transaction->id);
    }

    public function test_user_cannot_view_other_users_transaction(): void
    {
        $other       = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/transactions/{$transaction->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_user_can_update_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->category->id,
            'amount'      => 100,
        ]);

        $response = $this->withHeaders($this->auth())->putJson("/api/transactions/{$transaction->id}", [
            'amount'      => 250,
            'description' => 'Atualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('amount', '250.00')
            ->assertJsonPath('description', 'Atualizado');
    }

    public function test_update_fails_for_other_users_transaction(): void
    {
        $other       = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->putJson("/api/transactions/{$transaction->id}", ['amount' => 999])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function test_user_can_delete_own_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->withHeaders($this->auth())->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertStatus(200)->assertJsonPath('message', 'Transação excluída com sucesso.');
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_delete_fails_for_other_users_transaction(): void
    {
        $other       = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/transactions/{$transaction->id}")
            ->assertStatus(404);
    }

    public function test_delete_nonexistent_transaction_returns_404(): void
    {
        $this->withHeaders($this->auth())
            ->deleteJson('/api/transactions/99999')
            ->assertStatus(404);
    }
}
