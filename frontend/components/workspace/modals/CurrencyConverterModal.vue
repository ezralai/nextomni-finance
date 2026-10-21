<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
  >
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
      <button
        @click="close"
        class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
      >
        <X class="w-5 h-5" />
      </button>

      <div class="space-y-1">
        <h3 class="text-lg font-bold text-white flex items-center space-x-2">
          <ArrowLeftRight class="w-5 h-5 text-sky-400" />
          <span>Foreign Exchange (FX) Rate Engine</span>
        </h3>
        <p class="text-xs text-slate-400">Strict 6-decimal arbitrary precision currency conversion</p>
      </div>

      <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ error }}</span>
      </div>

      <form @submit.prevent="runConversion" class="space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Amount to Convert</label>
          <input
            v-model.number="form.amount"
            @input="runConversion"
            type="number"
            step="0.01"
            min="0.01"
            required
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono focus:outline-none focus:border-sky-500"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">From</label>
            <select
              v-model="form.from"
              @change="runConversion"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
            >
              <option value="USD">USD ($)</option>
              <option value="MYR">MYR (RM)</option>
              <option value="SGD">SGD (S$)</option>
              <option value="EUR">EUR (€)</option>
              <option value="GBP">GBP (£)</option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">To</label>
            <select
              v-model="form.to"
              @change="runConversion"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
            >
              <option value="MYR">MYR (RM)</option>
              <option value="SGD">SGD (S$)</option>
              <option value="USD">USD ($)</option>
              <option value="EUR">EUR (€)</option>
              <option value="GBP">GBP (£)</option>
            </select>
          </div>
        </div>
      </form>

      <!-- Live Conversion Calculation Result Card -->
      <div v-if="conversionResult" class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 space-y-2">
        <div class="text-xs text-slate-400 flex items-center justify-between">
          <span>Exchange Rate (6 Decimals)</span>
          <span class="font-mono text-sky-400 font-bold">1 {{ conversionResult.from_currency }} = {{ conversionResult.rate }} {{ conversionResult.to_currency }}</span>
        </div>
        <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between">
          <span class="text-xs text-slate-400">Converted Value</span>
          <span class="text-xl font-extrabold font-mono text-white">
            {{ conversionResult.converted_formatted }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { ArrowLeftRight, AlertCircle, X } from 'lucide-vue-next'
import { useCurrencyStore, type ConversionResult } from '~/stores/currencies'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    defaultToCurrency?: string
  }>(),
  {
    defaultToCurrency: 'MYR',
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
}>()

const currencyStore = useCurrencyStore()

const form = ref({
  amount: 100,
  from: 'USD',
  to: props.defaultToCurrency || 'MYR',
})

const conversionResult = ref<ConversionResult | null>(null)
const error = ref<string | null>(null)

const runConversion = async () => {
  if (!form.value.amount || form.value.amount <= 0) return
  error.value = null
  try {
    conversionResult.value = await currencyStore.convert(
      form.value.amount,
      form.value.from,
      form.value.to
    )
  } catch (err: any) {
    error.value = err?.data?.message || err?.message || 'Conversion failed.'
  }
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      form.value.from = 'USD'
      form.value.to = props.defaultToCurrency || 'MYR'
      form.value.amount = 100
      conversionResult.value = null
      error.value = null
      runConversion()
    }
  }
)

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}
</script>
