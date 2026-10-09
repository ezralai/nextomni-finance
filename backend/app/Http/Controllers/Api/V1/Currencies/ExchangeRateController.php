<?php

namespace App\Http\Controllers\Api\V1\Currencies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Currencies\ConvertCurrencyRequest;
use App\Services\Currencies\CurrencyConversionService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function __construct(
        protected CurrencyConversionService $currencyConversionService
    ) {}

    /**
     * Display current exchange rates for a given base currency.
     */
    public function index(Request $request): JsonResponse
    {
        $base = $request->query('base', $request->user()?->base_currency ?? 'MYR');
        $rates = $this->currencyConversionService->getRatesForBase($base);

        return response()->json([
            'base_currency' => strtoupper($base),
            'rates' => $rates,
        ]);
    }

    /**
     * Convert an arbitrary monetary amount between currencies.
     */
    public function convert(ConvertCurrencyRequest $request): JsonResponse
    {
        $amount = $request->input('amount');
        $from = strtoupper($request->input('from_currency'));
        $to = strtoupper($request->input('to_currency'));

        $money = Money::of($amount, $from);
        $rate = $this->currencyConversionService->getRate($from, $to);
        $converted = $this->currencyConversionService->convert($money, $to);

        return response()->json([
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'original_amount' => $money->toDecimal(),
            'original_formatted' => $money->format(),
            'converted_amount' => $converted->toDecimal(),
            'converted_formatted' => $converted->format(),
        ]);
    }
}
