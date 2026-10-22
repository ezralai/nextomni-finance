<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import {
  ShieldCheck,
  X,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  ChevronUp,
  Globe,
  Monitor,
  Clock,
  FileCode,
  LogIn,
  LogOut,
  UserCheck,
  User,
  PlusCircle,
  FileEdit,
  Trash2,
  Upload,
  Layers,
  AlertCircle,
} from 'lucide-vue-next'
import { useApi } from '~/composables/useApi'

interface AuditLogEntry {
  id: number
  event: string
  auditable_type: string
  auditable_id: string
  old_values: Record<string, any> | null
  new_values: Record<string, any> | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

interface Props {
  modelValue: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const { request } = useApi()

const isLoading = ref(false)
const error = ref<string | null>(null)
const logs = ref<AuditLogEntry[]>([])
const meta = ref<PaginationMeta | null>(null)
const currentPage = ref(1)
const selectedFilter = ref<string>('all')
const expandedLogIds = ref<Set<number>>(new Set())

const filterOptions = [
  { id: 'all', label: 'All Activities', auditable_type: '', event: '' },
  { id: 'auth', label: 'Security & Auth', auditable_type: 'User', event: '' },
  { id: 'transactions', label: 'Transactions', auditable_type: 'Transaction', event: '' },
  { id: 'transfers', label: 'Transfers', auditable_type: 'Transfer', event: '' },
  { id: 'accounts', label: 'Accounts', auditable_type: 'Account', event: '' },
  { id: 'goals', label: 'Financial Goals', auditable_type: 'Goal', event: '' },
  { id: 'imports', label: 'Statement Imports', auditable_type: '', event: 'statement.imported' },
]

const close = () => {
  emit('update:modelValue', false)
}

const toggleExpand = (id: number) => {
  if (expandedLogIds.value.has(id)) {
    expandedLogIds.value.delete(id)
  } else {
    expandedLogIds.value.add(id)
  }
}

const fetchAuditLogs = async (page: number = 1) => {
  isLoading.value = true
  error.value = null
  currentPage.value = page

  try {
    const activeFilter = filterOptions.find(f => f.id === selectedFilter.value)
    const params: Record<string, any> = {
      page,
      per_page: 15,
    }

    if (activeFilter?.auditable_type) {
      params.auditable_type = activeFilter.auditable_type
    }
    if (activeFilter?.event) {
      params.event = activeFilter.event
    }

    const response = await request<{ data: AuditLogEntry[]; meta: PaginationMeta }>('/audit-logs', {
      params,
    })

    logs.value = response.data || []
    meta.value = response.meta || null
  } catch (err: any) {
    error.value = err.data?.message || err.message || 'Failed to load audit logs.'
  } finally {
    isLoading.value = false
  }
}

const applyFilter = (filterId: string) => {
  selectedFilter.value = filterId
  fetchAuditLogs(1)
}

const formatTimestamp = (isoString: string) => {
  try {
    const d = new Date(isoString)
    return new Intl.DateTimeFormat('en-MY', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: true,
    }).format(d)
  } catch {
    return isoString
  }
}

const formatRelativeTime = (isoString: string) => {
  try {
    const diff = (Date.now() - new Date(isoString).getTime()) / 1000
    if (diff < 60) return 'Just now'
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    return `${Math.floor(diff / 86400)}d ago`
  } catch {
    return ''
  }
}

const getEventBadgeClass = (event: string) => {
  if (event.includes('deleted')) {
    return 'bg-rose-500/10 text-rose-500 dark:text-rose-400 border-rose-500/20'
  }
  if (event.includes('created') || event.includes('registered')) {
    return 'bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 border-emerald-500/20'
  }
  if (event.includes('login')) {
    return 'bg-teal-500/10 text-teal-500 dark:text-teal-400 border-teal-500/20'
  }
  if (event.includes('logout')) {
    return 'bg-slate-500/10 text-slate-500 dark:text-slate-400 border-slate-500/20'
  }
  if (event.includes('updated') || event.includes('contributed')) {
    return 'bg-sky-500/10 text-sky-500 dark:text-sky-400 border-sky-500/20'
  }
  if (event.includes('imported')) {
    return 'bg-indigo-500/10 text-indigo-500 dark:text-indigo-400 border-indigo-500/20'
  }
  return 'bg-slate-500/10 text-slate-500 dark:text-slate-400 border-slate-500/20'
}

const simplifyUserAgent = (ua: string | null) => {
  if (!ua) return 'Unknown Client'
  if (ua.includes('NextOmniTest')) return 'API Test Agent'
  if (ua.includes('Chrome')) return 'Chrome Browser'
  if (ua.includes('Safari')) return 'Safari Browser'
  if (ua.includes('Firefox')) return 'Firefox Browser'
  if (ua.includes('Postman')) return 'Postman API'
  return ua.slice(0, 24)
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      expandedLogIds.value.clear()
      fetchAuditLogs(1)
    }
  }
)
</script>

<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 sm:p-6"
    @click.self="close"
  >
    <div
      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] transition-all"
    >
      <!-- Modal Header -->
      <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex items-start justify-between bg-slate-50/50 dark:bg-slate-900/50">
        <div class="flex items-center space-x-3.5">
          <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 dark:text-emerald-400 flex items-center justify-center">
            <ShieldCheck class="w-6 h-6" />
          </div>
          <div>
            <div class="flex items-center space-x-2">
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">Security & Compliance Audit Trail</h3>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                SOC-2 Ready
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Immutable forensic ledger recording financial mutations, data imports, and authentication sessions
            </p>
          </div>
        </div>

        <div class="flex items-center space-x-2">
          <button
            type="button"
            @click="fetchAuditLogs(currentPage)"
            :disabled="isLoading"
            class="p-2 rounded-xl text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition disabled:opacity-50 cursor-pointer"
            title="Refresh Audit Logs"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
          </button>
          <button
            type="button"
            @click="close"
            class="p-2 rounded-xl text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- Filter Chips Bar -->
      <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/30 dark:bg-slate-900/20 flex items-center justify-between overflow-x-auto gap-2">
        <div class="flex items-center space-x-1.5 flex-nowrap">
          <button
            v-for="filter in filterOptions"
            :key="filter.id"
            type="button"
            @click="applyFilter(filter.id)"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition cursor-pointer"
            :class="[
              selectedFilter === filter.id
                ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20 font-bold'
                : 'bg-slate-100 dark:bg-slate-800/70 hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400'
            ]"
          >
            {{ filter.label }}
          </button>
        </div>

        <span v-if="meta" class="text-xs text-slate-400 font-mono hidden md:inline-block whitespace-nowrap">
          {{ meta.total }} total entries
        </span>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="m-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs flex items-center space-x-2">
        <AlertCircle class="w-4 h-4 flex-shrink-0" />
        <span>{{ error }}</span>
      </div>

      <!-- Main Audit Trail Body -->
      <div class="flex-1 overflow-y-auto p-6 space-y-3">
        <!-- Skeleton Loading State -->
        <div v-if="isLoading && logs.length === 0" class="space-y-3">
          <div
            v-for="i in 5"
            :key="i"
            class="p-4 rounded-2xl bg-slate-100/60 dark:bg-slate-800/40 border border-slate-200/50 dark:border-slate-800/60 animate-pulse flex items-center justify-between"
          >
            <div class="space-y-2 flex-1">
              <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/4"></div>
              <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
            </div>
            <div class="h-8 bg-slate-200 dark:bg-slate-700 rounded-lg w-20"></div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="logs.length === 0"
          class="text-center py-16 px-4 space-y-3"
        >
          <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center">
            <Layers class="w-7 h-7" />
          </div>
          <h4 class="text-sm font-bold text-slate-900 dark:text-white">No Audit Events Found</h4>
          <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
            No compliance or mutation records match the active filter. Activity will populate automatically upon financial changes.
          </p>
        </div>

        <!-- Audit Records List -->
        <div
          v-for="item in logs"
          :key="item.id"
          class="rounded-2xl border transition-all duration-200 overflow-hidden"
          :class="[
            expandedLogIds.has(item.id)
              ? 'bg-slate-50/80 dark:bg-slate-800/60 border-slate-300 dark:border-slate-700 shadow-md'
              : 'bg-white dark:bg-slate-900/40 border-slate-200 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700'
          ]"
        >
          <!-- Record Row -->
          <div
            @click="toggleExpand(item.id)"
            class="p-4 flex items-center justify-between gap-4 cursor-pointer select-none"
          >
            <!-- Left Info -->
            <div class="flex items-center space-x-3.5 min-w-0">
              <!-- Event Badge -->
              <span
                class="px-2.5 py-1 rounded-xl text-xs font-mono font-bold border uppercase flex items-center space-x-1.5 flex-shrink-0"
                :class="getEventBadgeClass(item.event)"
              >
                <span>{{ item.event }}</span>
              </span>

              <!-- Target Info -->
              <div class="min-w-0">
                <div class="flex items-center space-x-2">
                  <span class="text-xs font-bold text-slate-900 dark:text-white truncate">
                    Target: {{ item.auditable_type }}
                  </span>
                  <span class="text-[11px] font-mono text-slate-400 dark:text-slate-500">
                    #{{ item.auditable_id }}
                  </span>
                </div>
                <div class="flex items-center space-x-3 text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                  <span class="flex items-center space-x-1">
                    <Clock class="w-3 h-3 text-slate-400" />
                    <span>{{ formatRelativeTime(item.created_at) }}</span>
                    <span class="text-slate-400">&bull;</span>
                    <span class="font-mono">{{ formatTimestamp(item.created_at) }}</span>
                  </span>
                </div>
              </div>
            </div>

            <!-- Right Client Metadata & Expand -->
            <div class="flex items-center space-x-3 flex-shrink-0">
              <!-- IP & Client Chips -->
              <div class="hidden sm:flex items-center space-x-2 text-[10px] font-mono text-slate-500 dark:text-slate-400">
                <span v-if="item.ip_address" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center space-x-1">
                  <Globe class="w-2.5 h-2.5" />
                  <span>{{ item.ip_address }}</span>
                </span>
                <span v-if="item.user_agent" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center space-x-1 max-w-[140px] truncate" :title="item.user_agent">
                  <Monitor class="w-2.5 h-2.5 flex-shrink-0" />
                  <span class="truncate">{{ simplifyUserAgent(item.user_agent) }}</span>
                </span>
              </div>

              <!-- Chevron -->
              <div class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <ChevronUp v-if="expandedLogIds.has(item.id)" class="w-4 h-4" />
                <ChevronDown v-else class="w-4 h-4" />
              </div>
            </div>
          </div>

          <!-- Expanded Diff Inspector -->
          <div
            v-if="expandedLogIds.has(item.id)"
            class="px-4 pb-4 pt-2 border-t border-slate-200 dark:border-slate-800/80 space-y-3 bg-slate-50 dark:bg-slate-950/60"
          >
            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-slate-400">
              <span class="flex items-center space-x-1.5">
                <FileCode class="w-3.5 h-3.5 text-emerald-500" />
                <span>Forensic State Inspector & Mutation Payload</span>
              </span>
              <span class="text-[10px] font-mono text-slate-400">Strict Redaction Enforced</span>
            </div>

            <!-- Side by side or single state grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs font-mono">
              <!-- Old Values (if present) -->
              <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1.5">
                <div class="flex items-center justify-between text-[11px] font-bold text-amber-600 dark:text-amber-400">
                  <span>PREVIOUS STATE (old_values)</span>
                </div>
                <div v-if="item.old_values && Object.keys(item.old_values).length > 0">
                  <pre class="text-[11px] text-slate-700 dark:text-slate-300 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(item.old_values, null, 2) }}</pre>
                </div>
                <div v-else class="text-[11px] text-slate-400 italic">
                  None (Initial creation or state-free event)
                </div>
              </div>

              <!-- New Values -->
              <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1.5">
                <div class="flex items-center justify-between text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                  <span>APPLIED STATE (new_values)</span>
                </div>
                <div v-if="item.new_values && Object.keys(item.new_values).length > 0">
                  <pre class="text-[11px] text-slate-700 dark:text-slate-300 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(item.new_values, null, 2) }}</pre>
                </div>
                <div v-else class="text-[11px] text-slate-400 italic">
                  None
                </div>
              </div>
            </div>

            <!-- Client Full User Agent Details on Mobile -->
            <div v-if="item.user_agent" class="text-[10px] text-slate-500 dark:text-slate-400 font-mono truncate">
              User-Agent: {{ item.user_agent }}
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer with Pagination -->
      <div class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
        <span v-if="meta" class="text-xs text-slate-500 dark:text-slate-400 font-mono">
          Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} records)
        </span>
        <span v-else></span>

        <div class="flex items-center space-x-2">
          <button
            type="button"
            @click="fetchAuditLogs(currentPage - 1)"
            :disabled="!meta || currentPage <= 1 || isLoading"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 transition flex items-center space-x-1 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
          >
            <ChevronLeft class="w-3.5 h-3.5" />
            <span>Previous</span>
          </button>
          <button
            type="button"
            @click="fetchAuditLogs(currentPage + 1)"
            :disabled="!meta || currentPage >= meta.last_page || isLoading"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 transition flex items-center space-x-1 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
          >
            <span>Next</span>
            <ChevronRight class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
