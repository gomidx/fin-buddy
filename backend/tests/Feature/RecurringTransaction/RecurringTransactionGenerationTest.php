<?php

namespace Tests\Feature\RecurringTransaction;

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringTransactionGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;
    private RecurringTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user     = User::factory()->create();
        $this->category = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
        $this->service  = app(RecurringTransactionService::class);
    }

    private function makeRecurring(array $overrides = []): RecurringTransaction
    {
        return RecurringTransaction::create(array_merge([
            'user_id'           => $this->user->id,
            'category_id'       => $this->category->id,
            'description'       => 'Netflix',
            'amount'            => 49.90,
            'type'              => 'expense',
            'frequency'         => 'monthly',
            'start_date'        => now()->subMonth()->toDateString(),
            'end_date'          => null,
            'last_generated_at' => null,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Monthly generation
    // -------------------------------------------------------------------------

    public function test_generates_monthly_transaction_when_never_generated(): void
    {
        $this->makeRecurring(['frequency' => 'monthly', 'last_generated_at' => null]);

        $count = $this->service->generateDue();

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('transactions', [
            'user_id'      => $this->user->id,
            'is_recurring' => true,
        ]);
    }

    public function test_does_not_generate_monthly_if_already_generated_this_month(): void
    {
        $this->makeRecurring([
            'frequency'         => 'monthly',
            'last_generated_at' => now()->startOfMonth(),
        ]);

        $count = $this->service->generateDue();

        $this->assertEquals(0, $count);
    }

    public function test_generates_monthly_if_last_generated_was_previous_month(): void
    {
        $this->makeRecurring([
            'frequency'         => 'monthly',
            'last_generated_at' => now()->subMonth()->startOfMonth(),
        ]);

        $count = $this->service->generateDue();

        $this->assertEquals(1, $count);
    }

    // -------------------------------------------------------------------------
    // Yearly generation
    // -------------------------------------------------------------------------

    public function test_generates_yearly_transaction_when_never_generated(): void
    {
        $this->makeRecurring([
            'frequency'         => 'yearly',
            'start_date'        => now()->subYear()->toDateString(),
            'last_generated_at' => null,
        ]);

        $count = $this->service->generateDue();

        $this->assertEquals(1, $count);
    }

    public function test_does_not_generate_yearly_if_already_generated_this_year(): void
    {
        $this->makeRecurring([
            'frequency'         => 'yearly',
            'start_date'        => now()->subYear()->toDateString(),
            'last_generated_at' => now()->startOfYear(),
        ]);

        $count = $this->service->generateDue();

        $this->assertEquals(0, $count);
    }

    public function test_generates_yearly_if_last_generated_was_previous_year(): void
    {
        $this->makeRecurring([
            'frequency'         => 'yearly',
            'start_date'        => now()->subYears(2)->toDateString(),
            'last_generated_at' => now()->subYear()->startOfYear(),
        ]);

        $count = $this->service->generateDue();

        $this->assertEquals(1, $count);
    }

    // -------------------------------------------------------------------------
    // end_date rules
    // -------------------------------------------------------------------------

    public function test_does_not_generate_when_end_date_has_passed(): void
    {
        $this->makeRecurring(['end_date' => now()->subDay()->toDateString()]);

        $count = $this->service->generateDue();

        $this->assertEquals(0, $count);
    }

    public function test_generates_when_end_date_is_today(): void
    {
        $this->makeRecurring(['end_date' => now()->toDateString()]);

        $count = $this->service->generateDue();

        $this->assertEquals(1, $count);
    }

    public function test_generates_when_end_date_is_future(): void
    {
        $this->makeRecurring(['end_date' => now()->addMonths(6)->toDateString()]);

        $count = $this->service->generateDue();

        $this->assertEquals(1, $count);
    }

    // -------------------------------------------------------------------------
    // start_date rule
    // -------------------------------------------------------------------------

    public function test_does_not_generate_when_start_date_is_future(): void
    {
        $this->makeRecurring(['start_date' => now()->addDay()->toDateString()]);

        $count = $this->service->generateDue();

        $this->assertEquals(0, $count);
    }

    // -------------------------------------------------------------------------
    // Generated transaction fields
    // -------------------------------------------------------------------------

    public function test_generated_transaction_has_correct_fields(): void
    {
        $rt = $this->makeRecurring([
            'description' => 'Academia',
            'amount'      => 120.00,
            'type'        => 'expense',
        ]);

        $this->service->generateDue();

        $transaction = Transaction::where('recurring_id', $rt->id)->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($this->user->id, $transaction->user_id);
        $this->assertEquals($this->category->id, $transaction->category_id);
        $this->assertEquals('expense', $transaction->type);
        $this->assertEquals('120.00', $transaction->amount);
        $this->assertEquals('Academia', $transaction->description);
        $this->assertTrue((bool) $transaction->is_recurring);
        $this->assertEquals($rt->id, $transaction->recurring_id);
    }

    public function test_updates_last_generated_at_after_generation(): void
    {
        $rt = $this->makeRecurring();

        $before = now()->subSecond();
        $this->service->generateDue();
        $after = now()->addSecond();

        $rt->refresh();
        $this->assertNotNull($rt->last_generated_at);
        $this->assertTrue($rt->last_generated_at->between($before, $after));
    }

    // -------------------------------------------------------------------------
    // Count and multiple
    // -------------------------------------------------------------------------

    public function test_generate_due_returns_correct_count(): void
    {
        $this->makeRecurring();
        $this->makeRecurring(['description' => 'Spotify']);
        $this->makeRecurring(['description' => 'Internet']);

        $count = $this->service->generateDue();

        $this->assertEquals(3, $count);
    }

    public function test_does_not_generate_already_generated_ones_in_same_run(): void
    {
        $this->makeRecurring();

        $this->service->generateDue();
        $count = $this->service->generateDue(); // Second run — should not re-generate

        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('transactions', 1);
    }

    // -------------------------------------------------------------------------
    // Artisan command
    // -------------------------------------------------------------------------

    public function test_artisan_command_generates_recurring_transactions(): void
    {
        $this->makeRecurring();
        $this->makeRecurring(['description' => 'Academia']);

        $this->artisan('recurring:generate')
            ->expectsOutput('Generated 2 recurring transaction(s).')
            ->assertExitCode(0);

        $this->assertDatabaseCount('transactions', 2);
    }
}
