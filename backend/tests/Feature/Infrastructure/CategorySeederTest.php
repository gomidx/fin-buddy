<?php

namespace Tests\Feature\Infrastructure;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_default_income_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $incomeCategories = DB::table('categories')
            ->whereNull('user_id')
            ->where('type', 'income')
            ->pluck('name')
            ->toArray();

        $this->assertContains('Salário', $incomeCategories);
        $this->assertContains('Freelance', $incomeCategories);
        $this->assertContains('Investimento', $incomeCategories);
        $this->assertContains('Outros', $incomeCategories);
    }

    public function test_seeder_creates_default_expense_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $expenseCategories = DB::table('categories')
            ->whereNull('user_id')
            ->where('type', 'expense')
            ->pluck('name')
            ->toArray();

        foreach (['Reserva de Emergência', 'Moradia', 'Alimentação', 'Transporte', 'Saúde', 'Educação', 'Lazer', 'Compras', 'Pets', 'Outros'] as $name) {
            $this->assertContains($name, $expenseCategories);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $count = DB::table('categories')->whereNull('user_id')->count();

        $this->assertEquals(14, $count);
    }

    public function test_all_default_categories_have_null_user_id(): void
    {
        $this->seed(CategorySeeder::class);

        $withUserId = DB::table('categories')->whereNotNull('user_id')->count();

        $this->assertEquals(0, $withUserId);
    }
}
