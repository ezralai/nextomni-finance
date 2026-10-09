<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useAccountStore } from '~/stores/accounts'
import { useTransactionStore } from '~/stores/transactions'
import { useBudgetStore } from '~/stores/budgets'
import {
  Wallet,
  ArrowUpRight,
  ArrowDownLeft,
  ArrowRightLeft,
  Activity,
  CheckCircle2,
  AlertCircle,
  Database,
  Server,
  Layers,
  Sparkles,
  RefreshCw,
  TrendingUp,
  User as UserIcon,
  LogIn,
  LogOut,
  X,
  Lock,
  Mail,
  UserCheck,
  ShieldCheck,
  Globe,
  Plus,
  CreditCard,
  Building,
  Smartphone,
  Coins,
  PiggyBank,
  Trash2,
  ExternalLink,
  Search,
  Filter,
  Calendar,
  Tag,
  ReceiptText,
  DollarSign,
  Target,
  PieChart,
  BarChart3,
  Percent,
} from 'lucide-vue-next'

const config = useRuntimeConfig()
const apiBase = config.public.apiBase
const authStore = useAuthStore()
const accountStore = useAccountStore()
const transactionStore = useTransactionStore()
const budgetStore = useBudgetStore()

interface HealthResponse {
  status: string
  app: string
  version: string
  environment: string
  timestamp: string
  database: {
    status: string
    driver: string
  }
}

const health = ref<HealthResponse | null>(null)
const isHealthLoading = ref(false)
const responseTimeMs = ref<number | null>(null)

// Auth Modal State
const showAuthModal = ref(false)
const authMode = ref<'login' | 'register'>('login')
const isAuthSubmitting = ref(false)
const authError = ref<string | null>(null)

// Forms
const loginForm = ref({ email: '', password: '' })
const registerForm = ref({ name: '', email: '', password: '', base_currency: 'MYR' })

// Add Account Modal State
const showAddAccountModal = ref(false)
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

// Transaction & Transfer Modals State
const showTransactionModal = ref(false)
const showTransferModal = ref(false)
const transactionError = ref<string | null>(null)
const transferError = ref<string | null>(null)

const newTransactionForm = ref({
  account_id: '',
  category_id: '',
  type: 'expense' as 'expense' | 'income',
  amount: '',
  description: '',
  notes: '',
  transacted_at: new Date().toISOString().slice(0, 16),
})

const newTransferForm = ref({
  source_account_id: '',
  destination_account_id: '',
  amount: '',
  fee: '0.00',
  notes: '',
  transacted_at: new Date().toISOString().slice(0, 16),
})

// Budget Modal State
const showBudgetModal = ref(false)
const budgetError = ref<string | null>(null)

const now = new Date()
const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
const lastDayOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)

const newBudgetForm = ref({
  category_id: '',
  amount: '',
  period: 'monthly',
  start_date: firstDayOfMonth,
  end_date: lastDayOfMonth,
  notes: '',
})

// Transaction Filter State
const filterType = ref<'all' | 'income' | 'expense' | 'transfer'>('all')
const filterAccountId = ref<string>('')
const filterSearch = ref<string>('')

// Currency Switcher
const isUpdatingCurrency = ref(false)

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

const checkHealth = async () => {
  isHealthLoading.value = true
  const startTime = performance.now()
  try {
    const data = await $fetch<HealthResponse>(`${apiBase}/health`)
    health.value = data
    responseTimeMs.value = Math.round(performance.now() - startTime)
  } catch {
    health.value = null
    responseTimeMs.value = null
  } finally {
    isHealthLoading.value = false
  }
}

const handleLogin = async () => {
  isAuthSubmitting.value = true
  authError.value = null
  try {
    await authStore.login(loginForm.value)
    showAuthModal.value = false
    loginForm.value = { email: '', password: '' }
    await refreshDashboard()
  } catch (err: any) {
    authError.value = err?.data?.message || err?.message || 'Login failed. Please check credentials.'
  } finally {
    isAuthSubmitting.value = false
  }
}

const handleRegister = async () => {
  isAuthSubmitting.value = true
  authError.value = null
  try {
    await authStore.register(registerForm.value)
    showAuthModal.value = false
    registerForm.value = { name: '', email: '', password: '', base_currency: 'MYR' }
    await refreshDashboard()
  } catch (err: any) {
    authError.value = err?.data?.message || err?.message || 'Registration failed.'
  } finally {
    isAuthSubmitting.value = false
  }
}

const handleDemoLogin = async () => {
  isAuthSubmitting.value = true
  authError.value = null
  const demoEmail = 'demo@nextomni.com'
  const demoPass = 'NextOmniDemo2026!'

  try {
    await authStore.login({ email: demoEmail, password: demoPass })
    showAuthModal.value = false
    await refreshDashboard()
  } catch {
    try {
      await authStore.register({
        name: 'Demo Investor',
        email: demoEmail,
        password: demoPass,
        base_currency: 'MYR',
      })
      showAuthModal.value = false
      await refreshDashboard()
    } catch (regErr: any) {
      authError.value = regErr?.data?.message || 'Failed to create demo session.'
    }
  } finally {
    isAuthSubmitting.value = false
  }
}

const handleCurrencyChange = async (newCurrency: string) => {
  if (!authStore.isAuthenticated || isUpdatingCurrency.value) return
  isUpdatingCurrency.value = true
  try {
    await authStore.updateProfile({ base_currency: newCurrency })
    await refreshDashboard()
  } catch (err: any) {
    console.error('Failed to update base currency:', err)
  } finally {
    isUpdatingCurrency.value = false
  }
}

const openAddAccountModal = () => {
  if (accountStore.institutions.length > 0 && !newAccountForm.value.institution_id) {
    newAccountForm.value.institution_id = accountStore.institutions[0].id
  }
  newAccountForm.value.currency = authStore.user?.base_currency || 'MYR'
  accountError.value = null
  showAddAccountModal.value = true
}

const handleCreateAccount = async () => {
  isAccountSubmitting.value = true
  accountError.value = null
  try {
    await accountStore.createAccount({
      ...newAccountForm.value,
      opening_balance: Number(newAccountForm.value.opening_balance),
    })
    showAddAccountModal.value = false
    newAccountForm.value = {
      institution_id: accountStore.institutions[0]?.id || '',
      name: '',
      account_type: 'bank',
      currency: authStore.user?.base_currency || 'MYR',
      opening_balance: 0,
      account_number_masked: '',
      notes: '',
    }
    await refreshDashboard()
  } catch (err: any) {
    accountError.value = err?.data?.message || err?.message || 'Failed to create account.'
  } finally {
    isAccountSubmitting.value = false
  }
}

const handleDeleteAccount = async (id: string, name: string) => {
  if (confirm(`Are you sure you want to remove account "${name}"? All associated transactions will be removed.`)) {
    try {
      await accountStore.deleteAccount(id)
      await refreshDashboard()
    } catch (err: any) {
      alert(err?.data?.message || 'Failed to delete account.')
    }
  }
}

// Transaction Modal Handlers
const openAddTransactionModal = (defaultType: 'expense' | 'income' = 'expense') => {
  newTransactionForm.value.type = defaultType
  if (accountStore.accounts.length > 0 && !newTransactionForm.value.account_id) {
    newTransactionForm.value.account_id = accountStore.accounts[0].id
  }
  newTransactionForm.value.amount = ''
  newTransactionForm.value.description = ''
  newTransactionForm.value.notes = ''
  newTransactionForm.value.transacted_at = new Date().toISOString().slice(0, 16)
  transactionError.value = null
  showTransactionModal.value = true
}

const handleCreateTransaction = async () => {
  transactionError.value = null
  try {
    await transactionStore.createTransaction({
      account_id: newTransactionForm.value.account_id,
      category_id: newTransactionForm.value.category_id || undefined,
      type: newTransactionForm.value.type,
      amount: newTransactionForm.value.amount,
      description: newTransactionForm.value.description,
      notes: newTransactionForm.value.notes || undefined,
      transacted_at: newTransactionForm.value.transacted_at || undefined,
    })
    showTransactionModal.value = false
    await refreshDashboard()
  } catch (err: any) {
    transactionError.value = err.message || 'Failed to record transaction.'
  }
}

const openAddTransferModal = () => {
  if (accountStore.accounts.length >= 2) {
    newTransferForm.value.source_account_id = accountStore.accounts[0].id
    newTransferForm.value.destination_account_id = accountStore.accounts[1].id
  } else if (accountStore.accounts.length === 1) {
    newTransferForm.value.source_account_id = accountStore.accounts[0].id
  }
  newTransferForm.value.amount = ''
  newTransferForm.value.fee = '0.00'
  newTransferForm.value.notes = ''
  newTransferForm.value.transacted_at = new Date().toISOString().slice(0, 16)
  transferError.value = null
  showTransferModal.value = true
}

const handleCreateTransfer = async () => {
  transferError.value = null
  try {
    await transactionStore.createTransfer({
      source_account_id: newTransferForm.value.source_account_id,
      destination_account_id: newTransferForm.value.destination_account_id,
      amount: newTransferForm.value.amount,
      fee: newTransferForm.value.fee || 0,
      notes: newTransferForm.value.notes || undefined,
      transacted_at: newTransferForm.value.transacted_at || undefined,
    })
    showTransferModal.value = false
    await refreshDashboard()
  } catch (err: any) {
    transferError.value = err.message || 'Failed to execute transfer.'
  }
}

const handleDeleteTransaction = async (id: string, desc: string) => {
  if (confirm(`Delete transaction "${desc}"? Account balance will be restored.`)) {
    try {
      await transactionStore.deleteTransaction(id)
      await refreshDashboard()
    } catch (err: any) {
      alert(err.message || 'Failed to delete transaction.')
    }
  }
}

// Budget Modal Handlers
const openAddBudgetModal = () => {
  if (transactionStore.categories.length > 0 && !newBudgetForm.value.category_id) {
    const expenseCats = transactionStore.categories.filter(c => c.type === 'expense')
    newBudgetForm.value.category_id = expenseCats[0]?.id || ''
  }
  newBudgetForm.value.amount = ''
  newBudgetForm.value.notes = ''
  budgetError.value = null
  showBudgetModal.value = true
}

const handleCreateBudget = async () => {
  budgetError.value = null
  try {
    await budgetStore.createBudget({
      category_id: newBudgetForm.value.category_id,
      amount: newBudgetForm.value.amount,
      period: newBudgetForm.value.period,
      start_date: newBudgetForm.value.start_date,
      end_date: newBudgetForm.value.end_date,
      notes: newBudgetForm.value.notes || undefined,
    })
    showBudgetModal.value = false
    await refreshDashboard()
  } catch (err: any) {
    budgetError.value = err.message || 'Failed to create budget limit.'
  }
}

const handleDeleteBudget = async (id: string, catName: string) => {
  if (confirm(`Remove budget limit for "${catName}"?`)) {
    try {
      await budgetStore.deleteBudget(id)
      await refreshDashboard()
    } catch (err: any) {
      alert(err.message || 'Failed to delete budget.')
    }
  }
}

const applyTransactionFilters = () => {
  transactionStore.fetchTransactions({
    type: filterType.value === 'all' ? undefined : filterType.value,
    account_id: filterAccountId.value || undefined,
    search: filterSearch.value.trim() || undefined,
  })
}

// Seed a quick 3-account starter portfolio with real transactions and budgets
const seedSamplePortfolio = async () => {
  if (!authStore.isAuthenticated) return
  isAccountSubmitting.value = true
  try {
    const mbb = accountStore.institutions.find(i => i.code === 'MBB')
    const tng = accountStore.institutions.find(i => i.code === 'TNG')
    const cimb = accountStore.institutions.find(i => i.code === 'CIMB')

    let mbbAcc, tngAcc, cimbAcc

    if (mbb) {
      mbbAcc = await accountStore.createAccount({
        institution_id: mbb.id,
        name: 'Maybank Premier Savings',
        account_type: 'bank',
        currency: authStore.user?.base_currency || 'MYR',
        opening_balance: 14250.00,
        account_number_masked: '••••4198',
      })
    }
    if (tng) {
      tngAcc = await accountStore.createAccount({
        institution_id: tng.id,
        name: 'Touch n Go eWallet',
        account_type: 'e_wallet',
        currency: authStore.user?.base_currency || 'MYR',
        opening_balance: 850.50,
      })
    }
    if (cimb) {
      cimbAcc = await accountStore.createAccount({
        institution_id: cimb.id,
        name: 'CIMB Visa Signature',
        account_type: 'credit_card',
        currency: authStore.user?.base_currency || 'MYR',
        opening_balance: 1240.20,
        account_number_masked: '••••8821',
      })
    }

    // Seed Sample Transactions
    const salaryCat = transactionStore.categories.find(c => c.slug === 'income')?.children?.find(c => c.slug === 'salary-wages')
    const foodCat = transactionStore.categories.find(c => c.slug === 'food-dining')
    const groceriesCat = foodCat?.children?.find(c => c.slug === 'groceries-supermarket')
    const transportCat = transactionStore.categories.find(c => c.slug === 'transportation')
    const fuelCat = transportCat?.children?.find(c => c.slug === 'fuel-petrol')

    if (mbbAcc) {
      await transactionStore.createTransaction({
        account_id: mbbAcc.id,
        category_id: salaryCat?.id,
        type: 'income',
        amount: 8500.00,
        description: 'Monthly Salary Oct 2026',
      })
      await transactionStore.createTransaction({
        account_id: mbbAcc.id,
        category_id: groceriesCat?.id,
        type: 'expense',
        amount: 320.50,
        description: 'Jaya Grocer Weekly Shopping',
      })
    }

    if (cimbAcc && fuelCat) {
      await transactionStore.createTransaction({
        account_id: cimbAcc.id,
        category_id: fuelCat.id,
        type: 'expense',
        amount: 95.00,
        description: 'Petronas RON97 Fuel',
      })
    }

    if (mbbAcc && tngAcc) {
      await transactionStore.createTransfer({
        source_account_id: mbbAcc.id,
        destination_account_id: tngAcc.id,
        amount: 200.00,
        fee: 0,
        notes: 'Top-up TNG RFID for highway tolls',
      })
    }

    // Seed Sample Budgets
    if (foodCat) {
      await budgetStore.createBudget({
        category_id: foodCat.id,
        amount: 1200.00,
        period: 'monthly',
        start_date: firstDayOfMonth,
        end_date: lastDayOfMonth,
        notes: 'Monthly food and groceries cap',
      })
    }

    if (transportCat) {
      await budgetStore.createBudget({
        category_id: transportCat.id,
        amount: 400.00,
        period: 'monthly',
        start_date: firstDayOfMonth,
        end_date: lastDayOfMonth,
        notes: 'Monthly petrol and tolls cap',
      })
    }

    await refreshDashboard()
  } catch (err: any) {
    console.error('Error seeding portfolio:', err)
  } finally {
    isAccountSubmitting.value = false
  }
}

const refreshDashboard = async () => {
  if (authStore.isAuthenticated) {
    await Promise.all([
      accountStore.fetchAccounts(),
      transactionStore.fetchCategories(),
      budgetStore.fetchBudgets(),
      budgetStore.fetchCashFlow(),
      applyTransactionFilters(),
    ])
  }
}

const getAccountIcon = (type: string) => {
  switch (type) {
    case 'bank':
      return Building
    case 'e_wallet':
      return Smartphone
    case 'credit_card':
      return CreditCard
    case 'cash':
      return Coins
    case 'investment':
      return PiggyBank
    default:
      return Wallet
  }
}

const formatDate = (isoString: string) => {
  try {
    const d = new Date(isoString)
    return d.toLocaleDateString('en-MY', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return isoString
  }
}

onMounted(async () => {
  await Promise.all([
    checkHealth(),
    accountStore.fetchInstitutions(),
    authStore.fetchMe(),
  ])

  if (authStore.isAuthenticated) {
    await refreshDashboard()
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col selection:bg-emerald-500 selection:text-white">
    <!-- Top Navigation Bar -->
    <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
            <Wallet class="w-5 h-5 text-slate-950 font-bold" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">NextOmni Finance</span>
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Phase 5 Active</span>
            </div>
            <p class="text-[11px] text-slate-400 font-mono">Enterprise Financial Architecture</p>
          </div>
        </div>

        <div class="flex items-center space-x-3">
          <!-- Backend Connectivity Badge -->
          <div
            class="hidden sm:flex items-center space-x-2 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all"
            :class="health?.status === 'ok' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-amber-500/10 border-amber-500/30 text-amber-300'"
          >
            <span class="relative flex h-2 w-2">
              <span
                v-if="health?.status === 'ok'"
                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"
              ></span>
              <span
                class="relative inline-flex rounded-full h-2 w-2"
                :class="health?.status === 'ok' ? 'bg-emerald-500' : 'bg-amber-500'"
              ></span>
            </span>
            <span>{{ health?.status === 'ok' ? 'Backend Online' : (isHealthLoading ? 'Connecting...' : 'API Offline') }}</span>
            <span v-if="responseTimeMs !== null" class="text-[10px] text-slate-400 font-mono">({{ responseTimeMs }}ms)</span>
          </div>

          <button
            @click="checkHealth"
            :disabled="isHealthLoading"
            class="p-2 rounded-lg bg-slate-800/80 hover:bg-slate-700/80 text-slate-300 hover:text-white transition border border-slate-700/60 disabled:opacity-50"
            title="Refresh System Health"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isHealthLoading }" />
          </button>

          <!-- User Authentication Section -->
          <div v-if="authStore.isAuthenticated" class="flex items-center space-x-2 pl-2 border-l border-slate-800">
            <div class="flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-slate-800/60 border border-slate-700">
              <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs font-bold font-mono">
                {{ authStore.user?.name?.charAt(0).toUpperCase() || 'U' }}
              </div>
              <div class="text-left hidden md:block">
                <div class="text-xs font-semibold text-white leading-none">{{ authStore.user?.name }}</div>
                <div class="text-[10px] text-slate-400 font-mono">{{ authStore.user?.email }}</div>
              </div>
              <select
                :value="authStore.user?.base_currency"
                @change="handleCurrencyChange(($event.target as HTMLSelectElement).value)"
                :disabled="isUpdatingCurrency"
                class="bg-slate-900 border border-slate-700 text-emerald-400 text-xs rounded px-1.5 py-0.5 font-mono cursor-pointer focus:outline-none focus:border-emerald-500"
                title="Change Base Currency"
              >
                <option value="MYR">MYR</option>
                <option value="SGD">SGD</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
              </select>
            </div>

            <button
              @click="authStore.logout"
              class="p-2 rounded-lg bg-slate-800/80 hover:bg-red-500/20 text-slate-400 hover:text-red-400 border border-slate-700/60 transition"
              title="Sign Out"
            >
              <LogOut class="w-4 h-4" />
            </button>
          </div>

          <div v-else class="flex items-center space-x-2 pl-2 border-l border-slate-800">
            <button
              @click="authMode = 'login'; showAuthModal = true"
              class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-200 hover:text-white hover:bg-slate-800/60 transition"
            >
              Sign In
            </button>
            <button
              @click="authMode = 'register'; showAuthModal = true"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition shadow-md shadow-emerald-500/20"
            >
              Get Started
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      <!-- Authenticated User Banner with Quick Financial Actions -->
      <section v-if="authStore.isAuthenticated" class="p-6 rounded-2xl bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900/60 border border-emerald-500/30 shadow-xl backdrop-blur-md flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
            <UserCheck class="w-6 h-6" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <h2 class="text-xl font-bold text-white">{{ authStore.user?.name }}'s Financial Hub</h2>
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Double-Entry & Budgets</span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Ledger: <span class="text-emerald-400 font-mono font-bold">{{ authStore.user?.base_currency }}</span> &bull;
              Accounts: <span class="text-slate-200 font-mono">{{ accountStore.accounts.length }}</span> &bull;
              Budgets: <span class="text-slate-200 font-mono">{{ budgetStore.budgets.length }}</span> &bull;
              Transactions: <span class="text-slate-200 font-mono">{{ transactionStore.meta.total }}</span>
            </p>
          </div>
        </div>

        <!-- Quick Transaction, Transfer, and Budget Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
          <button
            @click="openAddTransactionModal('expense')"
            :disabled="accountStore.accounts.length === 0"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/40 transition flex items-center space-x-1.5 disabled:opacity-50"
          >
            <ArrowUpRight class="w-4 h-4 text-rose-400" />
            <span>Record Expense</span>
          </button>

          <button
            @click="openAddTransactionModal('income')"
            :disabled="accountStore.accounts.length === 0"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 transition flex items-center space-x-1.5 disabled:opacity-50"
          >
            <ArrowDownLeft class="w-4 h-4 text-emerald-400" />
            <span>Record Income</span>
          </button>

          <button
            @click="openAddTransferModal"
            :disabled="accountStore.accounts.length < 2"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-sky-500/20 hover:bg-sky-500/30 text-sky-300 border border-sky-500/40 transition flex items-center space-x-1.5 disabled:opacity-50"
          >
            <ArrowRightLeft class="w-4 h-4 text-sky-400" />
            <span>Transfer Funds</span>
          </button>

          <button
            @click="openAddBudgetModal"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-300 border border-indigo-500/40 transition flex items-center space-x-1.5"
          >
            <Target class="w-4 h-4 text-indigo-400" />
            <span>Set Budget</span>
          </button>

          <button
            @click="openAddAccountModal"
            class="px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-lg shadow-emerald-500/20"
          >
            <Plus class="w-4 h-4" />
            <span>New Account</span>
          </button>
        </div>
      </section>

      <!-- Guest Welcome Banner -->
      <section v-else class="relative overflow-hidden rounded-2xl bg-gradient-to-b from-slate-900/90 to-slate-900/40 border border-slate-800 p-8 shadow-2xl backdrop-blur-md">
        <div class="absolute -right-16 -top-16 w-80 h-80 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-80 h-80 rounded-full bg-teal-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl space-y-4">
          <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-slate-800/80 border border-slate-700 text-xs font-semibold text-emerald-400">
            <Sparkles class="w-3.5 h-3.5" />
            <span>Phase 5 — Spending Budgets & Real-time Analytics</span>
          </div>

          <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
            NextOmni Finance — High-Precision Personal Financial Architecture
          </h1>

          <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
            Engineered with <span class="text-emerald-400 font-medium">Laravel 11 (PHP 8.3)</span>,
            <span class="text-emerald-400 font-medium">PostgreSQL 16 UUIDv7</span>, and
            <span class="text-emerald-400 font-medium">Nuxt 3</span>. Features strict <code class="text-emerald-400 font-mono">NUMERIC(15, 4)</code>
            BCMath precision, SHA-256 deduplication, atomic double-entry transfers, and real-time category budget tracking.
          </p>

          <div class="flex flex-wrap items-center gap-3 pt-2">
            <button
              @click="handleDemoLogin"
              :disabled="isAuthSubmitting"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-2 shadow-lg shadow-emerald-500/20"
            >
              <LogIn class="w-4 h-4" />
              <span>Launch Live Demo Session</span>
            </button>
            <button
              @click="authMode = 'register'; showAuthModal = true"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
            >
              Create Account
            </button>
          </div>
        </div>
      </section>

      <!-- Live Financial Metrics Grid (Bound to Real API Aggregations) -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-white flex items-center space-x-2">
            <TrendingUp class="w-5 h-5 text-emerald-400" />
            <span>Portfolio Net Worth & Liquidity Overview</span>
          </h2>
          <span class="text-xs text-slate-400 font-mono">Calculated with BCMath Arbitrary Precision</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Total Net Worth Card -->
          <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-slate-700 transition space-y-2">
            <div class="text-xs font-medium text-slate-400">Total Net Worth</div>
            <div class="text-2xl font-extrabold text-white font-mono">
              {{ accountStore.summary ? accountStore.summary.net_worth_formatted : `${currencySymbol} 0.00` }}
            </div>
            <div class="flex items-center text-xs text-emerald-400 font-medium space-x-1">
              <ArrowUpRight class="w-3.5 h-3.5" />
              <span>Assets minus Liabilities</span>
            </div>
          </div>

          <!-- Liquid Cash & Bank Deposits -->
          <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-slate-700 transition space-y-2">
            <div class="text-xs font-medium text-slate-400">Bank Deposits</div>
            <div class="text-2xl font-extrabold text-white font-mono">
              {{ currencySymbol }} {{ accountStore.summary ? accountStore.summary.breakdown.bank : '0.00' }}
            </div>
            <div class="flex items-center text-xs text-slate-400 font-medium space-x-1">
              <Building class="w-3.5 h-3.5 text-blue-400" />
              <span>Checking & Savings</span>
            </div>
          </div>

          <!-- E-Wallets & Digital Cash -->
          <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-slate-700 transition space-y-2">
            <div class="text-xs font-medium text-slate-400">E-Wallets & Digital Cash</div>
            <div class="text-2xl font-extrabold text-white font-mono">
              {{ currencySymbol }} {{ accountStore.summary ? accountStore.summary.breakdown.e_wallet : '0.00' }}
            </div>
            <div class="flex items-center text-xs text-slate-400 font-medium space-x-1">
              <Smartphone class="w-3.5 h-3.5 text-emerald-400" />
              <span>TNG, GrabPay, ShopeePay</span>
            </div>
          </div>

          <!-- Liabilities (Credit Cards & Loans) -->
          <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-slate-700 transition space-y-2">
            <div class="text-xs font-medium text-slate-400">Credit Card Debt</div>
            <div class="text-2xl font-extrabold text-amber-400 font-mono">
              {{ currencySymbol }} {{ accountStore.summary ? accountStore.summary.breakdown.credit_card : '0.00' }}
            </div>
            <div class="flex items-center text-xs text-amber-400/80 space-x-1">
              <CreditCard class="w-3.5 h-3.5" />
              <span>Deducted from Net Worth</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Cash Flow & Monthly Analytics (Phase 5 Feature) -->
      <section v-if="authStore.isAuthenticated && budgetStore.cashFlow" class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h3 class="text-base font-bold text-white flex items-center space-x-2">
              <BarChart3 class="w-5 h-5 text-indigo-400" />
              <span>Monthly Cash Flow & Savings Rate</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Period: {{ budgetStore.cashFlow.period.start_date }} to {{ budgetStore.cashFlow.period.end_date }}</p>
          </div>

          <div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-mono font-bold">
            <Percent class="w-4 h-4" />
            <span>Savings Rate: {{ budgetStore.cashFlow.summary.savings_rate }}%</span>
          </div>
        </div>

        <!-- 3-Column Cash Flow Breakdown -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
            <span class="text-xs text-slate-400 font-medium">Total Inflows (Income)</span>
            <div class="text-xl font-bold font-mono text-emerald-400">
              {{ budgetStore.cashFlow.summary.total_income_formatted }}
            </div>
          </div>

          <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
            <span class="text-xs text-slate-400 font-medium">Total Outflows (Expenses)</span>
            <div class="text-xl font-bold font-mono text-rose-400">
              {{ budgetStore.cashFlow.summary.total_expense_formatted }}
            </div>
          </div>

          <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
            <span class="text-xs text-slate-400 font-medium">Net Monthly Savings</span>
            <div
              class="text-xl font-bold font-mono"
              :class="Number(budgetStore.cashFlow.summary.net_cash_flow) >= 0 ? 'text-emerald-400' : 'text-rose-400'"
            >
              {{ budgetStore.cashFlow.summary.net_cash_flow_formatted }}
            </div>
          </div>
        </div>

        <!-- Category Spending Distribution -->
        <div v-if="budgetStore.cashFlow.categories.length > 0" class="space-y-3 pt-2">
          <span class="text-xs font-bold text-slate-300 uppercase tracking-wider block">Spending Distribution by Category</span>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <div
              v-for="cat in budgetStore.cashFlow.categories"
              :key="cat.name"
              class="p-3 rounded-lg bg-slate-950/40 border border-slate-800 flex items-center justify-between"
            >
              <div class="overflow-hidden pr-2">
                <div class="text-xs font-semibold text-white truncate">{{ cat.name }}</div>
                <div class="text-[11px] font-mono text-slate-400">{{ cat.amount_formatted }}</div>
              </div>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-mono font-bold"
                :style="{ backgroundColor: `${cat.color}20`, color: cat.color || '#38bdf8' }"
              >
                {{ cat.percentage }}%
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- Budgets & Spending Progress (Phase 5 Core Feature) -->
      <section v-if="authStore.isAuthenticated" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <Target class="w-5 h-5 text-indigo-400" />
            <h2 class="text-lg font-bold text-white">Monthly Budgets & Category Spending Limits ({{ budgetStore.budgets.length }})</h2>
          </div>
          <button
            @click="openAddBudgetModal"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 transition flex items-center space-x-1"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Set Category Budget</span>
          </button>
        </div>

        <!-- Budget Cards Grid -->
        <div v-if="budgetStore.budgets.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="item in budgetStore.budgets"
            :key="item.budget.id"
            class="p-5 rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition relative group space-y-4"
          >
            <div class="flex items-start justify-between">
              <div>
                <div class="flex items-center space-x-2">
                  <h3 class="font-bold text-sm text-white">{{ item.category?.name || 'Category' }}</h3>
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                    :class="{
                      'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': item.status === 'healthy',
                      'bg-amber-500/10 text-amber-400 border border-amber-500/20': item.status === 'warning',
                      'bg-rose-500/10 text-rose-400 border border-rose-500/20': item.status === 'exceeded',
                    }"
                  >
                    {{ item.status === 'exceeded' ? 'Over Budget' : `${item.percent_used}%` }}
                  </span>
                </div>
                <p class="text-[11px] text-slate-400 font-mono mt-0.5">
                  {{ item.budget.start_date }} &rarr; {{ item.budget.end_date }}
                </p>
              </div>

              <button
                @click="handleDeleteBudget(item.budget.id, item.category?.name || 'budget')"
                class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-slate-800 opacity-0 group-hover:opacity-100 transition"
                title="Remove Budget Limit"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>

            <!-- Visual Progress Meter -->
            <div class="space-y-1.5">
              <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{ width: `${Math.min(item.percent_used, 100)}%` }"
                  :class="{
                    'bg-emerald-500': item.status === 'healthy',
                    'bg-amber-500': item.status === 'warning',
                    'bg-rose-500': item.status === 'exceeded',
                  }"
                ></div>
              </div>

              <div class="flex items-center justify-between text-xs font-mono">
                <span class="text-slate-400">Spent: <strong class="text-white">{{ item.spent_formatted }}</strong></span>
                <span class="text-slate-400">Cap: <strong class="text-slate-200">{{ item.amount_formatted }}</strong></span>
              </div>
            </div>

            <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
              <span class="text-slate-400">Remaining</span>
              <span
                class="font-mono font-bold"
                :class="item.is_over_budget ? 'text-rose-400' : 'text-emerald-400'"
              >
                {{ item.remaining_formatted }}
              </span>
            </div>
          </div>
        </div>

        <!-- Empty State for Budgets -->
        <div v-else class="p-8 rounded-2xl bg-slate-900/40 border border-dashed border-slate-800 text-center space-y-3">
          <div class="w-12 h-12 rounded-2xl bg-slate-800/80 mx-auto flex items-center justify-center text-slate-400">
            <Target class="w-6 h-6" />
          </div>
          <div class="space-y-1">
            <h3 class="text-base font-bold text-white">No Category Budgets Configured Yet</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
              Set monthly spending targets for Food & Dining, Transportation, or Utilities to monitor burn rate with visual warning meters.
            </p>
          </div>
          <div class="pt-1">
            <button
              @click="openAddBudgetModal"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-500 hover:bg-indigo-400 text-slate-950 transition inline-flex items-center space-x-1.5"
            >
              <Plus class="w-4 h-4" />
              <span>Set Your First Budget</span>
            </button>
          </div>
        </div>
      </section>

      <!-- User's Financial Accounts Grid -->
      <section v-if="authStore.isAuthenticated" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <Wallet class="w-5 h-5 text-emerald-400" />
            <h2 class="text-lg font-bold text-white">Your Financial Accounts ({{ accountStore.accounts.length }})</h2>
          </div>
          <button
            @click="openAddAccountModal"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition flex items-center space-x-1"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Add Account</span>
          </button>
        </div>

        <!-- Account Cards -->
        <div v-if="accountStore.accounts.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="acc in accountStore.accounts"
            :key="acc.id"
            class="p-5 rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition relative group space-y-4"
          >
            <div class="flex items-start justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-emerald-400">
                  <component :is="getAccountIcon(acc.account_type)" class="w-5 h-5" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-white">{{ acc.name }}</h3>
                  <p class="text-xs text-slate-400 flex items-center space-x-1.5 mt-0.5">
                    <span>{{ acc.institution?.name || 'Manual Account' }}</span>
                    <span v-if="acc.account_number_masked" class="font-mono text-[11px] text-slate-500">({{ acc.account_number_masked }})</span>
                  </p>
                </div>
              </div>

              <!-- Delete button -->
              <button
                @click="handleDeleteAccount(acc.id, acc.name)"
                class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-slate-800 opacity-0 group-hover:opacity-100 transition"
                title="Remove Account"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>

            <div class="pt-2 border-t border-slate-800/80 flex items-end justify-between">
              <div>
                <span class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold block">Balance</span>
                <span class="text-xl font-extrabold font-mono" :class="acc.account_type === 'credit_card' ? 'text-amber-400' : 'text-white'">
                  {{ acc.current_balance_formatted }}
                </span>
              </div>
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-medium uppercase bg-slate-800 text-slate-400 border border-slate-700/60">
                {{ acc.account_type }}
              </span>
            </div>
          </div>
        </div>

        <!-- Empty State for Accounts -->
        <div v-else class="p-8 rounded-2xl bg-slate-900/40 border border-dashed border-slate-800 text-center space-y-4">
          <div class="w-12 h-12 rounded-2xl bg-slate-800/80 mx-auto flex items-center justify-center text-slate-400">
            <Wallet class="w-6 h-6" />
          </div>
          <div class="space-y-1">
            <h3 class="text-base font-bold text-white">No Financial Accounts Configured Yet</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
              Add your Malaysian bank accounts (Maybank, CIMB), e-wallets (TNG, GrabPay), or physical cash to track your net worth.
            </p>
          </div>
          <div class="flex items-center justify-center space-x-3 pt-2">
            <button
              @click="openAddAccountModal"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-2"
            >
              <Plus class="w-4 h-4" />
              <span>Add Your First Account</span>
            </button>
            <button
              @click="seedSamplePortfolio"
              :disabled="isAccountSubmitting"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
            >
              Seed Sample Portfolio & Activity
            </button>
          </div>
        </div>
      </section>

      <!-- Transaction Ledger & Feed (Phase 4 Feature) -->
      <section v-if="authStore.isAuthenticated" class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center space-x-2">
            <ReceiptText class="w-5 h-5 text-emerald-400" />
            <h2 class="text-lg font-bold text-white">Transaction Ledger & Activity Feed ({{ transactionStore.meta.total }})</h2>
          </div>

          <!-- Filter & Search Controls -->
          <div class="flex flex-wrap items-center gap-2">
            <!-- Search Input -->
            <div class="relative">
              <Search class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2.5 pointer-events-none" />
              <input
                v-model="filterSearch"
                @input="applyTransactionFilters"
                type="text"
                placeholder="Search description..."
                class="pl-8 pr-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 w-44"
              />
            </div>

            <!-- Account Filter Dropdown -->
            <select
              v-model="filterAccountId"
              @change="applyTransactionFilters"
              class="bg-slate-900 border border-slate-800 text-xs text-slate-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-emerald-500 cursor-pointer"
            >
              <option value="">All Accounts</option>
              <option v-for="acc in accountStore.accounts" :key="acc.id" :value="acc.id">
                {{ acc.name }}
              </option>
            </select>

            <!-- Type Filter Segmented Control -->
            <div class="flex rounded-lg bg-slate-900 p-0.5 border border-slate-800">
              <button
                @click="filterType = 'all'; applyTransactionFilters()"
                class="px-2.5 py-1 text-xs rounded-md transition"
                :class="filterType === 'all' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-400 hover:text-white'"
              >
                All
              </button>
              <button
                @click="filterType = 'income'; applyTransactionFilters()"
                class="px-2.5 py-1 text-xs rounded-md transition"
                :class="filterType === 'income' ? 'bg-emerald-500/20 text-emerald-400 font-semibold' : 'text-slate-400 hover:text-white'"
              >
                Income
              </button>
              <button
                @click="filterType = 'expense'; applyTransactionFilters()"
                class="px-2.5 py-1 text-xs rounded-md transition"
                :class="filterType === 'expense' ? 'bg-rose-500/20 text-rose-400 font-semibold' : 'text-slate-400 hover:text-white'"
              >
                Expense
              </button>
              <button
                @click="filterType = 'transfer'; applyTransactionFilters()"
                class="px-2.5 py-1 text-xs rounded-md transition"
                :class="filterType === 'transfer' ? 'bg-sky-500/20 text-sky-400 font-semibold' : 'text-slate-400 hover:text-white'"
              >
                Transfer
              </button>
            </div>
          </div>
        </div>

        <!-- Transaction Feed Table / List -->
        <div v-if="transactionStore.transactions.length > 0" class="rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-xl">
          <div class="divide-y divide-slate-800/80">
            <div
              v-for="txn in transactionStore.transactions"
              :key="txn.id"
              class="p-4 hover:bg-slate-800/40 transition flex items-center justify-between gap-4 group"
            >
              <div class="flex items-center space-x-3.5 min-w-0">
                <div
                  class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                  :class="{
                    'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': txn.type === 'income',
                    'bg-rose-500/10 text-rose-400 border border-rose-500/20': txn.type === 'expense',
                    'bg-sky-500/10 text-sky-400 border border-sky-500/20': txn.type === 'transfer',
                  }"
                >
                  <ArrowDownLeft v-if="txn.type === 'income'" class="w-5 h-5" />
                  <ArrowUpRight v-else-if="txn.type === 'expense'" class="w-5 h-5" />
                  <ArrowRightLeft v-else class="w-5 h-5" />
                </div>

                <div class="min-w-0">
                  <div class="flex items-center space-x-2">
                    <span class="font-bold text-sm text-white truncate">{{ txn.description }}</span>
                    <span
                      v-if="txn.category"
                      class="px-2 py-0.5 rounded text-[10px] font-medium border"
                      :style="{
                        backgroundColor: `${txn.category.color}15`,
                        borderColor: `${txn.category.color}40`,
                        color: txn.category.color || '#94a3b8',
                      }"
                    >
                      {{ txn.category.name }}
                    </span>
                  </div>
                  <div class="text-xs text-slate-400 flex items-center space-x-2 mt-0.5 font-mono">
                    <span>{{ formatDate(txn.transacted_at) }}</span>
                    <span>&bull;</span>
                    <span class="text-slate-300">{{ txn.account?.name || 'Account' }}</span>
                    <span v-if="txn.notes" class="text-slate-500 italic truncate max-w-xs">&mdash; {{ txn.notes }}</span>
                  </div>
                </div>
              </div>

              <div class="flex items-center space-x-4 flex-shrink-0">
                <div class="text-right">
                  <div
                    class="font-mono font-bold text-base"
                    :class="{
                      'text-emerald-400': txn.type === 'income',
                      'text-rose-400': txn.type === 'expense',
                      'text-sky-300': txn.type === 'transfer',
                    }"
                  >
                    <span v-if="txn.type === 'income'">+</span>
                    <span v-else-if="txn.type === 'expense'">&minus;</span>
                    {{ txn.amount_formatted }}
                  </div>
                  <div class="text-[10px] text-slate-500 font-mono uppercase tracking-wider">
                    {{ txn.status }}
                  </div>
                </div>

                <button
                  @click="handleDeleteTransaction(txn.id, txn.description)"
                  class="p-2 rounded-lg text-slate-500 hover:text-red-400 hover:bg-slate-800 opacity-0 group-hover:opacity-100 transition"
                  title="Delete Transaction & Reverse Balance"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State for Transactions -->
        <div v-else class="p-8 rounded-2xl bg-slate-900/40 border border-dashed border-slate-800 text-center space-y-3">
          <div class="w-12 h-12 rounded-2xl bg-slate-800/80 mx-auto flex items-center justify-center text-slate-400">
            <ReceiptText class="w-6 h-6" />
          </div>
          <div class="space-y-1">
            <h3 class="text-base font-bold text-white">No Transactions Found</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
              {{ filterSearch || filterType !== 'all' || filterAccountId ? 'No transactions match the selected filter criteria.' : 'Record your first income or expense to begin tracking cash flows.' }}
            </p>
          </div>
          <div v-if="accountStore.accounts.length > 0" class="flex items-center justify-center space-x-3 pt-1">
            <button
              @click="openAddTransactionModal('expense')"
              class="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition"
            >
              Record Expense
            </button>
            <button
              @click="openAddTransactionModal('income')"
              class="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
            >
              Record Income
            </button>
          </div>
        </div>
      </section>

      <!-- Malaysian Financial Institutions Directory -->
      <section class="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-bold text-white">Supported Institutions & Connectors ({{ accountStore.institutions.length }})</h3>
          <span class="text-xs text-slate-400 font-mono">SE Asia Financial Registry</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
          <div
            v-for="inst in accountStore.institutions"
            :key="inst.id"
            class="p-3 rounded-lg bg-slate-800/40 border border-slate-700/50 flex items-center space-x-3"
          >
            <div class="w-8 h-8 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-xs font-mono">
              {{ inst.code.slice(0, 4) }}
            </div>
            <div class="overflow-hidden">
              <div class="text-xs font-semibold text-white truncate">{{ inst.name }}</div>
              <div class="text-[10px] text-slate-400 font-mono">Country: {{ inst.country }}</div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-900 py-6 text-center text-xs text-slate-400 bg-slate-950">
      <p>NextOmni Finance &copy; 2026 &mdash; Built for Portfolio Demonstration & Technical Rigor. Non-custodial personal finance platform.</p>
    </footer>

    <!-- Interactive Authentication Modal -->
    <div
      v-if="showAuthModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showAuthModal = false"
          class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
        >
          <X class="w-5 h-5" />
        </button>

        <div class="space-y-3">
          <div class="flex items-center space-x-2">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
              <Lock class="w-4 h-4" />
            </div>
            <h3 class="text-lg font-bold text-white">
              {{ authMode === 'login' ? 'Sign In to NextOmni Finance' : 'Create an Account' }}
            </h3>
          </div>

          <div class="flex rounded-lg bg-slate-800/60 p-1 border border-slate-700/50">
            <button
              @click="authMode = 'login'; authError = null"
              class="flex-1 py-1.5 text-xs font-medium rounded-md transition text-center"
              :class="authMode === 'login' ? 'bg-emerald-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-white'"
            >
              Sign In
            </button>
            <button
              @click="authMode = 'register'; authError = null"
              class="flex-1 py-1.5 text-xs font-medium rounded-md transition text-center"
              :class="authMode === 'register' ? 'bg-emerald-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-white'"
            >
              Create Account
            </button>
          </div>
        </div>

        <div v-if="authError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ authError }}</span>
        </div>

        <!-- Sign In Form -->
        <form v-if="authMode === 'login'" @submit.prevent="handleLogin" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Email Address</label>
            <div class="relative">
              <Mail class="w-4 h-4 text-slate-500 absolute left-3 top-3 pointer-events-none" />
              <input
                v-model="loginForm.email"
                type="email"
                required
                placeholder="name@example.com"
                class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Password</label>
            <div class="relative">
              <Lock class="w-4 h-4 text-slate-500 absolute left-3 top-3 pointer-events-none" />
              <input
                v-model="loginForm.password"
                type="password"
                required
                placeholder="••••••••"
                class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>

          <button
            type="submit"
            :disabled="isAuthSubmitting"
            class="w-full py-2.5 rounded-lg text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50"
          >
            <RefreshCw v-if="isAuthSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ isAuthSubmitting ? 'Authenticating...' : 'Sign In' }}</span>
          </button>
        </form>

        <!-- Register Form -->
        <form v-else @submit.prevent="handleRegister" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Full Name</label>
            <input
              v-model="registerForm.name"
              type="text"
              required
              placeholder="e.g. Ezra Lai"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Email Address</label>
            <input
              v-model="registerForm.email"
              type="email"
              required
              placeholder="name@example.com"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Password (Min. 8 characters)</label>
            <input
              v-model="registerForm.password"
              type="password"
              required
              minlength="8"
              placeholder="••••••••"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Base Currency</label>
            <select
              v-model="registerForm.base_currency"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
            >
              <option value="MYR">MYR — Malaysian Ringgit</option>
              <option value="SGD">SGD — Singapore Dollar</option>
              <option value="USD">USD — US Dollar</option>
              <option value="EUR">EUR — Euro</option>
              <option value="GBP">GBP — British Pound</option>
            </select>
          </div>

          <button
            type="submit"
            :disabled="isAuthSubmitting"
            class="w-full py-2.5 rounded-lg text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50"
          >
            <RefreshCw v-if="isAuthSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ isAuthSubmitting ? 'Creating Account...' : 'Create Account' }}</span>
          </button>
        </form>

        <div class="pt-4 border-t border-slate-800 text-center">
          <p class="text-xs text-slate-400 mb-2">Need a quick preview for review?</p>
          <button
            @click="handleDemoLogin"
            :disabled="isAuthSubmitting"
            type="button"
            class="w-full py-2 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
          >
            Launch Instant Demo Account
          </button>
        </div>
      </div>
    </div>

    <!-- Add Account Modal -->
    <div
      v-if="showAddAccountModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showAddAccountModal = false"
          class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
        >
          <X class="w-5 h-5" />
        </button>

        <div class="space-y-1">
          <h3 class="text-lg font-bold text-white flex items-center space-x-2">
            <Plus class="w-5 h-5 text-emerald-400" />
            <span>Add Financial Account</span>
          </h3>
          <p class="text-xs text-slate-400">Configure a bank, e-wallet, or credit card ledger</p>
        </div>

        <div v-if="accountError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ accountError }}</span>
        </div>

        <form @submit.prevent="handleCreateAccount" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Financial Institution</label>
            <select
              v-model="newAccountForm.institution_id"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
            >
              <option
                v-for="inst in accountStore.institutions"
                :key="inst.id"
                :value="inst.id"
              >
                {{ inst.name }} ({{ inst.code }})
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Account Label</label>
            <input
              v-model="newAccountForm.name"
              type="text"
              required
              placeholder="e.g. Maybank Premier Savings"
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
              <label class="text-xs font-medium text-slate-300">Currency</label>
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
            class="w-full py-2.5 rounded-lg text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50"
          >
            <RefreshCw v-if="isAccountSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ isAccountSubmitting ? 'Provisioning Account...' : 'Confirm & Create Account' }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Record Transaction Modal (Expense / Income) -->
    <div
      v-if="showTransactionModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showTransactionModal = false"
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
            class="flex-1 py-1.5 text-xs font-semibold rounded-md transition text-center"
            :class="newTransactionForm.type === 'expense' ? 'bg-rose-500/30 text-rose-300 border border-rose-500/40 shadow' : 'text-slate-400 hover:text-white'"
          >
            Expense (Outflow)
          </button>
          <button
            @click="newTransactionForm.type = 'income'"
            type="button"
            class="flex-1 py-1.5 text-xs font-semibold rounded-md transition text-center"
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
                {{ acc.name }} &mdash; Balance: {{ acc.current_balance_formatted }}
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

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Amount</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
              <input
                v-model="newTransactionForm.amount"
                type="number"
                step="0.01"
                min="0.01"
                required
                placeholder="0.00"
                class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>

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
            class="w-full py-2.5 rounded-lg text-xs font-bold transition flex items-center justify-center space-x-2 disabled:opacity-50"
            :class="newTransactionForm.type === 'income' ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950' : 'bg-rose-500 hover:bg-rose-400 text-white'"
          >
            <RefreshCw v-if="transactionStore.isSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ transactionStore.isSubmitting ? 'Posting to Ledger...' : `Confirm & Record ${newTransactionForm.type === 'income' ? 'Income' : 'Expense'}` }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Transfer Funds Modal -->
    <div
      v-if="showTransferModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showTransferModal = false"
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
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">From Account (Source)</label>
            <select
              v-model="newTransferForm.source_account_id"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-500"
            >
              <option v-for="acc in accountStore.accounts" :key="acc.id" :value="acc.id">
                {{ acc.name }} &mdash; Balance: {{ acc.current_balance_formatted }}
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
                {{ acc.name }} &mdash; Balance: {{ acc.current_balance_formatted }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Transfer Amount</label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
                <input
                  v-model="newTransferForm.amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  required
                  placeholder="0.00"
                  class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-sky-500"
                />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Transfer Fee</label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
                <input
                  v-model="newTransferForm.fee"
                  type="number"
                  step="0.01"
                  min="0.00"
                  placeholder="0.00"
                  class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-sky-500"
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
            class="w-full py-2.5 rounded-lg text-xs font-bold bg-sky-500 hover:bg-sky-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50"
          >
            <RefreshCw v-if="transactionStore.isSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ transactionStore.isSubmitting ? 'Executing Transfer...' : 'Confirm & Execute Transfer' }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Set Budget Modal (Phase 5 Feature) -->
    <div
      v-if="showBudgetModal"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6">
        <button
          @click="showBudgetModal = false"
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

        <div v-if="budgetError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
          <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
          <span>{{ budgetError }}</span>
        </div>

        <form @submit.prevent="handleCreateBudget" class="space-y-4">
          <!-- Category Selector -->
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Expense Category</label>
            <select
              v-model="newBudgetForm.category_id"
              required
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
            >
              <option
                v-for="cat in transactionStore.categories.filter(c => c.type === 'expense')"
                :key="cat.id"
                :value="cat.id"
              >
                {{ cat.name }} (Parent & Subcategories)
              </option>
            </select>
          </div>

          <!-- Budget Amount -->
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Spending Cap Limit</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-slate-400 font-mono text-xs">{{ currencySymbol }}</span>
              <input
                v-model="newBudgetForm.amount"
                type="number"
                step="0.01"
                min="0.01"
                required
                placeholder="1000.00"
                class="w-full pl-10 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <!-- Date Interval -->
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Start Date</label>
              <input
                v-model="newBudgetForm.start_date"
                type="date"
                required
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">End Date</label>
              <input
                v-model="newBudgetForm.end_date"
                type="date"
                required
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <!-- Notes -->
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-300">Optional Notes</label>
            <input
              v-model="newBudgetForm.notes"
              type="text"
              placeholder="e.g. Monthly groceries and food cap"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <button
            type="submit"
            :disabled="budgetStore.isSubmitting"
            class="w-full py-2.5 rounded-lg text-xs font-bold bg-indigo-500 hover:bg-indigo-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50"
          >
            <RefreshCw v-if="budgetStore.isSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ budgetStore.isSubmitting ? 'Establishing Limit...' : 'Confirm & Set Budget Cap' }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
