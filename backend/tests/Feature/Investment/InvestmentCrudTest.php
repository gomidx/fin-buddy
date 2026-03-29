<?php

namespace Tests\Feature\Investment;

use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentCrudTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Tesouro IPCA+',
            'type'        => 'fixed_income',
            'institution' => 'Banco do Brasil',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_user_can_list_investments_with_totals(): void
    {
        $inv = Investment::factory()->create(['user_id' => $this->user->id, 'type' => 'stock']);
        InvestmentTransaction::factory()->create(['investment_id' => $inv->id, 'type' => 'buy', 'amount' => 1000]);

        $response = $this->withHeaders($this->auth())->getJson('/api/investments');

        $response->assertStatus(200)
            ->assertJsonStructure(['investments', 'totals_by_type', 'grand_total'])
            ->assertJsonCount(1, 'investments');

        $this->assertEquals(1000, $response->json('grand_total'));
        $this->assertEquals(1000, $response->json('totals_by_type.stock'));
    }

    public function test_list_does_not_show_other_users_investments(): void
    {
        $other = User::factory()->create();
        Investment::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->auth())->getJson('/api/investments');

        $response->assertStatus(200)->assertJsonCount(0, 'investments');
    }

    public function test_grand_total_zero_when_no_investments(): void
    {
        $response = $this->withHeaders($this->auth())->getJson('/api/investments');
        $this->assertEquals(0, $response->json('grand_total'));
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/investments')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_user_can_create_investment(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/investments', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Tesouro IPCA+')
            ->assertJsonPath('type', 'fixed_income')
            ->assertJsonPath('user_id', $this->user->id);

        $this->assertDatabaseHas('investments', [
            'user_id' => $this->user->id,
            'name'    => 'Tesouro IPCA+',
        ]);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/investments', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_create_fails_with_invalid_type(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/investments', $this->validPayload(['type' => 'savings']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/investments', $this->validPayload())->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_user_can_view_own_investment(): void
    {
        $inv = Investment::factory()->create(['user_id' => $this->user->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/investments/{$inv->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $inv->id)
            ->assertJsonStructure(['total_invested']);
    }

    public function test_user_cannot_view_other_users_investment(): void
    {
        $other = User::factory()->create();
        $inv   = Investment::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/investments/{$inv->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_user_can_update_investment(): void
    {
        $inv = Investment::factory()->create(['user_id' => $this->user->id]);

        $this->withHeaders($this->auth())
            ->putJson("/api/investments/{$inv->id}", ['name' => 'VALE3', 'type' => 'stock'])
            ->assertStatus(200)
            ->assertJsonPath('name', 'VALE3')
            ->assertJsonPath('type', 'stock');
    }

    public function test_update_fails_for_other_users_investment(): void
    {
        $other = User::factory()->create();
        $inv   = Investment::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->putJson("/api/investments/{$inv->id}", ['name' => 'Hack'])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_user_can_delete_investment(): void
    {
        $inv = Investment::factory()->create(['user_id' => $this->user->id]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/investments/{$inv->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Investimento excluído com sucesso.');

        $this->assertDatabaseMissing('investments', ['id' => $inv->id]);
    }

    public function test_deleting_investment_cascades_to_transactions(): void
    {
        $inv = Investment::factory()->create(['user_id' => $this->user->id]);
        InvestmentTransaction::factory()->create(['investment_id' => $inv->id]);

        $this->withHeaders($this->auth())->deleteJson("/api/investments/{$inv->id}")->assertStatus(200);

        $this->assertDatabaseMissing('investment_transactions', ['investment_id' => $inv->id]);
    }

    public function test_delete_fails_for_other_users_investment(): void
    {
        $other = User::factory()->create();
        $inv   = Investment::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/investments/{$inv->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Totals by type
    // -------------------------------------------------------------------------

    public function test_totals_by_type_aggregates_correctly(): void
    {
        $stock1 = Investment::factory()->create(['user_id' => $this->user->id, 'type' => 'stock']);
        $stock2 = Investment::factory()->create(['user_id' => $this->user->id, 'type' => 'stock']);
        $crypto = Investment::factory()->create(['user_id' => $this->user->id, 'type' => 'crypto']);

        InvestmentTransaction::factory()->create(['investment_id' => $stock1->id, 'type' => 'buy', 'amount' => 3000]);
        InvestmentTransaction::factory()->create(['investment_id' => $stock2->id, 'type' => 'buy', 'amount' => 2000]);
        InvestmentTransaction::factory()->create(['investment_id' => $crypto->id, 'type' => 'buy', 'amount' => 1000]);

        $response = $this->withHeaders($this->auth())->getJson('/api/investments');

        $this->assertEquals(5000, $response->json('totals_by_type.stock'));
        $this->assertEquals(1000, $response->json('totals_by_type.crypto'));
        $this->assertEquals(6000, $response->json('grand_total'));
    }
}
