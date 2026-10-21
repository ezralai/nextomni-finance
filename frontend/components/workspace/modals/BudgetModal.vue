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
          <Target class="w-5 h-5 text-indigo-400" />
          <span>Set Category Spending Target</span>
        </h3>
        <p class="text-xs text-slate-400">Establish budget caps with threshold progress tracking</p>
      </div>

      <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ error }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- 1-Click Budget Starter Presets for New Users -->
        <div class="space-y-1.5 p-3 rounded-xl bg-slate-950/60 border border-slate-800">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-indigo-400 flex items-center space-x-1">
              <Sparkles class="w-3.5 h-3.5" />
              <span>1-Click Starter Presets</span>
            </span>
            <span class="text-[10px] text-slate-400">Pre-fills category & limit</span>
          </div>
          <div class="flex flex-wrap gap-1.5 pt-1">
            <button
              v-for="preset in popularBudgetPresets"
              :key="preset.name"
              type="button"
              @click="applyPreset(preset)"
              class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-900 hover:bg-indigo-500/10 text-slate-300 hover:text-indigo-400 border border-slate-800 hover:border-indigo-500/40 transition flex items-center space-x-1.5 cursor-pointer"
            >
              <span>{{ preset.icon }}</span>
              <span>{{ preset.name }}</span>
              <span class="text-[10px] font-mono text-indigo-300 font-bold">({{ currencySymbol }} {{ preset.amount }})</span>
            </button>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Expense Category</label>
          <select
            v-model="form.category_id"
            required
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
          >
            <option
              v-for="cat in expenseCategories"
              :key="cat.id"
              :value="cat.id"
            >
              {{ cat.name }} (Parent & Subcategories)
            </option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Spending Cap Limit</label>
          <div class="relative">
            <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
            <input
              v-model="form.amount"
              type="number"
              step="0.01"
              min="0.01"
              required
              placeholder="1000.00"
              class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Start Date</label>
            <input
              v-model="form.start_date"
              type="date"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
            />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">End Date</label>
            <input
              v-model="form.end_date"
              type="date"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Optional Notes</label>
          <input
            v-model="form.notes"
            type="text"
            placeholder="e.g. Monthly groceries and food cap"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
          />
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full py-2.5 rounded-lg text-xs font-bold bg-indigo-500 hover:bg-indigo-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <RefreshCw v-if="isSubmitting" class="w-4 h-4 animate-spin" />
          <span>{{ isSubmitting ? 'Establishing Limit...' : 'Confirm & Set Budget Cap' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Target, Sparkles, AlertCircle, RefreshCw, X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    categories: any[]
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

const getMonthDates = () => {
  const now = new Date()
  const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
  return { firstDay, lastDay }
}

const expenseCategories = computed(() => {
  return props.categories.filter((c: any) => c.type === 'expense')
})

const popularBudgetPresets = [
  { icon: '🍔', name: 'Food & Dining', slug: 'food-dining', amount: 800 },
  { icon: '🛒', name: 'Groceries', slug: 'groceries-supermarket', amount: 500 },
  { icon: '🚗', name: 'Transportation', slug: 'transportation', amount: 350 },
  { icon: '💡', name: 'Utilities & Bills', slug: 'housing-utilities', amount: 450 },
  { icon: '🛍️', name: 'Lifestyle & Shopping', slug: 'lifestyle-shopping', amount: 300 },
]

const { firstDay, lastDay } = getMonthDates()
const form = ref({
  category_id: '',
  amount: '',
  period: 'monthly',
  start_date: firstDay,
  end_date: lastDay,
  notes: '',
})

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      const dates = getMonthDates()
      const firstExpense = expenseCategories.value[0]?.id || ''
      form.value = {
        category_id: firstExpense,
        amount: '',
        period: 'monthly',
        start_date: dates.firstDay,
        end_date: dates.lastDay,
        notes: '',
      }
    }
  }
)

const applyPreset = (preset: typeof popularBudgetPresets[0]) => {
  let cat = props.categories.find((c: any) => c.slug === preset.slug)
  if (!cat) {
    for (const parent of props.categories) {
      const match = parent.children?.find((ch: any) => ch.slug === preset.slug)
      if (match) {
        cat = parent
        break
      }
    }
  }
  if (!cat) {
    cat = props.categories.find((c: any) =>
      c.name.toLowerCase().includes(preset.name.toLowerCase().slice(0, 4))
    )
  }
  if (cat) {
    form.value.category_id = cat.id
  }
  form.value.amount = String(preset.amount)
}

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const handleSubmit = () => {
  emit('submit', { ...form.value })
}
</script>
