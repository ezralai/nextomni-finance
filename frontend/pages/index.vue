<script setup lang="ts">
import { ref } from 'vue'
import {
  TrendingUp,
  ShieldCheck,
  Zap,
  ArrowRight,
  Repeat,
  Coins,
  FileSpreadsheet,
  Lock,
  CheckCircle2,
  Cpu,
  Download,
  Database,
  Server,
  Layers,
  ChevronDown,
} from 'lucide-vue-next'
import LandingNav from '~/components/landing/LandingNav.vue'
import DashboardPreviewMockup from '~/components/landing/DashboardPreviewMockup.vue'
import TechSpecsDrawer from '~/components/landing/TechSpecsDrawer.vue'
import InstitutionLogo from '~/components/ui/InstitutionLogo.vue'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

useHead({
  title: 'NextOmni Finance — Non-Custodial Multi-Currency Financial OS',
  meta: [
    {
      name: 'description',
      content:
        'NextOmni Finance: High-precision personal wealth tracker with automated recurring subscriptions, non-custodial bank statement ETL, and real-time multi-currency consolidation.',
    },
  ],
})

const authStore = useAuthStore()
const toast = useToast()

const isTechSpecsOpen = ref(false)
const isLaunchingDemo = ref(false)

const emit = defineEmits<{
  (e: 'open-auth', mode: 'login' | 'register'): void
}>()

const handleDemoLaunch = async () => {
  isLaunchingDemo.value = true
  try {
    await authStore.demoLogin()
    toast.success('Demo Session Active', 'Welcome to NextOmni Finance Live Demo Workspace')
    await navigateTo('/workspace')
  } catch (err: any) {
    toast.error('Demo Error', err.message || 'Failed to initialize demo session.')
  } finally {
    isLaunchingDemo.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col selection:bg-emerald-500 selection:text-white transition-colors duration-200">
    <!-- Navbar -->
    <LandingNav
      :is-authenticated="authStore.isAuthenticated"
      @open-auth="(mode) => emit('open-auth', mode)"
      @open-specs="isTechSpecsOpen = true"
    />

    <!-- Hero Section -->
    <section class="relative pt-12 pb-20 md:pt-20 md:pb-32 overflow-hidden">
      <!-- Ambient Background Glows -->
      <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[350px] bg-gradient-to-tr from-emerald-500/15 to-teal-500/10 blur-[120px] rounded-full pointer-events-none -z-10"></div>
      <div class="absolute top-1/3 right-1/4 w-[400px] h-[250px] bg-gradient-to-br from-indigo-500/10 to-sky-500/10 blur-[100px] rounded-full pointer-events-none -z-10"></div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-6 max-w-4xl mx-auto">
          <!-- Architecture Pill Tag specifically for evaluators -->
          <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/10 dark:bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-semibold">
            <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Non-Custodial Multi-Currency Operating System</span>
            <span class="text-slate-400 dark:text-slate-600">•</span>
            <button
              type="button"
              @click="isTechSpecsOpen = true"
              class="font-mono text-[11px] underline hover:text-emerald-600 dark:hover:text-emerald-300 cursor-pointer"
            >
              Specs
            </button>
          </div>

          <!-- Main Value Proposition Headline -->
          <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-slate-950 dark:text-white leading-[1.1] sm:leading-[1.1]">
            Master Your Wealth With
            <span class="bg-gradient-to-r from-emerald-600 via-teal-500 to-sky-500 dark:from-emerald-400 dark:via-teal-300 dark:to-sky-400 bg-clip-text text-transparent">
              Absolute Precision
            </span>
          </h1>

          <!-- Value Proposition Subheadline -->
          <p class="text-base sm:text-xl text-slate-600 dark:text-slate-300 max-w-2xl mx-auto leading-relaxed">
            Consolidate Malaysian and global bank accounts, stream live Bursa & US stock portfolios, forecast recurring subscriptions, and reconcile statements with zero custodial risk.
          </p>

          <!-- Action CTAs -->
          <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5 pt-2">
            <template v-if="authStore.isAuthenticated">
              <NuxtLink
                to="/workspace"
                class="w-full sm:w-auto px-8 py-3.5 rounded-2xl text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 shadow-xl shadow-emerald-500/20 hover:scale-[1.02]"
              >
                <span>Enter Workspace</span>
                <ArrowRight class="w-4 h-4" />
              </NuxtLink>
            </template>
            <template v-else>
              <button
                type="button"
                @click="handleDemoLaunch"
                :disabled="isLaunchingDemo"
                class="w-full sm:w-auto px-8 py-3.5 rounded-2xl text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 shadow-xl shadow-emerald-500/20 hover:scale-[1.02] cursor-pointer disabled:opacity-50"
              >
                <Zap class="w-4 h-4 text-slate-950 fill-current" />
                <span>{{ isLaunchingDemo ? 'Bootstrapping Demo...' : 'Launch Live Demo Session' }}</span>
              </button>
              <button
                type="button"
                @click="emit('open-auth', 'register')"
                class="w-full sm:w-auto px-7 py-3.5 rounded-2xl text-sm font-semibold border border-slate-300 dark:border-slate-700/80 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 transition flex items-center justify-center space-x-2 cursor-pointer"
              >
                <span>Create Account</span>
              </button>
            </template>
          </div>

          <!-- Trust Badges Under Hero -->
          <div class="flex flex-wrap items-center justify-center gap-y-2 gap-x-6 pt-4 text-xs font-mono text-slate-500 dark:text-slate-400">
            <span class="flex items-center space-x-1.5">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500" />
              <span>Zero Scraping Credentials</span>
            </span>
            <span class="flex items-center space-x-1.5">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500" />
              <span>Real-Time Bursa & US Markets</span>
            </span>
            <span class="flex items-center space-x-1.5">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500" />
              <span>BCMath Arbitrary Precision</span>
            </span>
            <span class="flex items-center space-x-1.5">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500" />
              <span>Malaysian PDPA Compliant</span>
            </span>
          </div>

          <!-- Interactive Partner & Institution Logo Ribbon -->
          <div class="pt-6 pb-2">
            <p class="text-[11px] uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400 font-mono text-center mb-3">
              Seamless Reconciliation Across Leading Banks & Digital E-Wallets
            </p>
            <div class="flex flex-wrap items-center justify-center gap-2.5 sm:gap-3.5 max-w-4xl mx-auto">
              <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-sm hover:border-sky-500/50 hover:shadow-sky-500/10 transition group">
                <InstitutionLogo code="TNG" size="sm" interactive />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-sky-500 transition-colors">Touch 'n Go</span>
              </div>
              <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-sm hover:border-amber-500/50 hover:shadow-amber-500/10 transition group">
                <InstitutionLogo code="MBB" size="sm" interactive />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-amber-500 transition-colors">Maybank</span>
              </div>
              <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-sm hover:border-red-500/50 hover:shadow-red-500/10 transition group">
                <InstitutionLogo code="CIMB" size="sm" interactive />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-red-500 transition-colors">CIMB</span>
              </div>
              <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-sm hover:border-emerald-500/50 hover:shadow-emerald-500/10 transition group">
                <InstitutionLogo code="GRAB" size="sm" interactive />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-emerald-500 transition-colors">GrabPay</span>
              </div>
              <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-sm hover:border-rose-500/50 hover:shadow-rose-500/10 transition group">
                <InstitutionLogo code="PBB" size="sm" interactive />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-rose-500 transition-colors">Public Bank</span>
              </div>
              <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-sm hover:border-blue-500/50 hover:shadow-blue-500/10 transition group">
                <InstitutionLogo code="RHB" size="sm" interactive />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-blue-500 transition-colors">RHB</span>
              </div>
              <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-sm hover:border-lime-500/50 hover:shadow-lime-500/10 transition group">
                <InstitutionLogo code="WISE" size="sm" interactive />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-lime-500 transition-colors">Wise</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Interactive Visual Dashboard Mockup -->
        <div id="demo" class="pt-4 max-w-6xl mx-auto">
          <DashboardPreviewMockup />
        </div>
      </div>
    </section>

    <!-- Core Feature Highlights Section (Benefit-First) -->
    <section id="features" class="py-20 border-t border-slate-200 dark:border-slate-800/80 bg-slate-100/40 dark:bg-slate-900/20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        <div class="text-center space-y-3 max-w-2xl mx-auto">
          <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-mono">
            ENGINEERED FOR WEALTH CLARITY
          </span>
          <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 dark:text-white tracking-tight">
            Comprehensive Financial Control Without Compromise
          </h2>
          <p class="text-sm text-slate-600 dark:text-slate-400">
            Built from the ground up to eliminate guesswork in personal liquidity, automated recurring subscriptions, and multi-currency triangulation.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <!-- Feature 1: Real-Time Stock Markets -->
          <div class="p-7 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 hover:border-emerald-500/40 transition">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center">
              <TrendingUp class="w-6 h-6" />
            </div>
            <div class="space-y-1.5">
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">Real-Time Stock Markets</h3>
              <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                Stream live quotes, technical charts, and position P&L for Bursa Malaysia (KLSE) and US Markets (NYSE/NASDAQ).
              </p>
            </div>
            <ul class="text-xs font-mono text-slate-500 dark:text-slate-400 space-y-1.5 pt-1">
              <li class="flex items-center space-x-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Live USD ↔ MYR valuation</span>
              </li>
              <li class="flex items-center space-x-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Personal watchlist & P&L tracker</span>
              </li>
            </ul>
          </div>

          <!-- Feature 2: Automated Recurring Transactions -->
          <div class="p-7 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 hover:border-emerald-500/40 transition">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center">
              <Repeat class="w-6 h-6" />
            </div>
            <div class="space-y-1.5">
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">Recurring Obligation Engine</h3>
              <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                Automatically track and project recurring SaaS subscriptions, utility bills, and insurance premiums. Never get surprised by renewals.
              </p>
            </div>
            <ul class="text-xs font-mono text-slate-500 dark:text-slate-400 space-y-1.5 pt-1">
              <li class="flex items-center space-x-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Flexible cadences (Weekly, Monthly)</span>
              </li>
              <li class="flex items-center space-x-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Autonomous scheduling</span>
              </li>
            </ul>
          </div>

          <!-- Feature 3: Multi-Currency Triangulation -->
          <div class="p-7 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 hover:border-sky-500/40 transition">
            <div class="w-12 h-12 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 flex items-center justify-center">
              <Coins class="w-6 h-6" />
            </div>
            <div class="space-y-1.5">
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">Multi-Currency Engine</h3>
              <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                Consolidate bank accounts in MYR, USD, SGD, EUR, and GBP into your primary base currency with zero calculation drift.
              </p>
            </div>
            <ul class="text-xs font-mono text-slate-500 dark:text-slate-400 space-y-1.5 pt-1">
              <li class="flex items-center space-x-2">
                <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                <span>Cross-currency triangulation</span>
              </li>
              <li class="flex items-center space-x-2">
                <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                <span>BCMath decimal precision</span>
              </li>
            </ul>
          </div>

          <!-- Feature 4: Non-Custodial Bank Statement ETL -->
          <div class="p-7 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 hover:border-indigo-500/40 transition">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 flex items-center justify-center">
              <FileSpreadsheet class="w-6 h-6" />
            </div>
            <div class="space-y-1.5">
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">Bank Statement Ingestion</h3>
              <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                Drop standard CSV statements from Maybank, CIMB, or Wise. Automated SHA-256 fingerprinting guarantees zero duplicate transactions.
              </p>
            </div>
            <ul class="text-xs font-mono text-slate-500 dark:text-slate-400 space-y-1.5 pt-1">
              <li class="flex items-center space-x-2">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                <span>Deterministic hashing</span>
              </li>
              <li class="flex items-center space-x-2">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                <span>Anti-formula CSV mitigation</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- Trust, Security & Privacy Section -->
    <section id="security" class="py-20 border-t border-slate-200 dark:border-slate-800/80">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="max-w-3xl space-y-3">
          <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-mono">
            SECURITY & PRIVACY MODEL
          </span>
          <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-950 dark:text-white tracking-tight">
            Non-Custodial Architecture. Your Financial Data Belongs to You.
          </h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
            Most finance apps demand your banking login credentials to reverse-engineer private APIs. NextOmni Finance is non-custodial: we never ask for your online banking password.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 space-y-2.5">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold">
              <Lock class="w-4 h-4" />
            </div>
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Zero Scraping Credentials</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              We never collect or store banking passwords. You maintain complete control through user-driven statement reconciliation.
            </p>
          </div>

          <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 space-y-2.5">
            <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center font-bold">
              <ShieldCheck class="w-4 h-4" />
            </div>
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Tenant Scoped Isolation</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              Every API mutation and query is tenant-scoped to your authenticated user ID, guarded by strict authorization policies.
            </p>
          </div>

          <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 space-y-2.5">
            <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center font-bold">
              <Download class="w-4 h-4" />
            </div>
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Complete Data Portability</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              Export your entire transaction ledger and audit logs in standardized RFC 4180 CSV or JSON format at any time.
            </p>
          </div>

          <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 space-y-2.5">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">
              <Cpu class="w-4 h-4" />
            </div>
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Open Technical Specs</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              Full-stack architecture documentation openly published for engineering inspection and compliance verification.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Technical Architecture Banner Specifically for Tech Leads / Recruiters -->
    <section class="py-12 border-t border-slate-200 dark:border-slate-800 bg-slate-100/60 dark:bg-slate-900/40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="p-8 rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-slate-800 text-white flex flex-col md:flex-row items-center justify-between gap-6 shadow-xl">
          <div class="space-y-2 max-w-xl text-center md:text-left">
            <div class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-mono text-[11px]">
              <Cpu class="w-3.5 h-3.5" />
              <span>Full-Stack Engineering Evaluation</span>
            </div>
            <h3 class="text-xl sm:text-2xl font-bold">Review System Architecture & Engineering Specs</h3>
            <p class="text-xs text-slate-300 leading-relaxed">
              Inspect our Laravel 11 API domain topology, PostgreSQL arbitrary precision schema, Docker setup, and automated feature test suites.
            </p>
          </div>

          <div class="flex items-center space-x-3 flex-shrink-0">
            <button
              type="button"
              @click="isTechSpecsOpen = true"
              class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition flex items-center space-x-2 cursor-pointer shadow-lg shadow-indigo-600/30"
            >
              <span>View Interactive Specs</span>
              <ArrowRight class="w-4 h-4" />
            </button>
            <NuxtLink
              to="/specs"
              class="px-4 py-2.5 rounded-xl text-xs font-semibold border border-slate-700 bg-slate-800/80 hover:bg-slate-800 text-slate-200 transition"
            >
              Full Page
            </NuxtLink>
          </div>
        </div>
      </div>
    </section>

    <!-- High-Impact Conversion Banner -->
    <section class="py-20 border-t border-slate-200 dark:border-slate-800/80">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
        <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-slate-950 dark:text-white">
          Ready to Experience High-Precision Wealth Tracking?
        </h2>
        <p class="text-base text-slate-600 dark:text-slate-400 max-w-xl mx-auto">
          Try the full platform instantly with pre-populated demo data, or create an account to start tracking your assets.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5 pt-2">
          <button
            type="button"
            @click="handleDemoLaunch"
            :disabled="isLaunchingDemo"
            class="w-full sm:w-auto px-8 py-3.5 rounded-2xl text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 shadow-xl shadow-emerald-500/20 cursor-pointer disabled:opacity-50"
          >
            <Zap class="w-4 h-4 text-slate-950 fill-current" />
            <span>Launch Live Demo Session</span>
          </button>
          <button
            type="button"
            @click="emit('open-auth', 'register')"
            class="w-full sm:w-auto px-7 py-3.5 rounded-2xl text-sm font-semibold border border-slate-300 dark:border-slate-700/80 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 transition cursor-pointer"
          >
            Create Free Account
          </button>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="mt-auto border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 py-12 text-xs text-slate-500 dark:text-slate-400">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center space-x-3">
            <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-500 flex items-center justify-center">
              <Wallet class="w-4 h-4" />
            </div>
            <span class="font-bold text-slate-900 dark:text-white">NextOmni Finance</span>
          </div>

          <div class="flex flex-wrap items-center gap-6 text-xs">
            <NuxtLink to="/specs" class="hover:text-slate-900 dark:hover:text-white transition">Architecture Specs</NuxtLink>
            <a href="#features" class="hover:text-slate-900 dark:hover:text-white transition">Features</a>
            <a href="#security" class="hover:text-slate-900 dark:hover:text-white transition">Security</a>
            <button type="button" @click="emit('open-auth', 'login')" class="hover:text-slate-900 dark:hover:text-white transition cursor-pointer">Sign In</button>
          </div>
        </div>

        <div class="pt-6 border-t border-slate-100 dark:border-slate-900 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px]">
          <p>NextOmni Finance &copy; 2026. Self-directed personal finance platform. Non-custodial by design.</p>
          <p class="text-slate-400 dark:text-slate-500">Not a licensed financial advisory service under Bank Negara Malaysia.</p>
        </div>
      </div>
    </footer>

    <!-- Slide-over Tech Specs Drawer -->
    <TechSpecsDrawer :is-open="isTechSpecsOpen" @close="isTechSpecsOpen = false" />
  </div>
</template>
