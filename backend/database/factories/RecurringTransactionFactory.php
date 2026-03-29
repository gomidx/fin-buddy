<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    protected $model = RecurringTransaction::class;

    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'category_id'       => Category::factory(),
            'description'       => fake()->sentence(),
            'amount'            => fake()->randomFloat(2, 10, 2000),
            'type'              => fake()->randomElement(['income', 'expense']),
            'frequency'         => fake()->randomElement(['monthly', 'yearly']),
            'start_date'        => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'end_date'          => null,
            'last_generated_at' => null,
        ];
    }

    public function monthly(): static
    {
        return $this->state(['frequency' => 'monthly']);
    }

    public function yearly(): static
    {
        return $this->state(['frequency' => 'yearly']);
    }

    public function active(): static
    {
        return $this->state(['end_date' => null]);
    }

    public function expired(): static
    {
        return $this->state([
            'end_date' => now()->subDay()->format('Y-m-d'),
        ]);
    }
}
