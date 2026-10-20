<script setup lang="ts">
import { ref, computed } from 'vue'
import type { ChartPoint } from '~/stores/markets'

interface Props {
  points?: ChartPoint[]
  currency?: string
  range?: '1d' | '5d' | '1m' | '6m' | '1y'
  previousClose?: number
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  points: () => [],
  currency: 'MYR',
  range: '1d',
  previousClose: 0,
  isLoading: false,
})

const emit = defineEmits<{
  (e: 'change-range', range: '1d' | '5d' | '1m' | '6m' | '1y'): void
}>()

const ranges: ('1d' | '5d' | '1m' | '6m' | '1y')[] = ['1d', '5d', '1m', '6m', '1y']

// Hover / Interactive Crosshair State
const hoveredIndex = ref<number | null>(null)
const hoveredPoint = computed(() => {
  if (hoveredIndex.value === null || !props.points[hoveredIndex.value]) return null
  return props.points[hoveredIndex.value]
})

// Calculate Min & Max for SVG scaling
const stats = computed(() => {
  if (!props.points || props.points.length === 0) {
    return { min: 0, max: 100, span: 100, isPositive: true, firstPrice: 0, lastPrice: 0 }
  }

  const prices = props.points.map((p) => p.price)
  const min = Math.min(...prices)
  const max = Math.max(...prices)
  const span = max - min || 1
  const firstPrice = prices[0]
  const lastPrice = prices[prices.length - 1]
  const isPositive = lastPrice >= firstPrice

  return { min, max, span, isPositive, firstPrice, lastPrice }
})

// SVG Coordinates (Width 600, Height 220, padding 20)
const svgWidth = 600
const svgHeight = 220
const padY = 20
const usableHeight = svgHeight - padY * 2

const svgPath = computed(() => {
  if (!props.points || props.points.length === 0) return ''
  const len = props.points.length
  if (len === 1) return `M 0,${svgHeight / 2} L ${svgWidth},${svgHeight / 2}`

  const coords = props.points.map((p, i) => {
    const x = (i / (len - 1)) * svgWidth
    const normY = (p.price - stats.value.min) / stats.value.span
    const y = svgHeight - padY - normY * usableHeight
    return `${x.toFixed(1)},${y.toFixed(1)}`
  })

  return `M ${coords.join(' L ')}`
})

const areaPath = computed(() => {
  if (!svgPath.value) return ''
  return `${svgPath.value} L ${svgWidth},${svgHeight} L 0,${svgHeight} Z`
})

// Hover coordinates
const hoverCoords = computed(() => {
  if (hoveredIndex.value === null || !props.points || props.points.length === 0) return null
  const len = props.points.length
  const x = (hoveredIndex.value / (len - 1)) * svgWidth
  const p = props.points[hoveredIndex.value]
  const normY = (p.price - stats.value.min) / stats.value.span
  const y = svgHeight - padY - normY * usableHeight
  return { x, y }
})

const handleMouseMove = (e: MouseEvent) => {
  if (!props.points || props.points.length === 0) return
  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
  const mouseX = Math.max(0, Math.min(e.clientX - rect.left, rect.width))
  const ratio = mouseX / rect.width
  const idx = Math.round(ratio * (props.points.length - 1))
  hoveredIndex.value = Math.max(0, Math.min(idx, props.points.length - 1))
}

const handleMouseLeave = () => {
  hoveredIndex.value = null
}

const formatDate = (isoStr: string) => {
  if (!isoStr) return ''
  try {
    const d = new Date(isoStr)
    return d.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return isoStr
  }
}
</script>

<template>
  <div class="space-y-4 select-none">
    <!-- Header with Range Controls -->
    <div class="flex items-center justify-between gap-2">
      <!-- Hovered point live readout or overall span -->
      <div class="space-y-0.5">
        <div v-if="hoveredPoint" class="flex items-baseline space-x-2 font-mono">
          <span class="text-xl font-extrabold text-slate-900 dark:text-white tabular-nums">
            {{ currency }} {{ hoveredPoint.price.toFixed(2) }}
          </span>
          <span class="text-[11px] text-slate-500 dark:text-slate-400">
            {{ formatDate(hoveredPoint.datetime) }}
          </span>
        </div>
        <div v-else class="flex items-baseline space-x-2 font-mono">
          <span class="text-xl font-extrabold text-slate-900 dark:text-white tabular-nums">
            {{ currency }} {{ stats.lastPrice.toFixed(2) }}
          </span>
          <span
            class="text-xs font-bold"
            :class="stats.isPositive ? 'text-emerald-500' : 'text-rose-500'"
          >
            {{ stats.isPositive ? '+' : '' }}{{ (stats.lastPrice - stats.firstPrice).toFixed(2) }}
            ({{ stats.firstPrice > 0 ? (((stats.lastPrice - stats.firstPrice) / stats.firstPrice) * 100).toFixed(2) : '0.00' }}%)
          </span>
        </div>
      </div>

      <!-- Timeframe Range Switcher -->
      <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
        <button
          v-for="r in ranges"
          :key="r"
          type="button"
          @click="emit('change-range', r)"
          class="px-2.5 py-1 text-xs font-bold rounded-lg uppercase transition-all cursor-pointer"
          :class="range === r ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          {{ r }}
        </button>
      </div>
    </div>

    <!-- Interactive SVG Chart Canvas -->
    <div
      class="relative w-full h-56 rounded-2xl bg-slate-50/60 dark:bg-slate-950/40 border border-slate-200/80 dark:border-slate-800/80 overflow-hidden cursor-crosshair"
      @mousemove="handleMouseMove"
      @mouseleave="handleMouseLeave"
    >
      <!-- Loading Skeleton Overlay -->
      <div
        v-if="isLoading"
        class="absolute inset-0 z-20 flex items-center justify-center bg-slate-900/30 backdrop-blur-[1px]"
      >
        <div class="flex items-center space-x-2 text-xs font-mono font-semibold text-emerald-400 animate-pulse">
          <span>Loading historical quotes...</span>
        </div>
      </div>

      <!-- No Data Fallback -->
      <div
        v-else-if="!points || points.length === 0"
        class="absolute inset-0 flex items-center justify-center text-xs text-slate-400 font-medium"
      >
        No chart points available for this range.
      </div>

      <svg
        v-else
        :viewBox="`0 0 ${svgWidth} ${svgHeight}`"
        preserveAspectRatio="none"
        class="w-full h-full"
      >
        <defs>
          <!-- Green Gradient (Positive Period) -->
          <linearGradient id="positiveGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#10b981" stop-opacity="0.25" />
            <stop offset="100%" stop-color="#10b981" stop-opacity="0.0" />
          </linearGradient>

          <!-- Red Gradient (Negative Period) -->
          <linearGradient id="negativeGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.25" />
            <stop offset="100%" stop-color="#f43f5e" stop-opacity="0.0" />
          </linearGradient>
        </defs>

        <!-- Area Fill Under Curve -->
        <path
          :d="areaPath"
          :fill="stats.isPositive ? 'url(#positiveGrad)' : 'url(#negativeGrad)'"
        />

        <!-- Price Stroke Line -->
        <path
          :d="svgPath"
          fill="none"
          :stroke="stats.isPositive ? '#10b981' : '#f43f5e'"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />

        <!-- Interactive Hover Crosshair Line & Point -->
        <g v-if="hoverCoords">
          <line
            :x1="hoverCoords.x"
            y1="0"
            :x2="hoverCoords.x"
            :y2="svgHeight"
            stroke="#94a3b8"
            stroke-width="1"
            stroke-dasharray="3,3"
            opacity="0.7"
          />
          <circle
            :cx="hoverCoords.x"
            :cy="hoverCoords.y"
            r="4.5"
            :fill="stats.isPositive ? '#10b981' : '#f43f5e'"
            stroke="#ffffff"
            stroke-width="2"
          />
        </g>
      </svg>

      <!-- Min & Max Y-Axis Indicators -->
      <div class="absolute top-2 right-3 pointer-events-none text-[10px] font-mono text-slate-400 bg-white/70 dark:bg-slate-900/70 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-800">
        High: {{ stats.max.toFixed(2) }}
      </div>
      <div class="absolute bottom-2 right-3 pointer-events-none text-[10px] font-mono text-slate-400 bg-white/70 dark:bg-slate-900/70 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-800">
        Low: {{ stats.min.toFixed(2) }}
      </div>
    </div>
  </div>
</template>
