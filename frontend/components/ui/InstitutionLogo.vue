<script setup lang="ts">
import { ref, computed } from 'vue'

const props = withDefaults(
  defineProps<{
    code?: string | null
    name?: string | null
    logoUrl?: string | null
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
    interactive?: boolean
    border?: boolean
  }>(),
  {
    code: 'CASH',
    name: '',
    logoUrl: null,
    size: 'md',
    interactive: false,
    border: true,
  }
)

const hasError = ref(false)

// Canonical mapping of codes to SVG logos in /images/institutions/
const logoMap: Record<string, string> = {
  tng: '/images/institutions/tng.svg',
  mbb: '/images/institutions/mbb.svg',
  maybank: '/images/institutions/mbb.svg',
  cimb: '/images/institutions/cimb.svg',
  grab: '/images/institutions/grab.svg',
  grabpay: '/images/institutions/grab.svg',
  pbb: '/images/institutions/pbb.svg',
  publicbank: '/images/institutions/pbb.svg',
  rhb: '/images/institutions/rhb.svg',
  hlb: '/images/institutions/hlb.svg',
  hongleong: '/images/institutions/hlb.svg',
  wise: '/images/institutions/wise.svg',
  cash: '/images/institutions/cash.svg',
}

// Brand glow colors for interactive micro-animations
const glowMap: Record<string, string> = {
  tng: 'hover:shadow-sky-500/30 hover:border-sky-400',
  mbb: 'hover:shadow-amber-500/35 hover:border-amber-400',
  maybank: 'hover:shadow-amber-500/35 hover:border-amber-400',
  cimb: 'hover:shadow-red-500/30 hover:border-red-400',
  grab: 'hover:shadow-emerald-500/30 hover:border-emerald-400',
  grabpay: 'hover:shadow-emerald-500/30 hover:border-emerald-400',
  pbb: 'hover:shadow-rose-500/30 hover:border-rose-400',
  publicbank: 'hover:shadow-rose-500/30 hover:border-rose-400',
  rhb: 'hover:shadow-blue-500/30 hover:border-blue-400',
  hlb: 'hover:shadow-blue-900/30 hover:border-blue-600',
  hongleong: 'hover:shadow-blue-900/30 hover:border-blue-600',
  wise: 'hover:shadow-lime-500/30 hover:border-lime-400',
  cash: 'hover:shadow-emerald-500/30 hover:border-emerald-400',
}

const normalizedCode = computed(() => (props.code || 'cash').toLowerCase().trim())

const resolvedLogoSrc = computed(() => {
  if (props.logoUrl) return props.logoUrl
  return logoMap[normalizedCode.value] || null
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return { container: 'w-5 h-5 rounded', text: 'text-[9px]' }
    case 'sm':
      return { container: 'w-7 h-7 rounded-md', text: 'text-[10px]' }
    case 'lg':
      return { container: 'w-12 h-12 rounded-2xl', text: 'text-sm' }
    case 'xl':
      return { container: 'w-16 h-16 rounded-3xl', text: 'text-base' }
    case 'md':
    default:
      return { container: 'w-10 h-10 rounded-xl', text: 'text-xs' }
  }
})

const glowClass = computed(() => {
  if (!props.interactive) return ''
  return glowMap[normalizedCode.value] || 'hover:shadow-emerald-500/20 hover:border-emerald-400'
})

const initials = computed(() => {
  if (props.code) return props.code.slice(0, 3).toUpperCase()
  if (props.name) return props.name.slice(0, 3).toUpperCase()
  return 'BNK'
})

const handleError = () => {
  hasError.value = true
}
</script>

<template>
  <div
    class="relative flex items-center justify-center flex-shrink-0 select-none overflow-hidden transition-all duration-200"
    :class="[
      sizeClasses.container,
      border ? 'border border-slate-200/90 dark:border-slate-700/80' : '',
      interactive ? 'hover:scale-105 active:scale-95 cursor-pointer shadow-sm hover:shadow-lg' : '',
      glowClass,
      'bg-white dark:bg-slate-900'
    ]"
    :title="name || code || 'Financial Institution'"
  >
    <!-- Real Vector Brand Logo -->
    <img
      v-if="resolvedLogoSrc && !hasError"
      :src="resolvedLogoSrc"
      :alt="name || code || 'Bank Logo'"
      class="w-full h-full object-cover transition-transform duration-200"
      :class="interactive ? 'group-hover:scale-110' : ''"
      loading="lazy"
      @error="handleError"
    />

    <!-- Fallback Monogram Badge -->
    <div
      v-else
      class="w-full h-full flex items-center justify-center font-bold font-mono tracking-tighter bg-gradient-to-br from-slate-800 to-slate-950 text-emerald-400"
      :class="sizeClasses.text"
    >
      {{ initials }}
    </div>
  </div>
</template>
