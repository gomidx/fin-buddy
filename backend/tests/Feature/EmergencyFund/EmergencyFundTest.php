<?php

namespace Tests\Feature\EmergencyFund;

use App\Models\Category;
use App\Models\EmergencyFund;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyFundTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Category $expenseCategory;
    private Category $incomeCategory;
    private Category $emergencyCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user     = User::factory()->create();
        $this->token    = $this->user->createToken('api-token')->plainTextToken;

        $this->expenseCategory   = Category::factory()->create(['user_id' => null, 'type' => 'expense', 'name' => 'Moradia']);
        $this->incomeCategory    = Category::factory()->create(['user_id' => null, 'type' => 'income',  'name' => 'Salário']);
        $this->emergencyCategory = Category::factory()->create([
            'user_id' => null,
            'type'    => 'expense',
            'name'    => 'Reserva de Emergência',
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // -------------------------------------------------------------------------
    // GET /emergency-fund
    // -------------------------------------------------------------------------

    public function test_show_returns_not_configured_when_no_goal_set(): void
    {
        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');

        $response->assertStatus(200)
            ->assertJsonPath('has_goal', false)
            ->assertJsonPath('target_months', null)
            ->assertJsonPath('target_amount', null);

        $this->assertEquals(0, $response->json('current_amount'));
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/emergency-fund')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // PUT /emergency-fund
    // -------------------------------------------------------------------------

    public function test_user_can_set_goal_with_target_months(): void
    {
        // Create some expense transactions to calculate avg
        Transaction::factory()->count(3)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'amount'      => 1000,
            'date'        => now()->subMonth()->toDateString(),
        ]);

        $response = $this->withHeaders($this->auth())->putJson('/api/emergency-fund', [
            'target_months' => 6,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('has_goal', true)
            ->assertJsonPath('target_months', 6);

        $this->assertDatabaseHas('emergency_funds', [
            'user_id'       => $this->user->id,
            'target_months' => 6,
        ]);
    }

    public function test_user_can_override_target_amount_manually(): void
    {
        $response = $this->withHeaders($this->auth())->putJson('/api/emergency-fund', [
            'target_months' => 6,
            'target_amount' => 30000,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(30000, $response->json('target_amount'));

        $this->assertDatabaseHas('emergency_funds', [
            'user_id'       => $this->user->id,
            'target_months' => 6,
            'target_amount' => 30000,
        ]);
    }

    public function test_update_fails_without_target_months(): void
    {
        $this->withHeaders($this->auth())
            ->putJson('/api/emergency-fund', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_months']);
    }

    public function test_update_fails_with_invalid_target_months(): void
    {
        $this->withHeaders($this->auth())
            ->putJson('/api/emergency-fund', ['target_months' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_months']);
    }

    public function test_update_is_idempotent(): void
    {
        // First update
        $this->withHeaders($this->auth())->putJson('/api/emergency-fund', ['target_months' => 3]);
        $this->assertDatabaseCount('emergency_funds', 1);

        // Second update — should not create duplicate
        $this->withHeaders($this->auth())->putJson('/api/emergency-fund', ['target_months' => 6]);
        $this->assertDatabaseCount('emergency_funds', 1);

        $this->assertDatabaseHas('emergency_funds', [
            'user_id'       => $this->user->id,
            'target_months' => 6,
        ]);
    }

    public function test_update_requires_authentication(): void
    {
        $this->putJson('/api/emergency-fund', ['target_months' => 6])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // POST /emergency-fund/deposit
    // -------------------------------------------------------------------------

    public function test_user_can_deposit_to_emergency_fund(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/emergency-fund/deposit', [
            'amount'      => 500,
            'description' => 'Depósito inicial',
            'date'        => '2026-03-15',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('type', 'emergency_fund')
            ->assertJsonPath('user_id', $this->user->id);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'type'    => 'emergency_fund',
            'amount'  => 500,
        ]);
    }

    public function test_deposit_fails_without_required_fields(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/emergency-fund/deposit', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'date']);
    }

    public function test_deposit_fails_with_zero_amount(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/emergency-fund/deposit', ['amount' => 0, 'date' => '2026-03-15'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_deposit_requires_authentication(): void
    {
        $this->postJson('/api/emergency-fund/deposit', [])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Progress calculations
    // -------------------------------------------------------------------------

    public function test_current_amount_reflects_deposits(): void
    {
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->emergencyCategory->id,
            'type'        => 'emergency_fund',
            'amount'      => 5000,
            'date'        => now()->toDateString(),
        ]);
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->emergencyCategory->id,
            'type'        => 'emergency_fund',
            'amount'      => 3000,
            'date'        => now()->toDateString(),
        ]);

        $this->withHeaders($this->auth())->putJson('/api/emergency-fund', [
            'target_months' => 6,
            'target_amount' => 20000,
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');

        $response->assertStatus(200);
        $this->assertEquals(8000, $response->json('current_amount'));
    }

    public function test_percentage_is_calculated_correctly(): void
    {
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->emergencyCategory->id,
            'type'        => 'emergency_fund',
            'amount'      => 6000,
            'date'        => now()->toDateString(),
        ]);

        $this->withHeaders($this->auth())->putJson('/api/emergency-fund', [
            'target_months' => 6,
            'target_amount' => 10000,
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');

        $response->assertStatus(200);
        $this->assertEquals(60, $response->json('percentage'));
    }

    public function test_percentage_caps_at_100(): void
    {
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->emergencyCategory->id,
            'type'        => 'emergency_fund',
            'amount'      => 15000,
            'date'        => now()->toDateString(),
        ]);

        $this->withHeaders($this->auth())->putJson('/api/emergency-fund', [
            'target_months' => 6,
            'target_amount' => 10000,
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');
        $this->assertEquals(100, $response->json('percentage'));
    }

    // -------------------------------------------------------------------------
    // Status calculation
    // -------------------------------------------------------------------------

    public function test_status_is_risk_when_covered_less_than_3_months(): void
    {
        // avg expenses = 2000/month; covered = 1 month
        Transaction::factory()->count(3)->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'amount'      => 2000,
            'date'        => now()->subMonth()->toDateString(),
        ]);
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->emergencyCategory->id,
            'type'        => 'emergency_fund',
            'amount'      => 2000,
            'date'        => now()->toDateString(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');
        $response->assertStatus(200)->assertJsonPath('status', 'risk');
    }

    public function test_status_is_attention_when_covered_3_to_5_months(): void
    {
        // avg expenses = 1000/month; current = 4000 (4 months)
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'amount'      => 1000,
            'date'        => now()->subMonth()->toDateString(),
        ]);
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->emergencyCategory->id,
            'type'        => 'emergency_fund',
            'amount'      => 4000,
            'date'        => now()->toDateString(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');
        $response->assertStatus(200)->assertJsonPath('status', 'attention');
    }

    public function test_status_is_safe_when_covered_6_or_more_months(): void
    {
        // avg expenses = 1000/month; current = 7000 (7 months)
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'amount'      => 1000,
            'date'        => now()->subMonth()->toDateString(),
        ]);
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->emergencyCategory->id,
            'type'        => 'emergency_fund',
            'amount'      => 7000,
            'date'        => now()->toDateString(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');
        $response->assertStatus(200)->assertJsonPath('status', 'safe');
    }

    public function test_status_is_not_configured_when_no_expense_history(): void
    {
        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');
        $response->assertStatus(200)->assertJsonPath('status', 'not_configured');
    }

    // -------------------------------------------------------------------------
    // average_monthly_expenses calculation
    // -------------------------------------------------------------------------

    public function test_average_monthly_expenses_calculated_from_last_3_months(): void
    {
        // Month 1: 1000, Month 2: 3000 → avg = 2000
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'amount'      => 1000,
            'date'        => now()->subMonths(2)->toDateString(),
        ]);
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'type'        => 'expense',
            'amount'      => 3000,
            'date'        => now()->subMonth()->toDateString(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');
        $response->assertStatus(200);
        $this->assertEquals(2000, $response->json('average_monthly_expenses'));
    }

    public function test_data_is_isolated_between_users(): void
    {
        $other = User::factory()->create();
        EmergencyFund::create(['user_id' => $other->id, 'target_months' => 6, 'target_amount' => 10000]);
        Transaction::factory()->create([
            'user_id'     => $other->id,
            'category_id' => $this->emergencyCategory->id,
            'type'        => 'emergency_fund',
            'amount'      => 5000,
            'date'        => now()->toDateString(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/emergency-fund');

        $response->assertStatus(200)
            ->assertJsonPath('has_goal', false);
        $this->assertEquals(0, $response->json('current_amount'));
    }
}
