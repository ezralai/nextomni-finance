<script setup lang="ts">
import {
  Cpu,
  ArrowLeft,
  Layers,
  Database,
  Lock,
  Server,
  ShieldCheck,
  CheckCircle2,
  Terminal,
  ExternalLink,
  Zap,
} from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'

useHead({
  title: 'System Architecture & Engineering Specs — NextOmni Finance',
  meta: [
    {
      name: 'description',
      content:
        'Technical architecture, database design, and security engineering specifications for NextOmni Finance. Laravel 11 REST API, Nuxt 3 SSR, PostgreSQL 16, and Docker.',
    },
  ],
})

const authStore = useAuthStore()

const handleDemoLaunch = async () => {
  try {
    await authStore.demoLogin()
    await navigateTo('/workspace')
  } catch (err: any) {
    console.error('Demo error', err)
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col font-sans transition-colors duration-200">
    <!-- Top Nav -->
    <header class="sticky top-0 z-40 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/80 backdrop-blur-xl">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <NuxtLink
          to="/"
          class="flex items-center space-x-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-950 dark:hover:text-white transition"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Product</span>
        </NuxtLink>

        <div class="flex items-center space-x-3">
          <span class="px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 text-[11px] font-mono font-semibold">
            Technical Specification v1.0
          </span>

          <button
            type="button"
            @click="handleDemoLaunch"
            class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-sm"
          >
            <Zap class="w-3.5 h-3.5 fill-current" />
            <span>Launch Live Demo</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Main Spec Content -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
      <!-- Document Header -->
      <div class="space-y-4 border-b border-slate-200 dark:border-slate-800 pb-8">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-mono text-xs font-bold">
          <Cpu class="w-4 h-4" />
          <span>NextOmni Finance Engineering Profile</span>
        </div>
        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-950 dark:text-white">
          System Architecture & Technical Specifications
        </h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl">
          An architectural breakdown of the non-custodial personal finance platform. Detailed for engineering evaluators, recruiters, and security auditors.
        </p>
      </div>

      <!-- 1. High-Level Architecture Topology -->
      <section class="space-y-4">
        <h2 class="text-xl font-bold flex items-center space-x-2 text-slate-900 dark:text-white">
          <Layers class="w-5 h-5 text-emerald-500" />
          <span>1. Architecture Topology & Tier Separation</span>
        </h2>
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
          The system follows a strict decoupling between presentation, gateway, asynchronous background processing, and high-precision persistence.
        </p>

        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 text-slate-200 font-mono text-xs overflow-x-auto shadow-inner leading-relaxed">
<pre class="text-[11px] text-emerald-400">
┌─────────────────────────────────────────────────────────────┐
│                   Nuxt 3 Client (SPA/SSR)                   │
│   (Vue 3, TypeScript, Tailwind CSS, Pinia, Tabular-Nums)    │
└──────────────────────────────┬──────────────────────────────┘
                               │ HTTPS / JSON REST API (v1)
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                   Laravel 11 API Gateway                    │
│   - Sanctum Token Authentication & IP Rate Limiting         │
│   - FormRequest Strict Validation & Schema Enforcement      │
│   - Policy-based Authorization (Tenant Data Isolation)      │
└──────────────┬──────────────────────────────┬───────────────┘
               │                              │
       Synchronous Queries            Async Dispatches
               ▼                              ▼
┌──────────────────────────────┐ ┌────────────────────────────┐
│      Application Domain      │ │     Redis Queue Worker     │
│ - AccountService             │ │ - CsvStatementImportJob    │
│ - TransactionService         │ │ - StatementNormalizerJob   │
│ - BudgetTrackingService      │ │ - RecurringTxGeneratorJob  │
│ - DuplicateDetector          │ │ - AnomalyDetectorJob       │
└──────────────┬───────────────┘ └────────────┬───────────────┘
               │                              │
               └──────────────┬───────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                 PostgreSQL 16 Database                      │
│ - Users, Accounts, Transactions, Categories, Budgets        │
│ - Deduplication Expression Indexes & Numeric(15, 4) Amounts │
└─────────────────────────────────────────────────────────────┘
</pre>
        </div>
      </section>

      <!-- 2. Core Engineering Decisions -->
      <section class="space-y-4">
        <h2 class="text-xl font-bold flex items-center space-x-2 text-slate-900 dark:text-white">
          <Database class="w-5 h-5 text-sky-500" />
          <span>2. Monetary & Mathematical Integrity</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white">BCMath & Arbitrary Precision</h3>
            <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
              Standard binary floating-point representation (IEEE 754) introduces cumulative rounding drift during multi-currency conversions. All financial balance operations employ PHP BCMath functions with fixed scale precision, mapped to PostgreSQL <code class="text-emerald-500 font-mono">NUMERIC(15, 4)</code> columns.
            </p>
          </div>

          <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white">Multi-Tenant Scoping</h3>
            <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
              Every data model query is scoped to the authenticated user ID (<code class="text-indigo-400 font-mono">user_id = Auth::id()</code>). Laravel authorization policies guard resource parameters, completely preventing Insecure Direct Object References (IDOR).
            </p>
          </div>
        </div>
      </section>

      <!-- 3. Ingestion Pipeline & Security -->
      <section class="space-y-4">
        <h2 class="text-xl font-bold flex items-center space-x-2 text-slate-900 dark:text-white">
          <Lock class="w-5 h-5 text-indigo-500" />
          <span>3. Non-Custodial Security & Deduplication Hashing</span>
        </h2>
        <div class="p-6 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-4 text-xs">
          <div class="space-y-2">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white">SHA-256 Idempotent Reconciliation</h3>
            <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
              When users upload bank statement CSV files, each transaction record computes a deterministic SHA-256 hash based on <code class="font-mono text-emerald-500">sha256(user_id + account_id + transacted_at + amount + description)</code>. Overlapping statements skip duplicate rows automatically, eliminating manual reconciliation overhead.
            </p>
          </div>

          <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white">Anti-Formula CSV Injection Mitigation</h3>
            <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
              Export and upload streams sanitize cells beginning with spreadsheet calculation operators (<code class="font-mono text-amber-500">=, +, -, @, \t, \r</code>) by prefixing a single quotation mark, completely safeguarding downstream accounting exports from formula execution attacks.
            </p>
          </div>
        </div>
      </section>

      <!-- 4. Deployment & Infrastructure -->
      <section class="space-y-4">
        <h2 class="text-xl font-bold flex items-center space-x-2 text-slate-900 dark:text-white">
          <Server class="w-5 h-5 text-amber-500" />
          <span>4. DevOps, Containerization & CI/CD</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs font-mono">
          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-1">
            <div class="text-slate-500 dark:text-slate-400 text-[10px]">BACKEND HOSTING</div>
            <div class="font-bold text-slate-900 dark:text-white">Render Web Service</div>
            <div class="text-emerald-500 text-[11px]">Docker Multi-Stage PHP 8.3</div>
          </div>
          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-1">
            <div class="text-slate-500 dark:text-slate-400 text-[10px]">DATABASE HOSTING</div>
            <div class="font-bold text-slate-900 dark:text-white">Supabase PostgreSQL 16</div>
            <div class="text-sky-500 text-[11px]">Connection Pooling (Port 6543)</div>
          </div>
          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-1">
            <div class="text-slate-500 dark:text-slate-400 text-[10px]">FRONTEND HOSTING</div>
            <div class="font-bold text-slate-900 dark:text-white">Vercel Edge Network</div>
            <div class="text-indigo-500 text-[11px]">Nuxt 3 Nitro Serverless</div>
          </div>
        </div>
      </section>

      <!-- Footer CTA -->
      <div class="p-6 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-indigo-500/10 border border-emerald-500/30 flex items-center justify-between">
        <div>
          <h4 class="text-sm font-bold text-slate-900 dark:text-white">Evaluate the Running Application</h4>
          <p class="text-xs text-slate-600 dark:text-slate-400">Launch an ephemeral session with full accounts, ledgers, and budgets pre-configured.</p>
        </div>
        <button
          type="button"
          @click="handleDemoLaunch"
          class="px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20"
        >
          <span>Launch Live Demo</span>
          <Zap class="w-3.5 h-3.5 fill-current" />
        </button>
      </div>
    </main>
  </div>
</template>
