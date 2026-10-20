<?php

namespace Tests\Feature\Markets;

use App\Services\Markets\StockMarketService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockMarketTest extends TestCase
{
    /**
     * Test fetching market benchmark indices.
     */
    public function test_can_fetch_market_indices(): void
    {
        $response = $this->getJson('/api/v1/markets/indices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'symbol',
                        'name',
                        'region',
                        'currency',
                        'price',
                        'change',
                        'change_percent',
                        'is_positive',
                        'market_status',
                    ],
                ],
            ])
            ->assertJson(['status' => 'success']);
    }

    /**
     * Test fetching quote for a Bursa Malaysia stock with 4-digit code (e.g. 1155).
     */
    public function test_can_fetch_quote_for_bursa_stock_using_4digit_code(): void
    {
        $response = $this->getJson('/api/v1/markets/quote/1155');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'symbol',
                    'ticker_code',
                    'name',
                    'currency',
                    'exchange',
                    'current_price',
                    'change',
                    'change_percent',
                    'is_positive',
                    'previous_close',
                    'day_high',
                    'day_low',
                    'fifty_two_week_high',
                    'fifty_two_week_low',
                    'market_status' => [
                        'market',
                        'timezone',
                        'local_time',
                        'is_open',
                        'session',
                        'trading_hours',
                    ],
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'symbol' => '1155.KL',
                    'ticker_code' => '1155',
                    'currency' => 'MYR',
                ],
            ]);
    }

    /**
     * Test fetching quote for a US stock (e.g. AAPL).
     */
    public function test_can_fetch_quote_for_us_stock(): void
    {
        $response = $this->getJson('/api/v1/markets/quote/AAPL');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'symbol',
                    'ticker_code',
                    'name',
                    'currency',
                    'exchange',
                    'current_price',
                    'change',
                    'change_percent',
                    'is_positive',
                    'market_status',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'symbol' => 'AAPL',
                    'currency' => 'USD',
                ],
            ]);
    }

    /**
     * Test fetching historical time series chart points.
     */
    public function test_can_fetch_historical_chart_points(): void
    {
        $response = $this->getJson('/api/v1/markets/chart/1155.KL?range=5d');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'symbol',
                    'name',
                    'currency',
                    'range',
                    'points_count',
                    'points',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'symbol' => '1155.KL',
                    'range' => '5d',
                ],
            ]);
    }

    /**
     * Test fetching curated movers for Bursa Malaysia and US markets.
     */
    public function test_can_fetch_curated_movers_for_bursa_and_us(): void
    {
        // Bursa movers
        $bursaResponse = $this->getJson('/api/v1/markets/movers?market=bursa');
        $bursaResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'market',
                'count',
                'data' => [
                    '*' => [
                        'symbol',
                        'code',
                        'name',
                        'category',
                        'price',
                        'change',
                        'change_percent',
                        'is_positive',
                    ],
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'market' => 'bursa',
            ]);

        // US movers
        $usResponse = $this->getJson('/api/v1/markets/movers?market=us');
        $usResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'market' => 'us',
            ]);
    }

    /**
     * Test stock search by name and code.
     */
    public function test_can_search_stocks_by_name_and_code(): void
    {
        // Search by Bursa 4-digit code
        $codeSearch = $this->getJson('/api/v1/markets/search?q=1155');
        $codeSearch->assertStatus(200)
            ->assertJsonFragment(['symbol' => '1155.KL', 'code' => '1155']);

        // Search by company name
        $nameSearch = $this->getJson('/api/v1/markets/search?q=Maybank');
        $nameSearch->assertStatus(200)
            ->assertJsonFragment(['symbol' => '1155.KL', 'code' => '1155']);

        // Search US stock
        $usSearch = $this->getJson('/api/v1/markets/search?q=Apple');
        $usSearch->assertStatus(200)
            ->assertJsonFragment(['symbol' => 'AAPL']);
    }

    /**
     * Test symbol normalization in StockMarketService.
     */
    public function test_symbol_normalization(): void
    {
        $service = app(StockMarketService::class);

        $this->assertEquals('1155.KL', $service->normalizeSymbol('1155'));
        $this->assertEquals('1023.KL', $service->normalizeSymbol('1023kl'));
        $this->assertEquals('1023.KL', $service->normalizeSymbol('1023.KL'));
        $this->assertEquals('AAPL', $service->normalizeSymbol('aapl'));
        $this->assertEquals('^KLSE', $service->normalizeSymbol('^klse'));
    }

    /**
     * Test fallback quote returns safe response when external API fails.
     */
    public function test_quote_handles_fallback_gracefully(): void
    {
        Http::fake([
            'https://query1.finance.yahoo.com/*' => Http::response(null, 500),
        ]);

        $response = $this->getJson('/api/v1/markets/quote/9999');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'symbol' => '9999.KL',
                    'currency' => 'MYR',
                ],
            ]);
    }
}
