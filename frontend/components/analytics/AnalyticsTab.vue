<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  BarChart3,
  TrendingUp,
  TrendingDown,
  ShieldAlert,
  ShieldCheck,
  Calendar,
  Layers,
  Sparkles,
  Percent,
  Clock,
  Building,
  CreditCard,
  PiggyBank,
  AlertTriangle,
  Flame,
  CheckCircle2,
  RefreshCw,
  ArrowUpRight,
  ArrowDownLeft,
  DollarSign,
  Coffee,
  ShoppingBag,
} from 'lucide-vue-next'
import { useBudgetStore, type CashFlowAnalytics } from '~/stores/budgets'
import { useAuthStore } from '~/stores/auth'
import InfoTooltip from '~/components/ui/InfoTooltip.vue'
import MalaysianInsightsCard from '~/components/analytics/MalaysianInsightsCard.vue'
import MlForecastCard from '~/components/analytics/MlForecastCard.vue'
import FinancialHealthScoreCard from '~/components/analytics/FinancialHealthScoreCard.vue'
import SpendingPersonaCard from '~/components/analytics/SpendingPersonaCard.vue'

const props = defineProps<{
  currencySymbol?: string
}>()

const emit = defineEmits<{
  (e: 'record-expense'): void
  (e: 'record-income'): void
  (e: 'open-import'): void
}>()

const budgetStore = useBudgetStore()
const authStore = useAuthStore()

// Period filter state
type PeriodOption = 'current_month' | 'last_30_days' | 'last_90_days' | 'ytd'
const selectedPeriod = ref<PeriodOption>('current_month')
const isRefreshing = ref(false)

const loadPeriodAnalytics = async (period: PeriodOption) => {
  selectedPeriod.value = period
  isRefreshing.value = true

  const now = new Date()
  let start = ''
  let end = now.toISOString().slice(0, 10)

  if (period === 'current_month') {
    start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
    end = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
  } else if (period === 'last_30_days') {
    const d = new Date()
    d.setDate(d.getDate() - 30)
    start = d.toISOString().slice(0, 10)
  } else if (period === 'last_90_days') {
    const d = new Date()
    d.setDate(d.getDate() - 90)
    start = d.toISOString().slice(0, 10)
  } else if (period === 'ytd') {
    start = new Date(now.getFullYear(), 0, 1).toISOString().slice(0, 10)
  }

  try {
    await budgetStore.fetchCashFlow(start, end)
  } finally {
    isRefreshing.value = false
  }
}

onMounted(async () => {
  if (!budgetStore.cashFlow) {
    await loadPeriodAnalytics('current_month')
  }
})

const cashFlow = computed(() => budgetStore.cashFlow)

const formatNumber = (val: number | string | undefined | null, decimals: number = 2): string => {
  if (val === null || val === undefined) return '0.00'
  const num = typeof val === 'string' ? parseFloat(val) : val
  if (isNaN(num)) return '0.00'
  return num.toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header with Intelligence Title & Period Filter -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <div class="flex items-center space-x-2">
          <BarChart3 class="w-5 h-5 text-indigo-500" />
          <h2 class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
            Financial Intelligence & Health Analytics
          </h2>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Liquidity runway velocity, top merchant concentration, spending patterns, and statistical anomaly detection.
        </p>
      </div>

      <!-- Period Filter Segmented Control -->
      <div class="flex items-center space-x-2 overflow-x-auto no-scrollbar">
        <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 text-xs font-semibold">
          <button
            type="button"
            @click="loadPeriodAnalytics('current_month')"
            class="px-3 py-1.5 rounded-lg transition cursor-pointer"
            :class="selectedPeriod === 'current_month' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          >
            This Month
          </button>
          <button
            type="button"
            @click="loadPeriodAnalytics('last_30_days')"
            class="px-3 py-1.5 rounded-lg transition cursor-pointer"
            :class="selectedPeriod === 'last_30_days' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          >
            Last 30 Days
          </button>
          <button
            type="button"
            @click="loadPeriodAnalytics('last_90_days')"
            class="px-3 py-1.5 rounded-lg transition cursor-pointer"
            :class="selectedPeriod === 'last_90_days' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          >
            Last 90 Days
          </button>
          <button
            type="button"
            @click="loadPeriodAnalytics('ytd')"
            class="px-3 py-1.5 rounded-lg transition cursor-pointer"
            :class="selectedPeriod === 'ytd' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          >
            Year to Date
          </button>
        </div>

        <button
          type="button"
          @click="loadPeriodAnalytics(selectedPeriod)"
          :disabled="isRefreshing"
          class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition cursor-pointer"
          title="Refresh Analytics"
        >
          <RefreshCw class="w-4 h-4" :class="isRefreshing ? 'animate-spin text-indigo-500' : ''" />
        </button>
      </div>
    </div>

    <!-- Empty State if No Transactions -->
    <div
      v-if="!cashFlow || (Number(cashFlow.summary.total_income) === 0 && Number(cashFlow.summary.total_expense) === 0)"
      class="p-12 rounded-2xl bg-white dark:bg-slate-900/60 border border-dashed border-slate-200 dark:border-slate-800 text-center space-y-4 shadow-sm"
    >
      <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 mx-auto flex items-center justify-center text-indigo-500">
        <BarChart3 class="w-7 h-7" />
      </div>
      <div class="space-y-1">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">No Financial Activity for this Period</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
          Record transactions or import a bank statement to unlock real-time financial runway, top merchant velocity, and statistical anomaly detection.
        </p>
      </div>
      <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
        <button
          @click="emit('record-expense')"
          class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-500 hover:bg-rose-400 text-white transition inline-flex items-center space-x-1.5 shadow"
        >
          <span>Record Expense</span>
        </button>
        <button
          @click="emit('record-income')"
          class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition inline-flex items-center space-x-1.5 shadow"
        >
          <span>Record Income</span>
        </button>
        <button
          @click="emit('open-import')"
          class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition"
        >
          <span>Import Statement CSV</span>
        </button>
      </div>
    </div>

    <!-- Active Analytics Content -->
    <div v-else class="space-y-6">
      <!-- Multi-Pillar Financial Health Score & Stability Radar -->
      <FinancialHealthScoreCard
        :health-score="cashFlow?.health_score"
        :currency-symbol="currencySymbol"
      />

      <!-- Machine Learning Predictive Spending Trajectory & Anomaly Intelligence -->
      <MlForecastCard :currency-symbol="currencySymbol" />

      <!-- Malaysian Consumer Spending Persona Archetype -->
      <SpendingPersonaCard
        :persona="budgetStore.mlIntelligence?.spending_persona"
        :is-loading="budgetStore.isMlLoading"
      />

      <!-- Top Row: Financial Health Runway & Burn Rate Gauge -->
      <div
        v-if="cashFlow.runway"
        class="p-6 rounded-2xl bg-gradient-to-br from-white to-slate-50 dark:from-slate-900/90 dark:to-slate-900/40 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6"
      >
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div class="flex items-center space-x-2">
              <Flame class="w-5 h-5 text-amber-500" />
              <h3 class="text-base font-bold text-slate-900 dark:text-white">
                Liquidity Runway & Burn Velocity
              </h3>
              <span
                class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold uppercase tracking-wider"
                :class="{
                  'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30': cashFlow.runway.health_tier === 'robust' || cashFlow.runway.health_tier === 'healthy',
                  'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30': cashFlow.runway.health_tier === 'caution',
                  'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30': cashFlow.runway.health_tier === 'critical',
                }"
              >
                {{ cashFlow.runway.health_label }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Based on your liquid checking/cash reserves and average daily consumption rate.
            </p>
          </div>

          <!-- Big Runway Readout -->
          <div class="text-left md:text-right">
            <div class="flex items-baseline md:justify-end space-x-1.5">
              <span class="text-4xl font-black font-mono tracking-tight text-slate-900 dark:text-white tabular-nums">
                {{ cashFlow.runway.runway_months >= 999 ? '∞' : cashFlow.runway.runway_months }}
              </span>
              <span class="text-sm font-bold text-slate-500 dark:text-slate-400">Months</span>
            </div>
            <p class="text-[11px] text-slate-400">
              Survival duration without new income
            </p>
          </div>
        </div>

        <!-- 3 Runway Stat Pillars -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
          <div class="p-4 rounded-xl bg-white dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Liquid Cash Reserves</div>
            <div class="text-xl font-extrabold font-mono text-slate-900 dark:text-white tabular-nums">
              {{ cashFlow.runway.liquid_balance_formatted }}
            </div>
            <div class="text-[11px] text-slate-400">Checking, savings & cash</div>
          </div>

          <div class="p-4 rounded-xl bg-white dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Average Daily Burn Rate</div>
            <div class="text-xl font-extrabold font-mono text-slate-900 dark:text-white tabular-nums">
              {{ props.currencySymbol || 'RM' }} {{ formatNumber(cashFlow.runway.daily_burn_rate) }}
            </div>
            <div class="text-[11px] text-slate-400">Daily outflow velocity</div>
          </div>

          <div class="p-4 rounded-xl bg-white dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Projected Monthly Outflow</div>
            <div class="text-xl font-extrabold font-mono text-rose-600 dark:text-rose-400 tabular-nums">
              {{ props.currencySymbol || 'RM' }} {{ formatNumber(cashFlow.runway.monthly_burn_rate) }}
            </div>
            <div class="text-[11px] text-slate-400">Estimated 30-day requirement</div>
          </div>
        </div>
      </div>

      <!-- Middle Split: Cash Flow Summary & Anomaly Radar -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cash Flow Inflows vs Outflows -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
              <TrendingUp class="w-4 h-4 text-emerald-500" />
              <span>Net Cash Flow & Savings</span>
            </h3>
            <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-lg">
              Savings Rate: {{ cashFlow.summary.savings_rate }}%
            </span>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
              <span class="text-[11px] text-slate-400 font-medium">Inflows</span>
              <div class="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                {{ cashFlow.summary.total_income_formatted }}
              </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
              <span class="text-[11px] text-slate-400 font-medium">Outflows</span>
              <div class="text-base font-bold font-mono text-rose-600 dark:text-rose-400 tabular-nums">
                {{ cashFlow.summary.total_expense_formatted }}
              </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
              <span class="text-[11px] text-slate-400 font-medium">Net Savings</span>
              <div
                class="text-base font-bold font-mono tabular-nums"
                :class="Number(cashFlow.summary.net_cash_flow) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
              >
                {{ cashFlow.summary.net_cash_flow_formatted }}
              </div>
            </div>
          </div>

          <!-- Behavioral Weekday vs Weekend Split -->
          <div v-if="cashFlow.spending_patterns" class="pt-2 space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
              <span class="font-semibold">Spending Timing Velocity</span>
              <span class="font-mono text-[11px]">
                Weekday: {{ cashFlow.spending_patterns.weekday_percent }}% &bull; Weekend: {{ cashFlow.spending_patterns.weekend_percent }}%
              </span>
            </div>
            <!-- Dual-color stacked bar -->
            <div class="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden flex">
              <div
                class="bg-indigo-500 transition-all duration-500"
                :style="{ width: `${cashFlow.spending_patterns.weekday_percent}%` }"
                :title="`Weekday: ${cashFlow.spending_patterns.weekday_formatted}`"
              />
              <div
                class="bg-amber-500 transition-all duration-500"
                :style="{ width: `${cashFlow.spending_patterns.weekend_percent}%` }"
                :title="`Weekend: ${cashFlow.spending_patterns.weekend_formatted}`"
              />
            </div>
            <div class="flex items-center justify-between text-[11px] text-slate-400">
              <div class="flex items-center space-x-1">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                <span>Mon-Fri: {{ props.currencySymbol || 'RM' }} {{ formatNumber(cashFlow.spending_patterns.weekday_daily_avg) }}/day</span>
              </div>
              <div class="flex items-center space-x-1">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Sat-Sun: {{ props.currencySymbol || 'RM' }} {{ formatNumber(cashFlow.spending_patterns.weekend_daily_avg) }}/day</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistical Spending Anomaly Radar -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
              <ShieldAlert class="w-4 h-4 text-amber-500" />
              <span>Statistical Anomaly Radar</span>
            </h3>
            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500">
              Z-Score &bull; IQR Outlier Model
            </span>
          </div>

          <div v-if="cashFlow.anomalies && cashFlow.anomalies.length > 0" class="space-y-2.5">
            <div
              v-for="anom in cashFlow.anomalies"
              :key="anom.id"
              class="p-3 rounded-xl bg-amber-500/5 dark:bg-amber-500/10 border border-amber-500/30 flex items-center justify-between gap-3"
            >
              <div class="min-w-0">
                <div class="flex items-center space-x-2">
                  <span class="font-bold text-xs text-slate-900 dark:text-white truncate">
                    {{ anom.description }}
                  </span>
                  <span
                    class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase font-mono"
                    :class="anom.severity === 'high' ? 'bg-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'"
                  >
                    {{ anom.severity === 'high' ? 'High Outlier' : 'Unusual Spend' }}
                  </span>
                </div>
                <div class="text-[11px] text-amber-700 dark:text-amber-300 mt-0.5">
                  {{ anom.reason }} &bull; {{ anom.category }} ({{ anom.date }})
                </div>
              </div>

              <div class="text-right flex-shrink-0 font-mono font-bold text-sm text-slate-900 dark:text-white tabular-nums">
                {{ anom.amount_formatted }}
              </div>
            </div>
          </div>

          <div
            v-else
            class="p-6 rounded-xl bg-slate-50/60 dark:bg-slate-950/40 border border-slate-200/60 dark:border-slate-800 text-center space-y-1.5"
          >
            <CheckCircle2 class="w-6 h-6 text-emerald-500 mx-auto" />
            <h4 class="text-xs font-bold text-slate-900 dark:text-white">All Spending Within Expected Bounds</h4>
            <p class="text-[11px] text-slate-400 max-w-sm mx-auto">
              No statistical anomalies detected. All transactions align with normal category baselines.
            </p>
          </div>
        </div>
      </div>

      <!-- Bottom Split: Top Merchant Concentration & Category Distribution -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Merchant Concentration -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
              <Building class="w-4 h-4 text-indigo-500" />
              <span>Top Outflow Counterparties</span>
            </h3>
            <span class="text-xs text-slate-400">By total spend</span>
          </div>

          <div v-if="cashFlow.top_merchants && cashFlow.top_merchants.length > 0" class="space-y-3">
            <div
              v-for="(merchant, idx) in cashFlow.top_merchants"
              :key="merchant.name"
              class="space-y-1"
            >
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                  <span class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-mono text-[10px] font-bold text-slate-600 dark:text-slate-400">
                    {{ idx + 1 }}
                  </span>
                  <span class="font-bold text-slate-900 dark:text-white truncate max-w-[200px]">
                    {{ merchant.name }}
                  </span>
                  <span class="text-[10px] text-slate-400 font-mono">
                    ({{ merchant.count }} txns)
                  </span>
                </div>
                <div class="flex items-center space-x-2 font-mono">
                  <span class="font-bold text-slate-900 dark:text-white tabular-nums">
                    {{ merchant.amount_formatted }}
                  </span>
                  <span class="text-[11px] text-slate-400 w-12 text-right">
                    {{ merchant.percentage }}%
                  </span>
                </div>
              </div>
              <!-- Progress bar -->
              <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div
                  class="h-full bg-indigo-500 rounded-full transition-all duration-500"
                  :style="{ width: `${merchant.percentage}%` }"
                />
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6 text-xs text-slate-400">
            No merchant data available for this period.
          </div>
        </div>

        <!-- Category Spending Distribution -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
              <Layers class="w-4 h-4 text-emerald-500" />
              <span>Category Spending Distribution</span>
            </h3>
            <span class="text-xs text-slate-400">Share of outflows</span>
          </div>

          <div v-if="cashFlow.categories.length > 0" class="space-y-3">
            <div
              v-for="cat in cashFlow.categories"
              :key="cat.name"
              class="space-y-1"
            >
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                  <span
                    class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                    :style="{ backgroundColor: cat.color }"
                  />
                  <span class="font-bold text-slate-900 dark:text-white">
                    {{ cat.name }}
                  </span>
                </div>
                <div class="flex items-center space-x-2 font-mono">
                  <span class="font-bold text-slate-900 dark:text-white tabular-nums">
                    {{ cat.amount_formatted }}
                  </span>
                  <span class="text-[11px] text-slate-400 w-12 text-right">
                    {{ cat.percentage }}%
                  </span>
                </div>
              </div>
              <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{ width: `${cat.percentage}%`, backgroundColor: cat.color }"
                />
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6 text-xs text-slate-400">
            No categorized expenses in this period.
          </div>
        </div>
      </div>
    </div>

    <!-- Malaysian Daily Spending Behavior & Consumer Insights Hub -->
    <MalaysianInsightsCard />
  </div>
</template>
