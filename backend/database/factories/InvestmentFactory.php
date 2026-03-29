<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestmentFactory extends Factory
{
    protected $model = Investment::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'name'        => $this->faker->words(2, true),
            'type'        => $this->faker->randomElement(['stock', 'crypto', 'fund', 'fixed_income']),
            'institution' => $this->faker->company(),
        ];
    }
}
