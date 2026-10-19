<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  ArrowDownLeft,
  ArrowUpRight,
  ArrowRightLeft,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  Trash2,
  ReceiptText,
} from 'lucide-vue-next'
import SkeletonLoader from '~/components/ui/SkeletonLoader.vue'
import InstitutionLogo from '~/components/ui/InstitutionLogo.vue'

interface TransactionItem {
  id: number
  description: string
  amount: number
  amount_formatted: string
  type: 'income' | 'expense' | 'transfer'
  status: string
  transacted_at: string
  notes?: string
  account?: {
    id: number | string
    name: string
    currency: string
    institution?: {
      id?: string | number
      code?: string
      name?: string
      logo_url?: string | null
    }
  }
  category?: {
    id: number
    name: string
    color: string
  }
}

interface Props {
  transactions: TransactionItem[]
  isLoading?: boolean
  canCreate?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  canCreate: true,
})

const emit = defineEmits<{
  (e: 'delete', id: number, description: string): void
  (e: 'record', type: 'income' | 'expense'): void
}>()

// Sorting state
type SortField = 'transacted_at' | 'description' | 'category' | 'amount'
type SortOrder = 'asc' | 'desc'

const sortField = ref<SortField>('transacted_at')
const sortOrder = ref<SortOrder>('desc')

const toggleSort = (field: SortField) => {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortOrder.value = field === 'transacted_at' || field === 'amount' ? 'desc' : 'asc'
  }
}

const sortedTransactions = computed(() => {
  const list = [...props.transactions]
  list.sort((a, b) => {
    let comparison = 0
    if (sortField.value === 'transacted_at') {
      const dateA = new Date(a.transacted_at).getTime() || 0
      const dateB = new Date(b.transacted_at).getTime() || 0
      comparison = dateA - dateB
    } else if (sortField.value === 'amount') {
      comparison = (Number(a.amount) || 0) - (Number(b.amount) || 0)
    } else if (sortField.value === 'description') {
      comparison = (a.description || '').localeCompare(b.description || '')
    } else if (sortField.value === 'category') {
      const catA = a.category?.name || ''
      const catB = b.category?.name || ''
      comparison = catA.localeCompare(catB)
    }
    return sortOrder.value === 'asc' ? comparison : -comparison
  })
  return list
})

const formatDate = (isoString: string) => {
  try {
    const d = new Date(isoString)
    return d.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return isoString
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Skeleton loader state -->
    <div
      v-if="isLoading"
      class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 overflow-hidden shadow-sm"
    >
      <SkeletonLoader variant="table-row" :count="5" />
    </div>

    <!-- Data Loaded State -->
    <div
      v-else-if="sortedTransactions.length > 0"
      class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 overflow-hidden shadow-sm"
    >
      <!-- Desktop & Tablet Table View -->
      <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr
              class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/80 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider sticky top-0 z-10 backdrop-blur-sm"
            >
              <th scope="col" class="py-3 px-4">
                <button
                  type="button"
                  @click="toggleSort('description')"
                  class="flex items-center space-x-1.5 hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
                >
                  <span>Transaction / Memo</span>
                  <ArrowUpDown v-if="sortField !== 'description'" class="w-3 h-3 opacity-40" />
                  <ArrowUp v-else-if="sortOrder === 'asc'" class="w-3 h-3 text-emerald-500" />
                  <ArrowDown v-else class="w-3 h-3 text-emerald-500" />
                </button>
              </th>
              <th scope="col" class="py-3 px-4">
                <button
                  type="button"
                  @click="toggleSort('category')"
                  class="flex items-center space-x-1.5 hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
                >
                  <span>Category</span>
                  <ArrowUpDown v-if="sortField !== 'category'" class="w-3 h-3 opacity-40" />
                  <ArrowUp v-else-if="sortOrder === 'asc'" class="w-3 h-3 text-emerald-500" />
                  <ArrowDown v-else class="w-3 h-3 text-emerald-500" />
                </button>
              </th>
              <th scope="col" class="py-3 px-4">
                <button
                  type="button"
                  @click="toggleSort('transacted_at')"
                  class="flex items-center space-x-1.5 hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
                >
                  <span>Date & Time</span>
                  <ArrowUpDown v-if="sortField !== 'transacted_at'" class="w-3 h-3 opacity-40" />
                  <ArrowUp v-else-if="sortOrder === 'asc'" class="w-3 h-3 text-emerald-500" />
                  <ArrowDown v-else class="w-3 h-3 text-emerald-500" />
                </button>
              </th>
              <th scope="col" class="py-3 px-4 text-right">
                <button
                  type="button"
                  @click="toggleSort('amount')"
                  class="inline-flex items-center space-x-1.5 hover:text-slate-900 dark:hover:text-white transition cursor-pointer ml-auto"
                >
                  <span>Amount</span>
                  <ArrowUpDown v-if="sortField !== 'amount'" class="w-3 h-3 opacity-40" />
                  <ArrowUp v-else-if="sortOrder === 'asc'" class="w-3 h-3 text-emerald-500" />
                  <ArrowDown v-else class="w-3 h-3 text-emerald-500" />
                </button>
              </th>
              <th scope="col" class="py-3 px-3 text-center w-12">
                <span class="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
            <tr
              v-for="txn in sortedTransactions"
              :key="txn.id"
              class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition group"
            >
              <td class="py-3.5 px-4 min-w-0">
                <div class="flex items-center space-x-3">
                  <div
                    class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                    :class="{
                      'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20': txn.type === 'income',
                      'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20': txn.type === 'expense',
                      'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20': txn.type === 'transfer',
                    }"
                  >
                    <ArrowDownLeft v-if="txn.type === 'income'" class="w-4 h-4" />
                    <ArrowUpRight v-else-if="txn.type === 'expense'" class="w-4 h-4" />
                    <ArrowRightLeft v-else class="w-4 h-4" />
                  </div>
                  <div class="min-w-0">
                    <p class="font-bold text-sm text-slate-900 dark:text-white truncate">
                      {{ txn.description }}
                    </p>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center space-x-1.5 font-mono">
                      <InstitutionLogo
                        v-if="txn.account?.institution?.code"
                        :code="txn.account.institution.code"
                        :name="txn.account.institution.name"
                        :logo-url="txn.account.institution.logo_url"
                        size="xs"
                      />
                      <span>{{ txn.account?.name || 'Main Account' }}</span>
                      <span v-if="txn.notes" class="italic truncate max-w-xs text-slate-400">
                        &mdash; {{ txn.notes }}
                      </span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-4">
                <span
                  v-if="txn.category"
                  class="inline-block px-2.5 py-0.5 rounded-md text-[10px] font-semibold border"
                  :style="{
                    backgroundColor: `${txn.category.color}15`,
                    borderColor: `${txn.category.color}40`,
                    color: txn.category.color || '#94a3b8',
                  }"
                >
                  {{ txn.category.name }}
                </span>
                <span v-else class="text-xs text-slate-400 dark:text-slate-500 italic">
                  Uncategorized
                </span>
              </td>
              <td class="py-3.5 px-4 text-xs font-mono text-slate-500 dark:text-slate-400 whitespace-nowrap">
                {{ formatDate(txn.transacted_at) }}
              </td>
              <td class="py-3.5 px-4 text-right font-mono font-bold text-sm tabular-nums whitespace-nowrap">
                <span
                  :class="{
                    'text-emerald-600 dark:text-emerald-400': txn.type === 'income',
                    'text-rose-600 dark:text-rose-400': txn.type === 'expense',
                    'text-sky-600 dark:text-sky-300': txn.type === 'transfer',
                  }"
                >
                  {{ txn.type === 'income' ? '+' : txn.type === 'expense' ? '−' : '' }}
                  {{ txn.amount_formatted }}
                </span>
                <div class="text-[10px] text-slate-400 uppercase tracking-wider font-mono">
                  {{ txn.status }}
                </div>
              </td>
              <td class="py-3.5 px-3 text-center">
                <button
                  type="button"
                  @click="emit('delete', txn.id, txn.description)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 opacity-0 group-hover:opacity-100 transition cursor-pointer"
                  title="Delete Transaction"
                  aria-label="Delete transaction"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card Fallback View -->
      <div class="sm:hidden divide-y divide-slate-100 dark:divide-slate-800">
        <div
          v-for="txn in sortedTransactions"
          :key="txn.id"
          class="p-4 flex items-center justify-between gap-3"
        >
          <div class="flex items-center space-x-3 min-w-0 flex-1">
            <div
              class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
              :class="{
                'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400': txn.type === 'income',
                'bg-rose-500/10 text-rose-600 dark:text-rose-400': txn.type === 'expense',
                'bg-sky-500/10 text-sky-600 dark:text-sky-400': txn.type === 'transfer',
              }"
            >
              <ArrowDownLeft v-if="txn.type === 'income'" class="w-4 h-4" />
              <ArrowUpRight v-else-if="txn.type === 'expense'" class="w-4 h-4" />
              <ArrowRightLeft v-else class="w-4 h-4" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-bold text-xs text-slate-900 dark:text-white truncate">
                {{ txn.description }}
              </p>
              <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono flex items-center space-x-1.5">
                <span>{{ formatDate(txn.transacted_at) }}</span>
                <span>•</span>
                <span>{{ txn.account?.name || 'Account' }}</span>
              </div>
            </div>
          </div>
          <div class="text-right flex-shrink-0">
            <p
              class="font-mono font-bold text-xs tabular-nums"
              :class="{
                'text-emerald-600 dark:text-emerald-400': txn.type === 'income',
                'text-rose-600 dark:text-rose-400': txn.type === 'expense',
                'text-sky-600 dark:text-sky-300': txn.type === 'transfer',
              }"
            >
              {{ txn.type === 'income' ? '+' : txn.type === 'expense' ? '−' : '' }}
              {{ txn.amount_formatted }}
            </p>
            <button
              type="button"
              @click="emit('delete', txn.id, txn.description)"
              class="text-[10px] text-rose-500 hover:underline pt-0.5"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="p-8 rounded-2xl bg-white dark:bg-slate-900/40 border border-dashed border-slate-200 dark:border-slate-800 text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 mx-auto flex items-center justify-center text-slate-400">
        <ReceiptText class="w-6 h-6" />
      </div>
      <div class="space-y-1">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">No Transactions Found</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
          No records match your selected criteria. Record an inflow or expense to maintain real-time double-entry balances.
        </p>
      </div>
      <div v-if="canCreate" class="flex items-center justify-center space-x-3 pt-2">
        <button
          type="button"
          @click="emit('record', 'expense')"
          class="px-4 py-2 rounded-xl text-xs font-semibold bg-rose-500 hover:bg-rose-400 text-white shadow transition cursor-pointer"
        >
          Record Expense
        </button>
        <button
          type="button"
          @click="emit('record', 'income')"
          class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow transition cursor-pointer"
        >
          Record Income
        </button>
      </div>
    </div>
  </div>
</template>
