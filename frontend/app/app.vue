<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useAccountStore } from '~/stores/accounts'
import {
  Wallet,
  ArrowUpRight,
  ArrowDownLeft,
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
} from 'lucide-vue-next'

const config = useRuntimeConfig()
const apiBase = config.public.apiBase
const authStore = useAuthStore()
const accountStore = useAccountStore()

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
    await accountStore.fetchAccounts()
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
    await accountStore.fetchAccounts()
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
    await accountStore.fetchAccounts()
  } catch {
    try {
      await authStore.register({
        name: 'Demo Investor',
        email: demoEmail,
        password: demoPass,
        base_currency: 'MYR',
      })
      showAuthModal.value = false
      await accountStore.fetchAccounts()
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
    await accountStore.fetchAccounts()
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
  } catch (err: any) {
    accountError.value = err?.data?.message || err?.message || 'Failed to create account.'
  } finally {
    isAccountSubmitting.value = false
  }
}

const handleDeleteAccount = async (id: string, name: string) => {
  if (confirm(`Are you sure you want to remove ${name}?`)) {
    try {
      await accountStore.deleteAccount(id)
    } catch (err: any) {
      alert(err?.data?.message || 'Failed to delete account.')
    }
  }
}

// Seed a quick 3-account starter portfolio for immediate portfolio demo
const seedSamplePortfolio = async () => {
  if (!authStore.isAuthenticated) return
  isAccountSubmitting.value = true
  try {
    const mbb = accountStore.institutions.find(i => i.code === 'MBB')
    const tng = accountStore.institutions.find(i => i.code === 'TNG')
    const cimb = accountStore.institutions.find(i => i.code === 'CIMB')

    if (mbb) {
      await accountStore.createAccount({
        institution_id: mbb.id,
        name: 'Maybank Premier Savings',
        account_type: 'bank',
        currency: authStore.user?.base_currency || 'MYR',
        opening_balance: 14250.00,
        account_number_masked: '••••4198',
      })
    }
    if (tng) {
      await accountStore.createAccount({
        institution_id: tng.id,
        name: 'Touch n Go eWallet',
        account_type: 'e_wallet',
        currency: authStore.user?.base_currency || 'MYR',
        opening_balance: 850.50,
      })
    }
    if (cimb) {
      await accountStore.createAccount({
        institution_id: cimb.id,
        name: 'CIMB Visa Signature',
        account_type: 'credit_card',
        currency: authStore.user?.base_currency || 'MYR',
        opening_balance: 1240.20,
        account_number_masked: '••••8821',
      })
    }
  } catch (err: any) {
    console.error('Error seeding portfolio:', err)
  } finally {
    isAccountSubmitting.value = false
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

onMounted(async () => {
  await Promise.all([
    checkHealth(),
    accountStore.fetchInstitutions(),
    authStore.fetchMe(),
  ])

  if (authStore.isAuthenticated) {
    await accountStore.fetchAccounts()
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
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Phase 3</span>
            </div>
            <p class="text-[11px] text-slate-400 font-mono">Personal Finance & Budget Platform</p>
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
      <!-- Authenticated User Banner or Guest Hero -->
      <section v-if="authStore.isAuthenticated" class="p-6 rounded-2xl bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900/60 border border-emerald-500/30 shadow-xl backdrop-blur-md flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
            <UserCheck class="w-6 h-6" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <h2 class="text-xl font-bold text-white">{{ authStore.user?.name }}'s Financial Hub</h2>
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">UUIDv7 Tenant Active</span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Base Ledger: <span class="text-emerald-400 font-mono font-bold">{{ authStore.user?.base_currency }}</span> &bull;
              User UUID: <span class="text-slate-300 font-mono text-[10px]">{{ authStore.user?.id }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center space-x-3">
          <button
            @click="openAddAccountModal"
            class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-2 shadow-lg shadow-emerald-500/20"
          >
            <Plus class="w-4 h-4" />
            <span>Add Financial Account</span>
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
            <span>Phase 3 — Financial Accounts Domain</span>
          </div>

          <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
            NextOmni Finance — Multi-Account Ledger & Ingestion Engine
          </h1>

          <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
            Engineered with <span class="text-emerald-400 font-medium">Laravel 11 (PHP 8.3)</span>,
            <span class="text-emerald-400 font-medium">PostgreSQL 16 UUIDv7</span>, and
            <span class="text-emerald-400 font-medium">Nuxt 3</span>. Features strict <code class="text-emerald-400 font-mono">NUMERIC(15, 4)</code>
            BCMath precision across banking, e-wallets, credit cards, and cash.
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

      <!-- User's Financial Accounts Grid (Phase 3 Core Feature) -->
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

        <!-- Empty State for Authenticated User with no accounts -->
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
              Seed Sample Portfolio (Maybank + TNG + CIMB)
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
          <!-- Financial Institution Selector -->
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

          <!-- Account Name -->
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

          <!-- Account Type -->
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

          <!-- Opening Balance & Currency -->
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

          <!-- Masked Number (Optional) -->
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
  </div>
</template>
