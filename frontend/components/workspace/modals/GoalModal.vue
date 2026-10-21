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
          <Trophy class="w-5 h-5 text-amber-400" />
          <span>Create Financial Savings Goal</span>
        </h3>
        <p class="text-xs text-slate-400">Track target accumulation with automated monthly savings pace calculations</p>
      </div>

      <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ error }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Goal Name</label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Emergency Fund (6 Months), House Down Payment"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Target Amount</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
              <input
                v-model="form.target_amount"
                type="number"
                step="0.01"
                min="0.01"
                required
                placeholder="30000.00"
                class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-amber-500"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Initial Saved</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
              <input
                v-model="form.current_amount"
                type="number"
                step="0.01"
                min="0"
                placeholder="5000.00"
                class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-amber-500"
              />
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Target Deadline</label>
            <input
              v-model="form.target_date"
              type="date"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Accent Color</label>
            <select
              v-model="form.color"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            >
              <option value="#10b981">Emerald Green</option>
              <option value="#0ea5e9">Sky Blue</option>
              <option value="#f59e0b">Amber Gold</option>
              <option value="#8b5cf6">Purple</option>
              <option value="#ec4899">Pink</option>
            </select>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Dedicated Account (Optional)</label>
          <select
            v-model="form.account_id"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
          >
            <option value="">(Unlinked / Portfolio-wide)</option>
            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
              {{ acc.name }} ({{ acc.currency }})
            </option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Notes / Strategy</label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="e.g. Save 20% of monthly salary into fixed deposit"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
          ></textarea>
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full py-2.5 rounded-xl text-sm font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <Trophy class="w-4 h-4" />
          <span>{{ isSubmitting ? 'Establishing Goal...' : 'Establish Goal' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Trophy, AlertCircle, X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    accounts: any[]
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
  (e: 'submit', form: any): void
}>()

const form = ref({
  name: '',
  target_amount: '',
  current_amount: '',
  target_date: '',
  color: '#10b981',
  account_id: '',
  description: '',
})

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      form.value = {
        name: '',
        target_amount: '',
        current_amount: '',
        target_date: '',
        color: '#10b981',
        account_id: '',
        description: '',
      }
    }
  }
)

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const handleSubmit = () => {
  emit('submit', { ...form.value })
}
</script>
