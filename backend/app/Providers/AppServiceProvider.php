<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\AccountRepositoryInterface::class,
            \App\Repositories\Eloquent\AccountRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\TransactionRepositoryInterface::class,
            \App\Repositories\Eloquent\TransactionRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\BudgetRepositoryInterface::class,
            \App\Repositories\Eloquent\BudgetRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
