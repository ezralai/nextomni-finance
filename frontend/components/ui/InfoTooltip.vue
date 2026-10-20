<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { HelpCircle } from 'lucide-vue-next'

interface Props {
  text: string
  title?: string
  position?: 'top' | 'bottom' | 'left' | 'right'
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  position: 'top',
})

const isVisible = ref(false)
const containerRef = ref<HTMLElement | null>(null)

const toggle = (e: MouseEvent) => {
  e.stopPropagation()
  isVisible.value = !isVisible.value
}

const show = () => {
  isVisible.value = true
}

const hide = () => {
  isVisible.value = false
}

const handleClickOutside = (e: MouseEvent) => {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    isVisible.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <span
    ref="containerRef"
    class="relative inline-flex items-center text-slate-400 hover:text-emerald-500 dark:hover:text-emerald-400 transition cursor-help focus:outline-none"
    @mouseenter="show"
    @mouseleave="hide"
    @focus="show"
    @blur="hide"
    tabindex="0"
    role="button"
    :aria-label="title || text"
    @click="toggle"
  >
    <HelpCircle class="w-3.5 h-3.5 inline-block opacity-70 hover:opacity-100 transition" />

    <!-- Popover Bubble -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isVisible"
        class="absolute z-50 w-64 p-3 rounded-xl bg-slate-900 text-slate-200 text-xs shadow-xl border border-slate-700/80 pointer-events-none text-left font-normal leading-relaxed"
        :class="{
          'bottom-full left-1/2 -translate-x-1/2 mb-2': position === 'top',
          'top-full left-1/2 -translate-x-1/2 mt-2': position === 'bottom',
          'right-full top-1/2 -translate-y-1/2 mr-2': position === 'left',
          'left-full top-1/2 -translate-y-1/2 ml-2': position === 'right',
        }"
      >
        <div v-if="title" class="font-bold text-white text-[11px] uppercase tracking-wider mb-1 flex items-center space-x-1">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
          <span>{{ title }}</span>
        </div>
        <p class="text-slate-300">{{ text }}</p>

        <!-- Subtle Arrow indicator -->
        <div
          v-if="position === 'top'"
          class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900"
        />
        <div
          v-else-if="position === 'bottom'"
          class="absolute bottom-full left-1/2 -translate-x-1/2 border-4 border-transparent border-b-slate-900"
        />
      </div>
    </Transition>
  </span>
</template>
