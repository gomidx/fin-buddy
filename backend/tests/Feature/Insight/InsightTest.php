<?php

namespace Tests\Feature\Insight;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function expenseCategory(string $name = 'Alimentação'): Category
    {
        return Category::where('name', $name)->where('type', 'expense')->first()
            ?? Category::factory()->create(['name' => $name, 'type' => 'expense', 'user_id' => null]);
    }

    private function incomeCategory(): Category
    {
        return Category::where('name', 'Salário')->where('type', 'income')->first()
            ?? Category::factory()->create(['name' => 'Salário', 'type' => 'income', 'user_id' => null]);
    }

    private function createTransaction(User $user, Category $category, string $type, float $amount, ?Carbon $date = null): Transaction
    {
        return Transaction::create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'type'        => $type,
            'amount'      => $amount,
            'description' => 'Test',
            'date'        => ($date ?? Carbon::now())->toDateString(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Auth guard
    // ──────────────────────────────────────────────

    public function test_insights_requires_authentication(): void
    {
        $this->getJson('/api/insights')->assertUnauthorized();
    }

    // ──────────────────────────────────────────────
    // Response structure
    // ──────────────────────────────────────────────

    public function test_insights_returns_correct_structure(): void
    {
        $user = $this->makeUser();
        $incomeCat = $this->incomeCategory();
        $expenseCat = $this->expenseCategory('Alimentação');

        $this->createTransaction($user, $incomeCat, 'income', 5000);
        $this->createTransaction($user, $expenseCat, 'expense', 1000);

        $response = $this->actingAs($user)->getJson('/api/insights')->assertOk();

        $response->assertJsonStructure([
            'health' => ['status', 'label', 'color'],
            'metrics' => [
                'savings_rate', 'income', 'expenses', 'saved_amount',
                'essential_expenses', 'leisure_expenses',
                'essential_ratio', 'leisure_ratio',
                'expense_change_pct', 'emergency_fund_months', 'emergency_fund_status',
            ],
            'recommendations',
        ]);
    }

    // ──────────────────────────────────────────────
    // Health classification — Proposta A
    // ──────────────────────────────────────────────

    public function test_health_is_healthy_when_savings_rate_at_least_20(): void
    {
        $user = $this->makeUser();
        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $this->expenseCategory(), 'expense', 3000); // save 40%

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals('healthy', $data['health']['status']);
        $this->assertEquals('Saudável', $data['health']['label']);
        $this->assertEquals('success', $data['health']['color']);
    }

    public function test_health_is_attention_when_savings_rate_10_to_19(): void
    {
        $user = $this->makeUser();
        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $this->expenseCategory(), 'expense', 4500); // save 10%

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals('attention', $data['health']['status']);
        $this->assertEquals('Atenção', $data['health']['label']);
    }

    public function test_health_is_risk_when_savings_rate_below_10(): void
    {
        $user = $this->makeUser();
        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $this->expenseCategory(), 'expense', 4900); // save 2%

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals('risk', $data['health']['status']);
        $this->assertEquals('Risco financeiro', $data['health']['label']);
        $this->assertEquals('danger', $data['health']['color']);
    }

    // ──────────────────────────────────────────────
    // Metrics calculation
    // ──────────────────────────────────────────────

    public function test_savings_rate_calculated_correctly(): void
    {
        $user = $this->makeUser();
        $this->createTransaction($user, $this->incomeCategory(), 'income', 4000);
        $this->createTransaction($user, $this->expenseCategory(), 'expense', 3200); // 20% saved

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals(20.0, $data['metrics']['savings_rate']);
        $this->assertEquals(4000.0, $data['metrics']['income']);
        $this->assertEquals(3200.0, $data['metrics']['expenses']);
        $this->assertEquals(800.0, $data['metrics']['saved_amount']);
    }

    public function test_essential_expenses_grouped_correctly(): void
    {
        $user     = $this->makeUser();
        $moradia  = $this->expenseCategory('Moradia');
        $lazer    = $this->expenseCategory('Lazer');

        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $moradia, 'expense', 1500); // essential
        $this->createTransaction($user, $lazer, 'expense', 500);    // leisure

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals(1500.0, $data['metrics']['essential_expenses']);
        $this->assertEquals(500.0, $data['metrics']['leisure_expenses']);
        $this->assertEquals(30.0, $data['metrics']['essential_ratio']); // 1500/5000
        $this->assertEquals(10.0, $data['metrics']['leisure_ratio']);   // 500/5000
    }

    public function test_top_category_returns_highest_expense(): void
    {
        $user    = $this->makeUser();
        $alim    = $this->expenseCategory('Alimentação');
        $transp  = $this->expenseCategory('Transporte');

        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $alim, 'expense', 1250);
        $this->createTransaction($user, $transp, 'expense', 400);

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals('Alimentação', $data['metrics']['top_category']['name']);
        $this->assertEquals(1250.0, $data['metrics']['top_category']['amount']);
    }

    public function test_expense_change_pct_vs_previous_month(): void
    {
        $user    = $this->makeUser();
        $cat     = $this->expenseCategory();
        $prev    = Carbon::now()->subMonth();

        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $cat, 'expense', 2000);               // current
        $this->createTransaction($user, $cat, 'expense', 1000, $prev);        // previous (+100%)

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals(100.0, $data['metrics']['expense_change_pct']);
    }

    public function test_expense_change_pct_is_null_when_no_previous_month(): void
    {
        $user = $this->makeUser();
        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $this->expenseCategory(), 'expense', 1000);

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertNull($data['metrics']['expense_change_pct']);
    }

    // ──────────────────────────────────────────────
    // Recommendations
    // ──────────────────────────────────────────────

    public function test_positive_recommendation_when_savings_at_least_20(): void
    {
        $user = $this->makeUser();
        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $this->expenseCategory(), 'expense', 3000);

        $data  = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();
        $types = array_column($data['recommendations'], 'type');
        $msgs  = array_column($data['recommendations'], 'message');

        $this->assertContains('success', $types);
        $this->assertTrue(str_contains(implode(' ', $msgs), 'Parabéns'));
    }

    public function test_warning_recommendation_when_savings_10_to_19(): void
    {
        $user = $this->makeUser();
        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $this->expenseCategory(), 'expense', 4500);

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();
        $msgs = array_column($data['recommendations'], 'message');

        $this->assertTrue(str_contains(implode(' ', $msgs), 'objetivo é 20%'));
    }

    public function test_danger_recommendation_when_savings_below_10(): void
    {
        $user = $this->makeUser();
        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $this->expenseCategory(), 'expense', 4900);

        $data  = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();
        $types = array_column($data['recommendations'], 'type');

        $this->assertContains('danger', $types);
    }

    public function test_leisure_warning_when_above_30_percent_of_income(): void
    {
        $user  = $this->makeUser();
        $lazer = $this->expenseCategory('Lazer');

        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $lazer, 'expense', 2000); // 40% em lazer

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();
        $msgs = array_column($data['recommendations'], 'message');

        $this->assertTrue(str_contains(implode(' ', $msgs), 'lazer'));
    }

    public function test_top_category_appears_in_recommendations(): void
    {
        $user = $this->makeUser();
        $cat  = $this->expenseCategory('Alimentação');

        $this->createTransaction($user, $this->incomeCategory(), 'income', 5000);
        $this->createTransaction($user, $cat, 'expense', 1250);

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();
        $msgs = array_column($data['recommendations'], 'message');

        $this->assertTrue(str_contains(implode(' ', $msgs), 'Alimentação'));
    }

    // ──────────────────────────────────────────────
    // Edge cases
    // ──────────────────────────────────────────────

    public function test_no_income_returns_info_recommendation(): void
    {
        $user = $this->makeUser();

        $data = $this->actingAs($user)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals('risk', $data['health']['status']);
        $this->assertEquals(0.0, $data['metrics']['income']);

        $types = array_column($data['recommendations'], 'type');
        $this->assertContains('info', $types);
    }

    public function test_data_isolation_between_users(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $cat   = $this->expenseCategory();

        $this->createTransaction($userA, $this->incomeCategory(), 'income', 10000);
        $this->createTransaction($userB, $cat, 'expense', 9999);

        $dataA = $this->actingAs($userA)->getJson('/api/insights')->assertOk()->json();

        $this->assertEquals(10000.0, $dataA['metrics']['income']);
        $this->assertEquals(0.0, $dataA['metrics']['expenses']);
    }
}
