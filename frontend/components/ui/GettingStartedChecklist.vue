<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import {
  CheckCircle2,
  Circle,
  ChevronDown,
  ChevronUp,
  X,
  Sparkles,
  ArrowRight,
  Wallet,
  ReceiptText,
  Target,
  CalendarClock,
  HelpCircle,
  RotateCcw,
} from 'lucide-vue-next'

interface Props {
  accountCount: number
  transactionCount: number
  budgetCount: number
  recurringCount: number
  forceVisible?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  forceVisible: false,
})

const emit = defineEmits<{
  (e: 'action', actionName: string): void
  (e: 'navigate', tab: string): void
  (e: 'open-tour'): void
  (e: 'dismiss'): void
}>()

const isDismissed = ref(false)
const isCollapsed = ref(false)

onMounted(() => {
  try {
    const dismissed = localStorage.getItem('nextomni_checklist_dismissed')
    if (dismissed === 'true' && !props.forceVisible) {
      isDismissed.value = true
    }
  } catch {
    // Ignore localStorage issues
  }
})

watch(
  () => props.forceVisible,
  (val) => {
    if (val) {
      isDismissed.value = false
      try {
        localStorage.removeItem('nextomni_checklist_dismissed')
      } catch {
        // Ignore
      }
    }
  }
)

const dismissChecklist = () => {
  isDismissed.value = true
  try {
    localStorage.setItem('nextomni_checklist_dismissed', 'true')
  } catch {
    // Ignore
  }
  emit('dismiss')
}

const restoreChecklist = () => {
  isDismissed.value = false
  isCollapsed.value = false
  try {
    localStorage.removeItem('nextomni_checklist_dismissed')
  } catch {
    // Ignore
  }
}

const checklistItems = computed(() => [
  {
    id: 'auth',
    title: 'Create Account & Sign In',
    description: 'Workspace authenticated with encrypted session tokens.',
    isDone: true,
    actionText: null,
    action: null,
    tab: null,
    icon: Sparkles,
  },
  {
    id: 'account',
    title: 'Add Your First Account or Wallet',
    description: 'Set up Maybank, CIMB, Touch \'n Go e-wallet, or seed demo portfolio.',
    isDone: props.accountCount > 0,
    actionText: 'Add Account',
    action: 'add-account',
    tab: 'accounts',
    icon: Wallet,
  },
  {
    id: 'transaction',
    title: 'Log Your First Income or Expense',
    description: 'Record a transaction or import statement CSV to populate cash flow.',
    isDone: props.transactionCount > 0,
    actionText: 'Record Transaction',
    action: 'record-transaction',
    tab: 'transactions',
    icon: ReceiptText,
  },
  {
    id: 'budget',
    title: 'Set a Monthly Category Budget',
    description: 'Define limits for Dining, Groceries, or Utilities to monitor burn rate.',
    isDone: props.budgetCount > 0,
    actionText: 'Set Budget',
    action: 'add-budget',
    tab: 'budgets',
    icon: Target,
  },
  {
    id: 'recurring',
    title: 'Schedule a Recurring Bill or Subscription',
    description: 'Track rent, internet, or Netflix with automated ledger posting.',
    isDone: props.recurringCount > 0,
    actionText: 'Schedule Bill',
    action: 'add-recurring',
    tab: 'budgets',
    icon: CalendarClock,
  },
])

const completedCount = computed(() => checklistItems.value.filter((i) => i.isDone).length)
const totalCount = computed(() => checklistItems.value.length)
const progressPercent = computed(() => Math.round((completedCount.value / totalCount.value) * 100))
const isAllCompleted = computed(() => completedCount.value === totalCount.value)

const handleItemClick = (item: (typeof checklistItems.value)[0]) => {
  if (item.tab) {
    emit('navigate', item.tab)
  }
  if (item.action) {
    emit('action', item.action)
  }
}

defineExpose({
  restoreChecklist,
  dismissChecklist,
  isDismissed,
  completedCount,
  totalCount,
})
</script>

<template>
  <div>
    <!-- Expanded or Standard Checklist Widget -->
    <div
      v-if="!isDismissed"
      class="p-5 sm:p-6 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-teal-500/5 to-slate-900/40 border border-emerald-500/20 shadow-sm relative overflow-hidden transition-all duration-300"
    >
      <!-- Background Glow -->
      <div class="absolute -top-12 -right-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />

      <!-- Header Section -->
      <div class="flex items-start sm:items-center justify-between gap-4 relative z-10">
        <div class="space-y-1">
          <div class="flex items-center space-x-2">
            <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <Sparkles class="w-4 h-4" />
            </div>
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
              {{ isAllCompleted ? 'Setup Complete! All Core Workflows Active' : 'Getting Started Checklist' }}
            </h3>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
              :class="isAllCompleted ? 'bg-emerald-500 text-slate-950' : 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'"
            >
              {{ completedCount }} of {{ totalCount }} Completed ({{ progressPercent }}%)
            </span>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400">
            {{ isAllCompleted ? 'Great job! Your workspace is fully primed for real-time net worth tracking and expense intelligence.' : 'Complete these recommended setup steps to unlock consolidated net worth, cash flow analytics, and budgeting.' }}
          </p>
        </div>

        <!-- Action Icons (Tour, Collapse, Dismiss) -->
        <div class="flex items-center space-x-1 flex-shrink-0">
          <button
            @click="emit('open-tour')"
            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10 transition hidden md:flex items-center space-x-1 cursor-pointer"
            title="Open Step-by-Step Tour"
          >
            <HelpCircle class="w-3.5 h-3.5" />
            <span>Interactive Tour</span>
          </button>

          <button
            @click="isCollapsed = !isCollapsed"
            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200/50 dark:hover:bg-slate-800 transition cursor-pointer"
            :title="isCollapsed ? 'Expand Checklist' : 'Collapse Checklist'"
            :aria-label="isCollapsed ? 'Expand Checklist' : 'Collapse Checklist'"
          >
            <ChevronUp v-if="!isCollapsed" class="w-4 h-4" />
            <ChevronDown v-else class="w-4 h-4" />
          </button>

          <button
            @click="dismissChecklist"
            class="p-1.5 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 transition cursor-pointer"
            title="Dismiss Checklist"
            aria-label="Dismiss Checklist"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Animated Progress Bar -->
      <div class="mt-4 mb-2 w-full h-2 rounded-full bg-slate-200 dark:bg-slate-800/80 overflow-hidden relative z-10">
        <div
          class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-700 ease-out"
          :style="{ width: `${progressPercent}%` }"
        />
      </div>

      <!-- Collapsible Checklist Grid -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div v-if="!isCollapsed" class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 relative z-10">
          <div
            v-for="item in checklistItems"
            :key="item.id"
            class="p-3.5 rounded-xl border transition-all duration-200 flex flex-col justify-between"
            :class="item.isDone ? 'bg-emerald-500/5 border-emerald-500/20 text-slate-500 dark:text-slate-400' : 'bg-white dark:bg-slate-900/60 border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white shadow-sm'"
          >
            <div class="flex items-start space-x-3">
              <div class="mt-0.5 flex-shrink-0">
                <CheckCircle2 v-if="item.isDone" class="w-4 h-4 text-emerald-500" />
                <Circle v-else class="w-4 h-4 text-slate-400" />
              </div>
              <div class="space-y-0.5 min-w-0 flex-1">
                <h4
                  class="text-xs font-bold leading-tight"
                  :class="item.isDone ? 'line-through text-slate-500 dark:text-slate-400' : 'text-slate-900 dark:text-white'"
                >
                  {{ item.title }}
                </h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                  {{ item.description }}
                </p>
              </div>
            </div>

            <div v-if="!item.isDone && item.actionText" class="pt-2 pl-7">
              <button
                @click="handleItemClick(item)"
                class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 transition inline-flex items-center space-x-1 cursor-pointer"
              >
                <span>{{ item.actionText }}</span>
                <ArrowRight class="w-3 h-3" />
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Dismissed State Subtle Restore Trigger Pill -->
    <div
      v-else
      class="flex items-center justify-between p-3 rounded-xl bg-slate-900/40 border border-slate-800/80 text-xs text-slate-400"
    >
      <div class="flex items-center space-x-2.5">
        <div class="w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
          <Sparkles class="w-3 h-3" />
        </div>
        <span>Getting Started Progress:</span>
        <span class="font-mono text-emerald-400 font-bold">{{ completedCount }}/{{ totalCount }} ({{ progressPercent }}%)</span>
      </div>
      <div class="flex items-center space-x-2">
        <button
          type="button"
          @click="restoreChecklist"
          class="text-xs font-semibold text-emerald-400 hover:text-emerald-300 underline underline-offset-4 cursor-pointer"
        >
          Show Checklist
        </button>
        <span class="text-slate-600">&bull;</span>
        <button
          type="button"
          @click="emit('open-tour')"
          class="text-xs font-semibold text-slate-400 hover:text-white cursor-pointer"
        >
          Open Tour
        </button>
      </div>
    </div>
  </div>
</template>
