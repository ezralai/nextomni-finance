<script setup lang="ts">
import { computed } from 'vue'
import {
  TrendingUp,
  TrendingDown,
  Activity,
  ArrowRight,
  Globe,
  Clock,
} from 'lucide-vue-next'
import { useMarketStore, type MarketIndex } from '~/stores/markets'

const emit = defineEmits<{
  (e: 'select-index', symbol: string): void
  (e: 'view-markets'): void
}>()

const marketStore = useMarketStore()

const formatNumber = (val: number, decimals: number = 2): string => {
  if (val === null || val === undefined) return '0.00'
  return val.toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })
}
</script>

<template>
  <div
    class="border-b border-slate-200 dark:border-slate-800/80 bg-slate-100/70 dark:bg-slate-900/40 backdrop-blur-md overflow-hidden text-xs select-none transition-colors duration-200"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-4">
      <!-- Left Label -->
      <div class="flex items-center space-x-2 flex-shrink-0 text-slate-500 dark:text-slate-400 font-semibold text-[11px] uppercase tracking-wider">
        <Activity class="w-3.5 h-3.5 text-emerald-500 animate-pulse" />
        <span class="hidden sm:inline">Markets</span>
      </div>

      <!-- Indices Horizontal Scroll Strip -->
      <div class="flex items-center space-x-4 sm:space-x-6 overflow-x-auto no-scrollbar flex-1 py-0.5">
        <div
          v-if="marketStore.isLoadingIndices && marketStore.indices.length === 0"
          class="flex items-center space-x-4 animate-pulse text-slate-400 font-mono text-[11px]"
        >
          <span>Loading live market benchmarks...</span>
        </div>

        <button
          v-for="idx in marketStore.indices"
          :key="idx.symbol"
          @click="emit('select-index', idx.symbol)"
          class="flex items-center space-x-2 hover:bg-white/60 dark:hover:bg-slate-800/60 px-2.5 py-1 rounded-lg transition-all cursor-pointer flex-shrink-0 group"
          :title="`${idx.name} (${idx.region}) • ${idx.market_status?.session || 'Market Closed'}`"
        >
          <!-- Market Open / Closed Indicator Dot -->
          <span
            class="w-1.5 h-1.5 rounded-full flex-shrink-0"
            :class="idx.market_status?.is_open ? 'bg-emerald-500 shadow-sm shadow-emerald-500/50' : 'bg-slate-400 dark:bg-slate-600'"
          />

          <!-- Index Name & Currency -->
          <span class="font-bold text-slate-700 dark:text-slate-200 group-hover:text-emerald-500 dark:group-hover:text-emerald-400 transition-colors">
            {{ idx.name }}
          </span>

          <!-- Price & Change -->
          <div class="flex items-center space-x-1.5 font-mono">
            <span class="font-semibold text-slate-900 dark:text-white tabular-nums">
              {{ formatNumber(idx.price) }}
            </span>

            <span
              class="flex items-center space-x-0.5 text-[10px] font-bold px-1.5 py-0.2 rounded"
              :class="idx.is_positive ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' : 'text-rose-600 dark:text-rose-400 bg-rose-500/10'"
            >
              <TrendingUp v-if="idx.is_positive" class="w-2.5 h-2.5 inline-block" />
              <TrendingDown v-else class="w-2.5 h-2.5 inline-block" />
              <span>{{ idx.is_positive ? '+' : '' }}{{ formatNumber(idx.change_percent) }}%</span>
            </span>
          </div>
        </button>
      </div>

      <!-- Right Quick-Link to Markets Tab -->
      <button
        @click="emit('view-markets')"
        class="hidden lg:flex items-center space-x-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 transition-colors flex-shrink-0 cursor-pointer"
      >
        <span>Stock Hub</span>
        <ArrowRight class="w-3 h-3" />
      </button>
    </div>
  </div>
</template>
