<?php

namespace Tests\Feature\RecurringTransaction;

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTransactionCrudTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type'        => 'expense',
            'amount'      => 49.90,
            'category_id' => $this->category->id,
            'description' => 'Netflix',
            'frequency'   => 'monthly',
            'start_date'  => '2026-01-01',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_user_can_create_recurring_transaction(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/recurring-transactions', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('type', 'expense')
            ->assertJsonPath('description', 'Netflix')
            ->assertJsonPath('frequency', 'monthly')
            ->assertJsonPath('user_id', $this->user->id);

        $this->assertDatabaseHas('recurring_transactions', [
            'user_id'     => $this->user->id,
            'description' => 'Netflix',
        ]);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/recurring-transactions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'amount', 'category_id', 'description', 'frequency', 'start_date']);
    }

    public function test_create_fails_with_invalid_type(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/recurring-transactions', $this->validPayload(['type' => 'invalid']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_fails_with_invalid_frequency(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/recurring-transactions', $this->validPayload(['frequency' => 'weekly']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['frequency']);
    }

    public function test_create_fails_with_end_date_before_start_date(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/recurring-transactions', $this->validPayload([
                'start_date' => '2026-03-15',
                'end_date'   => '2026-03-10',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_create_fails_with_other_users_category(): void
    {
        $other    = User::factory()->create();
        $otherCat = Category::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->postJson('/api/recurring-transactions', $this->validPayload(['category_id' => $otherCat->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/recurring-transactions', $this->validPayload())->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_user_can_list_own_recurring_transactions(): void
    {
        RecurringTransaction::factory()->count(3)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/recurring-transactions');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_list_does_not_show_other_users_recurring_transactions(): void
    {
        $other = User::factory()->create();
        RecurringTransaction::factory()->count(5)->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->auth())->getJson('/api/recurring-transactions');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    public function test_list_includes_category_data(): void
    {
        RecurringTransaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/recurring-transactions');

        $response->assertStatus(200);
        $this->assertArrayHasKey('category', $response->json()[0]);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_user_can_view_own_recurring_transaction(): void
    {
        $rt = RecurringTransaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->withHeaders($this->auth())
            ->getJson("/api/recurring-transactions/{$rt->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $rt->id);
    }

    public function test_user_cannot_view_other_users_recurring_transaction(): void
    {
        $other = User::factory()->create();
        $rt    = RecurringTransaction::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/recurring-transactions/{$rt->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_user_can_update_recurring_transaction(): void
    {
        $rt = RecurringTransaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->withHeaders($this->auth())
            ->putJson("/api/recurring-transactions/{$rt->id}", ['description' => 'Spotify', 'amount' => 21.90])
            ->assertStatus(200)
            ->assertJsonPath('description', 'Spotify')
            ->assertJsonPath('amount', '21.90');
    }

    public function test_update_fails_for_other_users_recurring_transaction(): void
    {
        $other = User::factory()->create();
        $rt    = RecurringTransaction::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->putJson("/api/recurring-transactions/{$rt->id}", ['amount' => 99])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_user_can_delete_recurring_transaction(): void
    {
        $rt = RecurringTransaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/recurring-transactions/{$rt->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Recorrência excluída com sucesso.');

        $this->assertDatabaseMissing('recurring_transactions', ['id' => $rt->id]);
    }

    public function test_delete_fails_for_other_users_recurring_transaction(): void
    {
        $other = User::factory()->create();
        $rt    = RecurringTransaction::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/recurring-transactions/{$rt->id}")
            ->assertStatus(404);
    }
}
