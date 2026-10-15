<script setup lang="ts">
import {
  Layers,
  ArrowLeftRight,
  Upload,
  Download,
  FileSpreadsheet,
} from 'lucide-vue-next'

interface Institution {
  id: number | string
  code: string
  name: string
  country: string
}

interface Props {
  canImport?: boolean
  institutions?: Institution[]
}

withDefaults(defineProps<Props>(), {
  canImport: true,
  institutions: () => [],
})

const emit = defineEmits<{
  (e: 'open-fx'): void
  (e: 'open-import'): void
  (e: 'export-csv'): void
  (e: 'export-json'): void
}>()
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <Layers class="w-5 h-5 text-emerald-500 dark:text-emerald-400" />
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Financial Tools & Enterprise Utilities</h2>
      </div>
      <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">ETL, Foreign Exchange & Data Export</span>
    </div>

    <!-- 3 Action Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Foreign Exchange Card -->
      <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 dark:text-sky-400 border border-sky-500/20 flex items-center justify-center">
          <ArrowLeftRight class="w-5 h-5" />
        </div>
        <div>
          <h3 class="text-base font-bold text-slate-900 dark:text-white">Real-Time FX Engine</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Cross-currency triangulation across MYR, USD, SGD, EUR, and GBP with arbitrary precision arithmetic.</p>
        </div>
        <button
          type="button"
          @click="emit('open-fx')"
          class="w-full py-2 px-3 rounded-xl text-xs font-semibold bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 border border-sky-500/30 transition flex items-center justify-center space-x-2 cursor-pointer"
        >
          <ArrowLeftRight class="w-3.5 h-3.5" />
          <span>Open FX Calculator</span>
        </button>
      </div>

      <!-- Statement Import Card -->
      <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center">
          <Upload class="w-5 h-5" />
        </div>
        <div>
          <h3 class="text-base font-bold text-slate-900 dark:text-white">Bank Statement Ingestion</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Automated CSV parser for Maybank, CIMB, and standard bank formats with deduplication hash checks.</p>
        </div>
        <button
          type="button"
          @click="emit('open-import')"
          :disabled="!canImport"
          class="w-full py-2 px-3 rounded-xl text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
        >
          <Upload class="w-3.5 h-3.5" />
          <span>Import Bank CSV</span>
        </button>
      </div>

      <!-- Data Export Card -->
      <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-500 dark:text-indigo-400 border border-indigo-500/20 flex items-center justify-center">
          <Download class="w-5 h-5" />
        </div>
        <div>
          <h3 class="text-base font-bold text-slate-900 dark:text-white">Financial Data Export</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Export transaction history and audit reports to CSV or structured JSON for accounting software.</p>
        </div>
        <div class="flex items-center space-x-2">
          <button
            type="button"
            @click="emit('export-csv')"
            class="flex-1 py-2 px-3 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition flex items-center justify-center space-x-1.5 cursor-pointer"
          >
            <FileSpreadsheet class="w-3.5 h-3.5" />
            <span>CSV</span>
          </button>
          <button
            type="button"
            @click="emit('export-json')"
            class="flex-1 py-2 px-3 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition flex items-center justify-center space-x-1.5 cursor-pointer"
          >
            <Download class="w-3.5 h-3.5" />
            <span>JSON</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Supported Institutions Directory -->
    <div
      v-if="institutions && institutions.length > 0"
      class="p-6 rounded-2xl bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
    >
      <div class="flex items-center justify-between">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">Supported Institutions & Connectors ({{ institutions.length }})</h3>
        <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">SE Asia Financial Registry</span>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
        <div
          v-for="inst in institutions"
          :key="inst.id"
          class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/50 flex items-center space-x-3"
        >
          <div class="w-8 h-8 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-xs font-mono flex-shrink-0">
            {{ inst.code.slice(0, 4) }}
          </div>
          <div class="overflow-hidden min-w-0">
            <div class="text-xs font-semibold text-slate-900 dark:text-white truncate">{{ inst.name }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">Country: {{ inst.country }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
