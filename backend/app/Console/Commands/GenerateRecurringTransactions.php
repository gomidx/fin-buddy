<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionService;
use Illuminate\Console\Command;

class GenerateRecurringTransactions extends Command
{
    protected $signature = 'recurring:generate';
    protected $description = 'Generate pending recurring transactions for the current period';

    public function __construct(private readonly RecurringTransactionService $recurringTransactionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->recurringTransactionService->generateDue();
        $this->info("Generated {$count} recurring transaction(s).");
        return Command::SUCCESS;
    }
}
