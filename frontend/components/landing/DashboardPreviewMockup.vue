<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  TrendingUp,
  ArrowDownLeft,
  ArrowUpRight,
  Building,
  CreditCard,
  PiggyBank,
  CheckCircle2,
  Calendar,
  Sparkles,
} from 'lucide-vue-next'
import InstitutionLogo from '~/components/ui/InstitutionLogo.vue'

type Currency = 'MYR' | 'USD' | 'SGD' | 'EUR'

const selectedCurrency = ref<Currency>('MYR')

const currencyRates: Record<Currency, { symbol: string; rate: number }> = {
  MYR: { symbol: 'RM', rate: 1.0 },
  USD: { symbol: '$', rate: 0.22 },
  SGD: { symbol: 'S$', rate: 0.30 },
  EUR: { symbol: '€', rate: 0.21 },
}

const baseNetWorth = 248650.0

const currentCurrencyInfo = computed(() => currencyRates[selectedCurrency.value])

const formattedNetWorth = computed(() => {
  const converted = baseNetWorth * currentCurrencyInfo.value.rate
  return `${currentCurrencyInfo.value.symbol} ${converted.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
})

const sampleTransactions = computed(() => [
  {
    id: 1,
    desc: 'Acme Cloud Infrastructure Services',
    account: 'Maybank Premier (MYR)',
    institutionCode: 'MBB',
    amount: -450.0 * currentCurrencyInfo.value.rate,
    type: 'expense',
    date: 'Today, 09:30 AM',
    category: 'Software & Cloud',
    categoryColor: '#38bdf8',
  },
  {
    id: 2,
    desc: 'Consulting Retainer & Engineering Dividends',
    account: 'CIMB Singapore (SGD)',
    institutionCode: 'CIMB',
    amount: 14200.0 * currentCurrencyInfo.value.rate,
    type: 'income',
    date: 'Yesterday, 14:15 PM',
    category: 'Consulting Income',
    categoryColor: '#10b981',
  },
  {
    id: 3,
    desc: 'Stripe Global Merchant Inflow',
    account: 'Wise Business Multi-Currency',
    institutionCode: 'WISE',
    amount: 3250.0 * currentCurrencyInfo.value.rate,
    type: 'income',
    date: 'Oct 12, 18:45 PM',
    category: 'E-Commerce',
    categoryColor: '#10b981',
  },
  {
    id: 4,
    desc: 'Touch n Go RFID Highway Tolls & Transit',
    account: 'Touch n Go eWallet',
    institutionCode: 'TNG',
    amount: -85.50 * currentCurrencyInfo.value.rate,
    type: 'expense',
    date: 'Oct 11, 19:10 PM',
    category: 'Transportation',
    categoryColor: '#f59e0b',
  },
  {
    id: 5,
    desc: 'Office Workspace & Fiber Backbone',
    account: 'Maybank Premier (MYR)',
    institutionCode: 'MBB',
    amount: -1280.0 * currentCurrencyInfo.value.rate,
    type: 'expense',
    date: 'Oct 10, 11:20 AM',
    category: 'Operations',
    categoryColor: '#f43f5e',
  },
])
</script>

<template>
  <div class="relative rounded-2xl md:rounded-3xl p-1.5 sm:p-2.5 bg-gradient-to-b from-slate-200/80 via-slate-300/40 to-slate-200/80 dark:from-slate-800/80 dark:via-slate-900/40 dark:to-slate-800/80 border border-slate-200 dark:border-slate-800 shadow-2xl backdrop-blur-xl">
    <!-- Browser Mockup Window Chrome -->
    <div class="rounded-xl md:rounded-2xl bg-white dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800/80 overflow-hidden shadow-inner">
      <!-- Chrome Bar -->
      <div class="px-4 py-3 bg-slate-50/80 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
        <div class="flex items-center space-x-2">
          <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
          <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
          <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
          <span class="text-[11px] font-mono text-slate-400 pl-2 hidden sm:inline">
            app.nextomni.com/workspace
          </span>
        </div>

        <!-- Interactive Currency Switcher -->
        <div class="flex items-center space-x-1 bg-slate-200/60 dark:bg-slate-800/60 p-1 rounded-lg">
          <button
            v-for="curr in (['MYR', 'USD', 'SGD', 'EUR'] as Currency[])"
            :key="curr"
            type="button"
            @click="selectedCurrency = curr"
            class="px-2.5 py-0.5 rounded-md text-[11px] font-mono font-bold transition cursor-pointer"
            :class="
              selectedCurrency === curr
                ? 'bg-emerald-500 text-slate-950 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-950 dark:hover:text-white'
            "
          >
            {{ curr }}
          </button>
        </div>
      </div>

      <!-- Mockup Workspace Content -->
      <div class="p-4 sm:p-6 space-y-6">
        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
          <!-- Total Consolidated Net Worth -->
          <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-1.5">
            <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Portfolio Net Worth</span>
            <div class="text-xl sm:text-2xl font-extrabold font-mono text-slate-900 dark:text-white tabular-nums tracking-tight">
              {{ formattedNetWorth }}
            </div>
            <div class="flex items-center text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold space-x-1">
              <TrendingUp class="w-3 h-3" />
              <span>+14.2% Past 30 Days</span>
            </div>
          </div>

          <!-- Liquid Cash -->
          <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-1.5">
            <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Liquid Cash & Bank Deposits</span>
            <div class="text-xl sm:text-2xl font-extrabold font-mono text-slate-900 dark:text-white tabular-nums tracking-tight">
              {{ currentCurrencyInfo.symbol }} {{ (114850 * currentCurrencyInfo.rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </div>
            <div class="flex items-center text-[10px] text-blue-600 dark:text-blue-400 font-medium space-x-1">
              <Building class="w-3 h-3" />
              <span>Checking, Savings & E-Wallets</span>
            </div>
          </div>

          <!-- Global Investments -->
          <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-1.5">
            <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Global Equities & Bonds</span>
            <div class="text-xl sm:text-2xl font-extrabold font-mono text-sky-600 dark:text-sky-400 tabular-nums tracking-tight">
              {{ currentCurrencyInfo.symbol }} {{ (138200 * currentCurrencyInfo.rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </div>
            <div class="flex items-center text-[10px] text-sky-600 dark:text-sky-400 font-medium space-x-1">
              <PiggyBank class="w-3 h-3" />
              <span>Triangulated Real-Time FX</span>
            </div>
          </div>

          <!-- Liabilities -->
          <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-1.5">
            <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Total Liabilities & Cards</span>
            <div class="text-xl sm:text-2xl font-extrabold font-mono text-amber-600 dark:text-amber-400 tabular-nums tracking-tight">
              {{ currentCurrencyInfo.symbol }} {{ (4400 * currentCurrencyInfo.rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </div>
            <div class="flex items-center text-[10px] text-amber-600 dark:text-amber-400 font-medium space-x-1">
              <CreditCard class="w-3 h-3" />
              <span>All Credit Balances Current</span>
            </div>
          </div>
        </div>

        <!-- Ledger & Recurring Highlights Split Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <!-- Real-Time Transaction Ledger (2 columns) -->
          <div class="lg:col-span-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-4 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Live Double-Entry Ledger Feed</h4>
              </div>
              <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400">Strict User Tenant Scoped</span>
            </div>

            <div class="divide-y divide-slate-200/80 dark:divide-slate-800/80 text-xs">
              <div
                v-for="item in sampleTransactions"
                :key="item.id"
                class="py-2.5 flex items-center justify-between gap-3 group"
              >
                <div class="flex items-center space-x-2.5 min-w-0">
                  <div
                    class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                    :class="
                      item.type === 'income'
                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                        : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                    "
                  >
                    <ArrowDownLeft v-if="item.type === 'income'" class="w-3.5 h-3.5" />
                    <ArrowUpRight v-else class="w-3.5 h-3.5" />
                  </div>
                  <div class="min-w-0">
                    <p class="font-bold text-slate-900 dark:text-slate-100 truncate">{{ item.desc }}</p>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono flex items-center space-x-1.5">
                      <InstitutionLogo :code="item.institutionCode" size="xs" />
                      <span>{{ item.account }}</span>
                      <span>•</span>
                      <span>{{ item.date }}</span>
                    </div>
                  </div>
                </div>

                <div class="text-right flex-shrink-0 font-mono font-bold tabular-nums">
                  <span :class="item.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                    {{ item.type === 'income' ? '+' : '−' }}
                    {{ currentCurrencyInfo.symbol }}
                    {{ Math.abs(item.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Automated Recurring Subscriptions Card (1 column) -->
          <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-4 space-y-3.5">
            <div class="flex items-center justify-between">
              <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center space-x-1.5">
                <Calendar class="w-3.5 h-3.5 text-indigo-500" />
                <span>Recurring Obligations</span>
              </h4>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold">
                Auto-Posting
              </span>
            </div>

            <div class="space-y-2.5">
              <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                  <div class="text-xs font-bold text-slate-900 dark:text-white">GitHub Team Subscription</div>
                  <div class="text-[10px] text-slate-400 font-mono">Monthly • Next on Nov 1</div>
                </div>
                <div class="text-right font-mono text-xs font-bold text-rose-500">
                  −{{ currentCurrencyInfo.symbol }}{{ (95 * currentCurrencyInfo.rate).toFixed(2) }}
                </div>
              </div>

              <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                  <div class="text-xs font-bold text-slate-900 dark:text-white">Figma Organization Seat</div>
                  <div class="text-[10px] text-slate-400 font-mono">Monthly • Next on Nov 4</div>
                </div>
                <div class="text-right font-mono text-xs font-bold text-rose-500">
                  −{{ currentCurrencyInfo.symbol }}{{ (45 * currentCurrencyInfo.rate).toFixed(2) }}
                </div>
              </div>

              <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                  <div class="text-xs font-bold text-slate-900 dark:text-white">Cloud Hosting & S3 Backup</div>
                  <div class="text-[10px] text-slate-400 font-mono">Monthly • Next on Nov 10</div>
                </div>
                <div class="text-right font-mono text-xs font-bold text-rose-500">
                  −{{ currentCurrencyInfo.symbol }}{{ (120 * currentCurrencyInfo.rate).toFixed(2) }}
                </div>
              </div>
            </div>

            <div class="p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center space-x-2 text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">
              <CheckCircle2 class="w-4 h-4 text-emerald-500 flex-shrink-0" />
              <span>3 recurring occurrences projected & funded for next month.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
