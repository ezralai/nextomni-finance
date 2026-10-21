<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import {
  Sparkles,
  Compass,
  TrendingUp,
  AlertTriangle,
  CheckCircle2,
  Calendar,
  ShieldCheck,
  Coffee,
  Car,
  Home,
  ShoppingBag,
  HelpCircle,
  RefreshCw,
  Sliders,
  DollarSign,
} from 'lucide-vue-next'
import { useConsumerInsightsStore } from '~/stores/consumerInsights'
import InfoTooltip from '~/components/ui/InfoTooltip.vue'

const store = useConsumerInsightsStore()

const stateOptions = [
  { code: 'MY-10', name: 'Selangor (National Baseline)' },
  { code: 'MY-14', name: 'Kuala Lumpur (Urban High Cost)' },
  { code: 'MY-07', name: 'Pulau Pinang' },
  { code: 'MY-01', name: 'Johor' },
  { code: 'MY-08', name: 'Perak' },
  { code: 'MY-02', name: 'Kedah' },
  { code: 'MY-03', name: 'Kelantan' },
  { code: 'MY-11', name: 'Terengganu' },
  { code: 'MY-12', name: 'Sabah' },
  { code: 'MY-13', name: 'Sarawak' },
]

const tierOptions = [
  { code: 'B40', label: 'B40 (< RM 5,250)' },
  { code: 'M40', label: 'M40 (RM 5,250 - RM 11,819)' },
  { code: 'T20', label: 'T20 (> RM 11,819)' },
]

const selectedState = ref('MY-10')
const selectedTier = ref('M40')
const isConfiguringCohort = ref(false)

onMounted(async () => {
  await store.fetchInsights()
  if (store.insights?.state_code) {
    selectedState.value = store.insights.state_code
  }
  if (store.insights?.income_tier) {
    selectedTier.value = store.insights.income_tier
  }
})

const handleCohortChange = async () => {
  await store.updateConsent({
    state_code: selectedState.value,
    income_tier: selectedTier.value,
  })
}

const toggleOptIn = async (optIn: boolean) => {
  await store.updateConsent({
    is_opted_in: optIn,
  })
}

const personaIcon = computed(() => {
  const id = store.insights?.user_persona?.id
  if (id === 'urban_commuter') return Car
  if (id === 'foodie_cafe_hopper') return Coffee
  if (id === 'family_anchor') return Home
  if (id === 'digital_native') return ShoppingBag
  return TrendingUp
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header with PDPA & Cohort Switcher -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900/60 border border-emerald-500/30 shadow-xl backdrop-blur-md">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-start space-x-3.5">
          <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center border border-emerald-500/30 shrink-0">
            <Compass class="w-5 h-5" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <h3 class="text-base sm:text-lg font-bold text-white flex items-center space-x-1.5">
                <span>Malaysian Daily Spending Behavior & Consumer Insights</span>
              </h3>
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                DOSM HIES Baseline
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Calibrated to DuitNow QR, Mamak dining, RON95 petrol, and monthly payday velocity
            </p>
          </div>
        </div>

        <!-- Controls: Refresh & Cohort Filter -->
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            @click="isConfiguringCohort = !isConfiguringCohort"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition flex items-center space-x-1.5 cursor-pointer"
          >
            <Sliders class="w-3.5 h-3.5 text-emerald-400" />
            <span>Cohort: {{ selectedState }} / {{ selectedTier }}</span>
          </button>

          <button
            type="button"
            @click="store.fetchInsights(true)"
            :disabled="store.isLoading"
            class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition disabled:opacity-50 cursor-pointer"
            title="Refresh Insights"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': store.isLoading }" />
          </button>
        </div>
      </div>

      <!-- Cohort Selector Dropdown Strip -->
      <div
        v-if="isConfiguringCohort"
        class="mt-4 pt-4 border-t border-slate-800/80 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs"
      >
        <div>
          <label class="block text-slate-400 font-medium mb-1">State / Federal Territory:</label>
          <select
            v-model="selectedState"
            @change="handleCohortChange"
            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-slate-200 focus:outline-none focus:border-emerald-500"
          >
            <option v-for="st in stateOptions" :key="st.code" :value="st.code">
              {{ st.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-slate-400 font-medium mb-1">Household Income Tier:</label>
          <select
            v-model="selectedTier"
            @change="handleCohortChange"
            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-slate-200 focus:outline-none focus:border-emerald-500"
          >
            <option v-for="t in tierOptions" :key="t.code" :value="t.code">
              {{ t.label }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Opt-Out Notice Banner if Consent is Disabled -->
    <div
      v-if="store.insights && !store.insights.consent_granted"
      class="p-6 rounded-2xl bg-amber-500/10 border border-amber-500/30 space-y-3"
    >
      <div class="flex items-start space-x-3 text-amber-300">
        <ShieldCheck class="w-5 h-5 shrink-0 mt-0.5" />
        <div class="space-y-1">
          <h4 class="text-sm font-bold">PDPA Analytics Consent Paused</h4>
          <p class="text-xs text-slate-300 leading-relaxed">
            In compliance with the Malaysian Personal Data Protection Act 2010 (PDPA), demographic peer benchmarking is paused. Your transaction data remains completely private and unshared.
          </p>
        </div>
      </div>
      <button
        type="button"
        @click="toggleOptIn(true)"
        class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition cursor-pointer"
      >
        Opt In & Enable Malaysian Peer Benchmarks
      </button>
    </div>

    <!-- Main Insights Grid -->
    <div v-else-if="store.insights?.user_persona" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- 1. Spending Persona Card -->
      <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 flex flex-col justify-between">
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Your Spending Archetype</span>
            <span class="text-[11px] font-mono text-emerald-400 font-bold bg-emerald-500/10 border border-emerald-500/30 px-2 py-0.5 rounded-full">
              {{ Math.round((store.insights.user_persona.confidence || 0.8) * 100) }}% Confidence
            </span>
          </div>

          <div class="space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
              <component :is="personaIcon" class="w-6 h-6" />
            </div>
            <h4 class="text-xl font-black text-slate-900 dark:text-white">
              {{ store.insights.user_persona.title }}
            </h4>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
              {{ store.insights.user_persona.description }}
            </p>
          </div>

          <!-- Persona Behavioral Traits -->
          <div class="space-y-2 pt-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Observed Patterns:</span>
            <div
              v-for="(trait, idx) in store.insights.user_persona.traits"
              :key="idx"
              class="text-xs text-slate-700 dark:text-slate-300 flex items-start space-x-2 bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-800"
            >
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
              <span>{{ trait }}</span>
            </div>
          </div>
        </div>

        <!-- Actionable Financial Tip -->
        <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs space-y-1">
          <div class="flex items-center space-x-1.5 text-emerald-400 font-bold">
            <Sparkles class="w-3.5 h-3.5" />
            <span>Tailored Financial Action:</span>
          </div>
          <p class="text-slate-300 text-[11px] leading-relaxed">
            {{ store.insights.user_persona.tip }}
          </p>
        </div>
      </div>

      <!-- 2. Payday Velocity & Temporal Dynamics -->
      <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 flex flex-col justify-between">
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Payday ("Gaji") Velocity</span>
            <span class="text-[11px] font-mono text-indigo-400 font-bold bg-indigo-500/10 border border-indigo-500/30 px-2 py-0.5 rounded-full">
              Monthly Cycle
            </span>
          </div>

          <div class="space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
              <Calendar class="w-6 h-6" />
            </div>
            <h4 class="text-xl font-black text-slate-900 dark:text-white">
              Surge Ratio: {{ store.insights.payday_dynamics?.surge_velocity_ratio }}x
            </h4>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
              {{ store.insights.payday_dynamics?.recommendation }}
            </p>
          </div>

          <!-- Daily Spending Velocity Bars -->
          <div class="space-y-3 pt-2 text-xs">
            <div class="space-y-1">
              <div class="flex justify-between text-slate-400">
                <span>Post-Payday Daily Avg (Day 26–5)</span>
                <span class="font-mono text-emerald-400 font-bold">
                  RM {{ store.insights.payday_dynamics?.post_payday_daily_avg?.toFixed(2) }}
                </span>
              </div>
              <div class="h-2 rounded-full bg-slate-800 overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full" style="width: 80%" />
              </div>
            </div>

            <div class="space-y-1">
              <div class="flex justify-between text-slate-400">
                <span>Mid-Month Daily Avg (Day 6–20)</span>
                <span class="font-mono text-slate-300 font-bold">
                  RM {{ store.insights.payday_dynamics?.mid_month_daily_avg?.toFixed(2) }}
                </span>
              </div>
              <div class="h-2 rounded-full bg-slate-800 overflow-hidden">
                <div class="h-full bg-indigo-500 rounded-full" style="width: 45%" />
              </div>
            </div>
          </div>
        </div>

        <!-- Privacy & Opt-Out Control -->
        <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500">
          <div class="flex items-center space-x-1 text-slate-400">
            <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
            <span>PDPA 2010 Protected (k &ge; 10)</span>
          </div>
          <button
            type="button"
            @click="toggleOptIn(false)"
            class="hover:text-rose-400 transition cursor-pointer"
          >
            Pause Sharing
          </button>
        </div>
      </div>

      <!-- 3. Demographic Peer Benchmark Card -->
      <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Demographic Peer Comparison</span>
          <span class="text-[11px] font-mono text-slate-400">
            {{ store.insights.demographic_benchmark?.cohort_label }}
          </span>
        </div>

        <div class="space-y-3.5 overflow-y-auto max-h-[380px] pr-1">
          <div
            v-for="cat in store.insights.demographic_benchmark?.categories"
            :key="cat.key"
            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800/80 space-y-2 text-xs"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-900 dark:text-white">{{ cat.name }}</span>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider border"
                :class="{
                  'bg-emerald-500/10 text-emerald-400 border-emerald-500/30': cat.status === 'healthy' || cat.status === 'optimal',
                  'bg-amber-500/10 text-amber-400 border-amber-500/30': cat.status === 'elevated',
                  'bg-rose-500/10 text-rose-400 border-rose-500/30': cat.status === 'warning',
                }"
              >
                {{ cat.status }}
              </span>
            </div>

            <!-- Comparison Percentages -->
            <div class="flex items-center justify-between font-mono text-[11px] text-slate-400">
              <span>You: <strong class="text-white">{{ cat.user_pct }}%</strong> (RM {{ cat.user_amount.toFixed(0) }})</span>
              <span>Peer Avg: <strong class="text-slate-300">{{ cat.peer_pct }}%</strong></span>
            </div>

            <!-- Visual Bar Comparison -->
            <div class="w-full h-1.5 rounded-full bg-slate-800 overflow-hidden relative">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="{
                  'bg-emerald-500': cat.status === 'healthy' || cat.status === 'optimal',
                  'bg-amber-400': cat.status === 'elevated',
                  'bg-rose-500': cat.status === 'warning',
                }"
                :style="{ width: `${Math.min(100, (cat.user_pct / (cat.peer_pct * 1.5 || 1)) * 100)}%` }"
              />
            </div>

            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-normal">
              {{ cat.advice }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
