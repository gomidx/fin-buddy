<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Fin Buddy
|--------------------------------------------------------------------------
|
| Routes are organized by module. Authentication routes are public.
| All other routes require a valid Sanctum token.
|
*/

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [\App\Http\Controllers\Api\Auth\RegisterController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::post('/login', [\App\Http\Controllers\Api\Auth\LoginController::class, 'store'])
        ->middleware('throttle:10,1');
});

// Protected routes
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/auth/logout', [\App\Http\Controllers\Api\Auth\LoginController::class, 'destroy']);

    // Profile
    Route::get('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'update']);

    // Categories
    Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);

    // Transactions
    Route::get('/transactions/suggest-category', [\App\Http\Controllers\Api\TransactionController::class, 'suggestCategory']);
    Route::apiResource('transactions', \App\Http\Controllers\Api\TransactionController::class);

    // Recurring transactions
    Route::apiResource('recurring-transactions', \App\Http\Controllers\Api\RecurringTransactionController::class);

    // Investments
    Route::apiResource('investments', \App\Http\Controllers\Api\InvestmentController::class);
    Route::get('investments/{investment}/transactions', [\App\Http\Controllers\Api\InvestmentTransactionController::class, 'index']);
    Route::post('investments/{investment}/transactions', [\App\Http\Controllers\Api\InvestmentTransactionController::class, 'store']);
    Route::apiResource('investment-transactions', \App\Http\Controllers\Api\InvestmentTransactionController::class)
        ->only(['show', 'update', 'destroy']);

    // Emergency fund
    Route::get('/emergency-fund', [\App\Http\Controllers\Api\EmergencyFundController::class, 'show']);
    Route::put('/emergency-fund', [\App\Http\Controllers\Api\EmergencyFundController::class, 'update']);
    Route::post('/emergency-fund/deposit', [\App\Http\Controllers\Api\EmergencyFundController::class, 'deposit']);

    // Financial goals
    Route::apiResource('financial-goals', \App\Http\Controllers\Api\FinancialGoalController::class);

    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);

    // Insights & saúde financeira
    Route::get('/insights', [\App\Http\Controllers\Api\InsightController::class, 'index']);

    // Notificações
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
});
