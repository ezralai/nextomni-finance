<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '~/stores/auth'
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
} from 'lucide-vue-next'

const config = useRuntimeConfig()
const apiBase = config.public.apiBase
const authStore = useAuthStore()

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
const authSuccess = ref<string | null>(null)

// Forms
const loginForm = ref({
  email: '',
  password: '',
})

const registerForm = ref({
  name: '',
  email: '',
  password: '',
  base_currency: 'MYR',
})

// Currency Switcher State
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
    // Attempt login first
    await authStore.login({ email: demoEmail, password: demoPass })
    showAuthModal.value = false
  } catch {
    // If not exists, register demo user
    try {
      await authStore.register({
        name: 'Demo Investor',
        email: demoEmail,
        password: demoPass,
        base_currency: 'MYR',
      })
      showAuthModal.value = false
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
  } catch (err: any) {
    console.error('Failed to update base currency:', err)
  } finally {
    isUpdatingCurrency.value = false
  }
}

onMounted(async () => {
  await Promise.all([checkHealth(), authStore.fetchMe()])
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
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Phase 2</span>
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
      <!-- Authenticated User Banner or Hero -->
      <section v-if="authStore.isAuthenticated" class="p-6 rounded-2xl bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900/60 border border-emerald-500/30 shadow-xl backdrop-blur-md flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
            <UserCheck class="w-6 h-6" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <h2 class="text-xl font-bold text-white">Welcome back, {{ authStore.user?.name }}</h2>
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Session Active</span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Base Ledger Currency: <span class="text-emerald-400 font-mono font-bold">{{ authStore.user?.base_currency }}</span> &bull;
              Account Isolation: <span class="text-slate-300 font-mono">User ID #{{ authStore.user?.id }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center space-x-3 text-xs text-slate-400 font-mono">
          <ShieldCheck class="w-4 h-4 text-emerald-400" />
          <span>Sanctum Token Authentication Enabled</span>
        </div>
      </section>

      <!-- Guest Welcome Banner -->
      <section v-else class="relative overflow-hidden rounded-2xl bg-gradient-to-b from-slate-900/90 to-slate-900/40 border border-slate-800 p-8 shadow-2xl backdrop-blur-md">
        <div class="absolute -right-16 -top-16 w-80 h-80 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-80 h-80 rounded-full bg-teal-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl space-y-4">
          <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-slate-800/80 border border-slate-700 text-xs font-semibold text-emerald-400">
            <Sparkles class="w-3.5 h-3.5" />
            <span>Architecture & Portfolio Specification</span>
          </div>

          <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
            NextOmni Finance — Enterprise Financial Intelligence
          </h1>

          <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
            Engineered with <span class="text-emerald-400 font-medium">Laravel 11 REST API</span>,
            <span class="text-emerald-400 font-medium">PostgreSQL 16</span>, and
            <span class="text-emerald-400 font-medium">Nuxt 3 (TypeScript)</span>. Featuring non-custodial statement ETL,
            deterministic duplicate detection, and canonical financial normalization.
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
              Create Personal Account
            </button>
          </div>
        </div>
      </section>

      <!-- System Connectivity & Health Grid -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-white flex items-center space-x-2">
            <Activity class="w-5 h-5 text-emerald-400" />
            <span>System Health & Authentication Verification</span>
          </h2>
          <span class="text-xs text-slate-400 font-mono">Phase 2 Verification</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Backend API Card -->
          <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-slate-700/80 transition space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                <Server class="w-4 h-4 text-emerald-400" />
                <span>Backend Gateway</span>
              </span>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold"
                :class="health?.status === 'ok' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30'"
              >
                {{ health ? health.status.toUpperCase() : 'PENDING' }}
              </span>
            </div>
            <div>
              <div class="text-xl font-bold text-white">{{ health?.app || 'NextOmni Finance API' }}</div>
              <p class="text-xs text-slate-400 font-mono mt-0.5">Version: {{ health?.version || '1.0.0' }} (Env: {{ health?.environment || 'local' }})</p>
            </div>
            <div class="pt-2 border-t border-slate-800/80 text-xs text-slate-400">
              Auth Guard: <code class="text-emerald-400 font-mono text-[11px]">Sanctum Bearer Token</code>
            </div>
          </div>

          <!-- Database Card -->
          <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-slate-700/80 transition space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                <Database class="w-4 h-4 text-emerald-400" />
                <span>Database Engine</span>
              </span>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold"
                :class="health?.database?.status === 'connected' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
              >
                {{ health?.database?.status ? health.database.status.toUpperCase() : 'CHECKING' }}
              </span>
            </div>
            <div>
              <div class="text-xl font-bold text-white capitalize">{{ health?.database?.driver || 'PostgreSQL / SQLite' }}</div>
              <p class="text-xs text-slate-400 font-mono mt-0.5">Numeric(15,4) precision & multi-tenant user schema</p>
            </div>
            <div class="pt-2 border-t border-slate-800/80 text-xs text-slate-400">
              User Isolation: <code class="text-emerald-400 font-mono text-[11px]">users.base_currency (Migrated)</code>
            </div>
          </div>

          <!-- Security Card -->
          <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-slate-700/80 transition space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                <ShieldCheck class="w-4 h-4 text-emerald-400" />
                <span>Security & Rate Limiting</span>
              </span>
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                ENFORCED
              </span>
            </div>
            <div>
              <div class="text-xl font-bold text-white">5 Attempts / Min</div>
              <p class="text-xs text-slate-400 font-mono mt-0.5">Brute-force protection & strict password entropy</p>
            </div>
            <div class="pt-2 border-t border-slate-800/80 text-xs text-slate-400">
              Feature Tests: <code class="text-emerald-400 font-mono text-[11px]">13 Passed (58 assertions)</code>
            </div>
          </div>
        </div>
      </section>

      <!-- Preview Financial Metrics (Reactive to Base Currency) -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-white flex items-center space-x-2">
            <TrendingUp class="w-5 h-5 text-emerald-400" />
            <span>Target Portfolio Dashboard (Preview Layout)</span>
          </h2>
          <span class="text-xs text-slate-400 font-mono">Base Currency: {{ authStore.user?.base_currency || 'MYR' }}</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Metric 1 -->
          <div class="p-5 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
            <div class="text-xs font-medium text-slate-400">Total Net Worth</div>
            <div class="text-2xl font-extrabold text-white font-mono">{{ currencySymbol }} 48,250.00</div>
            <div class="flex items-center text-xs text-emerald-400 font-medium space-x-1">
              <ArrowUpRight class="w-3.5 h-3.5" />
              <span>+4.2% from last month</span>
            </div>
          </div>

          <!-- Metric 2 -->
          <div class="p-5 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
            <div class="text-xs font-medium text-slate-400">Monthly Cash Inflow</div>
            <div class="text-2xl font-extrabold text-white font-mono">{{ currencySymbol }} 7,500.00</div>
            <div class="flex items-center text-xs text-emerald-400 font-medium space-x-1">
              <ArrowUpRight class="w-3.5 h-3.5" />
              <span>Salary & dividend yield</span>
            </div>
          </div>

          <!-- Metric 3 -->
          <div class="p-5 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
            <div class="text-xs font-medium text-slate-400">Monthly Expenses</div>
            <div class="text-2xl font-extrabold text-white font-mono">{{ currencySymbol }} 3,820.45</div>
            <div class="flex items-center text-xs text-amber-400 font-medium space-x-1">
              <ArrowDownLeft class="w-3.5 h-3.5" />
              <span>50.9% of total income</span>
            </div>
          </div>

          <!-- Metric 4 -->
          <div class="p-5 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
            <div class="text-xs font-medium text-slate-400">Savings Rate</div>
            <div class="text-2xl font-extrabold text-emerald-400 font-mono">49.1%</div>
            <div class="flex items-center text-xs text-slate-400 space-x-1">
              <span>Target: &gt; 40.0%</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Malaysian Financial Connectors & Institutions Preview -->
      <section class="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 space-y-4">
        <h3 class="text-base font-bold text-white">Supported Malaysian Institutions & Connector Architecture</h3>
        <p class="text-xs text-slate-400 leading-relaxed">
          Standardized via the <code class="text-emerald-400 font-mono">FinancialConnectorInterface</code> with non-custodial CSV statement parsing (M2U, CIMB Clicks, TNG eWallet, GrabPay) and deterministic deduplication.
        </p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
          <div class="p-3 rounded-lg bg-slate-800/40 border border-slate-700/50 flex items-center space-x-3">
            <div class="w-8 h-8 rounded-md bg-yellow-500/20 text-yellow-400 flex items-center justify-center font-bold text-xs">MB</div>
            <div>
              <div class="text-xs font-semibold text-white">Maybank</div>
              <div class="text-[10px] text-slate-400 font-mono">M2U CSV Parser</div>
            </div>
          </div>

          <div class="p-3 rounded-lg bg-slate-800/40 border border-slate-700/50 flex items-center space-x-3">
            <div class="w-8 h-8 rounded-md bg-red-500/20 text-red-400 flex items-center justify-center font-bold text-xs">CIMB</div>
            <div>
              <div class="text-xs font-semibold text-white">CIMB Bank</div>
              <div class="text-[10px] text-slate-400 font-mono">Clicks Statement</div>
            </div>
          </div>

          <div class="p-3 rounded-lg bg-slate-800/40 border border-slate-700/50 flex items-center space-x-3">
            <div class="w-8 h-8 rounded-md bg-blue-500/20 text-blue-400 flex items-center justify-center font-bold text-xs">TNG</div>
            <div>
              <div class="text-xs font-semibold text-white">TNG eWallet</div>
              <div class="text-[10px] text-slate-400 font-mono">Statement Parser</div>
            </div>
          </div>

          <div class="p-3 rounded-lg bg-slate-800/40 border border-slate-700/50 flex items-center space-x-3">
            <div class="w-8 h-8 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs">GRAB</div>
            <div>
              <div class="text-xs font-semibold text-white">GrabPay</div>
              <div class="text-[10px] text-slate-400 font-mono">Activity Export</div>
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

        <!-- Modal Header / Tabs -->
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

        <!-- Alert messages -->
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

        <!-- Quick Demo Session Option -->
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
  </div>
</template>
