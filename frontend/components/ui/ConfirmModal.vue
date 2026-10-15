<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { AlertTriangle, Info, Trash2, X } from 'lucide-vue-next'

interface Props {
  isOpen: boolean
  title?: string
  message: string
  confirmText?: string
  cancelText?: string
  variant?: 'danger' | 'warning' | 'info'
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Confirm Action',
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  variant: 'danger',
  isLoading: false,
})

const emit = defineEmits<{
  (e: 'confirm'): void
  (e: 'close'): void
}>()

const handleKeydown = (e: KeyboardEvent) => {
  if (!props.isOpen) return
  if (e.key === 'Escape') {
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
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        @click.self="emit('close')"
      >
        <div
          class="w-full max-w-md p-6 space-y-4 rounded-2xl border shadow-2xl transition-all transform bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100"
        >
          <div class="flex items-start justify-between">
            <div class="flex items-center space-x-3">
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                :class="{
                  'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60': variant === 'danger',
                  'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60': variant === 'warning',
                  'bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800/60': variant === 'info',
                }"
              >
                <Trash2 v-if="variant === 'danger'" class="w-5 h-5" />
                <AlertTriangle v-else-if="variant === 'warning'" class="w-5 h-5" />
                <Info v-else class="w-5 h-5" />
              </div>
              <h3 class="text-base font-bold">{{ title }}</h3>
            </div>
            <button
              type="button"
              @click="emit('close')"
              class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
              aria-label="Close dialog"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ message }}
          </p>

          <div class="flex items-center justify-end space-x-2.5 pt-2">
            <button
              type="button"
              @click="emit('close')"
              :disabled="isLoading"
              class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
            >
              {{ cancelText }}
            </button>
            <button
              type="button"
              @click="emit('confirm')"
              :disabled="isLoading"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-white transition flex items-center space-x-1.5 shadow-sm cursor-pointer disabled:opacity-50"
              :class="{
                'bg-rose-600 hover:bg-rose-500 shadow-rose-600/20': variant === 'danger',
                'bg-amber-600 hover:bg-amber-500 shadow-amber-600/20': variant === 'warning',
                'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/20': variant === 'info',
              }"
            >
              <span v-if="isLoading">Processing...</span>
              <span v-else>{{ confirmText }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
