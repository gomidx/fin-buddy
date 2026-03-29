<?php

namespace Tests\Feature\Notification;

use App\Models\Category;
use App\Models\FinancialGoal;
use App\Models\FinNotification;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function expenseCat(?User $user = null): Category
    {
        return Category::factory()->create(['user_id' => $user?->id, 'type' => 'expense']);
    }

    // ──────────────────────────────────────────────
    // Auth guard
    // ──────────────────────────────────────────────

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    // ──────────────────────────────────────────────
    // API: listagem e contagem
    // ──────────────────────────────────────────────

    public function test_list_returns_user_notifications(): void
    {
        $user = $this->makeUser();
        FinNotification::create(['user_id' => $user->id, 'type' => 'no_activity', 'title' => 'T1', 'message' => 'M1']);
        FinNotification::create(['user_id' => $user->id, 'type' => 'goal_reminder', 'title' => 'T2', 'message' => 'M2']);

        $data = $this->actingAs($user)->getJson('/api/notifications')->assertOk()->json();

        $this->assertCount(2, $data);
    }

    public function test_notifications_are_isolated_per_user(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        FinNotification::create(['user_id' => $userB->id, 'type' => 'no_activity', 'title' => 'T', 'message' => 'M']);

        $data = $this->actingAs($userA)->getJson('/api/notifications')->assertOk()->json();

        $this->assertCount(0, $data);
    }

    public function test_unread_count_returns_correct_count(): void
    {
        $user = $this->makeUser();
        FinNotification::create(['user_id' => $user->id, 'type' => 'no_activity', 'title' => 'T', 'message' => 'M', 'is_read' => false]);
        FinNotification::create(['user_id' => $user->id, 'type' => 'no_activity', 'title' => 'T2', 'message' => 'M2', 'is_read' => true]);

        $this->actingAs($user)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    // ──────────────────────────────────────────────
    // API: marcar como lida
    // ──────────────────────────────────────────────

    public function test_mark_as_read_works(): void
    {
        $user = $this->makeUser();
        $n = FinNotification::create(['user_id' => $user->id, 'type' => 'no_activity', 'title' => 'T', 'message' => 'M']);

        $this->actingAs($user)->postJson("/api/notifications/{$n->id}/read")->assertOk();

        $this->assertTrue(FinNotification::find($n->id)->is_read);
        $this->assertNotNull(FinNotification::find($n->id)->read_at);
    }

    public function test_mark_as_read_returns_404_for_other_user_notification(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $n = FinNotification::create(['user_id' => $userB->id, 'type' => 'no_activity', 'title' => 'T', 'message' => 'M']);

        $this->actingAs($userA)->postJson("/api/notifications/{$n->id}/read")->assertStatus(404);
    }

    public function test_mark_all_as_read_marks_only_users_notifications(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        FinNotification::create(['user_id' => $userA->id, 'type' => 'no_activity', 'title' => 'T1', 'message' => 'M']);
        FinNotification::create(['user_id' => $userA->id, 'type' => 'goal_reminder', 'title' => 'T2', 'message' => 'M']);
        FinNotification::create(['user_id' => $userB->id, 'type' => 'no_activity', 'title' => 'T3', 'message' => 'M']);

        $res = $this->actingAs($userA)->postJson('/api/notifications/read-all')->assertOk()->json();

        $this->assertEquals(2, $res['count']);

        // userB's notification must remain unread
        $this->assertFalse(FinNotification::where('user_id', $userB->id)->first()->is_read);
    }

    // ──────────────────────────────────────────────
    // Geração: despesas recorrentes próximas
    // ──────────────────────────────────────────────

    public function test_recurring_due_notification_generated(): void
    {
        $user = $this->makeUser();
        $cat  = $this->expenseCat($user);

        // Recorrência mensal com start_date no dia de hoje → vence em 0 dias (dentro da janela de 3)
        $today = Carbon::today();
        RecurringTransaction::create([
            'user_id'     => $user->id,
            'category_id' => $cat->id,
            'description' => 'Netflix',
            'amount'      => 45,
            'type'        => 'expense',
            'frequency'   => 'monthly',
            'start_date'  => $today->toDateString(),
        ]);

        $service = app(NotificationService::class);
        $count   = $service->generateRecurringDue($user);

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertDatabaseHas('fin_notifications', [
            'user_id' => $user->id,
            'type'    => 'recurring_due',
        ]);
    }

    public function test_recurring_due_not_duplicated_same_day(): void
    {
        $user = $this->makeUser();
        $cat  = $this->expenseCat($user);

        $rt = RecurringTransaction::create([
            'user_id'     => $user->id,
            'category_id' => $cat->id,
            'description' => 'Spotify',
            'amount'      => 20,
            'type'        => 'expense',
            'frequency'   => 'monthly',
            'start_date'  => Carbon::today()->toDateString(),
        ]);

        $service = app(NotificationService::class);
        $service->generateRecurringDue($user);
        $count2 = $service->generateRecurringDue($user); // segunda vez no mesmo dia

        $this->assertEquals(0, $count2);
        $this->assertEquals(1, FinNotification::where('user_id', $user->id)->where('type', 'recurring_due')->count());
    }

    // ──────────────────────────────────────────────
    // Geração: metas com prazo próximo
    // ──────────────────────────────────────────────

    public function test_goal_reminder_generated_when_deadline_near_and_progress_low(): void
    {
        $user = $this->makeUser();

        FinancialGoal::create([
            'user_id'        => $user->id,
            'name'           => 'Viagem',
            'target_amount'  => 10000,
            'current_amount' => 1000,         // 10% — abaixo de 70%
            'target_date'    => Carbon::today()->addDays(20)->toDateString(),
        ]);

        $service = app(NotificationService::class);
        $count   = $service->generateGoalReminders($user);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('fin_notifications', [
            'user_id' => $user->id,
            'type'    => 'goal_reminder',
        ]);
    }

    public function test_goal_reminder_not_generated_when_progress_above_70(): void
    {
        $user = $this->makeUser();

        FinancialGoal::create([
            'user_id'        => $user->id,
            'name'           => 'Carro',
            'target_amount'  => 10000,
            'current_amount' => 8000,         // 80% — acima de 70%
            'target_date'    => Carbon::today()->addDays(15)->toDateString(),
        ]);

        $count = app(NotificationService::class)->generateGoalReminders($user);

        $this->assertEquals(0, $count);
    }

    public function test_goal_reminder_not_generated_when_deadline_far(): void
    {
        $user = $this->makeUser();

        FinancialGoal::create([
            'user_id'        => $user->id,
            'name'           => 'Reserva',
            'target_amount'  => 10000,
            'current_amount' => 100,
            'target_date'    => Carbon::today()->addDays(60)->toDateString(), // fora da janela
        ]);

        $count = app(NotificationService::class)->generateGoalReminders($user);

        $this->assertEquals(0, $count);
    }

    // ──────────────────────────────────────────────
    // Geração: inatividade
    // ──────────────────────────────────────────────

    public function test_no_activity_notification_generated_after_7_days(): void
    {
        $user = $this->makeUser();
        // Sem transações nos últimos 7 dias

        $count = app(NotificationService::class)->generateNoActivityReminder($user);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('fin_notifications', [
            'user_id' => $user->id,
            'type'    => 'no_activity',
        ]);
    }

    public function test_no_activity_notification_not_generated_when_user_has_recent_transactions(): void
    {
        $user = $this->makeUser();
        $cat  = $this->expenseCat($user);

        Transaction::create([
            'user_id'     => $user->id,
            'category_id' => $cat->id,
            'type'        => 'expense',
            'amount'      => 50,
            'description' => 'Café',
            'date'        => Carbon::today()->toDateString(),
        ]);

        $count = app(NotificationService::class)->generateNoActivityReminder($user);

        $this->assertEquals(0, $count);
    }

    public function test_no_activity_not_duplicated_within_7_days(): void
    {
        $user = $this->makeUser();

        $service = app(NotificationService::class);
        $service->generateNoActivityReminder($user);
        $count2 = $service->generateNoActivityReminder($user);

        $this->assertEquals(0, $count2);
        $this->assertEquals(1, FinNotification::where('user_id', $user->id)->where('type', 'no_activity')->count());
    }
}
