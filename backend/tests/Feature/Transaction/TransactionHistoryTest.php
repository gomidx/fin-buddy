<?php

namespace Tests\Feature\Transaction;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes específicos do Histórico Financeiro (Etapa 11).
 * Cobre cenários de filtros avançados combinados e paginação.
 */
class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $expenseCat;
    private Category $incomeCat;
    private Category $anotherCat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user       = User::factory()->create();
        $this->expenseCat = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
        $this->incomeCat  = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'income']);
        $this->anotherCat = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
    }

    private function create(string $type, float $amount, string $date, ?Category $cat = null): Transaction
    {
        $cat ??= $type === 'income' ? $this->incomeCat : $this->expenseCat;

        return Transaction::create([
            'user_id'     => $this->user->id,
            'category_id' => $cat->id,
            'type'        => $type,
            'amount'      => $amount,
            'description' => 'Test',
            'date'        => $date,
        ]);
    }

    // ──────────────────────────────────────────────
    // Filtros combinados avançados
    // ──────────────────────────────────────────────

    public function test_combined_date_range_type_and_category(): void
    {
        // Dentro do intervalo + tipo + categoria corretos → deve aparecer
        $this->create('expense', 100, '2026-02-10');
        $this->create('expense', 200, '2026-02-20');

        // Tipo errado
        $this->create('income', 500, '2026-02-15');

        // Categoria errada
        $this->create('expense', 150, '2026-02-18', $this->anotherCat);

        // Fora do intervalo de datas
        $this->create('expense', 100, '2026-03-05');

        $response = $this->actingAs($this->user)->getJson(
            "/api/transactions?date_from=2026-02-01&date_to=2026-02-28&type=expense&category_id={$this->expenseCat->id}"
        );

        $response->assertOk()->assertJsonPath('total', 2);

        foreach ($response->json('data') as $t) {
            $this->assertEquals('expense', $t['type']);
            $this->assertEquals($this->expenseCat->id, $t['category_id']);
        }
    }

    public function test_combined_month_year_type_and_category(): void
    {
        // Corretos
        $this->create('expense', 100, '2026-01-10');
        $this->create('expense', 200, '2026-01-25');

        // Mês errado
        $this->create('expense', 100, '2026-02-10');

        // Tipo errado
        $this->create('income', 500, '2026-01-15');

        // Categoria errada
        $this->create('expense', 150, '2026-01-18', $this->anotherCat);

        $response = $this->actingAs($this->user)->getJson(
            "/api/transactions?month=1&year=2026&type=expense&category_id={$this->expenseCat->id}"
        );

        $response->assertOk()->assertJsonPath('total', 2);
    }

    // ──────────────────────────────────────────────
    // Paginação com filtros ativos
    // ──────────────────────────────────────────────

    public function test_pagination_with_active_filter(): void
    {
        // 20 despesas em março de 2026
        for ($i = 1; $i <= 20; $i++) {
            $this->create('expense', 50, '2026-03-' . str_pad($i, 2, '0', STR_PAD_LEFT));
        }
        // 5 receitas — não devem aparecer com filtro type=expense
        for ($i = 1; $i <= 5; $i++) {
            $this->create('income', 500, '2026-03-01');
        }

        // Página 1: 15 itens
        $page1 = $this->actingAs($this->user)->getJson('/api/transactions?type=expense&month=3&year=2026&page=1');
        $page1->assertOk()
            ->assertJsonPath('total', 20)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 15);
        $this->assertCount(15, $page1->json('data'));

        // Página 2: 5 itens
        $page2 = $this->actingAs($this->user)->getJson('/api/transactions?type=expense&month=3&year=2026&page=2');
        $page2->assertOk()
            ->assertJsonPath('total', 20)
            ->assertJsonPath('current_page', 2);
        $this->assertCount(5, $page2->json('data'));
    }

    public function test_pagination_last_page_has_correct_data(): void
    {
        for ($i = 0; $i < 18; $i++) {
            $this->create('expense', 10, '2026-03-01');
        }

        $page2 = $this->actingAs($this->user)->getJson('/api/transactions?page=2');
        $page2->assertOk()
            ->assertJsonPath('total', 18)
            ->assertJsonPath('current_page', 2);
        $this->assertCount(3, $page2->json('data'));
    }

    // ──────────────────────────────────────────────
    // Ordenação
    // ──────────────────────────────────────────────

    public function test_transactions_ordered_by_date_descending(): void
    {
        $this->create('expense', 10, '2026-02-01');
        $this->create('expense', 20, '2026-03-15');
        $this->create('expense', 30, '2026-01-20');

        $data = $this->actingAs($this->user)->getJson('/api/transactions')->assertOk()->json('data');

        $dates = array_map(fn($d) => substr($d, 0, 10), array_column($data, 'date'));
        $this->assertEquals(['2026-03-15', '2026-02-01', '2026-01-20'], $dates);
    }

    // ──────────────────────────────────────────────
    // Isolamento
    // ──────────────────────────────────────────────

    public function test_history_returns_only_authenticated_user_data(): void
    {
        $other = User::factory()->create();
        $otherCat = Category::factory()->create(['user_id' => $other->id, 'type' => 'expense']);

        // Transações do outro usuário — não devem aparecer
        Transaction::create([
            'user_id' => $other->id, 'category_id' => $otherCat->id,
            'type' => 'expense', 'amount' => 999, 'description' => 'Other', 'date' => '2026-03-01',
        ]);

        $this->create('expense', 50, '2026-03-01');

        $data = $this->actingAs($this->user)->getJson('/api/transactions')->assertOk()->json();

        $this->assertEquals(1, $data['total']);
        $this->assertEquals($this->user->id, $data['data'][0]['user_id']);
    }

    // ──────────────────────────────────────────────
    // Estrutura de dados (grouping helper)
    // ──────────────────────────────────────────────

    public function test_each_transaction_has_category_data(): void
    {
        $this->create('expense', 100, '2026-03-01');

        $item = $this->actingAs($this->user)->getJson('/api/transactions')->assertOk()->json('data.0');

        $this->assertArrayHasKey('category', $item);
        $this->assertArrayHasKey('name', $item['category']);
        $this->assertArrayHasKey('type', $item['category']);
    }
}
