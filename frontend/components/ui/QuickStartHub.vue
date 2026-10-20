<script setup lang="ts">
import { ref } from 'vue'
import {
  Sparkles,
  Wallet,
  Upload,
  ArrowRight,
  ShieldCheck,
  Coins,
  ReceiptText,
  X,
  HelpCircle,
} from 'lucide-vue-next'

interface Props {
  userName?: string
  baseCurrency?: string
  isSubmitting?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  userName: 'there',
  baseCurrency: 'MYR',
  isSubmitting: false,
})

const emit = defineEmits<{
  (e: 'add-account'): void
  (e: 'seed-demo'): void
  (e: 'import-csv'): void
  (e: 'open-tour'): void
}>()

const isDismissed = ref(false)
</script>

<template>
  <div
    v-if="!isDismissed"
    class="p-6 sm:p-8 rounded-2xl bg-gradient-to-br from-emerald-950/40 via-slate-900/90 to-slate-900/60 border border-emerald-500/30 shadow-2xl relative overflow-hidden backdrop-blur-md transition-all duration-300"
  >
    <!-- Ambient Background Glows -->
    <div class="absolute -top-16 -right-16 w-64 h-64 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none" />
    <div class="absolute -bottom-16 -left-16 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl pointer-events-none" />

    <!-- Top Header & Greeting -->
    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-800/80">
      <div class="space-y-1.5">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
          <Sparkles class="w-3.5 h-3.5" />
          <span>New Workspace Fast-Track</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
          Welcome to NextOmni Finance, {{ userName }}! 👋
        </h2>
        <p class="text-sm text-slate-300 max-w-2xl leading-relaxed">
          Your intelligent multi-currency financial command center is ready. Choose how you'd like to initialize your workspace:
        </p>
      </div>

      <div class="flex items-center space-x-2 self-start md:self-auto">
        <button
          type="button"
          @click="emit('open-tour')"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold text-emerald-400 hover:bg-emerald-500/10 border border-emerald-500/20 transition flex items-center space-x-1.5 cursor-pointer"
        >
          <HelpCircle class="w-4 h-4" />
          <span>Interactive Tour</span>
        </button>
        <button
          type="button"
          @click="isDismissed = true"
          class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
          title="Minimize Guide"
          aria-label="Minimize Guide"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- 3 Action Pathways Grid -->
    <div class="relative z-10 grid grid-cols-1 md:grid-cols-3 gap-4 pt-6">
      <!-- Path 1: Add First Account -->
      <div
        class="p-5 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-emerald-500/40 transition-all duration-200 flex flex-col justify-between space-y-4 group"
      >
        <div class="space-y-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center group-hover:scale-105 transition-transform">
            <Wallet class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-base font-bold text-white group-hover:text-emerald-300 transition">
              1. Add Your Bank or Wallet
            </h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Set up your primary Maybank, CIMB, Touch 'n Go, or cash account using our 1-click popular presets.
            </p>
          </div>
        </div>
        <button
          type="button"
          @click="emit('add-account')"
          class="w-full py-2.5 px-4 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 shadow-lg shadow-emerald-500/20 cursor-pointer"
        >
          <span>Add Account (1-Click Presets)</span>
          <ArrowRight class="w-3.5 h-3.5" />
        </button>
      </div>

      <!-- Path 2: 1-Click Demo Portfolio -->
      <div
        class="p-5 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-sky-500/40 transition-all duration-200 flex flex-col justify-between space-y-4 group"
      >
        <div class="space-y-3">
          <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/30 text-sky-400 flex items-center justify-center group-hover:scale-105 transition-transform">
            <Sparkles class="w-5 h-5" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <h3 class="text-base font-bold text-white group-hover:text-sky-300 transition">
                2. Try Sample Demo Portfolio
              </h3>
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-sky-500/20 text-sky-300">
                Instant
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Instantly seed 4 realistic accounts (bank, e-wallet, card, USD brokerage) with sample cash flow and budgets to explore everything.
            </p>
          </div>
        </div>
        <button
          type="button"
          @click="emit('seed-demo')"
          :disabled="isSubmitting"
          class="w-full py-2.5 px-4 rounded-xl text-xs font-bold bg-sky-500/20 hover:bg-sky-500/30 text-sky-300 border border-sky-500/40 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <span>{{ isSubmitting ? 'Loading Portfolio...' : 'Load Sample Portfolio' }}</span>
          <ArrowRight class="w-3.5 h-3.5" />
        </button>
      </div>

      <!-- Path 3: Import Bank Statement -->
      <div
        class="p-5 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-indigo-500/40 transition-all duration-200 flex flex-col justify-between space-y-4 group"
      >
        <div class="space-y-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 flex items-center justify-center group-hover:scale-105 transition-transform">
            <Upload class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-base font-bold text-white group-hover:text-indigo-300 transition">
              3. Import Bank Statement CSV
            </h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Export a CSV or statement from your online banking and upload it for automatic categorization and transaction reconciliation.
            </p>
          </div>
        </div>
        <button
          type="button"
          @click="emit('import-csv')"
          class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition flex items-center justify-center space-x-2 cursor-pointer"
        >
          <span>Upload Statement CSV</span>
          <ArrowRight class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Micro-Feature Guarantees Strip -->
    <div class="relative z-10 pt-6 mt-6 border-t border-slate-800/80 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-400">
      <div class="flex items-center space-x-2">
        <ShieldCheck class="w-4 h-4 text-emerald-400 flex-shrink-0" />
        <span>Privacy-first & non-custodial financial logs</span>
      </div>
      <div class="flex items-center space-x-2">
        <Coins class="w-4 h-4 text-sky-400 flex-shrink-0" />
        <span>Auto-triangulated FX rates (MYR, SGD, USD, EUR)</span>
      </div>
      <div class="flex items-center space-x-2">
        <ReceiptText class="w-4 h-4 text-indigo-400 flex-shrink-0" />
        <span>Double-entry ledger integrity on every balance</span>
      </div>
    </div>
  </div>
</template>
