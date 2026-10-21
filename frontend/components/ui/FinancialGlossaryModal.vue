<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  BookOpen,
  Search,
  X,
  HelpCircle,
  TrendingUp,
  DollarSign,
  Scale,
  RefreshCw,
  Target,
  ShieldCheck,
  Zap,
} from 'lucide-vue-next'

interface Props {
  isOpen: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const searchQuery = ref('')
const selectedCategory = ref<'all' | 'basics' | 'budgeting' | 'multi-currency'>('all')

const glossaryItems = [
  {
    term: 'Net Worth',
    category: 'basics',
    icon: DollarSign,
    badgeColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
    definition: 'The total value of everything you own (Assets) minus everything you owe (Liabilities).',
    plainEnglish: 'If you sold all your bank deposits, cash, and investments right now and paid off all credit cards and loans, your Net Worth is the exact cash balance you would keep.',
    example: 'RM 15,000 in bank savings - RM 3,000 credit card balance = RM 12,000 Net Worth.',
  },
  {
    term: 'Base Currency & FX Consolidation',
    category: 'multi-currency',
    icon: RefreshCw,
    badgeColor: 'text-blue-400 bg-blue-500/10 border-blue-500/30',
    definition: 'Your primary reporting currency (e.g., MYR, SGD, USD) used to calculate your overall financial net worth across accounts in different foreign currencies.',
    plainEnglish: 'If you have an account in SGD or USD, NextOmni automatically converts those funds to your Base Currency using current exchange rates so you see one clear total.',
    example: 'A wallet holding 100 USD is converted at 1 USD = 4.45 MYR and displays as RM 445 in your consolidated dashboard.',
  },
  {
    term: 'Double-Entry Ledger',
    category: 'basics',
    icon: Scale,
    badgeColor: 'text-indigo-400 bg-indigo-500/10 border-indigo-500/30',
    definition: 'An accounting standard where every financial transaction is recorded as both an inflow (credit) and an outflow (debit) between two accounts.',
    plainEnglish: 'When you transfer money from your bank to your e-wallet, it subtracts from your bank and adds to your wallet. No money vanishes or appears out of thin air.',
    example: 'Transferring RM 50 from Maybank to GrabPay: Maybank balance decreases by RM 50; GrabPay balance increases by RM 50.',
  },
  {
    term: 'Category Budget & Burn Velocity',
    category: 'budgeting',
    icon: Target,
    badgeColor: 'text-amber-400 bg-amber-500/10 border-amber-500/30',
    definition: 'A planned spending limit for an expense category (like Dining or Groceries) and the daily rate at which you are using that budget.',
    plainEnglish: 'Setting a monthly ceiling prevents overspending. Burn velocity warns you if you are spending money faster than the remaining days in the month allow.',
    example: 'With a RM 600 monthly dining budget, spending RM 300 in the first 5 days means a high burn velocity and a warning that you might run out of budget early.',
  },
  {
    term: 'Recurring Transactions & Subscriptions',
    category: 'budgeting',
    icon: Zap,
    badgeColor: 'text-purple-400 bg-purple-500/10 border-purple-500/30',
    definition: 'Fixed or predictable expenses that repeat on a regular schedule (weekly, monthly, or annually).',
    plainEnglish: 'Bills like Rent, Wi-Fi, Netflix, or Spotify. Scheduling them allows NextOmni to forecast future cash flow and remind you before money leaves your account.',
    example: 'Monthly Netflix subscription of RM 54.90 recurring on the 15th of every month.',
  },
  {
    term: 'Liquidity Runway',
    category: 'basics',
    icon: TrendingUp,
    badgeColor: 'text-rose-400 bg-rose-500/10 border-rose-500/30',
    definition: 'The number of months you could survive on your liquid cash reserves if your income completely stopped today.',
    plainEnglish: 'Also known as your emergency fund runway. It tells you how resilient your finances are to unexpected life events or job transitions.',
    example: 'RM 18,000 in liquid bank savings / RM 3,000 average monthly expenses = 6 Months Liquidity Runway.',
  },
  {
    term: 'Zero-Proof Privacy & Data Isolation',
    category: 'basics',
    icon: ShieldCheck,
    badgeColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
    definition: 'A security design where your financial statements and records are tied exclusively to your encrypted user session and never shared with third parties.',
    plainEnglish: 'NextOmni does not sell your transaction data, display third-party advertisements, or connect to unverified tracking networks.',
    example: 'Uploaded bank CSV statements are sanitized and parsed securely for your private workspace only.',
  },
]

const filteredItems = computed(() => {
  return glossaryItems.filter((item) => {
    const matchesCategory =
      selectedCategory.value === 'all' || item.category === selectedCategory.value

    const query = searchQuery.value.trim().toLowerCase()
    if (!query) return matchesCategory

    const matchesSearch =
      item.term.toLowerCase().includes(query) ||
      item.definition.toLowerCase().includes(query) ||
      item.plainEnglish.toLowerCase().includes(query) ||
      item.example.toLowerCase().includes(query)

    return matchesCategory && matchesSearch
  })
})
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 sm:p-6"
    @click.self="emit('close')"
  >
    <div
      class="relative w-full max-w-3xl bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
    >
      <!-- Header -->
      <div class="p-6 border-b border-slate-800 flex items-center justify-between bg-slate-900/80 backdrop-blur">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
            <BookOpen class="w-5 h-5" />
          </div>
          <div>
            <h2 class="text-xl font-bold text-white flex items-center space-x-2">
              <span>Financial Terms & Jargon Demystifier</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                Beginner Friendly
              </span>
            </h2>
            <p class="text-xs text-slate-400">Clear explanations of financial concepts used across NextOmni Finance</p>
          </div>
        </div>

        <button
          type="button"
          class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors"
          @click="emit('close')"
          aria-label="Close glossary"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Search & Category Filters -->
      <div class="p-4 bg-slate-950/40 border-b border-slate-800 space-y-3">
        <div class="relative">
          <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search terms, concepts, or examples (e.g. Net Worth, Burn Velocity, Ledger)..."
            class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700/80 rounded-xl text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition-colors"
          />
        </div>

        <!-- Filter tabs -->
        <div class="flex flex-wrap gap-2 text-xs">
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg border transition-colors font-medium"
            :class="selectedCategory === 'all' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800/60 text-slate-400 border-slate-700 hover:text-white'"
            @click="selectedCategory = 'all'"
          >
            All Concepts ({{ glossaryItems.length }})
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg border transition-colors font-medium"
            :class="selectedCategory === 'basics' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800/60 text-slate-400 border-slate-700 hover:text-white'"
            @click="selectedCategory = 'basics'"
          >
            Core Essentials
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg border transition-colors font-medium"
            :class="selectedCategory === 'budgeting' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800/60 text-slate-400 border-slate-700 hover:text-white'"
            @click="selectedCategory = 'budgeting'"
          >
            Budgeting & Cash Flow
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg border transition-colors font-medium"
            :class="selectedCategory === 'multi-currency' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800/60 text-slate-400 border-slate-700 hover:text-white'"
            @click="selectedCategory = 'multi-currency'"
          >
            Multi-Currency & FX
          </button>
        </div>
      </div>

      <!-- Glossary Term List -->
      <div class="flex-1 overflow-y-auto p-6 space-y-4 divide-y divide-slate-800/60">
        <div
          v-for="(item, idx) in filteredItems"
          :key="item.term"
          class="pt-4 first:pt-0 space-y-2.5"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
              <div class="w-8 h-8 rounded-xl flex items-center justify-center border" :class="item.badgeColor">
                <component :is="item.icon" class="w-4 h-4" />
              </div>
              <h3 class="text-base font-bold text-white">{{ item.term }}</h3>
            </div>
            <span class="text-[11px] font-mono uppercase tracking-wider text-slate-400 px-2 py-0.5 rounded bg-slate-800 border border-slate-700/50">
              {{ item.category }}
            </span>
          </div>

          <p class="text-xs text-slate-300 leading-relaxed font-medium">
            {{ item.definition }}
          </p>

          <div class="bg-slate-950/60 rounded-xl p-3 border border-slate-800/80 text-xs space-y-1.5">
            <div class="flex items-start space-x-2 text-emerald-400 font-medium">
              <HelpCircle class="w-3.5 h-3.5 shrink-0 mt-0.5" />
              <span>In Plain English: <span class="text-slate-300 font-normal">{{ item.plainEnglish }}</span></span>
            </div>
            <div class="flex items-start space-x-2 text-slate-400 pl-5.5">
              <span class="text-slate-500 font-semibold shrink-0">Example:</span>
              <span class="text-slate-300 font-mono text-[11px]">{{ item.example }}</span>
            </div>
          </div>
        </div>

        <div v-if="filteredItems.length === 0" class="py-12 text-center text-slate-400">
          <BookOpen class="w-8 h-8 mx-auto text-slate-600 mb-2" />
          <p class="text-sm font-medium">No financial terms matching "{{ searchQuery }}"</p>
          <p class="text-xs text-slate-500 mt-1">Try searching for "Net Worth", "Budget", or "Ledger"</p>
        </div>
      </div>

      <!-- Footer CTA -->
      <div class="p-4 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
        <span>Still have questions? Check individual metric tooltips marked with <span class="text-emerald-400 font-bold">(?)</span> across your dashboard.</span>
        <button
          type="button"
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-medium transition-colors"
          @click="emit('close')"
        >
          Got it
        </button>
      </div>
    </div>
  </div>
</template>
