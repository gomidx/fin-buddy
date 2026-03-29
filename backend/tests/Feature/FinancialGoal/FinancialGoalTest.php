<?php

namespace Tests\Feature\FinancialGoal;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialGoalTest extends TestCase
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
            'name'          => 'Viagem Europa',
            'target_amount' => 10000,
            'target_date'   => now()->addYear()->format('Y-m-d'),
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_user_can_list_own_goals(): void
    {
        FinancialGoal::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->auth())->getJson('/api/financial-goals');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_list_does_not_show_other_users_goals(): void
    {
        $other = User::factory()->create();
        FinancialGoal::factory()->count(2)->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->auth())->getJson('/api/financial-goals');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    public function test_list_includes_progress_percentage(): void
    {
        FinancialGoal::factory()->create([
            'user_id'        => $this->user->id,
            'target_amount'  => 10000,
            'current_amount' => 3000,
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/financial-goals');

        $response->assertStatus(200);
        $this->assertEquals(30.0, $response->json('0.progress_percentage'));
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/financial-goals')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_user_can_create_goal(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/financial-goals', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Viagem Europa')
            ->assertJsonPath('user_id', $this->user->id);

        $this->assertDatabaseHas('financial_goals', [
            'user_id' => $this->user->id,
            'name'    => 'Viagem Europa',
        ]);
    }

    public function test_create_sets_current_amount_to_zero_by_default(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/financial-goals', $this->validPayload());

        $response->assertStatus(201);
        $this->assertEquals('0.00', $response->json('current_amount'));
    }

    public function test_create_accepts_initial_current_amount(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/financial-goals', $this->validPayload(['current_amount' => 2000]));

        $response->assertStatus(201);
        $this->assertEquals('2000.00', $response->json('current_amount'));
    }

    public function test_create_without_target_date_is_allowed(): void
    {
        $payload = ['name' => 'Reserva livre', 'target_amount' => 5000];

        $this->withHeaders($this->auth())
            ->postJson('/api/financial-goals', $payload)
            ->assertStatus(201);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/financial-goals', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'target_amount']);
    }

    public function test_create_fails_with_zero_target_amount(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/financial-goals', $this->validPayload(['target_amount' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_amount']);
    }

    public function test_create_fails_with_past_target_date(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/financial-goals', $this->validPayload(['target_date' => '2020-01-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_date']);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/financial-goals', $this->validPayload())->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_user_can_view_own_goal(): void
    {
        $goal = FinancialGoal::factory()->create(['user_id' => $this->user->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/financial-goals/{$goal->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $goal->id)
            ->assertJsonStructure(['progress_percentage']);
    }

    public function test_user_cannot_view_other_users_goal(): void
    {
        $other = User::factory()->create();
        $goal  = FinancialGoal::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->getJson("/api/financial-goals/{$goal->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_user_can_update_goal_name(): void
    {
        $goal = FinancialGoal::factory()->create(['user_id' => $this->user->id, 'name' => 'Original']);

        $this->withHeaders($this->auth())
            ->putJson("/api/financial-goals/{$goal->id}", ['name' => 'Atualizada'])
            ->assertStatus(200)
            ->assertJsonPath('name', 'Atualizada');
    }

    public function test_user_can_update_current_amount(): void
    {
        $goal = FinancialGoal::factory()->create([
            'user_id'        => $this->user->id,
            'target_amount'  => 10000,
            'current_amount' => 0,
        ]);

        $this->withHeaders($this->auth())
            ->putJson("/api/financial-goals/{$goal->id}", ['current_amount' => 5000])
            ->assertStatus(200)
            ->assertJsonPath('current_amount', '5000.00');
    }

    public function test_progress_updates_after_current_amount_change(): void
    {
        $goal = FinancialGoal::factory()->create([
            'user_id'        => $this->user->id,
            'target_amount'  => 10000,
            'current_amount' => 0,
        ]);

        $response = $this->withHeaders($this->auth())
            ->putJson("/api/financial-goals/{$goal->id}", ['current_amount' => 5000]);

        $response->assertStatus(200);
        $this->assertEquals(50.0, $response->json('progress_percentage'));
    }

    public function test_update_fails_for_other_users_goal(): void
    {
        $other = User::factory()->create();
        $goal  = FinancialGoal::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->putJson("/api/financial-goals/{$goal->id}", ['name' => 'Hack'])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_user_can_delete_goal(): void
    {
        $goal = FinancialGoal::factory()->create(['user_id' => $this->user->id]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/financial-goals/{$goal->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Meta excluída com sucesso.');

        $this->assertDatabaseMissing('financial_goals', ['id' => $goal->id]);
    }

    public function test_delete_fails_for_other_users_goal(): void
    {
        $other = User::factory()->create();
        $goal  = FinancialGoal::factory()->create(['user_id' => $other->id]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/financial-goals/{$goal->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Progress percentage
    // -------------------------------------------------------------------------

    public function test_progress_is_zero_with_no_savings(): void
    {
        $goal = FinancialGoal::factory()->create([
            'user_id'        => $this->user->id,
            'target_amount'  => 10000,
            'current_amount' => 0,
        ]);

        $response = $this->withHeaders($this->auth())->getJson("/api/financial-goals/{$goal->id}");

        $this->assertEquals(0.0, $response->json('progress_percentage'));
    }

    public function test_progress_caps_at_100(): void
    {
        $goal = FinancialGoal::factory()->create([
            'user_id'        => $this->user->id,
            'target_amount'  => 1000,
            'current_amount' => 2000,
        ]);

        $response = $this->withHeaders($this->auth())->getJson("/api/financial-goals/{$goal->id}");

        $this->assertEquals(100.0, $response->json('progress_percentage'));
    }

    public function test_progress_calculated_correctly(): void
    {
        $goal = FinancialGoal::factory()->create([
            'user_id'        => $this->user->id,
            'target_amount'  => 8000,
            'current_amount' => 2000,
        ]);

        $response = $this->withHeaders($this->auth())->getJson("/api/financial-goals/{$goal->id}");

        $this->assertEquals(25.0, $response->json('progress_percentage'));
    }
}
