<?php

namespace App\Http\Controllers\Api\V1\Markets;

use App\Http\Controllers\Controller;
use App\Services\Markets\StockMarketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMarketController extends Controller
{
    public function __construct(
        protected StockMarketService $stockMarketService
    ) {}

    /**
     * Display major market benchmark indices (KLCI, S&P 500, NASDAQ, Dow Jones).
     */
    public function indices(): JsonResponse
    {
        $indices = $this->stockMarketService->getIndices();

        return response()->json([
            'status' => 'success',
            'data' => $indices,
        ]);
    }

    /**
     * Display real-time quote and metadata for a given stock symbol.
     */
    public function quote(string $symbol): JsonResponse
    {
        $quote = $this->stockMarketService->getQuote($symbol);

        return response()->json([
            'status' => 'success',
            'data' => $quote,
        ]);
    }

    /**
     * Display historical time-series points for interactive charts.
     */
    public function chart(Request $request, string $symbol): JsonResponse
    {
        $range = $request->query('range', '1d');
        $chart = $this->stockMarketService->getChart($symbol, $range);

        return response()->json([
            'status' => 'success',
            'data' => $chart,
        ]);
    }

    /**
     * Display curated movers and top bluechip equities for Malaysia or US markets.
     */
    public function movers(Request $request): JsonResponse
    {
        $market = $request->query('market', 'bursa');
        $movers = $this->stockMarketService->getMovers($market);

        return response()->json([
            'status' => 'success',
            'market' => $market,
            'count' => count($movers),
            'data' => $movers,
        ]);
    }

    /**
     * Autocomplete and search stock symbols by ticker code or company name.
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $results = $this->stockMarketService->search($query);

        return response()->json([
            'status' => 'success',
            'query' => $query,
            'count' => count($results),
            'data' => $results,
        ]);
    }
}
