<?php

use App\Http\Controllers\Api\V1\Accounts\AccountController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\V1\Budgets\BudgetController;
use App\Http\Controllers\Api\V1\Categories\CategoryController;
use App\Http\Controllers\Api\V1\DocsController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Institutions\InstitutionController;
use App\Http\Controllers\Api\V1\Reports\ExportController;
use App\Http\Controllers\Api\V1\Transactions\TransactionController;
use App\Http\Controllers\Api\V1\Transactions\TransferController;
use Illuminate\Support\Facades\Route;

// Public OpenAPI & Interactive Documentation
Route::get('/docs', [DocsController::class, 'ui'])->name('api.docs');
Route::get('/openapi.json', [DocsController::class, 'schema'])->name('api.openapi');

Route::prefix('v1')->group(function () {
    // Public Documentation Spec
    Route::get('/docs', [DocsController::class, 'ui'])->name('api.v1.docs');
    Route::get('/openapi.json', [DocsController::class, 'schema'])->name('api.v1.openapi');

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

        // Google OAuth 2.0
        Route::get('/google/redirect', [GoogleAuthController::class, 'redirect'])->name('api.v1.auth.google.redirect');
        Route::match(['get', 'post'], '/google/callback', [GoogleAuthController::class, 'callback'])->name('api.v1.auth.google.callback');
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

        // Recurring Transactions & Scheduled Posting
        Route::apiResource('recurring-transactions', \App\Http\Controllers\Api\V1\Transactions\RecurringTransactionController::class)
            ->names('api.v1.recurring_transactions');

        // Financial Goals & Savings Milestones
        Route::apiResource('goals', \App\Http\Controllers\Api\V1\Goals\GoalController::class)->names('api.v1.goals');
        Route::post('/goals/{goal}/contribute', [\App\Http\Controllers\Api\V1\Goals\GoalController::class, 'contribute'])->name('api.v1.goals.contribute');

        // Cash Flow Analytics
        Route::get('/analytics/cash-flow', [AnalyticsController::class, 'cashFlow'])->name('api.v1.analytics.cash_flow');

        // Financial Statements & Export Engine
        Route::prefix('reports')->group(function () {
            Route::get('/transactions/csv', [ExportController::class, 'exportCsv'])->name('api.v1.reports.transactions.csv');
            Route::get('/transactions/json', [ExportController::class, 'exportJson'])->name('api.v1.reports.transactions.json');
        });

        // Bank Statement Reconciliation & CSV Ingestion Engine
        Route::prefix('imports')->group(function () {
            Route::post('/statements', [\App\Http\Controllers\Api\V1\Imports\BankStatementImportController::class, 'import'])->name('api.v1.imports.statements');
            Route::get('/statements/history', [\App\Http\Controllers\Api\V1\Imports\BankStatementImportController::class, 'history'])->name('api.v1.imports.statements.history');
        });
    });
});
