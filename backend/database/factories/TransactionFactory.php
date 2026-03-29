<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'category_id'  => Category::factory(),
            'type'         => fake()->randomElement(['income', 'expense']),
            'amount'       => fake()->randomFloat(2, 1, 5000),
            'description'  => fake()->sentence(),
            'date'         => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'is_recurring' => false,
            'recurring_id' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(['type' => 'income']);
    }

    public function expense(): static
    {
        return $this->state(['type' => 'expense']);
    }
}
