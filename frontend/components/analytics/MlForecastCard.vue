<script setup lang="ts">
import { onMounted, computed } from 'vue'
import {
  Sparkles,
  TrendingUp,
  TrendingDown,
  AlertTriangle,
  CheckCircle2,
  RefreshCw,
  Cpu,
  ShieldAlert,
  Zap,
} from 'lucide-vue-next'
import { useBudgetStore } from '~/stores/budgets'
import SkeletonLoader from '~/components/ui/SkeletonLoader.vue'

const props = withDefaults(
  defineProps<{
    currencySymbol?: string
  }>(),
  {
    currencySymbol: 'RM',
  }
)

const budgetStore = useBudgetStore()

onMounted(async () => {
  if (!budgetStore.mlIntelligence && !budgetStore.isMlLoading) {
    await budgetStore.fetchMlIntelligence()
  }
})

const mlData = computed(() => budgetStore.mlIntelligence)
const isLoading = computed(() => budgetStore.isMlLoading)
const error = computed(() => budgetStore.mlError)

const formatNumber = (val: number | string | undefined | null): string => {
  if (val === null || val === undefined) return '0.00'
  const num = typeof val === 'string' ? parseFloat(val) : val
  if (isNaN(num)) return '0.00'
  return num.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const formatMonthLabel = (monthStr: string): string => {
  if (!monthStr) return ''
  const parts = monthStr.split('-')
  if (parts.length === 2) {
    const year = parseInt(parts[0], 10)
    const month = parseInt(parts[1], 10) - 1
    const d = new Date(year, month, 1)
    return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
  }
  return monthStr
}

const getTierBadge = (method: string) => {
  switch (method) {
    case 'ridge_autoregression':
      return {
        label: 'Tier 1: Autoregressive Ridge',
        classes: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
      }
    case 'weighted_trend_baseline':
      return {
        label: 'Tier 2: Weighted Trend',
        classes: 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
      }
    case 'cold_start_run_rate':
      return {
        label: 'Tier 3: Cold-Start Run-Rate',
        classes: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
      }
    default:
      return {
        label: 'Statistical Moving Average',
        classes: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
      }
  }
}

const handleRefresh = async () => {
  await budgetStore.fetchMlIntelligence(true)
}
</script>

<template>
  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-start space-x-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/20 to-emerald-500/20 text-indigo-500 dark:text-indigo-400 border border-indigo-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
          <Sparkles class="w-5 h-5" />
        </div>
        <div>
          <div class="flex items-center space-x-2">
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight">
              Predictive Spending Trajectory & ML Engine
            </h3>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 font-mono">
              Scikit-Learn
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Decoupled multi-tier forward rollout, category budget risk forecasts, and Isolation Forest outliers
          </p>
        </div>
      </div>

      <!-- Action & Metadata -->
      <div class="flex items-center space-x-3 self-end sm:self-auto">
        <div v-if="mlData?.duration_ms" class="text-right hidden sm:block">
          <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">Inference Time</div>
          <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 font-mono">
            {{ mlData.duration_ms }} ms ({{ mlData.records_analyzed }} records)
          </div>
        </div>

        <button
          type="button"
          @click="handleRefresh"
          :disabled="isLoading"
          class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition flex items-center space-x-1.5 text-xs font-semibold disabled:opacity-50 cursor-pointer"
          title="Recalculate ML Forecasts"
        >
          <RefreshCw class="w-4 h-4" :class="isLoading ? 'animate-spin text-indigo-500' : ''" />
          <span class="hidden md:inline">{{ isLoading ? 'Calculating...' : 'Recalculate' }}</span>
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-700 dark:text-amber-300 flex items-center space-x-2"
    >
      <AlertTriangle class="w-4 h-4 flex-shrink-0 text-amber-500" />
      <span>{{ error }}</span>
    </div>

    <!-- Loading Skeleton -->
    <div v-else-if="isLoading && !mlData" class="space-y-4">
      <SkeletonLoader variant="metric-card" :count="1" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!mlData || mlData.records_analyzed === 0"
      class="p-8 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-dashed border-slate-200 dark:border-slate-800 text-center space-y-2"
    >
      <Cpu class="w-8 h-8 text-slate-400 mx-auto" />
      <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">Cold-Start Model Initialized</h4>
      <p class="text-[11px] text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
        As you log or import transactions, our multi-tier machine learning pipeline automatically generates predictive trajectories.
      </p>
    </div>

    <!-- ML Data Visualizations -->
    <div v-else class="space-y-6">
      <!-- 3-Month Trajectory Cards -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center space-x-1.5">
            <Zap class="w-3.5 h-3.5 text-amber-500" />
            <span>3-Month Forward Expenditure Trajectory</span>
          </span>
          <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
            Confidence-Weighted Projections
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div
            v-for="(item, idx) in mlData.forecast"
            :key="item.month"
            class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/80 space-y-2.5 relative overflow-hidden"
          >
            <!-- Top Month & Step Indicator -->
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-900 dark:text-white">
                {{ formatMonthLabel(item.month) }}
              </span>
              <span class="text-[10px] font-mono font-semibold text-slate-500 dark:text-slate-400">
                Month +{{ idx + 1 }}
              </span>
            </div>

            <!-- Predicted Amount -->
            <div class="space-y-0.5">
              <div class="text-lg font-black text-slate-900 dark:text-white tracking-tight">
                {{ currencySymbol }} {{ formatNumber(item.predicted_expense) }}
              </div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400">
                Estimated Monthly Outflow
              </div>
            </div>

            <!-- Model Tier & Confidence Bar -->
            <div class="space-y-1.5 pt-1">
              <div class="flex items-center justify-between text-[10px]">
                <span class="font-mono text-slate-500 dark:text-slate-400">Confidence</span>
                <span class="font-mono font-bold text-slate-700 dark:text-slate-300">
                  {{ Math.round(item.confidence_level * 100) }}%
                </span>
              </div>
              <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-indigo-500 transition-all duration-500"
                  :style="{ width: `${Math.round(item.confidence_level * 100)}%` }"
                ></div>
              </div>
              <span
                class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold border font-mono mt-1"
                :class="getTierBadge(item.method).classes"
              >
                {{ getTierBadge(item.method).label }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Category Risk Projections (if available) -->
      <div
        v-if="mlData.category_forecast?.categories && mlData.category_forecast.categories.length > 0"
        class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-1.5">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
              Category Allocation & Risk Breakdown
            </span>
            <span
              v-if="(mlData.category_forecast.categories_at_risk_count ?? 0) > 0"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20"
            >
              {{ mlData.category_forecast.categories_at_risk_count }} Overrun Warning(s)
            </span>
          </div>
          <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
            Next Cycle ({{ formatMonthLabel(mlData.category_forecast.forecast_period || '') }})
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
          <div
            v-for="cat in mlData.category_forecast.categories.slice(0, 8)"
            :key="cat.category"
            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 space-y-1"
          >
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200 capitalize truncate">
                {{ cat.category.replace(/[-_]/g, ' ') }}
              </span>
              <span
                class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase font-mono"
                :class="
                  cat.trend_slope === 'Surging'
                    ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                    : cat.trend_slope === 'Declining'
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                    : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'
                "
              >
                {{ cat.trend_slope }}
              </span>
            </div>

            <div class="text-sm font-extrabold text-slate-900 dark:text-white">
              {{ currencySymbol }} {{ formatNumber(cat.projected_monthly_spend) }}
            </div>

            <div class="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400 font-mono">
              <span>Historical Avg:</span>
              <span>{{ currencySymbol }} {{ formatNumber(cat.historical_monthly_avg) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Isolation Forest Outlier Alerts -->
      <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center space-x-1.5">
            <ShieldAlert class="w-3.5 h-3.5 text-indigo-500" />
            <span>Isolation Forest Anomaly Telemetry</span>
          </span>
          <span class="text-[10px] font-mono text-slate-400">
            Contamination $\alpha = 0.05$
          </span>
        </div>

        <div
          v-if="mlData.anomalies && mlData.anomalies.length > 0"
          class="space-y-2"
        >
          <div
            v-for="anom in mlData.anomalies.slice(0, 3)"
            :key="anom.id || anom.description"
            class="p-3 rounded-xl bg-rose-500/5 dark:bg-rose-500/10 border border-rose-500/20 flex items-center justify-between gap-3 text-xs"
          >
            <div class="flex items-center space-x-2 min-w-0">
              <AlertTriangle class="w-4 h-4 text-rose-500 flex-shrink-0" />
              <div class="truncate">
                <span class="font-bold text-slate-900 dark:text-white">{{ anom.description }}</span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 ml-1.5">({{ anom.date }})</span>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">
                  {{ anom.reason || 'Statistical outlier detected against baseline distribution.' }}
                </p>
              </div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="font-bold text-rose-600 dark:text-rose-400">
                {{ currencySymbol }} {{ formatNumber(anom.amount) }}
              </div>
              <span class="text-[9px] font-mono px-1 rounded bg-rose-500/20 text-rose-500">Anomaly</span>
            </div>
          </div>
        </div>

        <div
          v-else
          class="p-3 rounded-xl bg-emerald-500/5 dark:bg-emerald-500/10 border border-emerald-500/20 flex items-center space-x-2 text-xs text-emerald-700 dark:text-emerald-300"
        >
          <CheckCircle2 class="w-4 h-4 text-emerald-500 flex-shrink-0" />
          <span>Zero anomalous expenditures detected. All recent transactions conform to expected historical variance.</span>
        </div>
      </div>
    </div>
  </div>
</template>
