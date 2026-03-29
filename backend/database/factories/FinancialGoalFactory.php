<?php

namespace Database\Factories;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialGoalFactory extends Factory
{
    protected $model = FinancialGoal::class;

    public function definition(): array
    {
        $target  = $this->faker->randomFloat(2, 1000, 50000);
        $current = $this->faker->randomFloat(2, 0, $target);

        return [
            'user_id'        => User::factory(),
            'name'           => $this->faker->words(3, true),
            'target_amount'  => $target,
            'current_amount' => $current,
            'target_date'    => $this->faker->dateTimeBetween('+1 month', '+3 years')->format('Y-m-d'),
        ];
    }
}
