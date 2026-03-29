<?php

namespace App\Providers;

use App\Repositories\CategoryRepository;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\EmergencyFundRepositoryInterface;
use App\Repositories\Contracts\FinancialGoalRepositoryInterface;
use App\Repositories\Contracts\InvestmentRepositoryInterface;
use App\Repositories\Contracts\InvestmentTransactionRepositoryInterface;
use App\Repositories\Contracts\RecurringTransactionRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EmergencyFundRepository;
use App\Repositories\FinancialGoalRepository;
use App\Repositories\InvestmentRepository;
use App\Repositories\InvestmentTransactionRepository;
use App\Repositories\RecurringTransactionRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->bind(RecurringTransactionRepositoryInterface::class, RecurringTransactionRepository::class);
        $this->app->bind(EmergencyFundRepositoryInterface::class, EmergencyFundRepository::class);
        $this->app->bind(InvestmentRepositoryInterface::class, InvestmentRepository::class);
        $this->app->bind(InvestmentTransactionRepositoryInterface::class, InvestmentTransactionRepository::class);
        $this->app->bind(FinancialGoalRepositoryInterface::class, FinancialGoalRepository::class);
    }
}
