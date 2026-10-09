<?php

namespace App\Repositories\Contracts;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Collection;

interface ExchangeRateRepositoryInterface
{
    /**
     * Find latest exchange rate for a given currency pair on or before effective date.
     */
    public function findLatestRate(string $baseCurrency, string $targetCurrency, ?string $date = null): ?ExchangeRate;

    /**
     * Retrieve all latest exchange rates for a given base currency.
     */
    public function getLatestRatesForBase(string $baseCurrency, ?string $date = null): Collection;

    /**
     * Store or update an exchange rate.
     */
    public function updateOrCreateRate(array $attributes, array $values): ExchangeRate;
}
