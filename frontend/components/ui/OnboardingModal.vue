<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  Sparkles,
  Wallet,
  ReceiptText,
  Target,
  BarChart3,
  ArrowRight,
  ArrowLeft,
  Check,
  X,
  CreditCard,
  Building,
  Smartphone,
  PieChart,
  Repeat,
  BookOpen,
} from 'lucide-vue-next'

interface Props {
  isOpen: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'action', actionName: string): void
  (e: 'navigate', tab: string): void
  (e: 'open-glossary'): void
}>()

const currentStep = ref(0)
const dontShowAgain = ref(false)

const steps = [
  {
    id: 'welcome',
    title: 'Welcome to NextOmni Finance',
    tagline: 'Your Intelligent Personal Financial Command Center',
    icon: Sparkles,
    badgeColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
    description:
      'NextOmni helps you take control of your financial journey with consolidated multi-currency net worth tracking, intelligent category budgeting, and cash flow forecasting.',
    keyPoints: [
      {
        title: 'Consolidated Net Worth',
        detail: 'Instantly view total assets minus liabilities across all your bank accounts and e-wallets.',
      },
      {
        title: 'Multi-Currency Precision',
        detail: 'Seamlessly hold MYR, SGD, USD, EUR, and GBP with real-time exchange rates.',
      },
      {
        title: 'Privacy-First Architecture',
        detail: 'Your financial statements and transaction logs are processed securely without third-party tracking.',
      },
    ],
    ctaText: 'Next: Set Up Accounts',
    action: null,
  },
  {
    id: 'accounts',
    title: 'Accounts & Wallets Setup',
    tagline: 'Organize Your Banks, E-Wallets & Brokerages',
    icon: Wallet,
    badgeColor: 'text-blue-400 bg-blue-500/10 border-blue-500/30',
    description:
      'Begin by adding the accounts you use every day. NextOmni supports Malaysian banks (Maybank, CIMB, Public Bank), e-wallets (Touch \'n Go, GrabPay), cash reserves, and global investments.',
    keyPoints: [
      {
        title: 'Local & Global Wallets',
        detail: 'Assign custom currencies and institution logos to match your real-world cards and accounts.',
      },
      {
        title: 'Live Net Worth Calculation',
        detail: 'Bank deposits and investments add to your wealth; credit cards and loans subtract as liabilities.',
      },
      {
        title: 'Instant Demo Portfolio',
        detail: 'Want to explore first? Use the "Seed Sample Portfolio" button to load realistic demo accounts.',
      },
    ],
    ctaText: 'Add an Account',
    action: 'add-account',
    tab: 'accounts',
  },
  {
    id: 'transactions',
    title: 'Logging & Tracking Transactions',
    tagline: 'Record Inflows, Expenses & Account Transfers',
    icon: ReceiptText,
    badgeColor: 'text-rose-400 bg-rose-500/10 border-rose-500/30',
    description:
      'Every financial action is logged cleanly in your double-entry ledger. Record daily coffee runs, monthly paychecks, or cross-wallet transfers with full currency conversion.',
    keyPoints: [
      {
        title: 'Categorized Spending',
        detail: 'Assign categories like Food, Utilities, Transit, or Entertainment to track where your money goes.',
      },
      {
        title: 'Statement Import (CSV)',
        detail: 'Export transaction statements from your bank and upload them for automatic reconciliation.',
      },
      {
        title: 'Audit-Safe Export',
        detail: 'Download formula-safe CSV and JSON statements anytime for your tax or accounting records.',
      },
    ],
    ctaText: 'Record First Transaction',
    action: 'record-transaction',
    tab: 'transactions',
  },
  {
    id: 'budgets',
    title: 'Budgets & Scheduled Subscriptions',
    tagline: 'Prevent Overspending with Automated Guardrails',
    icon: Target,
    badgeColor: 'text-amber-400 bg-amber-500/10 border-amber-500/30',
    description:
      'Set proactive spending limits and never get blindsided by unexpected subscription charges or forgotten utility bills.',
    keyPoints: [
      {
        title: 'Category Spending Limits',
        detail: 'Set monthly targets with visual burn-rate progress bars (Healthy, Warning, Exceeded).',
      },
      {
        title: 'Recurring Subscriptions',
        detail: 'Schedule Netflix, Unifi, gym dues, or rent to post automatically on their scheduled dates.',
      },
      {
        title: 'Savings Milestones',
        detail: 'Create financial goals with target dates (e.g., Emergency Fund or Vacation Reserve).',
      },
    ],
    ctaText: 'Create a Budget',
    action: 'add-budget',
    tab: 'budgets',
  },
  {
    id: 'analytics',
    title: 'Insights & Financial Analytics',
    tagline: 'Understand Your Savings Rate & Cash Flow Velocity',
    icon: BarChart3,
    badgeColor: 'text-indigo-400 bg-indigo-500/10 border-indigo-500/30',
    description:
      'Transform raw transaction logs into clear, actionable wealth-building metrics. Spot spending trends and optimize your monthly savings rate.',
    keyPoints: [
      {
        title: 'Monthly Cash Flow',
        detail: 'Compare Total Inflows (Income) vs Total Outflows (Expenses) to see your true net monthly surplus.',
      },
      {
        title: 'Savings Rate %',
        detail: 'Target saving 20% or more of your monthly income to accelerate financial independence.',
      },
      {
        title: 'Category Spending Distribution',
        detail: 'See visual percentage breakdowns to identify high-burn categories immediately.',
      },
    ],
    ctaText: 'Start Exploring NextOmni',
    action: 'explore',
    tab: 'dashboard',
  },
]

const step = computed(() => steps[currentStep.value])
const isLastStep = computed(() => currentStep.value === steps.length - 1)

const nextStep = () => {
  if (isLastStep.value) {
    completeTour()
  } else {
    currentStep.value++
  }
}

const prevStep = () => {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

const goToStep = (index: number) => {
  if (index >= 0 && index < steps.length) {
    currentStep.value = index
  }
}

const completeTour = () => {
  if (dontShowAgain.value || typeof window !== 'undefined') {
    try {
      localStorage.setItem('nextomni_onboarding_completed', 'true')
    } catch {
      // Ignore localStorage errors in private browsing
    }
  }
  emit('close')
}

const triggerStepAction = () => {
  if (step.value.action) {
    if (step.value.tab) {
      emit('navigate', step.value.tab)
    }
    emit('action', step.value.action)
    completeTour()
  } else {
    nextStep()
  }
}

const handleGlossaryClick = () => {
  emit('open-glossary')
}

const handleKeydown = (e: KeyboardEvent) => {
  if (!props.isOpen) return
  if (e.key === 'Escape') {
    completeTour()
  } else if (e.key === 'ArrowRight') {
    nextStep()
  } else if (e.key === 'ArrowLeft') {
    prevStep()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-label="Interactive Onboarding Guide"
        @click.self="completeTour"
      >
        <div
          class="w-full max-w-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] transition-all"
        >
          <!-- Header Bar with Step Badge & Controls -->
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
            <div class="flex items-center space-x-2">
              <span
                class="px-2.5 py-1 rounded-full text-[11px] font-mono font-bold border uppercase tracking-wider flex items-center space-x-1.5"
                :class="step.badgeColor"
              >
                <component :is="step.icon" class="w-3.5 h-3.5" />
                <span>Step {{ currentStep + 1 }} of {{ steps.length }}</span>
              </span>
              <button
                type="button"
                @click="handleGlossaryClick"
                class="text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-300 font-medium flex items-center space-x-1 px-2 py-0.5 rounded-lg hover:bg-emerald-500/10 transition-colors"
                title="View plain-English definitions of financial concepts"
              >
                <BookOpen class="w-3 h-3" />
                <span>Glossary</span>
              </button>
            </div>

            <button
              @click="completeTour"
              class="p-1.5 rounded-xl text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
              title="Close Guide"
              aria-label="Close Guide"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Step Progression Pill Tabs -->
          <div class="px-6 pt-3 pb-2 bg-slate-100/50 dark:bg-slate-950/40 border-b border-slate-200/40 dark:border-slate-800/40 flex items-center justify-between gap-1 overflow-x-auto">
            <button
              v-for="(s, idx) in steps"
              :key="s.id"
              type="button"
              class="flex-1 py-1 px-2 rounded-lg text-[11px] font-medium flex items-center justify-center space-x-1 transition-all"
              :class="idx === currentStep ? 'bg-emerald-500/20 text-emerald-400 font-bold border border-emerald-500/30' : (idx < currentStep ? 'text-emerald-500/70 hover:text-emerald-400' : 'text-slate-400 hover:text-slate-200')"
              @click="goToStep(idx)"
            >
              <Check v-if="idx < currentStep" class="w-3 h-3 text-emerald-400 shrink-0" />
              <span class="truncate">{{ s.title.split(' ')[0] }}</span>
            </button>
          </div>

          <!-- Modal Body Content -->
          <div class="p-6 sm:p-8 space-y-6 overflow-y-auto">
            <!-- Icon & Titles -->
            <div class="space-y-2">
              <div class="w-12 h-12 rounded-2xl flex items-center justify-center border shadow-sm" :class="step.badgeColor">
                <component :is="step.icon" class="w-6 h-6" />
              </div>
              <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                {{ step.title }}
              </h2>
              <p class="text-xs sm:text-sm font-medium text-emerald-600 dark:text-emerald-400">
                {{ step.tagline }}
              </p>
              <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed pt-1">
                {{ step.description }}
              </p>
            </div>

            <!-- Key Points Bullet Cards -->
            <div class="space-y-2.5 pt-1">
              <div
                v-for="(point, idx) in step.keyPoints"
                :key="idx"
                class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 flex items-start space-x-3"
              >
                <div class="w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0 mt-0.5 font-bold text-xs">
                  <Check class="w-3 h-3" />
                </div>
                <div class="space-y-0.5">
                  <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ point.title }}</h4>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-normal">{{ point.detail }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer Actions & Progress Dots -->
          <div class="p-6 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col sm:flex-row items-center justify-between gap-4">
            <!-- Progress Dots -->
            <div class="flex items-center space-x-1.5">
              <button
                v-for="(s, idx) in steps"
                :key="s.id"
                @click="goToStep(idx)"
                class="w-2.5 h-2.5 rounded-full transition-all cursor-pointer"
                :class="idx === currentStep ? 'w-6 bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700 hover:bg-slate-400 dark:hover:bg-slate-600'"
                :title="`Go to Step ${idx + 1}: ${s.title}`"
                :aria-label="`Go to Step ${idx + 1}`"
              />
            </div>

            <!-- Navigation Buttons -->
            <div class="flex items-center space-x-2.5 w-full sm:w-auto justify-end">
              <button
                v-if="currentStep > 0"
                @click="prevStep"
                class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center space-x-1 cursor-pointer"
              >
                <ArrowLeft class="w-3.5 h-3.5" />
                <span>Back</span>
              </button>

              <button
                v-if="step.action"
                @click="triggerStepAction"
                class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 cursor-pointer"
              >
                <span>{{ step.ctaText }}</span>
                <ArrowRight class="w-3.5 h-3.5" />
              </button>

              <button
                v-else
                @click="nextStep"
                class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 cursor-pointer"
              >
                <span>{{ isLastStep ? 'Finish Tour' : 'Next Step' }}</span>
                <ArrowRight class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Dismiss Permanently Option -->
          <div class="px-6 py-2.5 bg-slate-100/60 dark:bg-slate-950/60 border-t border-slate-200/50 dark:border-slate-800/50 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
            <label class="flex items-center space-x-2 cursor-pointer select-none">
              <input
                type="checkbox"
                v-model="dontShowAgain"
                class="rounded border-slate-300 dark:border-slate-700 text-emerald-500 focus:ring-emerald-500"
              />
              <span>Don't show this tour on future logins</span>
            </label>
            <span class="font-mono text-[10px]">ESC to close</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
