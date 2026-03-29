<?php

namespace Tests\Feature\Transaction;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Category $expenseCategory;
    private Category $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user            = User::factory()->create();
        $this->token           = $this->user->createToken('api-token')->plainTextToken;
        $this->expenseCategory = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
        $this->incomeCategory  = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'income']);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_list_returns_paginated_transactions(): void
    {
        Transaction::factory()->count(20)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total'])
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 20);

        $this->assertCount(15, $response->json('data'));
    }

    public function test_filter_by_type_expense(): void
    {
        Transaction::factory()->count(3)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
        ]);
        Transaction::factory()->count(2)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->incomeCategory->id,
            'type'        => 'income',
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/transactions?type=expense');

        $response->assertStatus(200)->assertJsonPath('total', 3);
        foreach ($response->json('data') as $t) {
            $this->assertEquals('expense', $t['type']);
        }
    }

    public function test_filter_by_type_income(): void
    {
        Transaction::factory()->count(4)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->incomeCategory->id,
            'type'        => 'income',
        ]);
        Transaction::factory()->count(2)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/transactions?type=income');

        $response->assertStatus(200)->assertJsonPath('total', 4);
    }

    public function test_filter_by_category(): void
    {
        Transaction::factory()->count(3)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
        ]);
        Transaction::factory()->count(2)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->incomeCategory->id,
            'type'        => 'income',
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/transactions?category_id={$this->expenseCategory->id}");

        $response->assertStatus(200)->assertJsonPath('total', 3);
    }

    public function test_filter_by_month_and_year(): void
    {
        Transaction::factory()->count(3)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'date'        => '2026-03-10',
        ]);
        Transaction::factory()->count(2)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'date'        => '2026-02-15',
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/transactions?month=3&year=2026');

        $response->assertStatus(200)->assertJsonPath('total', 3);
    }

    public function test_filter_by_date_range(): void
    {
        Transaction::factory()->count(2)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'date'        => '2026-03-05',
        ]);
        Transaction::factory()->count(3)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'date'        => '2026-03-20',
        ]);
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'date'        => '2026-04-01',
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/transactions?date_from=2026-03-01&date_to=2026-03-31');

        $response->assertStatus(200)->assertJsonPath('total', 5);
    }

    public function test_combined_filters(): void
    {
        Transaction::factory()->count(2)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'date'        => '2026-03-10',
        ]);
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->incomeCategory->id,
            'type'        => 'income',
            'date'        => '2026-03-10',
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/transactions?type=expense&category_id={$this->expenseCategory->id}&month=3&year=2026");

        $response->assertStatus(200)->assertJsonPath('total', 2);
    }

    public function test_list_does_not_return_other_users_transactions(): void
    {
        $other = User::factory()->create();
        Transaction::factory()->count(5)->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->auth())->getJson('/api/transactions');

        $response->assertStatus(200)->assertJsonPath('total', 0);
    }

    public function test_list_includes_category_data(): void
    {
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/transactions');

        $response->assertStatus(200);
        $this->assertArrayHasKey('category', $response->json('data.0'));
    }

    public function test_suggest_category_returns_most_used(): void
    {
        // 3 transactions with "Uber" → expenseCategory
        Transaction::factory()->count(3)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'description' => 'Uber para o trabalho',
        ]);
        // 1 transaction with "Uber" → incomeCategory
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->incomeCategory->id,
            'type'        => 'income',
            'description' => 'Uber Driver',
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/transactions/suggest-category?description=Uber');

        $response->assertStatus(200)
            ->assertJsonPath('category_id', $this->expenseCategory->id);
    }

    public function test_suggest_category_returns_null_when_no_history(): void
    {
        $response = $this->withHeaders($this->auth())
            ->getJson('/api/transactions/suggest-category?description=Supermercado');

        $response->assertStatus(200)->assertJsonPath('category_id', null);
    }

    public function test_suggest_category_returns_null_for_short_description(): void
    {
        $response = $this->withHeaders($this->auth())
            ->getJson('/api/transactions/suggest-category?description=a');

        $response->assertStatus(200)->assertJsonPath('category_id', null);
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/transactions')->assertStatus(401);
    }
}
