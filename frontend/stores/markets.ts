import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface MarketIndex {
  symbol: string
  name: string
  region: string
  currency: string
  price: number
  change: number
  change_percent: number
  is_positive: boolean
  market_status: {
    market: string
    timezone: string
    local_time: string
    is_open: boolean
    session: string
    trading_hours: string
  }
}

export interface StockQuote {
  symbol: string
  ticker_code: string
  name: string
  currency: string
  exchange: string
  current_price: number
  change: number
  change_percent: number
  is_positive: boolean
  previous_close: number
  day_high: number
  day_low: number
  fifty_two_week_high: number
  fifty_two_week_low: number
  market_status: {
    market: string
    timezone: string
    local_time: string
    is_open: boolean
    session: string
    trading_hours: string
  }
  last_updated: string
  is_fallback?: boolean
}

export interface ChartPoint {
  timestamp: number
  datetime: string
  price: number
  volume: number
}

export interface ChartData {
  symbol: string
  name: string
  currency: string
  range: string
  points_count: number
  points: ChartPoint[]
}

export interface StockMover {
  symbol: string
  code: string
  name: string
  category: string
  price: number
  change: number
  change_percent: number
  is_positive: boolean
  currency: string
  previous_close: number
}

export interface SearchResult {
  symbol: string
  code: string
  name: string
  category: string
  market: string
  currency: string
}

export interface StockHolding {
  id: string
  symbol: string
  name: string
  shares: number
  avg_buy_price: number
  currency: string
  market: 'bursa' | 'us'
  created_at: string
}

export const useMarketStore = defineStore('markets', () => {
  const { request } = useApi()

  // State
  const indices = ref<MarketIndex[]>([])
  const selectedQuote = ref<StockQuote | null>(null)
  const chartData = ref<ChartData | null>(null)
  const chartRange = ref<'1d' | '5d' | '1m' | '6m' | '1y'>('1d')
  const movers = ref<StockMover[]>([])
  const activeMarket = ref<'bursa' | 'us'>('bursa')
  const searchResults = ref<SearchResult[]>([])
  const watchlist = ref<string[]>(['1155.KL', 'AAPL', '1023.KL', 'NVDA'])
  const holdings = ref<StockHolding[]>([
    {
      id: 'h-1',
      symbol: '1155.KL',
      name: 'Malayan Banking Berhad (Maybank)',
      shares: 1000,
      avg_buy_price: 9.85,
      currency: 'MYR',
      market: 'bursa',
      created_at: new Date().toISOString(),
    },
    {
      id: 'h-2',
      symbol: 'AAPL',
      name: 'Apple Inc.',
      shares: 15,
      avg_buy_price: 185.0,
      currency: 'USD',
      market: 'us',
      created_at: new Date().toISOString(),
    },
  ])

  const isLoadingIndices = ref(false)
  const isLoadingQuote = ref(false)
  const isLoadingChart = ref(false)
  const isLoadingMovers = ref(false)
  const isSearching = ref(false)
  const error = ref<string | null>(null)

  // Initialize Watchlist and Holdings from LocalStorage
  const initWatchlist = () => {
    if (typeof window !== 'undefined') {
      try {
        const stored = localStorage.getItem('nextomni_market_watchlist')
        if (stored) {
          const parsed = JSON.parse(stored)
          if (Array.isArray(parsed) && parsed.length > 0) {
            watchlist.value = parsed
          }
        }
      } catch {
        // Fallback to default
      }
    }
  }

  const initHoldings = () => {
    if (typeof window !== 'undefined') {
      try {
        const stored = localStorage.getItem('nextomni_stock_holdings')
        if (stored) {
          const parsed = JSON.parse(stored)
          if (Array.isArray(parsed)) {
            holdings.value = parsed
          }
        }
      } catch {
        // Fallback to default
      }
    }
  }

  // Add Holding
  const addHolding = (entry: Omit<StockHolding, 'id' | 'created_at'>) => {
    const newHolding: StockHolding = {
      ...entry,
      id: `h-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
      created_at: new Date().toISOString(),
    }
    holdings.value.push(newHolding)
    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('nextomni_stock_holdings', JSON.stringify(holdings.value))
      } catch {
        // Ignore
      }
    }
    return newHolding
  }

  // Delete Holding
  const deleteHolding = (id: string) => {
    const idx = holdings.value.findIndex((h) => h.id === id)
    if (idx >= 0) {
      holdings.value.splice(idx, 1)
      if (typeof window !== 'undefined') {
        try {
          localStorage.setItem('nextomni_stock_holdings', JSON.stringify(holdings.value))
        } catch {
          // Ignore
        }
      }
    }
  }

  // Toggle Stock in Watchlist
  const toggleWatchlist = (symbol: string) => {
    const sym = symbol.toUpperCase().trim()
    const idx = watchlist.value.indexOf(sym)
    if (idx >= 0) {
      watchlist.value.splice(idx, 1)
    } else {
      watchlist.value.push(sym)
    }

    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('nextomni_market_watchlist', JSON.stringify(watchlist.value))
      } catch {
        // Ignore localStorage restrictions
      }
    }
  }

  const isWatchlisted = (symbol: string): boolean => {
    return watchlist.value.includes(symbol.toUpperCase().trim())
  }

  // Fetch Major Market Indices
  const fetchIndices = async () => {
    isLoadingIndices.value = true
    try {
      const res = await request<{ status: string; data: MarketIndex[] }>('/markets/indices')
      if (res?.data) {
        indices.value = res.data
      }
    } catch (err: any) {
      error.value = err?.message || 'Failed to fetch market indices.'
    } finally {
      isLoadingIndices.value = false
    }
  }

  // Fetch Single Stock Quote
  const fetchQuote = async (symbol: string) => {
    isLoadingQuote.value = true
    error.value = null
    try {
      const res = await request<{ status: string; data: StockQuote }>(`/markets/quote/${encodeURIComponent(symbol)}`)
      if (res?.data) {
        selectedQuote.value = res.data
        return res.data
      }
    } catch (err: any) {
      error.value = err?.message || `Failed to fetch quote for ${symbol}`
    } finally {
      isLoadingQuote.value = false
    }
    return null
  }

  // Fetch Time-Series Points for Chart
  const fetchChart = async (symbol: string, range: '1d' | '5d' | '1m' | '6m' | '1y' = '1d') => {
    isLoadingChart.value = true
    chartRange.value = range
    try {
      const res = await request<{ status: string; data: ChartData }>(
        `/markets/chart/${encodeURIComponent(symbol)}?range=${range}`
      )
      if (res?.data) {
        chartData.value = res.data
        return res.data
      }
    } catch (err: any) {
      error.value = err?.message || `Failed to fetch chart for ${symbol}`
    } finally {
      isLoadingChart.value = false
    }
    return null
  }

  // Fetch Curated Market Movers / Bluechips
  const fetchMovers = async (market: 'bursa' | 'us' = 'bursa') => {
    isLoadingMovers.value = true
    activeMarket.value = market
    try {
      const res = await request<{ status: string; market: string; count: number; data: StockMover[] }>(
        `/markets/movers?market=${market}`
      )
      if (res?.data) {
        movers.value = res.data
        return res.data
      }
    } catch (err: any) {
      error.value = err?.message || `Failed to fetch movers for ${market}`
    } finally {
      isLoadingMovers.value = false
    }
    return []
  }

  // Search Stocks by Name or Ticker
  const search = async (query: string) => {
    const q = query.trim()
    if (!q) {
      searchResults.value = []
      return []
    }

    isSearching.value = true
    try {
      const res = await request<{ status: string; query: string; count: number; data: SearchResult[] }>(
        `/markets/search?q=${encodeURIComponent(q)}`
      )
      if (res?.data) {
        searchResults.value = res.data
        return res.data
      }
    } catch (err: any) {
      error.value = err?.message || 'Search failed.'
    } finally {
      isSearching.value = false
    }
    return []
  }

  // Select quote and trigger chart load
  const selectQuote = async (symbol: string) => {
    const quote = await fetchQuote(symbol)
    if (quote) {
      await fetchChart(quote.symbol, chartRange.value)
    }
    return quote
  }

  return {
    indices,
    selectedQuote,
    chartData,
    chartRange,
    movers,
    activeMarket,
    searchResults,
    watchlist,
    holdings,
    isLoadingIndices,
    isLoadingQuote,
    isLoadingChart,
    isLoadingMovers,
    isSearching,
    error,
    initWatchlist,
    initHoldings,
    addHolding,
    deleteHolding,
    toggleWatchlist,
    isWatchlisted,
    fetchIndices,
    fetchQuote,
    fetchChart,
    fetchMovers,
    search,
    selectQuote,
  }
})
