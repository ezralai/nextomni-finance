<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  X,
  ReceiptText,
  AlertCircle,
  RefreshCw,
} from 'lucide-vue-next'
import { useAccountStore } from '~/stores/accounts'
import { useTransactionStore } from '~/stores/transactions'
import { useToast } from '~/composables/useToast'
import AmountInput from '~/components/ui/AmountInput.vue'

interface Props {
  isOpen: boolean
  defaultType?: 'expense' | 'income'
}

const props = withDefaults(defineProps<Props>(), {
  defaultType: 'expense',
})

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'created'): void
  (e: 'open-add-account'): void
}>()

const accountStore = useAccountStore()
const transactionStore = useTransactionStore()
const toast = useToast()

const transactionError = ref<string | null>(null)

const newTransactionForm = ref({
  account_id: '' as string | number,
  category_id: '' as string | number,
  type: props.defaultType as 'expense' | 'income',
  amount: 0,
  description: '',
  transacted_at: new Date().toISOString().slice(0, 16),
  notes: '',
})

const selectedTransactionAccount = computed(() => {
  return accountStore.accounts.find(a => String(a.id) === String(newTransactionForm.value.account_id))
})

const resetForm = () => {
  if (accountStore.accounts.length > 0) {
    newTransactionForm.value.account_id = accountStore.accounts[0].id
  }
  newTransactionForm.value.type = props.defaultType
  newTransactionForm.value.category_id = ''
  newTransactionForm.value.amount = 0
  newTransactionForm.value.description = ''
  newTransactionForm.value.transacted_at = new Date().toISOString().slice(0, 16)
  newTransactionForm.value.notes = ''
  transactionError.value = null
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      if (accountStore.accounts.length === 0) {
        emit('close')
        emit('open-add-account')
        toast.info('Account Required', 'Please set up an account before logging your first transaction.')
        return
      }
      resetForm()
    }
  }
)

watch(
  () => props.defaultType,
  (t) => {
    newTransactionForm.value.type = t
  }
)

const handleCreateTransaction = async () => {
  transactionError.value = null
  try {
    await transactionStore.createTransaction({
      account_id: String(newTransactionForm.value.account_id),
      category_id: newTransactionForm.value.category_id ? String(newTransactionForm.value.category_id) : undefined,
      type: newTransactionForm.value.type,
      amount: Number(newTransactionForm.value.amount),
      description: newTransactionForm.value.description,
      transacted_at: newTransactionForm.value.transacted_at,
      notes: newTransactionForm.value.notes || undefined,
    })
    emit('created')
    emit('close')
    toast.success(
      'Transaction Logged',
      `Recorded ${newTransactionForm.value.type === 'income' ? 'income' : 'expense'} of ${selectedTransactionAccount.value?.currency || ''} ${newTransactionForm.value.amount}`
    )
  } catch (err: any) {
    transactionError.value = err?.data?.message || err?.message || 'Failed to record transaction.'
    toast.error('Transaction Failed', transactionError.value)
  }
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
  >
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
      <button
        @click="emit('close')"
        class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
      >
        <X class="w-5 h-5" />
      </button>

      <div class="space-y-1">
        <h3 class="text-lg font-bold text-white flex items-center space-x-2">
          <ReceiptText class="w-5 h-5 text-emerald-400" />
          <span>Record {{ newTransactionForm.type === 'income' ? 'Income' : 'Expense' }}</span>
        </h3>
        <p class="text-xs text-slate-400">Atomic ledger posting with balance adjustment</p>
      </div>

      <div class="flex rounded-lg bg-slate-800/60 p-1 border border-slate-700/50">
        <button
          @click="newTransactionForm.type = 'expense'"
          type="button"
          class="flex-1 py-1.5 text-xs font-semibold rounded-md transition text-center cursor-pointer"
          :class="newTransactionForm.type === 'expense' ? 'bg-rose-500/30 text-rose-300 border border-rose-500/40 shadow' : 'text-slate-400 hover:text-white'"
        >
          Expense (Outflow)
        </button>
        <button
          @click="newTransactionForm.type = 'income'"
          type="button"
          class="flex-1 py-1.5 text-xs font-semibold rounded-md transition text-center cursor-pointer"
          :class="newTransactionForm.type === 'income' ? 'bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 shadow' : 'text-slate-400 hover:text-white'"
        >
          Income (Inflow)
        </button>
      </div>

      <div v-if="transactionError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ transactionError }}</span>
      </div>

      <form @submit.prevent="handleCreateTransaction" class="space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Financial Account</label>
          <select
            v-model="newTransactionForm.account_id"
            required
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
          >
            <option v-for="acc in accountStore.accounts" :key="acc.id" :value="acc.id">
              {{ acc.name }} ({{ acc.currency }}) &mdash; Balance: {{ acc.current_balance_formatted }}
            </option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Category</label>
          <select
            v-model="newTransactionForm.category_id"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
          >
            <option value="">(No Category / Uncategorized)</option>
            <optgroup
              v-for="cat in transactionStore.categories.filter(c => c.type === newTransactionForm.type || c.type === 'expense')"
              :key="cat.id"
              :label="cat.name"
            >
              <option :value="cat.id">{{ cat.name }} (General)</option>
              <option
                v-for="sub in cat.children || []"
                :key="sub.id"
                :value="sub.id"
              >
                &nbsp;&nbsp;↳ {{ sub.name }}
              </option>
            </optgroup>
          </select>
        </div>

        <AmountInput
          v-model="newTransactionForm.amount"
          :currency="selectedTransactionAccount?.currency || 'USD'"
          :max-balance="newTransactionForm.type === 'expense' ? (selectedTransactionAccount?.current_balance ?? null) : null"
          :show-quick-percent="newTransactionForm.type === 'expense'"
          required
        />

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Description</label>
          <input
            v-model="newTransactionForm.description"
            type="text"
            required
            placeholder="e.g. Weekly Groceries / Monthly Salary"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
          />
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Date & Time</label>
          <input
            v-model="newTransactionForm.transacted_at"
            type="datetime-local"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
          />
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Optional Notes</label>
          <input
            v-model="newTransactionForm.notes"
            type="text"
            placeholder="e.g. Receipt #9821"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
          />
        </div>

        <button
          type="submit"
          :disabled="transactionStore.isSubmitting"
          class="w-full py-2.5 rounded-lg text-xs font-bold transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
          :class="newTransactionForm.type === 'income' ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950' : 'bg-rose-500 hover:bg-rose-400 text-white'"
        >
          <RefreshCw v-if="transactionStore.isSubmitting" class="w-4 h-4 animate-spin" />
          <span>{{ transactionStore.isSubmitting ? 'Posting to Ledger...' : `Confirm & Record ${newTransactionForm.type === 'income' ? 'Income' : 'Expense'}` }}</span>
        </button>
      </form>
    </div>
  </div>
</template>
