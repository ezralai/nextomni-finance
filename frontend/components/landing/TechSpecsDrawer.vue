<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import {
  X,
  Cpu,
  Database,
  Server,
  Layers,
  Lock,
  ExternalLink,
  ShieldAlert,
  GitBranch,
  Terminal,
  Zap,
} from 'lucide-vue-next'

interface Props {
  isOpen: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const handleKeydown = (e: KeyboardEvent) => {
  if (props.isOpen && e.key === 'Escape') {
    emit('close')
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
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm"
        @click.self="emit('close')"
      >
        <Transition
          enter-active-class="transform transition ease-in-out duration-300"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          leave-active-class="transform transition ease-in-out duration-300"
          leave-from-class="translate-x-0"
          leave-to-class="translate-x-full"
        >
          <div
            v-if="isOpen"
            class="fixed inset-y-0 right-0 max-w-2xl w-full bg-white dark:bg-slate-950 border-l border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col z-50 text-slate-900 dark:text-slate-100"
            role="dialog"
            aria-modal="true"
            aria-label="System Architecture & Technical Specifications"
          >
            <!-- Drawer Header -->
            <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/60 dark:bg-slate-900/60">
              <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 flex items-center justify-center">
                  <Cpu class="w-5 h-5" />
                </div>
                <div>
                  <h3 class="text-base font-bold">System Architecture & Specs</h3>
                  <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">NextOmni Finance Engineering Profile</p>
                </div>
              </div>

              <div class="flex items-center space-x-2">
                <NuxtLink
                  to="/specs"
                  class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 transition flex items-center space-x-1"
                >
                  <span>Full Specs Page</span>
                  <ExternalLink class="w-3.5 h-3.5" />
                </NuxtLink>

                <button
                  type="button"
                  @click="emit('close')"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                  aria-label="Close technical specs drawer"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Drawer Content Body (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
              <!-- Executive Engineering Summary -->
              <div class="p-4 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800/40 space-y-2">
                <div class="flex items-center space-x-2 text-indigo-700 dark:text-indigo-300 font-bold text-xs uppercase tracking-wider">
                  <Zap class="w-4 h-4 text-indigo-500" />
                  <span>Engineering Highlights for Technical Evaluators</span>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                  NextOmni Finance was engineered with production rigor. It decouples high-precision double-entry ledgers from presentation layers using versioned REST APIs, strict multi-tenant authorization policies, and automated statement deduplication.
                </p>
              </div>

              <!-- High-Level Architecture Diagram -->
              <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2">
                  <Layers class="w-4 h-4 text-emerald-500" />
                  <span>Full-Stack Component Topology</span>
                </h4>

                <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 font-mono text-xs overflow-x-auto space-y-3 shadow-inner">
                  <div class="flex items-center justify-between text-[11px] text-emerald-400 border-b border-slate-800 pb-2">
                    <span>LAYER</span>
                    <span>TECH STACK & ROLE</span>
                  </div>
                  <div class="space-y-2 text-[11px]">
                    <div class="flex justify-between">
                      <span class="text-sky-400">Client / SSR:</span>
                      <span class="text-slate-300">Nuxt 3, Vue 3, Pinia, Tailwind CSS</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-emerald-400">API Gateway:</span>
                      <span class="text-slate-300">Laravel 11, Sanctum Auth, Policy Scoping</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-indigo-400">Database:</span>
                      <span class="text-slate-300">PostgreSQL 16 with Numeric(15, 4) Fields</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-amber-400">ETL Pipeline:</span>
                      <span class="text-slate-300">SHA-256 Idempotency Hashing & Anti-Formula CSV</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-rose-400">CI / CD & Deploy:</span>
                      <span class="text-slate-300">Docker Multi-Stage, Render API, Vercel Edge</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Technical Deep Dives Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- 1. Arbitrary Precision Arithmetic -->
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-2">
                  <div class="flex items-center space-x-2 text-slate-900 dark:text-white font-bold text-xs">
                    <Database class="w-4 h-4 text-emerald-500" />
                    <span>Zero Floating-Point Drift</span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    Monetary records avoid IEEE 754 floats entirely. The backend uses PHP BCMath and database <code class="text-emerald-500 font-mono">NUMERIC(15, 4)</code> to maintain absolute mathematical integrity across FX conversions.
                  </p>
                </div>

                <!-- 2. Non-Custodial Security & Tenant Isolation -->
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-2">
                  <div class="flex items-center space-x-2 text-slate-900 dark:text-white font-bold text-xs">
                    <Lock class="w-4 h-4 text-indigo-500" />
                    <span>Multi-Tenant Query Scoping</span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    Every database query is tenant-scoped to the authenticated user ID. Insecure Direct Object References (IDOR) are strictly blocked by granular Laravel Authorization Policies.
                  </p>
                </div>

                <!-- 3. Automated Statement Deduplication -->
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-2">
                  <div class="flex items-center space-x-2 text-slate-900 dark:text-white font-bold text-xs">
                    <ShieldAlert class="w-4 h-4 text-amber-500" />
                    <span>SHA-256 Statement Idempotency</span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    CSV statement normalization computes deterministic hashes combining account, date, amount, and reference to prevent duplicate ingestion across overlapping monthly statements.
                  </p>
                </div>

                <!-- 4. Containerization & Testing -->
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-2">
                  <div class="flex items-center space-x-2 text-slate-900 dark:text-white font-bold text-xs">
                    <Server class="w-4 h-4 text-sky-500" />
                    <span>Docker & Feature Testing</span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    Fully containerized with multi-stage Docker builds. Accompanied by automated Feature Test suites covering user isolation, Google OAuth, and statement reconciliation.
                  </p>
                </div>
              </div>

              <!-- Repository & Specs Link -->
              <div class="p-4 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                  <h5 class="text-xs font-bold text-slate-900 dark:text-white">Review Full Architecture Document</h5>
                  <p class="text-[10px] text-slate-500 dark:text-slate-400">Inspect the database ERD, API schema, and deployment guide.</p>
                </div>
                <NuxtLink
                  to="/specs"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1"
                >
                  <span>Explore Specs</span>
                  <ExternalLink class="w-3.5 h-3.5" />
                </NuxtLink>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
