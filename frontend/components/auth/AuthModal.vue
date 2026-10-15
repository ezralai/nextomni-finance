<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  X,
  Lock,
  Mail,
  RefreshCw,
  AlertCircle,
} from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

interface Props {
  isOpen: boolean
  initialMode?: 'login' | 'register'
}

const props = withDefaults(defineProps<Props>(), {
  initialMode: 'login',
})

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success'): void
}>()

const authStore = useAuthStore()
const toast = useToast()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase

const authMode = ref<'login' | 'register'>(props.initialMode)
const isAuthSubmitting = ref(false)
const authError = ref<string | null>(null)

const loginForm = ref({ email: '', password: '' })
const registerForm = ref({ name: '', email: '', password: '', base_currency: 'MYR' })

watch(
  () => props.initialMode,
  (val) => {
    authMode.value = val
    authError.value = null
  }
)

const handleLogin = async () => {
  isAuthSubmitting.value = true
  authError.value = null
  try {
    await authStore.login(loginForm.value.email, loginForm.value.password)
    emit('success')
    emit('close')
    toast.success('Signed In', `Welcome back, ${authStore.user?.name || 'User'}!`)
    await navigateTo('/workspace')
  } catch (err: any) {
    authError.value = err?.data?.message || err?.message || 'Invalid credentials.'
    toast.error('Authentication Failed', authError.value || undefined)
  } finally {
    isAuthSubmitting.value = false
  }
}

const handleRegister = async () => {
  isAuthSubmitting.value = true
  authError.value = null
  try {
    await authStore.register({
      name: registerForm.value.name,
      email: registerForm.value.email,
      password: registerForm.value.password,
      password_confirmation: registerForm.value.password,
      base_currency: registerForm.value.base_currency,
    })
    emit('success')
    emit('close')
    toast.success('Account Created', 'Your account has been initialized.')
    await navigateTo('/workspace')
  } catch (err: any) {
    authError.value = err?.data?.message || err?.message || 'Failed to create account.'
    toast.error('Registration Failed', authError.value || undefined)
  } finally {
    isAuthSubmitting.value = false
  }
}

const handleGoogleLogin = () => {
  isAuthSubmitting.value = true
  authError.value = null
  if (typeof window !== 'undefined') {
    window.location.href = `${apiBase}/auth/google/redirect`
  }
}

const handleDemoLogin = async () => {
  isAuthSubmitting.value = true
  authError.value = null
  try {
    await authStore.demoLogin()
    emit('success')
    emit('close')
    toast.success('Demo Session Active', 'Signed in as Demo User')
    await navigateTo('/workspace')
  } catch (err: any) {
    authError.value = err?.message || 'Failed to launch demo account.'
    toast.error('Demo Error', authError.value || undefined)
  } finally {
    isAuthSubmitting.value = false
  }
}
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
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="emit('close')"
      >
        <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-6 text-slate-100">
          <button
            type="button"
            @click="emit('close')"
            class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
            aria-label="Close dialog"
          >
            <X class="w-5 h-5" />
          </button>

          <div class="space-y-3">
            <div class="flex items-center space-x-2">
              <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
                <Lock class="w-4 h-4" />
              </div>
              <h3 class="text-lg font-bold text-white">
                {{ authMode === 'login' ? 'Sign In to NextOmni Finance' : 'Create an Account' }}
              </h3>
            </div>

            <div class="flex rounded-lg bg-slate-800/60 p-1 border border-slate-700/50">
              <button
                type="button"
                @click="authMode = 'login'; authError = null"
                class="flex-1 py-1.5 text-xs font-medium rounded-md transition text-center cursor-pointer"
                :class="authMode === 'login' ? 'bg-emerald-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-white'"
              >
                Sign In
              </button>
              <button
                type="button"
                @click="authMode = 'register'; authError = null"
                class="flex-1 py-1.5 text-xs font-medium rounded-md transition text-center cursor-pointer"
                :class="authMode === 'register' ? 'bg-emerald-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-white'"
              >
                Create Account
              </button>
            </div>
          </div>

          <div v-if="authError" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-start space-x-2">
            <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" />
            <span>{{ authError }}</span>
          </div>

          <!-- Google OAuth 2.0 Action -->
          <button
            @click="handleGoogleLogin"
            :disabled="isAuthSubmitting"
            type="button"
            class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700/80 active:bg-slate-700 border border-slate-700/80 text-white transition-all duration-200 flex items-center justify-center space-x-3 shadow-sm hover:shadow hover:border-slate-600 disabled:opacity-50 group cursor-pointer"
          >
            <svg class="w-4 h-4 flex-shrink-0 transition-transform group-hover:scale-105" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
            </svg>
            <span>{{ authMode === 'login' ? 'Continue with Google' : 'Sign up with Google' }}</span>
          </button>

          <!-- Visual Or Divider -->
          <div class="relative flex items-center justify-center">
            <div class="border-t border-slate-800 w-full"></div>
            <span class="bg-slate-900 px-3 text-[11px] font-medium text-slate-500 uppercase tracking-wider absolute">or continue with email</span>
          </div>

          <!-- Sign In Form -->
          <form v-if="authMode === 'login'" @submit.prevent="handleLogin" class="space-y-4">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Email Address</label>
              <div class="relative">
                <Mail class="w-4 h-4 text-slate-500 absolute left-3 top-3 pointer-events-none" />
                <input
                  v-model="loginForm.email"
                  type="email"
                  required
                  placeholder="name@example.com"
                  class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Password</label>
              <div class="relative">
                <Lock class="w-4 h-4 text-slate-500 absolute left-3 top-3 pointer-events-none" />
                <input
                  v-model="loginForm.password"
                  type="password"
                  required
                  placeholder="••••••••"
                  class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                />
              </div>
            </div>

            <button
              type="submit"
              :disabled="isAuthSubmitting"
              class="w-full py-2.5 rounded-lg text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
            >
              <RefreshCw v-if="isAuthSubmitting" class="w-4 h-4 animate-spin" />
              <span>{{ isAuthSubmitting ? 'Authenticating...' : 'Sign In' }}</span>
            </button>
          </form>

          <!-- Register Form -->
          <form v-else @submit.prevent="handleRegister" class="space-y-4">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Full Name</label>
              <input
                v-model="registerForm.name"
                type="text"
                required
                placeholder="e.g. Ezra Lai"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Email Address</label>
              <input
                v-model="registerForm.email"
                type="email"
                required
                placeholder="name@example.com"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Password (Min. 8 characters)</label>
              <input
                v-model="registerForm.password"
                type="password"
                required
                minlength="8"
                placeholder="••••••••"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Base Currency</label>
              <select
                v-model="registerForm.base_currency"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
              >
                <option value="MYR">MYR — Malaysian Ringgit</option>
                <option value="SGD">SGD — Singapore Dollar</option>
                <option value="USD">USD — US Dollar</option>
                <option value="EUR">EUR — Euro</option>
                <option value="GBP">GBP — British Pound</option>
              </select>
            </div>

            <button
              type="submit"
              :disabled="isAuthSubmitting"
              class="w-full py-2.5 rounded-lg text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition flex items-center justify-center space-x-2 disabled:opacity-50 cursor-pointer"
            >
              <RefreshCw v-if="isAuthSubmitting" class="w-4 h-4 animate-spin" />
              <span>{{ isAuthSubmitting ? 'Creating Account...' : 'Create Account' }}</span>
            </button>
          </form>

          <div class="pt-4 border-t border-slate-800 text-center">
            <p class="text-xs text-slate-400 mb-2">Need a quick preview for review?</p>
            <button
              type="button"
              @click="handleDemoLogin"
              :disabled="isAuthSubmitting"
              class="w-full py-2 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition cursor-pointer"
            >
              Launch Instant Demo Account
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
