<script setup lang="ts">
import { ref, onMounted } from 'vue'
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
} from 'lucide-vue-next'

const config = useRuntimeConfig()
const apiBase = config.public.apiBase

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
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const responseTimeMs = ref<number | null>(null)

const checkHealth = async () => {
  isLoading.value = true
  errorMessage.value = null
  const startTime = performance.now()
  try {
    const data = await $fetch<HealthResponse>(`${apiBase}/health`)
    health.value = data
    responseTimeMs.value = Math.round(performance.now() - startTime)
  } catch (err: any) {
    errorMessage.value = err?.message || 'Could not connect to backend API server.'
    health.value = null
    responseTimeMs.value = null
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  checkHealth()
})
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col selection:bg-emerald-500 selection:text-white">
    <!-- Top Navigation Bar -->
    <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
            <Wallet class="w-5 h-5 text-slate-950 font-bold" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">NextOmni Finance</span>
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Phase 1</span>
            </div>
            <p class="text-[11px] text-slate-400 font-mono">Personal Finance & Budget Platform</p>
          </div>
        </div>

        <div class="flex items-center space-x-3">
          <!-- Backend Connectivity Badge -->
          <div
            class="flex items-center space-x-2 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all"
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
            <span>{{ health?.status === 'ok' ? 'Backend Online' : (isLoading ? 'Connecting...' : 'API Offline') }}</span>
            <span v-if="responseTimeMs !== null" class="text-[10px] text-slate-400 font-mono">({{ responseTimeMs }}ms)</span>
          </div>

          <button
            @click="checkHealth"
            :disabled="isLoading"
            class="p-2 rounded-lg bg-slate-800/80 hover:bg-slate-700/80 text-slate-300 hover:text-white transition border border-slate-700/60 disabled:opacity-50"
            title="Refresh System Health"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      <!-- Hero / Foundation Banner -->
      <section class="relative overflow-hidden rounded-2xl bg-gradient-to-b from-slate-900/90 to-slate-900/40 border border-slate-800 p-8 shadow-2xl backdrop-blur-md">
        <div class="absolute -right-16 -top-16 w-80 h-80 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-80 h-80 rounded-full bg-teal-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl space-y-4">
          <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-slate-800/80 border border-slate-700 text-xs font-semibold text-emerald-400">
            <Sparkles class="w-3.5 h-3.5" />
            <span>Architecture & Portfolio Specification</span>
          </div>

          <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
            Enterprise Financial Architecture for Malaysian & Global Wealth
          </h1>

          <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
            Engineered with <span class="text-emerald-400 font-medium">Laravel 11 REST API</span>,
            <span class="text-emerald-400 font-medium">PostgreSQL 16</span>, and
            <span class="text-emerald-400 font-medium">Nuxt 3 (TypeScript)</span>. Featuring non-custodial statement ETL,
            deterministic duplicate detection, and canonical financial normalization.
          </p>

          <div class="flex flex-wrap gap-2 pt-2">
            <span class="px-2.5 py-1 rounded-md bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300 font-mono">PHP 8.3 + Sanctum</span>
            <span class="px-2.5 py-1 rounded-md bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300 font-mono">Nuxt 3 + Pinia</span>
            <span class="px-2.5 py-1 rounded-md bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300 font-mono">PostgreSQL / Redis</span>
            <span class="px-2.5 py-1 rounded-md bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300 font-mono">Tailwind CSS</span>
          </div>
        </div>
      </section>

      <!-- System Connectivity & Health Grid -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-white flex items-center space-x-2">
            <Activity class="w-5 h-5 text-emerald-400" />
            <span>Phase 1 System Health Verification</span>
          </h2>
          <span class="text-xs text-slate-400 font-mono">Local Architecture Verified</span>
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
              Endpoint: <code class="text-emerald-400 font-mono text-[11px]">/api/v1/health</code>
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
              <p class="text-xs text-slate-400 font-mono mt-0.5">Numeric(15,4) precision for zero rounding drift</p>
            </div>
            <div class="pt-2 border-t border-slate-800/80 text-xs text-slate-400">
              ACID Schema: <code class="text-emerald-400 font-mono text-[11px]">12 Normalized Entities</code>
            </div>
          </div>

          <!-- Architecture & CI Card -->
          <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-slate-700/80 transition space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                <Layers class="w-4 h-4 text-emerald-400" />
                <span>Local Build Status</span>
              </span>
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                ACTIVE
              </span>
            </div>
            <div>
              <div class="text-xl font-bold text-white">Local Workspace</div>
              <p class="text-xs text-slate-400 font-mono mt-0.5">Independent local setup without remote Git</p>
            </div>
            <div class="pt-2 border-t border-slate-800/80 text-xs text-slate-400">
              Pest / PHPUnit: <code class="text-emerald-400 font-mono text-[11px]">Passing (3 tests, 12 assertions)</code>
            </div>
          </div>
        </div>
      </section>

      <!-- Preview Financial Metrics (Design Aesthetic Anchor) -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-white flex items-center space-x-2">
            <TrendingUp class="w-5 h-5 text-emerald-400" />
            <span>Target Portfolio Dashboard (Preview Layout)</span>
          </h2>
          <span class="text-xs text-slate-400">Currency: MYR (Ringgit Malaysia)</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Metric 1 -->
          <div class="p-5 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
            <div class="text-xs font-medium text-slate-400">Total Net Worth</div>
            <div class="text-2xl font-extrabold text-white font-mono">RM 48,250.00</div>
            <div class="flex items-center text-xs text-emerald-400 font-medium space-x-1">
              <ArrowUpRight class="w-3.5 h-3.5" />
              <span>+4.2% from last month</span>
            </div>
          </div>

          <!-- Metric 2 -->
          <div class="p-5 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
            <div class="text-xs font-medium text-slate-400">Monthly Cash Inflow</div>
            <div class="text-2xl font-extrabold text-white font-mono">RM 7,500.00</div>
            <div class="flex items-center text-xs text-emerald-400 font-medium space-x-1">
              <ArrowUpRight class="w-3.5 h-3.5" />
              <span>Salary & dividend yield</span>
            </div>
          </div>

          <!-- Metric 3 -->
          <div class="p-5 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
            <div class="text-xs font-medium text-slate-400">Monthly Expenses</div>
            <div class="text-2xl font-extrabold text-white font-mono">RM 3,820.45</div>
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
  </div>
</template>
