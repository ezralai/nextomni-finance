<?php

namespace App\Services\Markets;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockMarketService
{
    protected const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Dictionary of prominent Bursa Malaysia companies mapped by 4-digit code.
     */
    protected const BURSA_DIRECTORY = [
        '1155' => ['symbol' => '1155.KL', 'code' => '1155', 'name' => 'Malayan Banking Berhad (Maybank)', 'category' => 'Financial Services'],
        '1023' => ['symbol' => '1023.KL', 'code' => '1023', 'name' => 'CIMB Group Holdings Berhad', 'category' => 'Financial Services'],
        '1295' => ['symbol' => '1295.KL', 'code' => '1295', 'name' => 'Public Bank Berhad', 'category' => 'Financial Services'],
        '5347' => ['symbol' => '5347.KL', 'code' => '5347', 'name' => 'Tenaga Nasional Berhad (TNB)', 'category' => 'Utilities'],
        '5225' => ['symbol' => '5225.KL', 'code' => '5225', 'name' => 'IHH Healthcare Berhad', 'category' => 'Healthcare'],
        '8869' => ['symbol' => '8869.KL', 'code' => '8869', 'name' => 'Press Metal Aluminium Holdings', 'category' => 'Industrial Products'],
        '5819' => ['symbol' => '5819.KL', 'code' => '5819', 'name' => 'Hong Leong Bank Berhad', 'category' => 'Financial Services'],
        '5285' => ['symbol' => '5285.KL', 'code' => '5285', 'name' => 'SD Guthrie Berhad', 'category' => 'Plantation'],
        '6742' => ['symbol' => '6742.KL', 'code' => '6742', 'name' => 'YTL Power International Berhad', 'category' => 'Utilities'],
        '5183' => ['symbol' => '5183.KL', 'code' => '5183', 'name' => 'PETRONAS Chemicals Group Berhad', 'category' => 'Petrochemicals'],
        '6033' => ['symbol' => '6033.KL', 'code' => '6033', 'name' => 'PETRONAS Gas Berhad', 'category' => 'Utilities'],
        '5681' => ['symbol' => '5681.KL', 'code' => '5681', 'name' => 'PETRONAS Dagangan Berhad', 'category' => 'Energy & Retail'],
        '4707' => ['symbol' => '4707.KL', 'code' => '4707', 'name' => 'Nestlé (Malaysia) Berhad', 'category' => 'Consumer Products'],
        '6947' => ['symbol' => '6947.KL', 'code' => '6947', 'name' => 'CelcomDigi Berhad', 'category' => 'Telecommunications'],
        '6888' => ['symbol' => '6888.KL', 'code' => '6888', 'name' => 'Axiata Group Berhad', 'category' => 'Telecommunications'],
        '5099' => ['symbol' => '5099.KL', 'code' => '5099', 'name' => 'Capital A Berhad (AirAsia)', 'category' => 'Aviation & Travel'],
        '7113' => ['symbol' => '7113.KL', 'code' => '7113', 'name' => 'Top Glove Corporation Bhd', 'category' => 'Healthcare & Gloves'],
        '3182' => ['symbol' => '3182.KL', 'code' => '3182', 'name' => 'Genting Berhad', 'category' => 'Consumer Services'],
        '4715' => ['symbol' => '4715.KL', 'code' => '4715', 'name' => 'Genting Malaysia Berhad', 'category' => 'Hospitality & Leisure'],
        '1961' => ['symbol' => '1961.KL', 'code' => '1961', 'name' => 'IOI Corporation Berhad', 'category' => 'Plantation'],
    ];

    /**
     * Dictionary of major US companies.
     */
    protected const US_DIRECTORY = [
        'AAPL' => ['symbol' => 'AAPL', 'code' => 'AAPL', 'name' => 'Apple Inc.', 'category' => 'Technology'],
        'MSFT' => ['symbol' => 'MSFT', 'code' => 'MSFT', 'name' => 'Microsoft Corporation', 'category' => 'Technology & Cloud'],
        'NVDA' => ['symbol' => 'NVDA', 'code' => 'NVDA', 'name' => 'NVIDIA Corporation', 'category' => 'Semiconductors & AI'],
        'GOOGL' => ['symbol' => 'GOOGL', 'code' => 'GOOGL', 'name' => 'Alphabet Inc. (Google)', 'category' => 'Technology & Advertising'],
        'AMZN' => ['symbol' => 'AMZN', 'code' => 'AMZN', 'name' => 'Amazon.com Inc.', 'category' => 'E-Commerce & Cloud'],
        'META' => ['symbol' => 'META', 'code' => 'META', 'name' => 'Meta Platforms Inc.', 'category' => 'Social Media & VR'],
        'TSLA' => ['symbol' => 'TSLA', 'code' => 'TSLA', 'name' => 'Tesla Inc.', 'category' => 'Electric Vehicles & Energy'],
        'BRK-B' => ['symbol' => 'BRK-B', 'code' => 'BRK-B', 'name' => 'Berkshire Hathaway Inc.', 'category' => 'Conglomerate & Insurance'],
        'JPM' => ['symbol' => 'JPM', 'code' => 'JPM', 'name' => 'JPMorgan Chase & Co.', 'category' => 'Banking & Financials'],
        'AVGO' => ['symbol' => 'AVGO', 'code' => 'AVGO', 'name' => 'Broadcom Inc.', 'category' => 'Semiconductors'],
        'V' => ['symbol' => 'V', 'code' => 'V', 'name' => 'Visa Inc.', 'category' => 'Payment Services'],
        'WMT' => ['symbol' => 'WMT', 'code' => 'WMT', 'name' => 'Walmart Inc.', 'category' => 'Retail'],
        'SPY' => ['symbol' => 'SPY', 'code' => 'SPY', 'name' => 'SPDR S&P 500 ETF Trust', 'category' => 'Index ETF'],
        'QQQ' => ['symbol' => 'QQQ', 'code' => 'QQQ', 'name' => 'Invesco QQQ Trust (Nasdaq 100)', 'category' => 'Tech Index ETF'],
    ];

    /**
     * Normalize ticker string (e.g. "1155" -> "1155.KL", "aapl" -> "AAPL").
     */
    public function normalizeSymbol(string $symbol): string
    {
        $sym = strtoupper(trim($symbol));

        // If purely 4 digits, it is a Bursa Malaysia ticker code
        if (preg_match('/^\d{4}$/', $sym)) {
            return "{$sym}.KL";
        }

        // Check if Bursa code without dot (e.g. "1155KL")
        if (preg_match('/^(\d{4})KL$/', $sym, $m)) {
            return "{$m[1]}.KL";
        }

        return $sym;
    }

    /**
     * Determine if market is currently open for trading.
     */
    public function getMarketStatus(string $exchangeOrSymbol): array
    {
        $isBursa = str_ends_with(strtoupper($exchangeOrSymbol), '.KL')
            || strtoupper($exchangeOrSymbol) === '^KLSE'
            || strtoupper($exchangeOrSymbol) === 'BURSA'
            || strtoupper($exchangeOrSymbol) === 'KLS';

        if ($isBursa) {
            $nowMyt = Carbon::now('Asia/Kuala_Lumpur');
            $dayOfWeek = $nowMyt->dayOfWeek; // 0=Sun, 6=Sat
            $time = $nowMyt->format('H:i');

            $isWeekday = $dayOfWeek >= 1 && $dayOfWeek <= 5;
            $isMorningSession = $time >= '09:00' && $time <= '12:30';
            $isAfternoonSession = $time >= '14:30' && $time <= '17:00';

            $isOpen = $isWeekday && ($isMorningSession || $isAfternoonSession);

            return [
                'market' => 'Bursa Malaysia',
                'timezone' => 'MYT (UTC+8)',
                'local_time' => $nowMyt->format('Y-m-d H:i:s'),
                'is_open' => $isOpen,
                'session' => $isOpen ? ($isMorningSession ? 'Morning Session' : 'Afternoon Session') : 'Market Closed',
                'trading_hours' => 'Mon-Fri 09:00-12:30, 14:30-17:00 MYT',
            ];
        }

        // US Market (NYSE / NASDAQ)
        $nowEst = Carbon::now('America/New_York');
        $dayOfWeek = $nowEst->dayOfWeek;
        $time = $nowEst->format('H:i');

        $isWeekday = $dayOfWeek >= 1 && $dayOfWeek <= 5;
        $isRegular = $time >= '09:30' && $time <= '16:00';
        $isPreMarket = $time >= '04:00' && $time < '09:30';
        $isAfterHours = $time > '16:00' && $time <= '20:00';

        $isOpen = $isWeekday && $isRegular;

        return [
            'market' => 'US Markets (NYSE/NASDAQ)',
            'timezone' => 'EST/EDT (New York)',
            'local_time' => $nowEst->format('Y-m-d H:i:s'),
            'is_open' => $isOpen,
            'session' => $isOpen ? 'Regular Trading' : ($isPreMarket ? 'Pre-Market' : ($isAfterHours ? 'After-Hours' : 'Market Closed')),
            'trading_hours' => 'Mon-Fri 09:30-16:00 EST',
        ];
    }

    /**
     * Resolve company display name.
     */
    protected function resolveCompanyName(string $symbol, ?string $apiName = null): string
    {
        $cleanSymbol = strtoupper(trim($symbol));
        $bursaCode = preg_replace('/\.KL$/', '', $cleanSymbol);

        if (isset(self::BURSA_DIRECTORY[$bursaCode])) {
            return self::BURSA_DIRECTORY[$bursaCode]['name'];
        }

        if (isset(self::US_DIRECTORY[$cleanSymbol])) {
            return self::US_DIRECTORY[$cleanSymbol]['name'];
        }

        if ($cleanSymbol === '^KLSE') return 'FTSE Bursa Malaysia KLCI';
        if ($cleanSymbol === '^GSPC') return 'S&P 500 Index';
        if ($cleanSymbol === '^IXIC') return 'NASDAQ Composite';
        if ($cleanSymbol === '^DJI') return 'Dow Jones Industrial Average';

        return $apiName ?: $cleanSymbol;
    }

    /**
     * Fetch key market indices for Malaysia and US.
     */
    public function getIndices(): array
    {
        return Cache::remember('market_indices_summary', 20, function () {
            $indexSymbols = [
                '^KLSE' => ['name' => 'FBM KLCI', 'region' => 'Malaysia', 'currency' => 'MYR'],
                '^GSPC' => ['name' => 'S&P 500', 'region' => 'United States', 'currency' => 'USD'],
                '^IXIC' => ['name' => 'NASDAQ', 'region' => 'United States', 'currency' => 'USD'],
                '^DJI' => ['name' => 'Dow Jones', 'region' => 'United States', 'currency' => 'USD'],
            ];

            $results = [];

            foreach ($indexSymbols as $symbol => $info) {
                try {
                    $quote = $this->getQuote($symbol, false);
                    $results[] = array_merge($info, [
                        'symbol' => $symbol,
                        'price' => $quote['current_price'],
                        'change' => $quote['change'],
                        'change_percent' => $quote['change_percent'],
                        'is_positive' => $quote['change'] >= 0,
                        'market_status' => $quote['market_status'],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Failed to fetch index quote for {$symbol}: " . $e->getMessage());
                }
            }

            return $results;
        });
    }

    /**
     * Fetch a real-time quote for a specific ticker.
     */
    public function getQuote(string $symbol, bool $useCache = true): array
    {
        $normalized = $this->normalizeSymbol($symbol);
        $cacheKey = "market_quote_{$normalized}";

        if ($useCache) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$normalized}?interval=1d&range=5d";

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(5)
                ->get($url);

            if (!$response->successful()) {
                throw new \RuntimeException("External market API responded with HTTP {$response->status()}");
            }

            $data = $response->json();
            $result = $data['chart']['result'][0] ?? null;

            if (!$result) {
                throw new \RuntimeException("No market chart data available for ticker {$symbol}");
            }

            $meta = $result['meta'];
            $currentPrice = (float) ($meta['regularMarketPrice'] ?? 0.0);
            $prevClose = (float) ($meta['chartPreviousClose'] ?? $currentPrice);
            $change = round($currentPrice - $prevClose, 4);
            $changePercent = $prevClose > 0 ? round(($change / $prevClose) * 100, 2) : 0.0;

            $status = $this->getMarketStatus($normalized);
            $name = $this->resolveCompanyName($normalized, $meta['shortName'] ?? null);

            $quote = [
                'symbol' => $normalized,
                'ticker_code' => preg_replace('/\.KL$/', '', $normalized),
                'name' => $name,
                'currency' => $meta['currency'] ?? (str_ends_with($normalized, '.KL') ? 'MYR' : 'USD'),
                'exchange' => $meta['exchangeName'] ?? (str_ends_with($normalized, '.KL') ? 'KLS' : 'US'),
                'current_price' => $currentPrice,
                'change' => $change,
                'change_percent' => $changePercent,
                'is_positive' => $change >= 0,
                'previous_close' => $prevClose,
                'day_high' => (float) ($meta['regularMarketDayHigh'] ?? $currentPrice),
                'day_low' => (float) ($meta['regularMarketDayLow'] ?? $currentPrice),
                'fifty_two_week_high' => (float) ($meta['fiftyTwoWeekHigh'] ?? $currentPrice),
                'fifty_two_week_low' => (float) ($meta['fiftyTwoWeekLow'] ?? $currentPrice),
                'market_status' => $status,
                'last_updated' => Carbon::now()->toIso8601String(),
            ];

            // Cache dynamic TTL: 20 seconds during open hours, 180 seconds during closed hours
            $ttl = $status['is_open'] ? 20 : 180;
            Cache::put($cacheKey, $quote, $ttl);

            return $quote;
        } catch (\Throwable $e) {
            Log::error("StockMarketService::getQuote error for {$symbol}: " . $e->getMessage());

            // If stale cache exists, return it
            $stale = Cache::get($cacheKey);
            if ($stale) {
                return $stale;
            }

            // Return resilient fallback
            return $this->getResilientFallbackQuote($normalized);
        }
    }

    /**
     * Fetch historical time series points for interactive charts.
     */
    public function getChart(string $symbol, string $range = '1d'): array
    {
        $normalized = $this->normalizeSymbol($symbol);
        $range = strtolower(trim($range));

        $validRanges = [
            '1d' => ['interval' => '5m', 'range' => '1d', 'ttl' => 30],
            '5d' => ['interval' => '15m', 'range' => '5d', 'ttl' => 120],
            '1m' => ['interval' => '1d', 'range' => '1mo', 'ttl' => 600],
            '6m' => ['interval' => '1d', 'range' => '6mo', 'ttl' => 1800],
            '1y' => ['interval' => '1wk', 'range' => '1y', 'ttl' => 3600],
        ];

        $config = $validRanges[$range] ?? $validRanges['1d'];
        $cacheKey = "market_chart_{$normalized}_{$range}";

        return Cache::remember($cacheKey, $config['ttl'], function () use ($normalized, $config, $range) {
            $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$normalized}?interval={$config['interval']}&range={$config['range']}";

            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(6)
                ->get($url);

            if (!$response->successful()) {
                return [
                    'symbol' => $normalized,
                    'name' => $this->resolveCompanyName($normalized),
                    'currency' => str_ends_with($normalized, '.KL') ? 'MYR' : 'USD',
                    'range' => $range,
                    'points_count' => 0,
                    'points' => [],
                ];
            }

            $data = $response->json();
            $result = $data['chart']['result'][0] ?? null;

            if (!$result || empty($result['timestamp'])) {
                return [
                    'symbol' => $normalized,
                    'name' => $this->resolveCompanyName($normalized),
                    'currency' => str_ends_with($normalized, '.KL') ? 'MYR' : 'USD',
                    'range' => $range,
                    'points_count' => 0,
                    'points' => [],
                ];
            }

            $timestamps = $result['timestamp'];
            $closes = $result['indicators']['quote'][0]['close'] ?? [];
            $volumes = $result['indicators']['quote'][0]['volume'] ?? [];

            $points = [];
            $count = count($timestamps);

            for ($i = 0; $i < $count; $i++) {
                $close = $closes[$i] ?? null;
                if ($close !== null) {
                    $ts = $timestamps[$i];
                    $points[] = [
                        'timestamp' => $ts,
                        'datetime' => Carbon::createFromTimestamp($ts)->toIso8601String(),
                        'price' => round((float) $close, 3),
                        'volume' => (int) ($volumes[$i] ?? 0),
                    ];
                }
            }

            return [
                'symbol' => $normalized,
                'name' => $this->resolveCompanyName($normalized),
                'currency' => $result['meta']['currency'] ?? (str_ends_with($normalized, '.KL') ? 'MYR' : 'USD'),
                'range' => $range,
                'points_count' => count($points),
                'points' => $points,
            ];
        });
    }

    /**
     * Get top curated movers / bluechips for a market.
     */
    public function getMovers(string $market = 'bursa'): array
    {
        $market = strtolower(trim($market));
        $cacheKey = "market_movers_{$market}";

        return Cache::remember($cacheKey, 60, function () use ($market) {
            $directory = $market === 'bursa' ? self::BURSA_DIRECTORY : self::US_DIRECTORY;
            $items = [];

            foreach ($directory as $code => $info) {
                try {
                    $quote = $this->getQuote($info['symbol']);
                    $items[] = array_merge($info, [
                        'code' => (string) $info['code'],
                        'price' => $quote['current_price'],
                        'change' => $quote['change'],
                        'change_percent' => $quote['change_percent'],
                        'is_positive' => $quote['change'] >= 0,
                        'currency' => $quote['currency'],
                        'previous_close' => $quote['previous_close'],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Failed to resolve mover for {$code}: " . $e->getMessage());
                }
            }

            return $items;
        });
    }

    /**
     * Search stocks across Bursa Malaysia and US markets by ticker or company name.
     */
    public function search(string $query): array
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return [];
        }

        $results = [];

        // Search Bursa Directory
        foreach (self::BURSA_DIRECTORY as $code => $info) {
            $codeStr = (string) $code;
            if (
                str_contains($codeStr, $q) ||
                str_contains(strtolower($info['symbol']), $q) ||
                str_contains(strtolower($info['name']), $q) ||
                str_contains(strtolower($info['category']), $q)
            ) {
                $results[] = [
                    'symbol' => $info['symbol'],
                    'code' => $codeStr,
                    'name' => $info['name'],
                    'category' => $info['category'],
                    'market' => 'Bursa Malaysia',
                    'currency' => 'MYR',
                ];
            }
        }

        // Search US Directory
        foreach (self::US_DIRECTORY as $code => $info) {
            $codeStr = (string) $code;
            if (
                str_contains(strtolower($codeStr), $q) ||
                str_contains(strtolower($info['name']), $q) ||
                str_contains(strtolower($info['category']), $q)
            ) {
                $results[] = [
                    'symbol' => $info['symbol'],
                    'code' => $codeStr,
                    'name' => $info['name'],
                    'category' => $info['category'],
                    'market' => 'US (NYSE/NASDAQ)',
                    'currency' => 'USD',
                ];
            }
        }

        return $results;
    }

    /**
     * Resilient fallback quote if external services are unreachable.
     */
    protected function getResilientFallbackQuote(string $symbol): array
    {
        $isBursa = str_ends_with($symbol, '.KL') || $symbol === '^KLSE';
        $fallbackPrice = $isBursa ? 10.00 : 150.00;

        return [
            'symbol' => $symbol,
            'ticker_code' => preg_replace('/\.KL$/', '', $symbol),
            'name' => $this->resolveCompanyName($symbol),
            'currency' => $isBursa ? 'MYR' : 'USD',
            'exchange' => $isBursa ? 'KLS' : 'US',
            'current_price' => $fallbackPrice,
            'change' => 0.0,
            'change_percent' => 0.0,
            'is_positive' => true,
            'previous_close' => $fallbackPrice,
            'day_high' => $fallbackPrice,
            'day_low' => $fallbackPrice,
            'fifty_two_week_high' => $fallbackPrice * 1.2,
            'fifty_two_week_low' => $fallbackPrice * 0.8,
            'market_status' => $this->getMarketStatus($symbol),
            'last_updated' => Carbon::now()->toIso8601String(),
            'is_fallback' => true,
        ];
    }
}
