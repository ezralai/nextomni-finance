<template>
  <div
    v-if="modelValue && goal"
    class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
  >
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-sm p-6 shadow-2xl relative space-y-5">
      <button
        @click="close"
        class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
      >
        <X class="w-5 h-5" />
      </button>

      <div class="space-y-1">
        <h3 class="text-base font-bold text-white flex items-center space-x-2">
          <Flag class="w-4 h-4 text-emerald-400" />
          <span>Deposit Savings Funds</span>
        </h3>
        <p class="text-xs text-slate-400">
          Contributing to <strong class="text-white">{{ goal.goal?.name || goal.name }}</strong>
        </p>
      </div>

      <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1 text-xs">
        <div class="flex justify-between text-slate-400">
          <span>Current Saved:</span>
          <span class="font-mono text-white font-bold">{{ goal.current_amount_formatted }}</span>
        </div>
        <div class="flex justify-between text-slate-400">
          <span>Target Goal:</span>
          <span class="font-mono text-slate-300">{{ goal.target_amount_formatted }}</span>
        </div>
        <div class="flex justify-between text-slate-400 pt-1 border-t border-slate-800">
          <span>Remaining Target:</span>
          <span class="font-mono text-emerald-400 font-bold">{{ goal.remaining_amount_formatted }}</span>
        </div>
      </div>

      <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ error }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Deposit Amount</label>
          <div class="relative">
            <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
            <input
              v-model="amount"
              type="number"
              step="0.01"
              min="0.01"
              required
              placeholder="500.00"
              class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-emerald-500"
            />
          </div>
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full py-2.5 rounded-xl text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <Check class="w-4 h-4" />
          <span>{{ isSubmitting ? 'Recording Deposit...' : 'Confirm Deposit' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Flag, AlertCircle, Check, X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    goal: any
    currencySymbol?: string
    isSubmitting?: boolean
    error?: string | null
  }>(),
  {
    currencySymbol: 'RM',
    isSubmitting: false,
    error: null,
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
  (e: 'submit', amount: string): void
}>()

const amount = ref('')

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      amount.value = ''
    }
  }
)

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const handleSubmit = () => {
  emit('submit', amount.value)
}
</script>
