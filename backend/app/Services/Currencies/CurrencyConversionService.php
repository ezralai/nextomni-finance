<?php

namespace App\Services\Currencies;

use App\Repositories\Contracts\ExchangeRateRepositoryInterface;
use App\Support\Money;
use Carbon\Carbon;
use InvalidArgumentException;

class CurrencyConversionService
{
    public const FX_SCALE = 6;

    public function __construct(
        protected ExchangeRateRepositoryInterface $exchangeRateRepository
    ) {}

    /**
     * Resolve exchange rate between any two currencies with direct, inverse, or triangulated lookup.
     */
    public function getRate(string $fromCurrency, string $toCurrency, ?Carbon $date = null): string
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === $to) {
            return '1.000000';
        }

        $dateString = $date ? $date->toDateString() : Carbon::now()->toDateString();

        // 1. Direct rate lookup
        $direct = $this->exchangeRateRepository->findLatestRate($from, $to, $dateString);
        if ($direct) {
            return $direct->rate;
        }

        // 2. Inverse rate calculation
        $inverse = $this->exchangeRateRepository->findLatestRate($to, $from, $dateString);
        if ($inverse && bccomp($inverse->rate, '0', self::FX_SCALE) > 0) {
            return bcdiv('1', $inverse->rate, self::FX_SCALE);
        }

        // 3. Triangulation via USD anchor
        if ($from !== 'USD' && $to !== 'USD') {
            $fromToUsd = $this->getRate($from, 'USD', $date);
            $usdToTarget = $this->getRate('USD', $to, $date);

            return bcmul($fromToUsd, $usdToTarget, self::FX_SCALE);
        }

        throw new InvalidArgumentException("Unable to resolve exchange rate for currency pair {$from}/{$to}.");
    }

    /**
     * Convert an arbitrary monetary value into target currency with BCMath arbitrary precision.
     */
    public function convert(Money $money, string $targetCurrency, ?Carbon $date = null): Money
    {
        $target = strtoupper(trim($targetCurrency));
        if ($money->getCurrency() === $target) {
            return $money;
        }

        $rate = $this->getRate($money->getCurrency(), $target, $date);
        $convertedAmount = bcmul($money->getAmount(), $rate, Money::DEFAULT_SCALE);

        return Money::of($convertedAmount, $target);
    }

    /**
     * Retrieve all active exchange rates for a specified base currency.
     */
    public function getRatesForBase(string $baseCurrency, ?Carbon $date = null): array
    {
        $base = strtoupper(trim($baseCurrency));
        $dateString = $date ? $date->toDateString() : Carbon::now()->toDateString();

        $rates = $this->exchangeRateRepository->getLatestRatesForBase($base, $dateString);

        $results = [];
        foreach ($rates as $r) {
            $results[] = [
                'base_currency' => $r->base_currency,
                'target_currency' => $r->target_currency,
                'rate' => $r->rate,
                'effective_date' => $r->effective_date->toDateString(),
            ];
        }

        return $results;
    }
}
