<?php

namespace Tests\Feature\Investment;

use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Investment $investment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user       = User::factory()->create();
        $this->token      = $this->user->createToken('api-token')->plainTextToken;
        $this->investment = Investment::factory()->create(['user_id' => $this->user->id, 'type' => 'stock']);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function test_user_can_list_transactions_for_own_investment(): void
    {
        InvestmentTransaction::factory()->count(3)->create(['investment_id' => $this->investment->id]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/investments/{$this->investment->id}/transactions");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_list_returns_404_for_other_users_investment(): void
    {
        $other = User::factory()->create();
        $inv   = Investment::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/investments/{$inv->id}/transactions")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_user_can_create_buy_transaction(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/investments/{$this->investment->id}/transactions", [
                'type'        => 'buy',
                'amount'      => 1500,
                'date'        => '2026-03-10',
                'description' => 'Compra de PETR4',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('type', 'buy')
            ->assertJsonPath('investment_id', $this->investment->id);

        $this->assertDatabaseHas('investment_transactions', [
            'investment_id' => $this->investment->id,
            'type'          => 'buy',
            'amount'        => 1500,
        ]);
    }

    public function test_user_can_create_sell_transaction(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/investments/{$this->investment->id}/transactions", [
                'type'   => 'sell',
                'amount' => 500,
                'date'   => '2026-03-15',
            ])
            ->assertStatus(201)
            ->assertJsonPath('type', 'sell');
    }

    public function test_user_can_create_dividend_transaction(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/investments/{$this->investment->id}/transactions", [
                'type'   => 'dividend',
                'amount' => 120,
                'date'   => '2026-03-15',
            ])
            ->assertStatus(201)
            ->assertJsonPath('type', 'dividend');
    }

    public function test_create_fails_without_required_fields(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/investments/{$this->investment->id}/transactions", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'amount', 'date']);
    }

    public function test_create_fails_with_invalid_type(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/investments/{$this->investment->id}/transactions", [
                'type' => 'transfer', 'amount' => 100, 'date' => '2026-03-10',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_fails_for_other_users_investment(): void
    {
        $other = User::factory()->create();
        $inv   = Investment::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->postJson("/api/investments/{$inv->id}/transactions", [
                'type' => 'buy', 'amount' => 100, 'date' => '2026-03-10',
            ])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_user_can_view_own_transaction(): void
    {
        $tx = InvestmentTransaction::factory()->create(['investment_id' => $this->investment->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/investment-transactions/{$tx->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $tx->id);
    }

    public function test_user_cannot_view_other_users_transaction(): void
    {
        $other = User::factory()->create();
        $inv   = Investment::factory()->create(['user_id' => $other->id]);
        $tx    = InvestmentTransaction::factory()->create(['investment_id' => $inv->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/investment-transactions/{$tx->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_user_can_update_transaction(): void
    {
        $tx = InvestmentTransaction::factory()->create([
            'investment_id' => $this->investment->id,
            'amount'        => 1000,
        ]);

        $this->withHeaders($this->auth())
            ->putJson("/api/investment-transactions/{$tx->id}", ['amount' => 1500])
            ->assertStatus(200)
            ->assertJsonPath('amount', '1500.00');
    }

    public function test_update_fails_for_other_users_transaction(): void
    {
        $other = User::factory()->create();
        $inv   = Investment::factory()->create(['user_id' => $other->id]);
        $tx    = InvestmentTransaction::factory()->create(['investment_id' => $inv->id]);

        $this->withHeaders($this->auth())
            ->putJson("/api/investment-transactions/{$tx->id}", ['amount' => 9999])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_user_can_delete_transaction(): void
    {
        $tx = InvestmentTransaction::factory()->create(['investment_id' => $this->investment->id]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/investment-transactions/{$tx->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Movimentação excluída com sucesso.');

        $this->assertDatabaseMissing('investment_transactions', ['id' => $tx->id]);
    }

    public function test_delete_fails_for_other_users_transaction(): void
    {
        $other = User::factory()->create();
        $inv   = Investment::factory()->create(['user_id' => $other->id]);
        $tx    = InvestmentTransaction::factory()->create(['investment_id' => $inv->id]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/investment-transactions/{$tx->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Total invested calculation
    // -------------------------------------------------------------------------

    public function test_total_invested_buy_minus_sell(): void
    {
        InvestmentTransaction::factory()->create(['investment_id' => $this->investment->id, 'type' => 'buy',  'amount' => 5000]);
        InvestmentTransaction::factory()->create(['investment_id' => $this->investment->id, 'type' => 'sell', 'amount' => 1500]);

        $response = $this->withHeaders($this->auth())->getJson("/api/investments/{$this->investment->id}");

        $this->assertEquals(3500, $response->json('total_invested'));
    }

    public function test_dividend_does_not_affect_total_invested(): void
    {
        InvestmentTransaction::factory()->create(['investment_id' => $this->investment->id, 'type' => 'buy',      'amount' => 5000]);
        InvestmentTransaction::factory()->create(['investment_id' => $this->investment->id, 'type' => 'dividend', 'amount' => 200]);

        $response = $this->withHeaders($this->auth())->getJson("/api/investments/{$this->investment->id}");

        $this->assertEquals(5000, $response->json('total_invested'));
    }

    public function test_total_invested_is_zero_with_no_transactions(): void
    {
        $response = $this->withHeaders($this->auth())->getJson("/api/investments/{$this->investment->id}");
        $this->assertEquals(0, $response->json('total_invested'));
    }

    public function test_total_invested_can_be_negative(): void
    {
        InvestmentTransaction::factory()->create(['investment_id' => $this->investment->id, 'type' => 'buy',  'amount' => 1000]);
        InvestmentTransaction::factory()->create(['investment_id' => $this->investment->id, 'type' => 'sell', 'amount' => 1500]);

        $response = $this->withHeaders($this->auth())->getJson("/api/investments/{$this->investment->id}");

        $this->assertEquals(-500, $response->json('total_invested'));
    }
}
