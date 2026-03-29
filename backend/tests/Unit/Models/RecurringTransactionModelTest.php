<?php

namespace Tests\Unit\Models;

use App\Models\RecurringTransaction;
use Carbon\Carbon;
use Tests\TestCase;

class RecurringTransactionModelTest extends TestCase
{
    public function test_is_active_returns_true_when_no_end_date(): void
    {
        $recurring = new RecurringTransaction();
        $recurring->end_date = null;

        $this->assertTrue($recurring->isActive());
    }

    public function test_is_active_returns_true_when_end_date_is_future(): void
    {
        $recurring = new RecurringTransaction();
        $recurring->end_date = Carbon::now()->addMonth();

        $this->assertTrue($recurring->isActive());
    }

    public function test_is_active_returns_false_when_end_date_is_past(): void
    {
        $recurring = new RecurringTransaction();
        $recurring->end_date = Carbon::now()->subDay();

        $this->assertFalse($recurring->isActive());
    }
}
