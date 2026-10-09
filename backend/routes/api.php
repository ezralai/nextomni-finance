<?php

use App\Http\Controllers\Api\V1\Accounts\AccountController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Budgets\BudgetController;
use App\Http\Controllers\Api\V1\Categories\CategoryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Institutions\InstitutionController;
use App\Http\Controllers\Api\V1\Reports\ExportController;
use App\Http\Controllers\Api\V1\Transactions\TransactionController;
use App\Http\Controllers\Api\V1\Transactions\TransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Health Check
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Public Financial Institutions Directory
    Route::get('/institutions', [InstitutionController::class, 'index'])->name('api.v1.institutions.index');

    // Public Foreign Exchange Rates & Real-Time Converter
    Route::prefix('currencies')->group(function () {
        Route::get('/rates', [\App\Http\Controllers\Api\V1\Currencies\ExchangeRateController::class, 'index'])->name('api.v1.currencies.rates');
        Route::post('/convert', [\App\Http\Controllers\Api\V1\Currencies\ExchangeRateController::class, 'convert'])->name('api.v1.currencies.convert');
    });

    // Public Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    });

    // Protected Routes (Sanctum Authenticated)
    Route::middleware('auth:sanctum')->group(function () {
        // User & Profile
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
            Route::put('/profile', [AuthController::class, 'updateProfile'])->name('api.v1.auth.profile');
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        });

        // Financial Accounts
        Route::apiResource('accounts', AccountController::class)->names('api.v1.accounts');

        // Categories
        Route::get('/categories', [CategoryController::class, 'index'])->name('api.v1.categories.index');

        // Transactions & Transfers
        Route::apiResource('transactions', TransactionController::class)
            ->except(['update'])
            ->names('api.v1.transactions');
        Route::post('/transfers', [TransferController::class, 'store'])->name('api.v1.transfers.store');

        // Budgets & Spending Limits
        Route::apiResource('budgets', BudgetController::class)->names('api.v1.budgets');

        // Cash Flow Analytics
        Route::get('/analytics/cash-flow', [AnalyticsController::class, 'cashFlow'])->name('api.v1.analytics.cash_flow');

        // Financial Statements & Export Engine
        Route::prefix('reports')->group(function () {
            Route::get('/transactions/csv', [ExportController::class, 'exportCsv'])->name('api.v1.reports.transactions.csv');
            Route::get('/transactions/json', [ExportController::class, 'exportJson'])->name('api.v1.reports.transactions.json');
        });
    });
});
