<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  X,
  Plus,
  Sparkles,
  AlertCircle,
  RefreshCw,
} from 'lucide-vue-next'
import { useAccountStore } from '~/stores/accounts'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import InstitutionLogo from '~/components/ui/InstitutionLogo.vue'

interface Props {
  isOpen: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'created', accountName: string): void
}>()

const accountStore = useAccountStore()
const authStore = useAuthStore()
const toast = useToast()

const isAccountSubmitting = ref(false)
const accountError = ref<string | null>(null)

const newAccountForm = ref({
  institution_id: '',
  name: '',
  account_type: 'bank',
  currency: 'MYR',
  opening_balance: 0,
  account_number_masked: '',
  notes: '',
})

// 1-Click Popular Account Presets for Quick Setup
const popularAccountPresets = [
  { flag: '🇲🇾', label: 'Maybank Savings', code: 'MBB', type: 'bank', currency: 'MYR' },
  { flag: '🇲🇾', label: 'Touch \'n Go eWallet', code: 'TNG', type: 'e_wallet', currency: 'MYR' },
  { flag: '🇲🇾', label: 'CIMB Current', code: 'CIMB', type: 'bank', currency: 'MYR' },
  { flag: '💵', label: 'Cash Wallet', code: 'MBB', type: 'cash', currency: 'MYR' },
  { flag: '🇺🇸', label: 'US Brokerage', code: 'MBB', type: 'investment', currency: 'USD' },
  { flag: '🇸🇬', label: 'Singapore DBS', code: 'CIMB', type: 'bank', currency: 'SGD' },
]

const resetForm = () => {
  if (accountStore.institutions.length > 0) {
    newAccountForm.value.institution_id = accountStore.institutions[0].id
  }
  newAccountForm.value.currency = authStore.user?.base_currency || 'MYR'
  newAccountForm.value.name = ''
  newAccountForm.value.account_type = 'bank'
  newAccountForm.value.opening_balance = 0
  newAccountForm.value.account_number_masked = ''
  newAccountForm.value.notes = ''
  accountError.value = null
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      resetForm()
    }
  }
)

const applyAccountPreset = (preset: typeof popularAccountPresets[0]) => {
  const matchInst = accountStore.institutions.find(i => i.code === preset.code) || accountStore.institutions[0]
  if (matchInst) {
    newAccountForm.value.institution_id = matchInst.id
  }
  newAccountForm.value.name = preset.label
  newAccountForm.value.account_type = preset.type
  newAccountForm.value.currency = preset.currency
}

const handleCreateAccount = async () => {
  isAccountSubmitting.value = true
  accountError.value = null
  const accountName = newAccountForm.value.name
  try {
    await accountStore.createAccount({
      ...newAccountForm.value,
      opening_balance: Number(newAccountForm.value.opening_balance),
    })
    emit('created', accountName)
    emit('close')
    toast.success('Account Created', `Successfully opened account "${accountName}"`)
  } catch (err: any) {
    accountError.value = err?.data?.message || err?.message || 'Failed to create account.'
    toast.error('Account Creation Failed', accountError.value)
  } finally {
    isAccountSubmitting.value = false
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
          <Plus class="w-5 h-5 text-emerald-400" />
          <span>Add Financial Account</span>
        </h3>
        <p class="text-xs text-slate-400">Configure a bank, e-wallet, or foreign currency ledger</p>
      </div>

      <div v-if="accountError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
        <span>{{ accountError }}</span>
      </div>

      <form @submit.prevent="handleCreateAccount" class="space-y-4">
        <!-- 1-Click Starter Presets for New Users -->
        <div class="space-y-1.5 p-3 rounded-xl bg-slate-950/60 border border-slate-800">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-emerald-400 flex items-center space-x-1">
              <Sparkles class="w-3.5 h-3.5" />
              <span>1-Click Popular Presets</span>
            </span>
            <span class="text-[10px] text-slate-400">Pre-fills institution & currency</span>
          </div>
          <div class="flex flex-wrap gap-1.5 pt-1">
            <button
              v-for="preset in popularAccountPresets"
              :key="preset.label"
              type="button"
              @click="applyAccountPreset(preset)"
              class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-900 hover:bg-emerald-500/10 text-slate-300 hover:text-emerald-400 border border-slate-800 hover:border-emerald-500/40 transition flex items-center space-x-1.5 cursor-pointer"
            >
              <span>{{ preset.flag }}</span>
              <span>{{ preset.label }}</span>
              <span class="text-[10px] font-mono text-slate-400">({{ preset.currency }})</span>
            </button>
          </div>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="text-xs font-medium text-slate-300">Financial Institution</label>
            <span class="text-[10px] text-slate-400 font-mono">1-Click Quick Select</span>
          </div>

          <!-- Quick Institution Logo Badges -->
          <div class="grid grid-cols-4 sm:grid-cols-5 gap-2">
            <button
              v-for="inst in accountStore.institutions.slice(0, 8)"
              :key="'quick-' + inst.id"
              type="button"
              @click="
                newAccountForm.institution_id = inst.id;
                if (!newAccountForm.name || newAccountForm.name.startsWith('My ')) {
                  newAccountForm.name = inst.name;
                }
                if (inst.code === 'TNG' || inst.code === 'GRAB') {
                  newAccountForm.account_type = 'e_wallet';
                }
              "
              class="p-2 rounded-xl border flex flex-col items-center justify-center space-y-1 transition-all duration-200 cursor-pointer text-center group"
              :class="
                newAccountForm.institution_id === inst.id
                  ? 'border-emerald-500 bg-emerald-500/10 shadow-sm shadow-emerald-500/20'
                  : 'border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-800/40'
              "
              :title="inst.name"
            >
              <InstitutionLogo :code="inst.code" :logo-url="inst.logo_url" size="sm" interactive />
              <span class="text-[10px] font-semibold text-slate-300 truncate max-w-full leading-none group-hover:text-white">
                {{ inst.code }}
              </span>
            </button>
          </div>

          <select
            v-model="newAccountForm.institution_id"
            required
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500 mt-2"
          >
            <option
              v-for="inst in accountStore.institutions"
              :key="inst.id"
              :value="inst.id"
            >
              {{ inst.name }} ({{ inst.code }}) &bull; {{ inst.country }}
            </option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Account Label</label>
          <input
            v-model="newAccountForm.name"
            type="text"
            required
            placeholder="e.g. Maybank Premier Savings / US Brokerage"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
          />
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Account Category</label>
          <select
            v-model="newAccountForm.account_type"
            required
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
          >
            <option value="bank">Bank Account (Checking / Savings)</option>
            <option value="e_wallet">E-Wallet (TNG, GrabPay, ShopeePay)</option>
            <option value="credit_card">Credit Card (Liability)</option>
            <option value="cash">Physical Cash / Wallet</option>
            <option value="investment">Investment / Brokerage</option>
            <option value="loan">Loan / Mortgage</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Opening Balance</label>
            <input
              v-model.number="newAccountForm.opening_balance"
              type="number"
              step="0.01"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono focus:outline-none focus:border-emerald-500"
            />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Account Currency</label>
            <select
              v-model="newAccountForm.currency"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
            >
              <option value="MYR">MYR</option>
              <option value="SGD">SGD</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="GBP">GBP</option>
            </select>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-slate-300">Masked Account Number (Optional)</label>
          <input
            v-model="newAccountForm.account_number_masked"
            type="text"
            placeholder="e.g. ••••4198"
            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
          />
        </div>

        <button
          type="submit"
          :disabled="isAccountSubmitting"
          class="w-full py-2.5 rounded-lg text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <RefreshCw v-if="isAccountSubmitting" class="w-4 h-4 animate-spin" />
          <span>{{ isAccountSubmitting ? 'Provisioning Account...' : 'Confirm & Create Account' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>
