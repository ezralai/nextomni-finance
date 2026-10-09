<?php

use App\Http\Controllers\Api\V1\Accounts\AccountController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Institutions\InstitutionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Health Check
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Public Financial Institutions Directory
    Route::get('/institutions', [InstitutionController::class, 'index'])->name('api.v1.institutions.index');

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
    });
});
