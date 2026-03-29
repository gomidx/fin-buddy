<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function incomeCategory(): Category
    {
        return Category::factory()->create(['user_id' => null, 'type' => 'income']);
    }

    private function expenseCategory(): Category
    {
        return Category::factory()->create(['user_id' => null, 'type' => 'expense']);
    }

    private function currentMonthDate(): string
    {
        return Carbon::now()->format('Y-m-') . '15';
    }

    // -------------------------------------------------------------------------
    // 1. Authentication
    // -------------------------------------------------------------------------

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/dashboard');
        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 2. Structure
    // -------------------------------------------------------------------------

    public function test_dashboard_returns_correct_structure(): void
    {
        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_month' => ['income', 'expenses', 'balance', 'savings_rate'],
                'emergency_fund',
                'expenses_by_category',
                'monthly_totals',
                'financial_evolution',
                'total_invested',
                'insight',
            ]);
    }

    // -------------------------------------------------------------------------
    // 3. Current month calculations
    // -------------------------------------------------------------------------

    public function test_current_month_calculates_correctly(): void
    {
        $incCat = $this->incomeCategory();
        $expCat = $this->expenseCategory();

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $incCat->id,
            'type'        => 'income',
            'amount'      => 5000.00,
            'date'        => $this->currentMonthDate(),
        ]);

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $expCat->id,
            'type'        => 'expense',
            'amount'      => 1580.00,
            'date'        => $this->currentMonthDate(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $data = $response->json('current_month');
        $this->assertEquals(5000.0, $data['income']);
        $this->assertEquals(1580.0, $data['expenses']);
        $this->assertEquals(3420.0, $data['balance']);
    }

    // -------------------------------------------------------------------------
    // 4. Savings rate
    // -------------------------------------------------------------------------

    public function test_savings_rate_calculated_correctly(): void
    {
        $incCat = $this->incomeCategory();
        $expCat = $this->expenseCategory();

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $incCat->id,
            'type'        => 'income',
            'amount'      => 5000.00,
            'date'        => $this->currentMonthDate(),
        ]);

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $expCat->id,
            'type'        => 'expense',
            'amount'      => 1000.00,
            'date'        => $this->currentMonthDate(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $this->assertEquals(80.0, $response->json('current_month.savings_rate'));
    }

    // -------------------------------------------------------------------------
    // 5. Savings rate zero when no income
    // -------------------------------------------------------------------------

    public function test_savings_rate_is_zero_when_no_income(): void
    {
        $expCat = $this->expenseCategory();

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $expCat->id,
            'type'        => 'expense',
            'amount'      => 500.00,
            'date'        => $this->currentMonthDate(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $this->assertEquals(0.0, $response->json('current_month.savings_rate'));
    }

    // -------------------------------------------------------------------------
    // 6. Expenses by category grouping
    // -------------------------------------------------------------------------

    public function test_expenses_by_category_groups_correctly(): void
    {
        $expCat = $this->expenseCategory();

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $expCat->id,
            'type'        => 'expense',
            'amount'      => 300.00,
            'date'        => $this->currentMonthDate(),
        ]);

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $expCat->id,
            'type'        => 'expense',
            'amount'      => 200.00,
            'date'        => $this->currentMonthDate(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $byCategory = $response->json('expenses_by_category');
        $this->assertCount(1, $byCategory);
        $this->assertEquals(500.0, $byCategory[0]['amount']);
        $this->assertEquals($expCat->name, $byCategory[0]['category']);
    }

    // -------------------------------------------------------------------------
    // 7. Monthly totals has 6 entries
    // -------------------------------------------------------------------------

    public function test_monthly_totals_includes_current_and_past_months(): void
    {
        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $this->assertCount(6, $response->json('monthly_totals'));
    }

    // -------------------------------------------------------------------------
    // 8. Financial evolution has 12 months
    // -------------------------------------------------------------------------

    public function test_financial_evolution_has_12_months(): void
    {
        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $this->assertCount(12, $response->json('financial_evolution'));
    }

    // -------------------------------------------------------------------------
    // 9. Total invested
    // -------------------------------------------------------------------------

    public function test_total_invested_reflects_investment_transactions(): void
    {
        $inv = Investment::factory()->create(['user_id' => $this->user->id]);
        InvestmentTransaction::factory()->create([
            'investment_id' => $inv->id,
            'type'          => 'buy',
            'amount'        => 5000.00,
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $this->assertEquals(5000.0, $response->json('total_invested'));
    }

    // -------------------------------------------------------------------------
    // 10. Insight positive for high savings
    // -------------------------------------------------------------------------

    public function test_insight_is_positive_for_high_savings(): void
    {
        $incCat = $this->incomeCategory();
        $expCat = $this->expenseCategory();

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $incCat->id,
            'type'        => 'income',
            'amount'      => 5000.00,
            'date'        => $this->currentMonthDate(),
        ]);

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $expCat->id,
            'type'        => 'expense',
            'amount'      => 500.00,
            'date'        => $this->currentMonthDate(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $this->assertStringContainsString('Parabéns', $response->json('insight'));
    }

    // -------------------------------------------------------------------------
    // 11. Insight warns when expenses exceed income
    // -------------------------------------------------------------------------

    public function test_insight_warns_when_expenses_exceed_income(): void
    {
        $incCat = $this->incomeCategory();
        $expCat = $this->expenseCategory();

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $incCat->id,
            'type'        => 'income',
            'amount'      => 1000.00,
            'date'        => $this->currentMonthDate(),
        ]);

        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'category_id' => $expCat->id,
            'type'        => 'expense',
            'amount'      => 2000.00,
            'date'        => $this->currentMonthDate(),
        ]);

        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $this->assertStringContainsString('Atenção', $response->json('insight'));
    }

    // -------------------------------------------------------------------------
    // 12. Data isolation
    // -------------------------------------------------------------------------

    public function test_data_isolation(): void
    {
        $otherUser = User::factory()->create();
        $incCat    = $this->incomeCategory();
        $expCat    = $this->expenseCategory();

        // Other user's transactions
        Transaction::factory()->create([
            'user_id'     => $otherUser->id,
            'category_id' => $incCat->id,
            'type'        => 'income',
            'amount'      => 99999.00,
            'date'        => $this->currentMonthDate(),
        ]);

        Transaction::factory()->create([
            'user_id'     => $otherUser->id,
            'category_id' => $expCat->id,
            'type'        => 'expense',
            'amount'      => 99999.00,
            'date'        => $this->currentMonthDate(),
        ]);

        // This user has no transactions
        $response = $this->withHeaders($this->auth())->getJson('/api/dashboard');
        $response->assertStatus(200);

        $data = $response->json('current_month');
        $this->assertEquals(0.0, $data['income']);
        $this->assertEquals(0.0, $data['expenses']);
    }
}
