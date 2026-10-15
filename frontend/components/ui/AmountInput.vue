<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle } from 'lucide-vue-next'

interface Props {
  modelValue: number | null
  currency?: string
  label?: string
  placeholder?: string
  maxBalance?: number | null
  required?: boolean
  disabled?: boolean
  showQuickPercent?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  currency: 'USD',
  label: 'Amount',
  placeholder: '0.00',
  maxBalance: null,
  required: false,
  disabled: false,
  showQuickPercent: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void
}>()

const currencySymbol = computed(() => {
  const map: Record<string, string> = {
    USD: '$',
    EUR: '€',
    GBP: '£',
    JPY: '¥',
    CAD: 'CA$',
    AUD: 'AU$',
    SGD: 'S$',
    MYR: 'RM',
    INR: '₹',
  }
  return map[props.currency.toUpperCase()] || props.currency
})

const isInsufficient = computed(() => {
  if (props.maxBalance === null || props.maxBalance === undefined) return false
  if (!props.modelValue || props.modelValue <= 0) return false
  return props.modelValue > props.maxBalance
})

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const rawValue = target.value.replace(/[^0-9.]/g, '')

  // Prevent multiple decimal points
  const parts = rawValue.split('.')
  let sanitized = parts[0]
  if (parts.length > 1) {
    sanitized += '.' + parts[1].slice(0, 2)
  }

  const num = parseFloat(sanitized)
  emit('update:modelValue', isNaN(num) ? null : num)
}

const applyPercentage = (percent: number) => {
  if (!props.maxBalance || props.maxBalance <= 0) return
  const computedAmount = Math.round(props.maxBalance * percent * 100) / 100
  emit('update:modelValue', computedAmount)
}
</script>

<template>
  <div class="space-y-1.5 w-full">
    <div class="flex items-center justify-between">
      <label v-if="label" class="text-xs font-semibold text-slate-700 dark:text-slate-300">
        {{ label }}
        <span v-if="required" class="text-rose-500">*</span>
      </label>
      <span
        v-if="maxBalance !== null && maxBalance !== undefined"
        class="text-[11px] font-mono text-slate-500 dark:text-slate-400"
      >
        Available: {{ currencySymbol }}{{ maxBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
      </span>
    </div>

    <div class="relative">
      <div
        class="absolute left-3.5 top-1/2 -translate-y-1/2 font-mono font-bold text-sm pointer-events-none select-none transition-colors"
        :class="isInsufficient ? 'text-rose-500' : 'text-slate-400 dark:text-slate-500'"
      >
        {{ currencySymbol }}
      </div>

      <input
        :value="modelValue ?? ''"
        @input="handleInput"
        type="text"
        inputmode="decimal"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        class="w-full pl-9 pr-16 py-2.5 rounded-xl border text-base font-mono font-bold transition-all focus:outline-none focus:ring-2 tabular-nums"
        :class="[
          isInsufficient
            ? 'border-rose-400 bg-rose-50/50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 focus:border-rose-500 focus:ring-rose-500/20'
            : 'border-slate-300 dark:border-slate-700/80 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-600 focus:border-emerald-500 focus:ring-emerald-500/20 dark:focus:ring-emerald-500/30',
          disabled ? 'opacity-60 cursor-not-allowed bg-slate-100 dark:bg-slate-900' : '',
        ]"
      />

      <div class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-mono font-bold text-slate-400 dark:text-slate-500 uppercase pointer-events-none select-none">
        {{ currency }}
      </div>
    </div>

    <!-- Quick Percent Chips for Available Balances -->
    <div
      v-if="showQuickPercent && maxBalance && maxBalance > 0"
      class="flex items-center gap-1.5 pt-1"
    >
      <button
        v-for="p in [0.25, 0.5, 0.75, 1.0]"
        :key="p"
        type="button"
        @click="applyPercentage(p)"
        class="px-2 py-0.5 rounded-md text-[10px] font-mono font-semibold border transition cursor-pointer"
        :class="
          modelValue === Math.round(maxBalance * p * 100) / 100
            ? 'bg-emerald-500 text-slate-950 border-emerald-500 shadow-sm'
            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700 hover:border-emerald-500/50'
        "
      >
        {{ p === 1.0 ? 'MAX' : `${p * 100}%` }}
      </button>
    </div>

    <!-- Error message for balance validation -->
    <div
      v-if="isInsufficient"
      class="flex items-center space-x-1.5 text-xs text-rose-500 dark:text-rose-400 pt-0.5"
    >
      <AlertCircle class="w-3.5 h-3.5 flex-shrink-0" />
      <span>Amount exceeds available account balance.</span>
    </div>
  </div>
</template>
