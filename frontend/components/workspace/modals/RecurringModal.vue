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
          <CalendarClock class="w-5 h-5 text-sky-400" />
          <span>Schedule Recurring Bill / Subscription</span>
        </h3>
        <p class="text-xs text-slate-400">Automate recurring expenses or salary inflows with cadence intervals</p>
      </div>

      <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ error }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Type Toggle -->
        <div class="flex rounded-lg bg-slate-950 p-1 border border-slate-800">
          <button
            type="button"
            @click="form.type = 'expense'"
            class="flex-1 py-1.5 text-xs font-semibold rounded-md transition"
            :class="form.type === 'expense' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-400 hover:text-white'"
          >
            Expense / Bill
          </button>
          <button
            type="button"
            @click="form.type = 'income'"
            class="flex-1 py-1.5 text-xs font-semibold rounded-md transition"
            :class="form.type === 'income' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-400 hover:text-white'"
          >
            Income / Retainer
          </button>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Description / Merchant</label>
          <input
            v-model="form.description"
            type="text"
            required
            placeholder="e.g. Unifi 300Mbps, Netflix 4K, AWS Cloud"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-sky-500"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Linked Account</label>
            <select
              v-model="form.account_id"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
            >
              <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                {{ acc.name }} ({{ acc.currency }})
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Category</label>
            <select
              v-model="form.category_id"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
            >
              <option value="">(Uncategorized)</option>
              <option
                v-for="cat in categories"
                :key="cat.id"
                :value="cat.id"
              >
                {{ cat.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Amount</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
              <input
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0.01"
                required
                placeholder="149.00"
                class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-sky-500"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Cadence / Frequency</label>
            <select
              v-model="form.frequency"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
            >
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="biweekly">Bi-weekly (14 days)</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly (3 months)</option>
              <option value="yearly">Yearly</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Start Date</label>
            <input
              v-model="form.start_date"
              type="date"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">End Date (Optional)</label>
            <input
              v-model="form.end_date"
              type="date"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
            />
          </div>
        </div>

        <div class="flex items-center space-x-2 pt-1">
          <input
            id="autoPostCheckbox"
            v-model="form.auto_post"
            type="checkbox"
            class="rounded bg-slate-950 border-slate-800 text-sky-500 focus:ring-0 focus:ring-offset-0 cursor-pointer"
          />
          <label for="autoPostCheckbox" class="text-xs text-slate-300 cursor-pointer">
            Automatically post to ledger on recurrence date
          </label>
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full py-2.5 rounded-xl text-sm font-bold bg-sky-500 hover:bg-sky-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <CalendarClock class="w-4 h-4" />
          <span>{{ isSubmitting ? 'Establishing Schedule...' : 'Confirm & Schedule' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { CalendarClock, AlertCircle, X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    accounts: any[]
    categories: any[]
    currencySymbol?: string
    isSubmitting?: boolean
    error?: string | null
    initialData?: Record<string, any>
  }>(),
  {
    currencySymbol: 'RM',
    isSubmitting: false,
    error: null,
    initialData: () => ({}),
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
  (e: 'submit', form: any): void
}>()

const form = ref({
  account_id: '',
  category_id: '',
  type: 'expense' as 'income' | 'expense',
  description: '',
  amount: '',
  frequency: 'monthly',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: '',
  auto_post: true,
  notes: '',
})

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      if (props.accounts.length > 0 && !form.value.account_id) {
        form.value.account_id = props.accounts[0]?.id || ''
      }
      if (props.categories.length > 0 && !form.value.category_id) {
        form.value.category_id = props.categories[0]?.id || ''
      }
      if (props.initialData && Object.keys(props.initialData).length > 0) {
        Object.assign(form.value, props.initialData)
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
