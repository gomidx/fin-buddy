<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestmentTransactionFactory extends Factory
{
    protected $model = InvestmentTransaction::class;

    public function definition(): array
    {
        return [
            'investment_id' => Investment::factory(),
            'type'          => $this->faker->randomElement(['buy', 'sell', 'dividend']),
            'amount'        => $this->faker->randomFloat(2, 100, 10000),
            'date'          => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description'   => $this->faker->optional()->sentence(),
        ];
    }
}
