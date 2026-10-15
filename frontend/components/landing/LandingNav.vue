<script setup lang="ts">
import { ref } from 'vue'
import {
  Wallet,
  Sun,
  Moon,
  LogIn,
  Cpu,
  Menu,
  X,
  ArrowRight,
  ShieldCheck,
} from 'lucide-vue-next'
import { useTheme } from '~/composables/useTheme'

interface Props {
  isAuthenticated?: boolean
}

withDefaults(defineProps<Props>(), {
  isAuthenticated: false,
})

const emit = defineEmits<{
  (e: 'open-auth', mode: 'login' | 'register'): void
  (e: 'open-specs'): void
}>()

const { isDark, toggleTheme } = useTheme()
const isMobileMenuOpen = ref(false)
</script>

<template>
  <header class="sticky top-0 z-40 border-b border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-950/80 backdrop-blur-xl transition-colors duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <!-- Brand Logo -->
      <NuxtLink to="/" class="flex items-center space-x-3 group">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform">
          <Wallet class="w-5 h-5 text-slate-950 font-bold" />
        </div>
        <div>
          <div class="flex items-center space-x-2">
            <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-slate-950 via-slate-800 to-slate-600 dark:from-white dark:via-slate-100 dark:to-slate-400 bg-clip-text text-transparent">
              NextOmni Finance
            </span>
            <span class="hidden sm:inline-flex px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
              v1.0
            </span>
          </div>
          <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono hidden sm:block">
            Multi-Currency Personal Finance
          </p>
        </div>
      </NuxtLink>

      <!-- Desktop Navigation Anchors -->
      <nav class="hidden md:flex items-center space-x-8 text-xs font-semibold text-slate-600 dark:text-slate-400">
        <a href="#features" class="hover:text-slate-950 dark:hover:text-white transition">Features</a>
        <a href="#security" class="hover:text-slate-950 dark:hover:text-white transition flex items-center space-x-1">
          <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
          <span>Security & Privacy</span>
        </a>
        <a href="#demo" class="hover:text-slate-950 dark:hover:text-white transition">Interactive Demo</a>

        <!-- Tech Evaluator / Recruiter CTA -->
        <button
          type="button"
          @click="emit('open-specs')"
          class="flex items-center space-x-1.5 px-2.5 py-1 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30 transition font-mono text-[11px] cursor-pointer"
        >
          <Cpu class="w-3.5 h-3.5" />
          <span>System Architecture</span>
        </button>
      </nav>

      <!-- Right CTAs & Theme Toggle -->
      <div class="flex items-center space-x-3">
        <!-- Theme Switcher -->
        <button
          type="button"
          @click="toggleTheme"
          class="p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
          :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
          :aria-label="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        >
          <Sun v-if="isDark" class="w-4 h-4 text-amber-400" />
          <Moon v-else class="w-4 h-4 text-indigo-500" />
        </button>

        <template v-if="isAuthenticated">
          <NuxtLink
            to="/workspace"
            class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20"
          >
            <span>Enter Workspace</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </NuxtLink>
        </template>
        <template v-else>
          <button
            type="button"
            @click="emit('open-auth', 'login')"
            class="hidden sm:inline-flex px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-950 dark:hover:text-white transition cursor-pointer"
          >
            Sign In
          </button>
          <button
            type="button"
            @click="emit('open-auth', 'register')"
            class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 cursor-pointer"
          >
            <LogIn class="w-3.5 h-3.5" />
            <span>Get Started</span>
          </button>
        </template>

        <!-- Mobile Menu Toggle Button -->
        <button
          type="button"
          @click="isMobileMenuOpen = !isMobileMenuOpen"
          class="md:hidden p-2 rounded-xl text-slate-600 dark:text-slate-400 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          aria-label="Toggle Navigation Menu"
        >
          <Menu v-if="!isMobileMenuOpen" class="w-5 h-5" />
          <X v-else class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Mobile Slide Down Menu -->
    <div
      v-if="isMobileMenuOpen"
      class="md:hidden border-t border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-950/95 backdrop-blur-xl px-4 py-4 space-y-3"
    >
      <a
        href="#features"
        @click="isMobileMenuOpen = false"
        class="block py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-emerald-500"
      >
        Features
      </a>
      <a
        href="#security"
        @click="isMobileMenuOpen = false"
        class="block py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-emerald-500"
      >
        Security & Privacy
      </a>
      <a
        href="#demo"
        @click="isMobileMenuOpen = false"
        class="block py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-emerald-500"
      >
        Interactive Demo
      </a>
      <button
        type="button"
        @click="isMobileMenuOpen = false; emit('open-specs')"
        class="w-full flex items-center justify-between py-2.5 px-3 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30 text-xs font-mono font-semibold"
      >
        <span class="flex items-center space-x-2">
          <Cpu class="w-4 h-4" />
          <span>System Architecture & Specs</span>
        </span>
        <ArrowRight class="w-3.5 h-3.5" />
      </button>

      <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex gap-2">
        <button
          v-if="!isAuthenticated"
          type="button"
          @click="isMobileMenuOpen = false; emit('open-auth', 'login')"
          class="flex-1 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300"
        >
          Sign In
        </button>
        <button
          v-if="!isAuthenticated"
          type="button"
          @click="isMobileMenuOpen = false; emit('open-auth', 'register')"
          class="flex-1 py-2 rounded-xl text-xs font-bold bg-emerald-500 text-slate-950"
        >
          Get Started
        </button>
        <NuxtLink
          v-else
          to="/workspace"
          @click="isMobileMenuOpen = false"
          class="w-full py-2 rounded-xl text-xs font-bold bg-emerald-500 text-slate-950 text-center"
        >
          Enter Workspace
        </NuxtLink>
      </div>
    </div>
  </header>
</template>
