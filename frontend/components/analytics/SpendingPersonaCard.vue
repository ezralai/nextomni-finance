<script setup lang="ts">
import { computed } from 'vue'
import {
  Sparkles,
  Car,
  Coffee,
  Home,
  ShoppingBag,
  TrendingUp,
  Lightbulb,
  CheckCircle2,
  Compass,
  Zap,
} from 'lucide-vue-next'
import type { SpendingPersonaData } from '~/stores/budgets'

interface Props {
  persona?: SpendingPersonaData | null
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  persona: null,
  isLoading: false,
})

const persona = computed(() => props.persona)

const getPersonaIcon = (id?: string) => {
  switch (id) {
    case 'urban_commuter':
      return Car
    case 'foodie_cafe_hopper':
      return Coffee
    case 'family_anchor':
      return Home
    case 'digital_native':
      return ShoppingBag
    case 'frugal_builder':
    default:
      return TrendingUp
  }
}

const getPersonaGradient = (id?: string) => {
  switch (id) {
    case 'urban_commuter':
      return 'from-amber-500/20 via-orange-500/10 to-transparent text-amber-500 border-amber-500/20'
    case 'foodie_cafe_hopper':
      return 'from-rose-500/20 via-pink-500/10 to-transparent text-rose-500 border-rose-500/20'
    case 'family_anchor':
      return 'from-teal-500/20 via-emerald-500/10 to-transparent text-teal-500 border-teal-500/20'
    case 'digital_native':
      return 'from-indigo-500/20 via-purple-500/10 to-transparent text-indigo-500 border-indigo-500/20'
    case 'frugal_builder':
    default:
      return 'from-emerald-500/20 via-sky-500/10 to-transparent text-emerald-500 border-emerald-500/20'
  }
}

const formatPct = (ratio?: number): string => {
  if (ratio === undefined || ratio === null) return '0%'
  return `${Math.round(ratio * 100)}%`
}
</script>

<template>
  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-start space-x-3">
        <div
          class="w-10 h-10 rounded-xl bg-gradient-to-br border flex items-center justify-center flex-shrink-0 mt-0.5"
          :class="getPersonaGradient(persona?.id)"
        >
          <component :is="getPersonaIcon(persona?.id)" class="w-5 h-5" />
        </div>
        <div>
          <div class="flex items-center space-x-2">
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight">
              Malaysian Spending Persona Archetype
            </h3>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20 font-mono">
              Archetype Clustering
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Behavioral classification calibrated against Malaysian household, commuting, and lifestyle dynamics
          </p>
        </div>
      </div>

      <!-- Match Confidence Badge -->
      <div v-if="persona?.confidence" class="self-end sm:self-auto">
        <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 font-mono">
          <Sparkles class="w-3.5 h-3.5 text-indigo-500" />
          <span>{{ Math.round(persona.confidence * 100) }}% Persona Affinity</span>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-if="!persona || !persona.id"
      class="p-8 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-dashed border-slate-200 dark:border-slate-800 text-center space-y-2"
    >
      <Compass class="w-8 h-8 text-slate-400 mx-auto" />
      <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">Evaluating Behavioral Archetype</h4>
      <p class="text-[11px] text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
        Log everyday dining, grocery, transport, or retail transactions to unlock your tailored Malaysian spending persona.
      </p>
    </div>

    <!-- Active Persona Profile -->
    <div v-else class="space-y-6">
      <!-- Archetype Title & Description Card -->
      <div
        class="p-4 rounded-xl border bg-gradient-to-r"
        :class="[
          persona.id === 'frugal_builder'
            ? 'from-emerald-500/10 via-teal-500/5 to-transparent border-emerald-500/20'
            : persona.id === 'foodie_cafe_hopper'
            ? 'from-rose-500/10 via-amber-500/5 to-transparent border-rose-500/20'
            : persona.id === 'urban_commuter'
            ? 'from-amber-500/10 via-orange-500/5 to-transparent border-amber-500/20'
            : persona.id === 'family_anchor'
            ? 'from-teal-500/10 via-sky-500/5 to-transparent border-teal-500/20'
            : 'from-indigo-500/10 via-purple-500/5 to-transparent border-indigo-500/20'
        ]"
      >
        <div class="flex items-center space-x-2">
          <span class="text-base font-black text-slate-900 dark:text-white">
            {{ persona.title }}
          </span>
          <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">
            {{ persona.badge }}
          </span>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed">
          {{ persona.description }}
        </p>
      </div>

      <!-- Key Behavioral Traits & Radar Indicators Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Behavioral Radar Indicators -->
        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center space-x-1.5">
              <Zap class="w-3.5 h-3.5 text-amber-500" />
              <span>Lifestyle Outflow Ratios</span>
            </span>
            <span class="text-[10px] text-slate-400 font-mono">Normalized</span>
          </div>

          <div v-if="persona.features" class="space-y-2.5">
            <!-- Dining -->
            <div class="space-y-1">
              <div class="flex justify-between text-[11px]">
                <span class="text-slate-600 dark:text-slate-400">Dining & Café Ratio</span>
                <span class="font-bold text-slate-900 dark:text-white font-mono">{{ formatPct(persona.features.dining_ratio) }}</span>
              </div>
              <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-rose-500 h-full rounded-full" :style="{ width: `${Math.min(100, (persona.features.dining_ratio ?? 0) * 100)}%` }"></div>
              </div>
            </div>

            <!-- Groceries -->
            <div class="space-y-1">
              <div class="flex justify-between text-[11px]">
                <span class="text-slate-600 dark:text-slate-400">Groceries & Supermarket</span>
                <span class="font-bold text-slate-900 dark:text-white font-mono">{{ formatPct(persona.features.groceries_ratio) }}</span>
              </div>
              <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-teal-500 h-full rounded-full" :style="{ width: `${Math.min(100, (persona.features.groceries_ratio ?? 0) * 100)}%` }"></div>
              </div>
            </div>

            <!-- Transport -->
            <div class="space-y-1">
              <div class="flex justify-between text-[11px]">
                <span class="text-slate-600 dark:text-slate-400">Commute, Tolls & Fuel</span>
                <span class="font-bold text-slate-900 dark:text-white font-mono">{{ formatPct(persona.features.transport_ratio) }}</span>
              </div>
              <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-amber-500 h-full rounded-full" :style="{ width: `${Math.min(100, (persona.features.transport_ratio ?? 0) * 100)}%` }"></div>
              </div>
            </div>

            <!-- Savings Rate -->
            <div class="space-y-1">
              <div class="flex justify-between text-[11px]">
                <span class="text-slate-600 dark:text-slate-400">Net Surplus / Savings Rate</span>
                <span class="font-bold text-slate-900 dark:text-white font-mono">{{ formatPct(persona.features.savings_rate) }}</span>
              </div>
              <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-emerald-500 h-full rounded-full" :style="{ width: `${Math.min(100, (persona.features.savings_rate ?? 0) * 100)}%` }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Key Archetype Traits -->
        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-3">
          <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
            Observed Behavioral Patterns
          </div>

          <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
            <li
              v-for="(trait, idx) in persona.traits"
              :key="idx"
              class="flex items-start space-x-2"
            >
              <CheckCircle2 class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0 mt-0.5" />
              <span>{{ trait }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Actionable Financial Tip Box -->
      <div
        v-if="persona.financial_tip"
        class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-900 dark:text-amber-200 flex items-start space-x-3"
      >
        <Lightbulb class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
        <div class="space-y-1">
          <span class="font-bold uppercase tracking-wider text-[10px] text-amber-700 dark:text-amber-400 block font-mono">
            Targeted Financial Recommendation
          </span>
          <p class="leading-relaxed">
            {{ persona.financial_tip }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
