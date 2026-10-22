<script setup lang="ts">
import { computed } from 'vue'
import {
  ShieldCheck,
  ShieldAlert,
  AlertTriangle,
  CheckCircle2,
  Sparkles,
  TrendingUp,
  Activity,
  Layers,
  ArrowUpRight,
  PieChart,
  Scale,
  Gauge,
} from 'lucide-vue-next'
import type { FinancialHealthScoreData, HealthScorePillar } from '~/stores/budgets'

interface Props {
  healthScore?: FinancialHealthScoreData | null
  currencySymbol?: string
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  healthScore: null,
  currencySymbol: 'RM',
  isLoading: false,
})

const score = computed(() => props.healthScore?.score ?? 0)
const grade = computed(() => props.healthScore?.grade ?? 'C')
const tier = computed(() => props.healthScore?.tier ?? 'Fair (Moderate)')
const tierColor = computed(() => props.healthScore?.tier_color ?? 'sky')
const pillars = computed(() => props.healthScore?.pillars)
const diagnostics = computed(() => props.healthScore?.diagnostics ?? [])
const recommendations = computed(() => props.healthScore?.recommendations ?? [])

// Radial gauge geometry
const radius = 64
const circumference = 2 * Math.PI * radius
const strokeDashoffset = computed(() => {
  const percentage = Math.min(100, Math.max(0, score.value))
  return circumference - (percentage / 100) * circumference
})

const getPillarColorClass = (pillarScore: number) => {
  if (pillarScore >= 80) return 'bg-emerald-500'
  if (pillarScore >= 60) return 'bg-teal-500'
  if (pillarScore >= 45) return 'bg-sky-500'
  if (pillarScore >= 30) return 'bg-amber-500'
  return 'bg-rose-500'
}

const getPillarBadgeClass = (status: string) => {
  switch (status) {
    case 'robust':
    case 'exceptional':
    case 'optimal':
    case 'stable':
    case 'balanced':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
    case 'healthy':
    case 'moderate':
    case 'manageable':
      return 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20'
    case 'caution':
    case 'elevated':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'
    default:
      return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20'
  }
}

const getGradeBadgeClass = computed(() => {
  switch (grade.value) {
    case 'A':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
    case 'B':
      return 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/30'
    case 'C':
      return 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/30'
    case 'D':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30'
    default:
      return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30'
  }
})

const getScoreTextColor = computed(() => {
  switch (grade.value) {
    case 'A':
      return 'text-emerald-500 dark:text-emerald-400'
    case 'B':
      return 'text-teal-500 dark:text-teal-400'
    case 'C':
      return 'text-sky-500 dark:text-sky-400'
    case 'D':
      return 'text-amber-500 dark:text-amber-400'
    default:
      return 'text-rose-500 dark:text-rose-400'
  }
})
</script>

<template>
  <div
    class="p-6 rounded-3xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6"
  >
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="flex items-center space-x-3.5">
        <div class="w-11 h-11 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-500 dark:text-indigo-400 flex items-center justify-center">
          <Activity class="w-6 h-6" />
        </div>
        <div>
          <div class="flex items-center space-x-2">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Financial Health Score & Stability Radar</h3>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono uppercase bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
              5-Pillar Model
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Composite solvency evaluation assessing liquidity, savings margin, fixed liabilities, spending variance, and concentration
          </p>
        </div>
      </div>

      <div class="flex items-center space-x-2 self-start sm:self-auto">
        <span
          class="px-3 py-1 rounded-xl text-xs font-bold font-mono border uppercase flex items-center space-x-1.5"
          :class="getGradeBadgeClass"
        >
          <span>Grade {{ grade }}</span>
          <span>&bull;</span>
          <span>{{ tier }}</span>
        </span>
      </div>
    </div>

    <!-- Upper Executive Section: Radial Score Gauge + Executive Posture -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center pt-2">
      <!-- Radial Gauge Widget -->
      <div class="lg:col-span-4 flex flex-col items-center justify-center p-6 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800">
        <div class="relative w-40 h-40 flex items-center justify-center">
          <svg class="w-full h-full transform -rotate-90" viewBox="0 0 160 160">
            <!-- Background Track -->
            <circle
              cx="80"
              cy="80"
              :r="radius"
              stroke-width="12"
              class="stroke-slate-200 dark:stroke-slate-700/60"
              fill="transparent"
            />
            <!-- Animated Value Ring -->
            <circle
              cx="80"
              cy="80"
              :r="radius"
              stroke-width="12"
              class="transition-all duration-1000 ease-out"
              :class="{
                'stroke-emerald-500': grade === 'A',
                'stroke-teal-500': grade === 'B',
                'stroke-sky-500': grade === 'C',
                'stroke-amber-500': grade === 'D',
                'stroke-rose-500': grade === 'F',
              }"
              stroke-linecap="round"
              fill="transparent"
              :stroke-dasharray="circumference"
              :stroke-dashoffset="strokeDashoffset"
            />
          </svg>

          <!-- Inside Radial Metric -->
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <span class="text-3xl font-extrabold font-mono tracking-tight" :class="getScoreTextColor">
              {{ score }}
            </span>
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mt-0.5">
              out of 100
            </span>
          </div>
        </div>

        <div class="text-center mt-3">
          <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
            {{ tier }}
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
            Balanced Multi-Pillar Solvency Index
          </div>
        </div>
      </div>

      <!-- Executive Posture Summary -->
      <div class="lg:col-span-8 space-y-4">
        <div class="p-4 rounded-2xl bg-slate-50/50 dark:bg-slate-800/30 border border-slate-200/60 dark:border-slate-800/80 space-y-2">
          <div class="flex items-center space-x-2 text-xs font-bold text-slate-900 dark:text-white">
            <Gauge class="w-4 h-4 text-indigo-500" />
            <span>Executive Resilience Assessment</span>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
            Your personal finance ecosystem achieves an overall score of <strong :class="getScoreTextColor">{{ score }}/100</strong>, placing your operational health in the <strong class="font-semibold">{{ tier }}</strong> tier. This rating reflects your composite financial defense across emergency cash buffers, monthly operating margins, debt liabilities, and day-to-day spending volatility.
          </p>
        </div>

        <!-- Pillar Micro-Cards Preview -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 space-y-1">
            <span class="text-[10px] text-slate-400 uppercase font-mono">Runway Buffer</span>
            <div class="text-sm font-bold font-mono text-slate-900 dark:text-white">
              {{ pillars?.liquidity?.score ?? '--' }}/100
            </div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate">
              {{ pillars?.liquidity?.metric_label ?? 'Liquidity Reserve' }}
            </div>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 space-y-1">
            <span class="text-[10px] text-slate-400 uppercase font-mono">Savings Margin</span>
            <div class="text-sm font-bold font-mono text-slate-900 dark:text-white">
              {{ pillars?.savings?.score ?? '--' }}/100
            </div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate">
              {{ pillars?.savings?.metric_label ?? 'Net Margin' }}
            </div>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 space-y-1 col-span-2 sm:col-span-1">
            <span class="text-[10px] text-slate-400 uppercase font-mono">Fixed Obligations</span>
            <div class="text-sm font-bold font-mono text-slate-900 dark:text-white">
              {{ pillars?.debt_liability?.score ?? '--' }}/100
            </div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate">
              {{ pillars?.debt_liability?.metric_label ?? 'Fixed Liabilities' }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Middle Section: 5 Detailed Pillar Breakdown Bars -->
    <div class="space-y-3 pt-2">
      <div class="flex items-center justify-between">
        <h4 class="text-xs font-bold text-slate-900 dark:text-white flex items-center space-x-1.5">
          <Scale class="w-4 h-4 text-emerald-500" />
          <span>Core Pillar Performance Breakdown</span>
        </h4>
        <span class="text-[10px] font-mono text-slate-400">Sum of Weighted Scores = {{ score }}/100</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5" v-if="pillars">
        <!-- 1. Liquidity Runway -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/80 dark:border-slate-800 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-bold text-slate-900 dark:text-white">{{ pillars.liquidity.name }}</span>
              <span class="text-[10px] font-mono text-slate-400 bg-slate-200/70 dark:bg-slate-700/60 px-1.5 py-0.2 rounded">
                {{ pillars.liquidity.weight }}% wt
              </span>
            </div>
            <span
              class="px-2 py-0.5 rounded-lg text-[10px] font-bold font-mono uppercase border"
              :class="getPillarBadgeClass(pillars.liquidity.status)"
            >
              {{ pillars.liquidity.status }}
            </span>
          </div>

          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-mono">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ pillars.liquidity.metric_label }}</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ pillars.liquidity.score }}</span>
            </div>
            <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="getPillarColorClass(pillars.liquidity.score)"
                :style="{ width: `${pillars.liquidity.score}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- 2. Savings Rate -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/80 dark:border-slate-800 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-bold text-slate-900 dark:text-white">{{ pillars.savings.name }}</span>
              <span class="text-[10px] font-mono text-slate-400 bg-slate-200/70 dark:bg-slate-700/60 px-1.5 py-0.2 rounded">
                {{ pillars.savings.weight }}% wt
              </span>
            </div>
            <span
              class="px-2 py-0.5 rounded-lg text-[10px] font-bold font-mono uppercase border"
              :class="getPillarBadgeClass(pillars.savings.status)"
            >
              {{ pillars.savings.status }}
            </span>
          </div>

          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-mono">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ pillars.savings.metric_label }}</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ pillars.savings.score }}</span>
            </div>
            <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="getPillarColorClass(pillars.savings.score)"
                :style="{ width: `${pillars.savings.score}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- 3. Fixed Liability Burden -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/80 dark:border-slate-800 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-bold text-slate-900 dark:text-white">{{ pillars.debt_liability.name }}</span>
              <span class="text-[10px] font-mono text-slate-400 bg-slate-200/70 dark:bg-slate-700/60 px-1.5 py-0.2 rounded">
                {{ pillars.debt_liability.weight }}% wt
              </span>
            </div>
            <span
              class="px-2 py-0.5 rounded-lg text-[10px] font-bold font-mono uppercase border"
              :class="getPillarBadgeClass(pillars.debt_liability.status)"
            >
              {{ pillars.debt_liability.status }}
            </span>
          </div>

          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-mono">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ pillars.debt_liability.metric_label }}</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ pillars.debt_liability.score }}</span>
            </div>
            <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="getPillarColorClass(pillars.debt_liability.score)"
                :style="{ width: `${pillars.debt_liability.score}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- 4. Spending Volatility -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/80 dark:border-slate-800 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-bold text-slate-900 dark:text-white">{{ pillars.volatility.name }}</span>
              <span class="text-[10px] font-mono text-slate-400 bg-slate-200/70 dark:bg-slate-700/60 px-1.5 py-0.2 rounded">
                {{ pillars.volatility.weight }}% wt
              </span>
            </div>
            <span
              class="px-2 py-0.5 rounded-lg text-[10px] font-bold font-mono uppercase border"
              :class="getPillarBadgeClass(pillars.volatility.status)"
            >
              {{ pillars.volatility.status }}
            </span>
          </div>

          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-mono">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ pillars.volatility.metric_label }}</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ pillars.volatility.score }}</span>
            </div>
            <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="getPillarColorClass(pillars.volatility.score)"
                :style="{ width: `${pillars.volatility.score}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- 5. Category Diversification -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/80 dark:border-slate-800 space-y-2.5 md:col-span-2 lg:col-span-1">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-bold text-slate-900 dark:text-white">{{ pillars.concentration.name }}</span>
              <span class="text-[10px] font-mono text-slate-400 bg-slate-200/70 dark:bg-slate-700/60 px-1.5 py-0.2 rounded">
                {{ pillars.concentration.weight }}% wt
              </span>
            </div>
            <span
              class="px-2 py-0.5 rounded-lg text-[10px] font-bold font-mono uppercase border"
              :class="getPillarBadgeClass(pillars.concentration.status)"
            >
              {{ pillars.concentration.status }}
            </span>
          </div>

          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-mono">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ pillars.concentration.metric_label }}</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ pillars.concentration.score }}</span>
            </div>
            <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="getPillarColorClass(pillars.concentration.score)"
                :style="{ width: `${pillars.concentration.score}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lower Section: Diagnostics & Prescriptive Recommendations -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
      <!-- Diagnostics -->
      <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 space-y-3">
        <h5 class="text-xs font-bold text-slate-900 dark:text-white flex items-center space-x-1.5">
          <ShieldCheck class="w-4 h-4 text-teal-500" />
          <span>Automated Solvency Diagnostics</span>
        </h5>
        <div class="space-y-2">
          <div
            v-for="(diag, idx) in diagnostics"
            :key="idx"
            class="p-2.5 rounded-xl border flex items-start space-x-2.5 text-xs"
            :class="[
              diag.type === 'positive'
                ? 'bg-emerald-500/5 dark:bg-emerald-500/10 border-emerald-500/20 text-emerald-800 dark:text-emerald-300'
                : diag.type === 'warning'
                ? 'bg-amber-500/5 dark:bg-amber-500/10 border-amber-500/20 text-amber-800 dark:text-amber-300'
                : 'bg-rose-500/5 dark:bg-rose-500/10 border-rose-500/20 text-rose-800 dark:text-rose-300'
            ]"
          >
            <CheckCircle2 v-if="diag.type === 'positive'" class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
            <AlertTriangle v-else-if="diag.type === 'warning'" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
            <ShieldAlert v-else class="w-4 h-4 text-rose-500 flex-shrink-0 mt-0.5" />
            <span class="leading-relaxed">{{ diag.message }}</span>
          </div>
        </div>
      </div>

      <!-- Prescriptive Recommendations -->
      <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 space-y-3">
        <h5 class="text-xs font-bold text-slate-900 dark:text-white flex items-center space-x-1.5">
          <Sparkles class="w-4 h-4 text-indigo-500" />
          <span>Strategic Optimization Advisory</span>
        </h5>
        <ul class="space-y-2">
          <li
            v-for="(rec, idx) in recommendations"
            :key="idx"
            class="p-2.5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 flex items-start space-x-2.5 text-xs text-slate-700 dark:text-slate-300"
          >
            <ArrowUpRight class="w-4 h-4 text-indigo-500 flex-shrink-0 mt-0.5" />
            <span class="leading-relaxed">{{ rec }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
