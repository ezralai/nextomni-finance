<?php

namespace App\Repositories\Eloquent;

use App\Models\ExchangeRate;
use App\Repositories\Contracts\ExchangeRateRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function findLatestRate(string $baseCurrency, string $targetCurrency, ?string $date = null): ?ExchangeRate
    {
        $effectiveDate = $date ?? Carbon::now()->toDateString();

        return ExchangeRate::where('base_currency', strtoupper($baseCurrency))
            ->where('target_currency', strtoupper($targetCurrency))
            ->whereDate('effective_date', '<=', $effectiveDate)
            ->orderBy('effective_date', 'desc')
            ->first();
    }

    public function getLatestRatesForBase(string $baseCurrency, ?string $date = null): Collection
    {
        $effectiveDate = $date ?? Carbon::now()->toDateString();

        return ExchangeRate::where('base_currency', strtoupper($baseCurrency))
            ->whereDate('effective_date', '<=', $effectiveDate)
            ->orderBy('effective_date', 'desc')
            ->get()
            ->unique('target_currency');
    }

    public function updateOrCreateRate(array $attributes, array $values): ExchangeRate
    {
        return ExchangeRate::updateOrCreate($attributes, $values);
    }
}
