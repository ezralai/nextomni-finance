<script setup lang="ts">
import {
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Info,
  X,
} from 'lucide-vue-next'
import { useToast } from '~/composables/useToast'

const { toasts, removeToast } = useToast()
</script>

<template>
  <div
    class="fixed bottom-5 right-5 z-[100] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"
    role="status"
    aria-live="polite"
  >
    <TransitionGroup
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-4 opacity-0 scale-95"
      enter-to-class="translate-y-0 opacity-100 scale-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 p-4 rounded-xl shadow-2xl border backdrop-blur-md transition-all"
        :class="{
          'bg-emerald-50/95 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800 text-emerald-950 dark:text-emerald-100':
            toast.type === 'success',
          'bg-rose-50/95 dark:bg-rose-950/80 border-rose-200 dark:border-rose-800 text-rose-950 dark:text-rose-100':
            toast.type === 'error',
          'bg-amber-50/95 dark:bg-amber-950/80 border-amber-200 dark:border-amber-800 text-amber-950 dark:text-amber-100':
            toast.type === 'warning',
          'bg-sky-50/95 dark:bg-slate-900/90 border-sky-200 dark:border-slate-800 text-slate-900 dark:text-slate-100':
            toast.type === 'info',
        }"
      >
        <div class="flex-shrink-0 mt-0.5">
          <CheckCircle2
            v-if="toast.type === 'success'"
            class="w-5 h-5 text-emerald-600 dark:text-emerald-400"
          />
          <AlertCircle
            v-else-if="toast.type === 'error'"
            class="w-5 h-5 text-rose-600 dark:text-rose-400"
          />
          <AlertTriangle
            v-else-if="toast.type === 'warning'"
            class="w-5 h-5 text-amber-600 dark:text-amber-400"
          />
          <Info
            v-else
            class="w-5 h-5 text-sky-600 dark:text-sky-400"
          />
        </div>

        <div class="flex-1 min-w-0">
          <h4 class="text-xs font-bold leading-tight">{{ toast.title }}</h4>
          <p
            v-if="toast.message"
            class="text-[11px] mt-1 opacity-90 leading-relaxed font-normal"
          >
            {{ toast.message }}
          </p>
        </div>

        <button
          @click="removeToast(toast.id)"
          class="flex-shrink-0 p-1 -mr-1 -mt-1 rounded-lg opacity-60 hover:opacity-100 transition cursor-pointer"
          aria-label="Dismiss notification"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
