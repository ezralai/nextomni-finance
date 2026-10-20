<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import {
  TrendingUp,
  TrendingDown,
  Search,
  Star,
  StarOff,
  Activity,
  Globe,
  Clock,
  Layers,
  Sparkles,
  RefreshCw,
  ExternalLink,
  ChevronRight,
  Plus,
  Trash2,
  PieChart,
  DollarSign,
  Briefcase,
  X,
  CheckCircle2,
} from 'lucide-vue-next'
import { useMarketStore, type StockQuote, type SearchResult, type StockHolding } from '~/stores/markets'
import { useCurrencyStore } from '~/stores/currencies'
import { useToast } from '~/composables/useToast'
import StockPriceChart from '~/components/markets/StockPriceChart.vue'

const marketStore = useMarketStore()
const currencyStore = useCurrencyStore()
const toast = useToast()

const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const isSearchFocused = ref(false)

// Add Holding Modal State
const showAddHoldingModal = ref(false)
const holdingForm = ref({
  symbol: '',
  name: '',
  shares: 100,
  avg_buy_price: 10.0,
  currency: 'MYR',
  market: 'bursa' as 'bursa' | 'us',
})

// Conversion Rate between USD and MYR
const usdToMyrRate = computed(() => {
  const usdItem = currencyStore.rates.find((r) => r.target_currency === 'USD')
  if (usdItem && Number(usdItem.rate) > 0) {
    return 1 / Number(usdItem.rate)
  }
  return 4.36 // Fallback exchange rate
})

// Estimated foreign currency conversion for selected quote
const convertedPriceEstimate = computed(() => {
  if (!marketStore.selectedQuote) return null
  const quote = marketStore.selectedQuote
  if (quote.currency === 'USD') {
    const myrVal = quote.current_price * usdToMyrRate.value
    return {
      targetCurrency: 'MYR',
      amount: myrVal,
      label: `≈ RM ${formatNumber(myrVal)} (at 1 USD = ${formatNumber(usdToMyrRate.value, 3)} MYR)`,
    }
  } else if (quote.currency === 'MYR') {
    const usdVal = quote.current_price / usdToMyrRate.value
    return {
      targetCurrency: 'USD',
      amount: usdVal,
      label: `≈ $ ${formatNumber(usdVal, 2)} USD`,
    }
  }
  return null
})

// Portfolio Holdings Analytics
const enrichedHoldings = computed(() => {
  return marketStore.holdings.map((h) => {
    // Check if the holding matches current selected quote for live pricing
    let currentPrice = h.avg_buy_price
    if (marketStore.selectedQuote && marketStore.selectedQuote.symbol.toUpperCase() === h.symbol.toUpperCase()) {
      currentPrice = marketStore.selectedQuote.current_price
    } else {
      // Find from movers or default to buy price with slight simulated benchmark
      const mover = marketStore.movers.find((m) => m.symbol.toUpperCase() === h.symbol.toUpperCase())
      if (mover) {
        currentPrice = mover.price
      }
    }

    const totalCost = h.shares * h.avg_buy_price
    const currentValue = h.shares * currentPrice
    const pnl = currentValue - totalCost
    const pnlPercent = totalCost > 0 ? (pnl / totalCost) * 100 : 0

    return {
      ...h,
      currentPrice,
      totalCost,
      currentValue,
      pnl,
      pnlPercent,
      isPositive: pnl >= 0,
    }
  })
})

const portfolioSummary = computed(() => {
  let totalCostMyr = 0
  let totalValueMyr = 0

  enrichedHoldings.value.forEach((h) => {
    const multiplier = h.currency === 'USD' ? usdToMyrRate.value : 1
    totalCostMyr += h.totalCost * multiplier
    totalValueMyr += h.currentValue * multiplier
  })

  const totalPnlMyr = totalValueMyr - totalCostMyr
  const totalRoiPercent = totalCostMyr > 0 ? (totalPnlMyr / totalCostMyr) * 100 : 0

  return {
    totalCostMyr,
    totalValueMyr,
    totalPnlMyr,
    totalRoiPercent,
    isPositive: totalPnlMyr >= 0,
  }
})

onMounted(async () => {
  marketStore.initWatchlist()
  marketStore.initHoldings()
  await Promise.all([
    marketStore.fetchIndices(),
    marketStore.fetchMovers(marketStore.activeMarket),
    currencyStore.fetchRates('MYR'),
  ])

  // Select default stock if none selected
  if (!marketStore.selectedQuote) {
    const defaultSymbol = marketStore.activeMarket === 'bursa' ? '1155.KL' : 'AAPL'
    await loadStock(defaultSymbol)
  }
})

watch(
  () => marketStore.activeMarket,
  async (newMarket) => {
    await marketStore.fetchMovers(newMarket)
    const defaultSymbol = newMarket === 'bursa' ? '1155.KL' : 'AAPL'
    await loadStock(defaultSymbol)
  }
)

const handleSearchInput = async () => {
  if (searchQuery.value.trim().length > 0) {
    await marketStore.search(searchQuery.value)
  }
}

const loadStock = async (symbol: string) => {
  searchQuery.value = ''
  isSearchFocused.value = false
  await Promise.all([
    marketStore.fetchQuote(symbol),
    marketStore.fetchChart(symbol, marketStore.chartRange),
  ])
}

const handleRangeChange = async (range: '1d' | '5d' | '1m' | '6m' | '1y') => {
  if (marketStore.selectedQuote) {
    await marketStore.fetchChart(marketStore.selectedQuote.symbol, range)
  }
}

const openAddHoldingModal = (quote?: StockQuote | null) => {
  if (quote) {
    holdingForm.value = {
      symbol: quote.symbol,
      name: quote.name,
      shares: quote.currency === 'USD' ? 10 : 100,
      avg_buy_price: quote.current_price,
      currency: quote.currency,
      market: quote.symbol.includes('.KL') ? 'bursa' : 'us',
    }
  } else {
    holdingForm.value = {
      symbol: marketStore.activeMarket === 'bursa' ? '1155.KL' : 'AAPL',
      name: marketStore.activeMarket === 'bursa' ? 'Maybank' : 'Apple Inc.',
      shares: 100,
      avg_buy_price: 10.0,
      currency: marketStore.activeMarket === 'bursa' ? 'MYR' : 'USD',
      market: marketStore.activeMarket,
    }
  }
  showAddHoldingModal.value = true
}

const handleSaveHolding = () => {
  if (!holdingForm.value.symbol || holdingForm.value.shares <= 0 || holdingForm.value.avg_buy_price <= 0) {
    toast.error('Invalid Position', 'Please enter a valid ticker symbol, share count, and buy price.')
    return
  }

  marketStore.addHolding({
    symbol: holdingForm.value.symbol.toUpperCase().trim(),
    name: holdingForm.value.name.trim() || holdingForm.value.symbol.toUpperCase().trim(),
    shares: Number(holdingForm.value.shares),
    avg_buy_price: Number(holdingForm.value.avg_buy_price),
    currency: holdingForm.value.currency,
    market: holdingForm.value.market,
  })

  toast.success(
    'Position Tracked',
    `Added ${holdingForm.value.shares} shares of ${holdingForm.value.symbol.toUpperCase()} to your equity portfolio.`
  )
  showAddHoldingModal.value = false
}

const handleDeleteHolding = (id: string, name: string) => {
  marketStore.deleteHolding(id)
  toast.info('Position Removed', `Removed ${name} from your equity portfolio.`)
}

const formatNumber = (val: number, decimals: number = 2): string => {
  if (val === null || val === undefined) return '0.00'
  return val.toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header with Market Selector Toggle & Search -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <div class="flex items-center space-x-2">
          <Activity class="w-5 h-5 text-emerald-500" />
          <h2 class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
            Real-Time Stock Markets & Equities
          </h2>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Live quotes, benchmark indices, technical charts, and position P&L for Bursa Malaysia (KLSE) and US Markets.
        </p>
      </div>

      <!-- Controls: Market Switcher & Search Bar -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <!-- Bursa vs US Segmented Control -->
        <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
          <button
            type="button"
            @click="marketStore.activeMarket = 'bursa'"
            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1.5 cursor-pointer"
            :class="
              marketStore.activeMarket === 'bursa'
                ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm border border-slate-200 dark:border-slate-700'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            "
          >
            <span>🇲🇾</span>
            <span>Bursa Malaysia</span>
          </button>

          <button
            type="button"
            @click="marketStore.activeMarket = 'us'"
            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1.5 cursor-pointer"
            :class="
              marketStore.activeMarket === 'us'
                ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm border border-slate-200 dark:border-slate-700'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            "
          >
            <span>🇺🇸</span>
            <span>US Markets</span>
          </button>
        </div>

        <!-- Instant Autocomplete Search Bar -->
        <div class="relative w-full sm:w-72">
          <div class="relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              @input="handleSearchInput"
              @focus="isSearchFocused = true"
              type="text"
              :placeholder="marketStore.activeMarket === 'bursa' ? 'Search 1155, Maybank, Tenaga...' : 'Search AAPL, NVDA, Tesla...'"
              class="w-full pl-9 pr-4 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/50 shadow-sm"
            />
          </div>

          <!-- Autocomplete Dropdown -->
          <div
            v-if="isSearchFocused && marketStore.searchResults.length > 0"
            class="absolute top-full left-0 right-0 mt-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl z-50 overflow-hidden divide-y divide-slate-100 dark:divide-slate-800"
          >
            <button
              v-for="res in marketStore.searchResults"
              :key="res.symbol"
              type="button"
              @click="loadStock(res.symbol)"
              class="w-full px-3.5 py-2.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/80 transition flex items-center justify-between cursor-pointer group"
            >
              <div>
                <div class="flex items-center space-x-2">
                  <span class="font-mono font-bold text-xs text-slate-900 dark:text-white group-hover:text-emerald-500">
                    {{ res.code || res.symbol }}
                  </span>
                  <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium">
                    {{ res.category }}
                  </span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[200px]">
                  {{ res.name }}
                </div>
              </div>
              <ChevronRight class="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-emerald-500 group-hover:translate-x-0.5 transition" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Stock Detail Hero Card -->
    <div
      v-if="marketStore.selectedQuote"
      class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6"
    >
      <!-- Top Row: Stock Identity & Real-Time Price -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center space-x-3">
            <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
              {{ marketStore.selectedQuote.ticker_code || marketStore.selectedQuote.symbol }}
            </h3>
            <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">
              {{ marketStore.selectedQuote.name }}
            </span>
            <button
              type="button"
              @click="marketStore.toggleWatchlist(marketStore.selectedQuote.symbol)"
              class="p-1.5 rounded-lg text-slate-400 hover:text-amber-400 transition cursor-pointer"
              :title="marketStore.isWatchlisted(marketStore.selectedQuote.symbol) ? 'Remove from Watchlist' : 'Add to Watchlist'"
            >
              <Star
                class="w-4 h-4"
                :class="marketStore.isWatchlisted(marketStore.selectedQuote.symbol) ? 'fill-amber-400 text-amber-400' : 'text-slate-400'"
              />
            </button>

            <!-- Quick Track Position Action -->
            <button
              type="button"
              @click="openAddHoldingModal(marketStore.selectedQuote)"
              class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 transition flex items-center space-x-1 cursor-pointer"
              title="Add this stock to your portfolio holdings tracker"
            >
              <Plus class="w-3.5 h-3.5" />
              <span>Track Position</span>
            </button>
          </div>

          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400 font-medium mt-1">
            <span>Exchange: <strong class="text-slate-700 dark:text-slate-200">{{ marketStore.selectedQuote.exchange }}</strong></span>
            <span>&bull;</span>
            <span>Currency: <strong class="text-slate-700 dark:text-slate-200">{{ marketStore.selectedQuote.currency }}</strong></span>
            <span>&bull;</span>
            <div class="inline-flex items-center space-x-1">
              <span
                class="w-2 h-2 rounded-full"
                :class="marketStore.selectedQuote.market_status?.is_open ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"
              />
              <span class="font-mono text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                {{ marketStore.selectedQuote.market_status?.session || 'Market Closed' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Big Price Readout & Live FX Conversion Equivalent -->
        <div class="text-left md:text-right space-y-1">
          <div class="text-3xl font-extrabold font-mono text-slate-900 dark:text-white tracking-tight tabular-nums">
            {{ marketStore.selectedQuote.currency }} {{ formatNumber(marketStore.selectedQuote.current_price) }}
          </div>

          <!-- Live FX Converted Estimate for Cross-Border Investors -->
          <div v-if="convertedPriceEstimate" class="text-xs font-mono font-medium text-slate-500 dark:text-slate-400">
            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ convertedPriceEstimate.label }}</span>
          </div>

          <div
            class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-lg font-mono text-xs font-bold"
            :class="marketStore.selectedQuote.is_positive ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' : 'text-rose-600 dark:text-rose-400 bg-rose-500/10'"
          >
            <TrendingUp v-if="marketStore.selectedQuote.is_positive" class="w-3.5 h-3.5" />
            <TrendingDown v-else class="w-3.5 h-3.5" />
            <span>
              {{ marketStore.selectedQuote.is_positive ? '+' : '' }}{{ formatNumber(marketStore.selectedQuote.change) }}
              ({{ marketStore.selectedQuote.is_positive ? '+' : '' }}{{ formatNumber(marketStore.selectedQuote.change_percent) }}%)
            </span>
          </div>
        </div>
      </div>

      <!-- Center: Interactive SVG Price Chart -->
      <StockPriceChart
        :points="marketStore.chartData?.points || []"
        :currency="marketStore.selectedQuote.currency"
        :range="marketStore.chartRange"
        :previous-close="marketStore.selectedQuote.previous_close"
        :is-loading="marketStore.isLoadingChart"
        @change-range="handleRangeChange"
      />

      <!-- Bottom Metric Cards: Day Range & 52-Week Range -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
          <div class="text-[11px] font-medium text-slate-400">Previous Close</div>
          <div class="text-base font-bold font-mono text-slate-900 dark:text-white tabular-nums">
            {{ formatNumber(marketStore.selectedQuote.previous_close) }}
          </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
          <div class="text-[11px] font-medium text-slate-400">Day's Range</div>
          <div class="text-base font-bold font-mono text-slate-900 dark:text-white tabular-nums">
            {{ formatNumber(marketStore.selectedQuote.day_low) }} &minus; {{ formatNumber(marketStore.selectedQuote.day_high) }}
          </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
          <div class="text-[11px] font-medium text-slate-400">52-Week Range</div>
          <div class="text-base font-bold font-mono text-slate-900 dark:text-white tabular-nums">
            {{ formatNumber(marketStore.selectedQuote.fifty_two_week_low) }} &minus; {{ formatNumber(marketStore.selectedQuote.fifty_two_week_high) }}
          </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
          <div class="text-[11px] font-medium text-slate-400">Exchange Trading Hours</div>
          <div class="text-xs font-semibold text-slate-700 dark:text-slate-300">
            {{ marketStore.selectedQuote.market_status?.trading_hours || 'Mon-Fri Regular Hours' }}
          </div>
        </div>
      </div>
    </div>

    <!-- Equity Portfolio & Position Holdings Tracker (Live Unrealized P&L) -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center space-x-2">
            <Briefcase class="w-5 h-5 text-emerald-500" />
            <h3 class="text-base font-bold text-slate-900 dark:text-white">
              Stock Holdings & Position Tracker
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold border border-emerald-500/20">
              Live Unrealized P&L
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Track your share count, cost basis, live valuation, and return on investment across Bursa and US stocks.
          </p>
        </div>

        <button
          type="button"
          @click="openAddHoldingModal(marketStore.selectedQuote)"
          class="px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 cursor-pointer self-start sm:self-auto"
        >
          <Plus class="w-4 h-4" />
          <span>Add Position</span>
        </button>
      </div>

      <!-- Portfolio Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Capital Invested</div>
          <div class="text-xl font-black font-mono text-slate-900 dark:text-white tabular-nums">
            RM {{ formatNumber(portfolioSummary.totalCostMyr) }}
          </div>
          <div class="text-[11px] text-slate-400">Consolidated Base Cost</div>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Current Market Valuation</div>
          <div class="text-xl font-black font-mono text-slate-900 dark:text-white tabular-nums">
            RM {{ formatNumber(portfolioSummary.totalValueMyr) }}
          </div>
          <div class="text-[11px] text-slate-400">Updated with live quotes</div>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Unrealized P&L</div>
          <div
            class="text-xl font-black font-mono tabular-nums flex items-center space-x-1.5"
            :class="portfolioSummary.isPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
          >
            <span>{{ portfolioSummary.isPositive ? '+' : '' }}RM {{ formatNumber(portfolioSummary.totalPnlMyr) }}</span>
            <span class="text-xs font-bold px-1.5 py-0.5 rounded bg-emerald-500/10">
              ({{ portfolioSummary.isPositive ? '+' : '' }}{{ formatNumber(portfolioSummary.totalRoiPercent) }}%)
            </span>
          </div>
          <div class="text-[11px] text-slate-400">Overall Portfolio Return</div>
        </div>
      </div>

      <!-- Holdings List / Table -->
      <div v-if="enrichedHoldings.length > 0" class="overflow-x-auto no-scrollbar">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
              <th class="py-3 px-3">Asset</th>
              <th class="py-3 px-3">Shares</th>
              <th class="py-3 px-3">Avg Cost</th>
              <th class="py-3 px-3">Current Price</th>
              <th class="py-3 px-3">Market Value</th>
              <th class="py-3 px-3">Unrealized P&L</th>
              <th class="py-3 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
            <tr
              v-for="h in enrichedHoldings"
              :key="h.id"
              class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition group"
            >
              <td class="py-3 px-3">
                <button
                  type="button"
                  @click="loadStock(h.symbol)"
                  class="text-left font-bold text-slate-900 dark:text-white hover:text-emerald-500 transition cursor-pointer flex items-center space-x-1.5"
                >
                  <span>{{ h.symbol }}</span>
                  <span class="text-[10px] font-sans px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-500">
                    {{ h.market === 'bursa' ? '🇲🇾 Bursa' : '🇺🇸 US' }}
                  </span>
                </button>
                <div class="text-[10px] text-slate-400 font-sans truncate max-w-[150px]">{{ h.name }}</div>
              </td>
              <td class="py-3 px-3 text-slate-700 dark:text-slate-300 font-bold tabular-nums">
                {{ formatNumber(h.shares, 0) }}
              </td>
              <td class="py-3 px-3 text-slate-600 dark:text-slate-400 tabular-nums">
                {{ h.currency }} {{ formatNumber(h.avg_buy_price) }}
              </td>
              <td class="py-3 px-3 text-slate-900 dark:text-white font-bold tabular-nums">
                {{ h.currency }} {{ formatNumber(h.currentPrice) }}
              </td>
              <td class="py-3 px-3 text-slate-900 dark:text-white font-bold tabular-nums">
                {{ h.currency }} {{ formatNumber(h.currentValue) }}
              </td>
              <td class="py-3 px-3 tabular-nums">
                <div
                  class="inline-flex items-center space-x-1 px-2 py-0.5 rounded font-bold text-[11px]"
                  :class="h.isPositive ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' : 'text-rose-600 dark:text-rose-400 bg-rose-500/10'"
                >
                  <span>{{ h.isPositive ? '+' : '' }}{{ formatNumber(h.pnl) }}</span>
                  <span>({{ h.isPositive ? '+' : '' }}{{ formatNumber(h.pnlPercent) }}%)</span>
                </div>
              </td>
              <td class="py-3 px-3 text-right">
                <button
                  type="button"
                  @click="handleDeleteHolding(h.id, h.symbol)"
                  class="p-1 rounded text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 transition cursor-pointer"
                  title="Delete Position"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="text-center py-6 text-slate-400 text-xs">
        No stock positions tracked yet. Click "Add Position" above to calculate your live unrealized gains/losses.
      </div>
    </div>

    <!-- Curated Top Bluechips Directory -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
          <Sparkles class="w-4 h-4 text-emerald-500" />
          <span>Curated Market Leaders ({{ marketStore.activeMarket === 'bursa' ? 'Bursa KLCI Top 10' : 'US Mega-Cap Top 10' }})</span>
        </h3>
        <span class="text-xs text-slate-400">Click any company to inspect live chart</span>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
        <div
          v-for="mover in marketStore.movers"
          :key="mover.symbol"
          @click="loadStock(mover.symbol)"
          class="p-3.5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-emerald-500/40 dark:hover:border-emerald-500/40 transition cursor-pointer group shadow-sm dark:shadow-none space-y-1.5"
          :class="marketStore.selectedQuote?.symbol === mover.symbol ? 'ring-2 ring-emerald-500/50 bg-emerald-500/5' : ''"
        >
          <div class="flex items-center justify-between">
            <span class="font-mono text-xs font-black text-slate-900 dark:text-white group-hover:text-emerald-500 transition">
              {{ mover.code }}
            </span>
            <button
              type="button"
              @click.stop="marketStore.toggleWatchlist(mover.symbol)"
              class="text-slate-400 hover:text-amber-400 transition cursor-pointer p-0.5"
            >
              <Star
                class="w-3.5 h-3.5"
                :class="marketStore.isWatchlisted(mover.symbol) ? 'fill-amber-400 text-amber-400' : 'text-slate-400'"
              />
            </button>
          </div>

          <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
            {{ mover.name }}
          </div>

          <div class="flex items-baseline justify-between pt-1 font-mono">
            <span class="text-sm font-extrabold text-slate-900 dark:text-white tabular-nums">
              {{ mover.currency }} {{ formatNumber(mover.price) }}
            </span>
            <span
              class="text-[10px] font-bold"
              :class="mover.is_positive ? 'text-emerald-500' : 'text-rose-500'"
            >
              {{ mover.is_positive ? '+' : '' }}{{ formatNumber(mover.change_percent) }}%
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Personal Watchlist Section -->
    <div v-if="marketStore.watchlist.length > 0" class="space-y-4 pt-2">
      <div class="flex items-center space-x-2">
        <Star class="w-4 h-4 fill-amber-400 text-amber-400" />
        <h3 class="text-base font-bold text-slate-900 dark:text-white">Your Stock Watchlist ({{ marketStore.watchlist.length }})</h3>
      </div>

      <div class="flex flex-wrap gap-2">
        <div
          v-for="sym in marketStore.watchlist"
          :key="sym"
          class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 text-xs font-mono font-bold text-slate-800 dark:text-slate-200"
        >
          <button
            type="button"
            @click="loadStock(sym)"
            class="hover:text-emerald-500 transition cursor-pointer flex items-center space-x-1"
          >
            <span>{{ sym }}</span>
            <ChevronRight class="w-3 h-3 text-slate-400" />
          </button>
          <button
            type="button"
            @click="marketStore.toggleWatchlist(sym)"
            class="text-slate-400 hover:text-rose-500 transition cursor-pointer ml-1"
            title="Remove from watchlist"
          >
            &times;
          </button>
        </div>
      </div>
    </div>

    <!-- Add Position Modal Dialog -->
    <div
      v-if="showAddHoldingModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
          <div class="flex items-center space-x-2">
            <Briefcase class="w-5 h-5 text-emerald-500" />
            <h3 class="font-bold text-base text-slate-900 dark:text-white">Track Stock Position</h3>
          </div>
          <button
            type="button"
            @click="showAddHoldingModal = false"
            class="p-1 rounded-lg text-slate-400 hover:text-slate-900 dark:hover:text-white transition"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="handleSaveHolding" class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Ticker Symbol</label>
              <input
                v-model="holdingForm.symbol"
                type="text"
                required
                placeholder="e.g. 1155.KL or AAPL"
                class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono font-bold uppercase"
              />
            </div>

            <div class="space-y-1">
              <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Market</label>
              <select
                v-model="holdingForm.market"
                @change="holdingForm.currency = holdingForm.market === 'bursa' ? 'MYR' : 'USD'"
                class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
              >
                <option value="bursa">🇲🇾 Bursa Malaysia</option>
                <option value="us">🇺🇸 US Stock Market</option>
              </select>
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Company Name</label>
            <input
              v-model="holdingForm.name"
              type="text"
              placeholder="e.g. Malayan Banking Berhad"
              class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Shares Count</label>
              <input
                v-model.number="holdingForm.shares"
                type="number"
                min="1"
                step="any"
                required
                class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono font-bold"
              />
            </div>

            <div class="space-y-1">
              <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                Avg Buy Price ({{ holdingForm.currency }})
              </label>
              <input
                v-model.number="holdingForm.avg_buy_price"
                type="number"
                min="0.0001"
                step="any"
                required
                class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono font-bold"
              />
            </div>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 text-xs font-mono space-y-1">
            <div class="text-[11px] text-slate-500 font-sans">Estimated Cost Basis</div>
            <div class="font-bold text-slate-900 dark:text-white">
              {{ holdingForm.currency }} {{ formatNumber((holdingForm.shares || 0) * (holdingForm.avg_buy_price || 0)) }}
            </div>
          </div>

          <div class="flex items-center justify-end space-x-2 pt-2">
            <button
              type="button"
              @click="showAddHoldingModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition shadow-md shadow-emerald-500/20"
            >
              Save Position
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
