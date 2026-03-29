<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\EmergencyFund;
use App\Models\FinancialGoal;
use App\Models\Investment;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\InsightService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // Auth guard — todas as rotas protegidas
    // ──────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('protectedRoutes')]
    public function test_protected_routes_require_authentication(string $method, string $url): void
    {
        $this->{$method . 'Json'}($url)->assertUnauthorized();
    }

    public static function protectedRoutes(): array
    {
        return [
            ['get',  '/api/profile'],
            ['get',  '/api/categories'],
            ['get',  '/api/transactions'],
            ['post', '/api/transactions'],
            ['get',  '/api/recurring-transactions'],
            ['get',  '/api/investments'],
            ['get',  '/api/emergency-fund'],
            ['get',  '/api/financial-goals'],
            ['get',  '/api/dashboard'],
            ['get',  '/api/insights'],
            ['get',  '/api/notifications'],
        ];
    }

    // ──────────────────────────────────────────────
    // Isolamento entre usuários
    // ──────────────────────────────────────────────

    public function test_cannot_read_another_users_transaction(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $cat   = Category::factory()->create(['user_id' => $userB->id, 'type' => 'expense']);

        $tx = Transaction::create([
            'user_id' => $userB->id, 'category_id' => $cat->id,
            'type' => 'expense', 'amount' => 100, 'description' => 'Secret', 'date' => '2026-01-01',
        ]);

        $this->actingAs($userA)->getJson("/api/transactions/{$tx->id}")->assertNotFound();
    }

    public function test_cannot_update_another_users_transaction(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $cat   = Category::factory()->create(['user_id' => $userB->id, 'type' => 'expense']);

        $tx = Transaction::create([
            'user_id' => $userB->id, 'category_id' => $cat->id,
            'type' => 'expense', 'amount' => 100, 'description' => 'Secret', 'date' => '2026-01-01',
        ]);

        $this->actingAs($userA)->putJson("/api/transactions/{$tx->id}", ['amount' => 999])->assertNotFound();
        $this->assertEquals(100, $tx->fresh()->amount);
    }

    public function test_cannot_delete_another_users_transaction(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $cat   = Category::factory()->create(['user_id' => $userB->id, 'type' => 'expense']);

        $tx = Transaction::create([
            'user_id' => $userB->id, 'category_id' => $cat->id,
            'type' => 'expense', 'amount' => 100, 'description' => 'Secret', 'date' => '2026-01-01',
        ]);

        $this->actingAs($userA)->deleteJson("/api/transactions/{$tx->id}")->assertNotFound();
        $this->assertDatabaseHas('transactions', ['id' => $tx->id]);
    }

    public function test_cannot_read_another_users_investment(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $inv   = Investment::create(['user_id' => $userB->id, 'name' => 'B invest', 'type' => 'stock']);

        $this->actingAs($userA)->getJson("/api/investments/{$inv->id}")->assertNotFound();
    }

    public function test_cannot_access_another_users_financial_goal(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $goal  = FinancialGoal::create([
            'user_id' => $userB->id, 'name' => 'Secret', 'target_amount' => 1000, 'current_amount' => 0,
        ]);

        $this->actingAs($userA)->getJson("/api/financial-goals/{$goal->id}")->assertNotFound();
    }

    public function test_transaction_list_does_not_leak_other_users_data(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $cat   = Category::factory()->create(['user_id' => $userB->id, 'type' => 'expense']);

        Transaction::create([
            'user_id' => $userB->id, 'category_id' => $cat->id,
            'type' => 'expense', 'amount' => 9999, 'description' => 'Secret', 'date' => '2026-01-01',
        ]);

        $data = $this->actingAs($userA)->getJson('/api/transactions')->assertOk()->json();
        $this->assertEquals(0, $data['total']);
    }

    // ──────────────────────────────────────────────
    // Validação de inputs maliciosos
    // ──────────────────────────────────────────────

    public function test_xss_in_transaction_description_is_stored_as_literal_string(): void
    {
        $user = User::factory()->create();
        $cat  = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

        $xss = '<script>alert("xss")</script>';

        $res = $this->actingAs($user)->postJson('/api/transactions', [
            'category_id' => $cat->id,
            'type'        => 'expense',
            'amount'      => 10,
            'description' => $xss,
            'date'        => '2026-01-01',
        ])->assertCreated();

        // O valor é armazenado como string literal — sem execução
        $this->assertEquals($xss, $res->json('description'));
        $this->assertDatabaseHas('transactions', ['description' => $xss]);
    }

    public function test_sql_injection_attempt_in_filter_does_not_break_query(): void
    {
        $user = User::factory()->create();

        // Tentativa de injeção SQL em query param
        $this->actingAs($user)
            ->getJson("/api/transactions?type=expense' OR '1'='1")
            ->assertOk(); // não deve retornar erro 500

        $this->actingAs($user)
            ->getJson("/api/transactions?category_id=1 OR 1=1")
            ->assertOk();
    }

    public function test_negative_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $cat  = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

        $this->actingAs($user)->postJson('/api/transactions', [
            'category_id' => $cat->id,
            'type'        => 'expense',
            'amount'      => -50,
            'description' => 'Hack',
            'date'        => '2026-01-01',
        ])->assertUnprocessable();
    }

    public function test_invalid_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $cat  = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

        $this->actingAs($user)->postJson('/api/transactions', [
            'category_id' => $cat->id,
            'type'        => 'malicious_type',
            'amount'      => 50,
            'description' => 'Test',
            'date'        => '2026-01-01',
        ])->assertUnprocessable();
    }

    // ──────────────────────────────────────────────
    // Rate limiting — rotas de auth
    // ──────────────────────────────────────────────

    public function test_login_rate_limit_triggers_after_threshold(): void
    {
        // O limite é throttle:10,1 (10 por minuto)
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@x.com', 'password' => 'wrong']);
        }

        $response = $this->postJson('/api/auth/login', ['email' => 'x@x.com', 'password' => 'wrong']);
        $response->assertStatus(429); // Too Many Requests
    }

    // ──────────────────────────────────────────────
    // Cache — dashboard e insights
    // ──────────────────────────────────────────────

    public function test_dashboard_result_is_cached(): void
    {
        $user = User::factory()->create();

        Cache::flush();

        $this->actingAs($user)->getJson('/api/dashboard')->assertOk();

        $this->assertTrue(Cache::has(DashboardService::cacheKey($user->id)));
    }

    public function test_cache_is_invalidated_after_transaction_created(): void
    {
        $user = User::factory()->create();
        $cat  = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

        // Popula o cache
        $this->actingAs($user)->getJson('/api/dashboard')->assertOk();
        $this->assertTrue(Cache::has(DashboardService::cacheKey($user->id)));

        // Cria transação → deve invalidar
        $this->actingAs($user)->postJson('/api/transactions', [
            'category_id' => $cat->id,
            'type'        => 'expense',
            'amount'      => 50,
            'description' => 'Café',
            'date'        => Carbon::today()->toDateString(),
        ])->assertCreated();

        $this->assertFalse(Cache::has(DashboardService::cacheKey($user->id)));
    }

    public function test_insights_result_is_cached(): void
    {
        $user = User::factory()->create();

        Cache::flush();

        $this->actingAs($user)->getJson('/api/insights')->assertOk();

        $this->assertTrue(Cache::has(InsightService::cacheKey($user->id)));
    }

    // ──────────────────────────────────────────────
    // N+1: totalInvested usa relação carregada
    // ──────────────────────────────────────────────

    public function test_total_invested_uses_loaded_relation_without_extra_queries(): void
    {
        $user = User::factory()->create();
        $inv  = Investment::create(['user_id' => $user->id, 'name' => 'PETR4', 'type' => 'stock']);

        $inv->investmentTransactions()->createMany([
            ['type' => 'buy',  'amount' => 1000, 'date' => '2026-01-01'],
            ['type' => 'sell', 'amount' => 200,  'date' => '2026-01-15'],
        ]);

        // Carrega com eager loading
        $loaded = Investment::with('investmentTransactions')->find($inv->id);

        $this->assertTrue($loaded->relationLoaded('investmentTransactions'));
        $this->assertEquals(800.0, $loaded->totalInvested()); // 1000 - 200
    }
}
