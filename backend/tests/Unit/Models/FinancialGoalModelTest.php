<?php

namespace Tests\Unit\Models;

use App\Models\FinancialGoal;
use PHPUnit\Framework\TestCase;

class FinancialGoalModelTest extends TestCase
{
    public function test_progress_percentage_returns_correct_value(): void
    {
        $goal = new FinancialGoal([
            'target_amount'  => 10000,
            'current_amount' => 3000,
        ]);

        $this->assertEquals(30.0, $goal->progressPercentage());
    }

    public function test_progress_percentage_does_not_exceed_100(): void
    {
        $goal = new FinancialGoal([
            'target_amount'  => 1000,
            'current_amount' => 2000,
        ]);

        $this->assertEquals(100.0, $goal->progressPercentage());
    }

    public function test_progress_percentage_returns_zero_when_target_is_zero(): void
    {
        $goal = new FinancialGoal([
            'target_amount'  => 0,
            'current_amount' => 500,
        ]);

        $this->assertEquals(0.0, $goal->progressPercentage());
    }

    public function test_progress_percentage_returns_zero_when_nothing_saved(): void
    {
        $goal = new FinancialGoal([
            'target_amount'  => 5000,
            'current_amount' => 0,
        ]);

        $this->assertEquals(0.0, $goal->progressPercentage());
    }
}
