<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  X,
  ArrowRightLeft,
  AlertCircle,
  RefreshCw,
} from 'lucide-vue-next'
import { useAccountStore } from '~/stores/accounts'
import { useTransactionStore } from '~/stores/transactions'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import AmountInput from '~/components/ui/AmountInput.vue'
import InstitutionLogo from '~/components/ui/InstitutionLogo.vue'

interface Props {
  isOpen: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'created'): void
  (e: 'open-add-account'): void
}>()

const accountStore = useAccountStore()
const transactionStore = useTransactionStore()
const authStore = useAuthStore()
const toast = useToast()

const transferError = ref<string | null>(null)

const newTransferForm = ref({
  source_account_id: '' as string | number,
  destination_account_id: '' as string | number,
  amount: 0,
  fee: 0,
  transacted_at: new Date().toISOString().slice(0, 16),
  notes: '',
})

const currencySymbol = computed(() => {
  const cur = authStore.user?.base_currency || 'MYR'
  switch (cur) {
    case 'USD':
      return '$'
    case 'SGD':
      return 'S$'
    case 'EUR':
      return '€'
    case 'GBP':
      return '£'
    default:
      return 'RM'
  }
})

const selectedTransferSourceAccount = computed(() => {
  return accountStore.accounts.find(a => String(a.id) === String(newTransferForm.value.source_account_id))
})

const selectedTransferDestAccount = computed(() => {
  return accountStore.accounts.find(a => String(a.id) === String(newTransferForm.value.destination_account_id))
})

const resetForm = () => {
  if (accountStore.accounts.length > 0) {
    newTransferForm.value.source_account_id = accountStore.accounts[0].id
    newTransferForm.value.destination_account_id = accountStore.accounts[1]?.id || ''
  }
  newTransferForm.value.amount = 0
  newTransferForm.value.fee = 0
  newTransferForm.value.transacted_at = new Date().toISOString().slice(0, 16)
  newTransferForm.value.notes = ''
  transferError.value = null
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      if (accountStore.accounts.length < 2) {
        emit('close')
        emit('open-add-account')
        toast.info('Multiple Accounts Required', 'Please create at least two accounts to execute transfers between them.')
        return
      }
      resetForm()
    }
  }
)

const handleCreateTransfer = async () => {
  transferError.value = null
  try {
    await transactionStore.createTransfer({
      source_account_id: String(newTransferForm.value.source_account_id),
      destination_account_id: String(newTransferForm.value.destination_account_id),
      amount: Number(newTransferForm.value.amount),
      fee: Number(newTransferForm.value.fee || 0),
      transacted_at: newTransferForm.value.transacted_at,
      notes: newTransferForm.value.notes || undefined,
    })
    emit('created')
    emit('close')
    toast.success(
      'Transfer Completed',
      `Transferred ${selectedTransferSourceAccount.value?.currency || ''} ${newTransferForm.value.amount} from "${selectedTransferSourceAccount.value?.name}" to "${selectedTransferDestAccount.value?.name}"`
    )
  } catch (err: any) {
    transferError.value = err?.data?.message || err?.message || 'Failed to execute transfer.'
    toast.error('Transfer Failed', transferError.value)
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
          <ArrowRightLeft class="w-5 h-5 text-sky-400" />
          <span>Transfer Funds (Double-Entry)</span>
        </h3>
        <p class="text-xs text-slate-400">Atomic balance shift between two accounts</p>
      </div>

      <div v-if="transferError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ transferError }}</span>
      </div>

      <form @submit.prevent="handleCreateTransfer" class="space-y-4">
        <!-- Visual Transfer Route Preview with Authentic Logos -->
        <div v-if="selectedTransferSourceAccount" class="p-3 rounded-xl bg-slate-950/80 border border-slate-800/80 flex items-center justify-between gap-3 shadow-inner">
          <div class="flex items-center space-x-2.5 min-w-0 flex-1">
            <InstitutionLogo
              :code="selectedTransferSourceAccount?.institution?.code || selectedTransferSourceAccount?.account_type"
              :name="selectedTransferSourceAccount?.institution?.name || selectedTransferSourceAccount?.name"
              :logo-url="selectedTransferSourceAccount?.institution?.logo_url"
              size="sm"
              interactive
            />
            <div class="min-w-0">
              <div class="text-[9px] text-slate-400 uppercase tracking-wider font-semibold font-mono">Source Account</div>
              <div class="text-xs font-bold text-white truncate">{{ selectedTransferSourceAccount?.name }}</div>
            </div>
          </div>

          <div class="w-7 h-7 rounded-full bg-sky-500/20 text-sky-400 flex items-center justify-center flex-shrink-0 border border-sky-500/30">
            <ArrowRightLeft class="w-3.5 h-3.5" />
          </div>

          <div class="flex items-center space-x-2.5 min-w-0 flex-1 justify-end text-right">
            <div class="min-w-0">
              <div class="text-[9px] text-slate-400 uppercase tracking-wider font-semibold font-mono">Destination</div>
              <div class="text-xs font-bold text-white truncate">{{ selectedTransferDestAccount?.name || 'Select...' }}</div>
            </div>
            <InstitutionLogo
              v-if="selectedTransferDestAccount"
              :code="selectedTransferDestAccount?.institution?.code || selectedTransferDestAccount?.account_type"
              :name="selectedTransferDestAccount?.institution?.name || selectedTransferDestAccount?.name"
              :logo-url="selectedTransferDestAccount?.institution?.logo_url"
              size="sm"
              interactive
            />
            <div v-else class="w-7 h-7 rounded-md border border-dashed border-slate-700 flex items-center justify-center text-slate-600 text-xs">?</div>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">From Account (Source)</label>
          <select
            v-model="newTransferForm.source_account_id"
            required
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
          >
            <option v-for="acc in accountStore.accounts" :key="acc.id" :value="acc.id">
              {{ acc.name }} ({{ acc.currency }}) &mdash; Balance: {{ acc.current_balance_formatted }}
            </option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">To Account (Destination)</label>
          <select
            v-model="newTransferForm.destination_account_id"
            required
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
          >
            <option
              v-for="acc in accountStore.accounts.filter(a => a.id !== newTransferForm.source_account_id)"
              :key="acc.id"
              :value="acc.id"
            >
              {{ acc.name }} ({{ acc.currency }}) &mdash; Balance: {{ acc.current_balance_formatted }}
            </option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <AmountInput
            v-model="newTransferForm.amount"
            label="Transfer Amount"
            :currency="selectedTransferSourceAccount?.currency || 'USD'"
            :max-balance="selectedTransferSourceAccount?.current_balance ?? null"
            :show-quick-percent="true"
            required
          />

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Transfer Fee</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 font-mono font-bold text-sm text-slate-400 dark:text-slate-500">{{ currencySymbol }}</span>
              <input
                v-model="newTransferForm.fee"
                type="number"
                step="0.01"
                min="0.00"
                placeholder="0.00"
                class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700/80 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-mono text-sm placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:ring-2 focus:border-sky-500 focus:ring-sky-500/20"
              />
            </div>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Date & Time</label>
          <input
            v-model="newTransferForm.transacted_at"
            type="datetime-local"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
          />
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Notes / Purpose</label>
          <input
            v-model="newTransferForm.notes"
            type="text"
            placeholder="e.g. E-wallet top-up / Credit card settlement"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-sky-500"
          />
        </div>

        <button
          type="submit"
          :disabled="transactionStore.isSubmitting"
          class="w-full py-2.5 rounded-lg text-xs font-bold bg-sky-500 hover:bg-sky-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <RefreshCw v-if="transactionStore.isSubmitting" class="w-4 h-4 animate-spin" />
          <span>{{ transactionStore.isSubmitting ? 'Executing Transfer...' : 'Confirm & Execute Transfer' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>
