<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'name', 'email', 'password', 'currency', 'created_at', 'updated_at',
        ]));
    }

    public function test_categories_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasColumns('categories', [
            'id', 'user_id', 'name', 'type', 'created_at',
        ]));
    }

    public function test_transactions_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('transactions'));
        $this->assertTrue(Schema::hasColumns('transactions', [
            'id', 'user_id', 'category_id', 'type', 'amount',
            'description', 'date', 'is_recurring', 'recurring_id',
            'created_at', 'updated_at',
        ]));
    }

    public function test_recurring_transactions_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('recurring_transactions'));
        $this->assertTrue(Schema::hasColumns('recurring_transactions', [
            'id', 'user_id', 'category_id', 'description', 'amount',
            'type', 'frequency', 'start_date', 'end_date', 'last_generated_at', 'created_at',
        ]));
    }

    public function test_investments_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('investments'));
        $this->assertTrue(Schema::hasColumns('investments', [
            'id', 'user_id', 'name', 'type', 'institution', 'created_at',
        ]));
    }

    public function test_investment_transactions_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('investment_transactions'));
        $this->assertTrue(Schema::hasColumns('investment_transactions', [
            'id', 'investment_id', 'type', 'amount', 'date', 'description', 'created_at',
        ]));
    }

    public function test_emergency_funds_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('emergency_funds'));
        $this->assertTrue(Schema::hasColumns('emergency_funds', [
            'id', 'user_id', 'target_months', 'target_amount', 'created_at', 'updated_at',
        ]));
    }

    public function test_financial_goals_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('financial_goals'));
        $this->assertTrue(Schema::hasColumns('financial_goals', [
            'id', 'user_id', 'name', 'target_amount', 'current_amount', 'target_date',
            'created_at', 'updated_at',
        ]));
    }
}
