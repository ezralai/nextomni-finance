<script setup lang="ts">
import { ref, onMounted, provide } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useAccountStore } from '~/stores/accounts'
import { useCurrencyStore } from '~/stores/currencies'
import { useToast } from '~/composables/useToast'
import ToastContainer from '~/components/ui/ToastContainer.vue'
import AuthModal from '~/components/auth/AuthModal.vue'

const authStore = useAuthStore()
const accountStore = useAccountStore()
const currencyStore = useCurrencyStore()
const toast = useToast()

const isAuthModalOpen = ref(false)
const authModalMode = ref<'login' | 'register'>('login')

const openAuthModal = (mode: 'login' | 'register' = 'login') => {
  authModalMode.value = mode
  isAuthModalOpen.value = true
}

provide('openAuthModal', openAuthModal)

onMounted(async () => {
  // Process incoming Google OAuth callback tokens and errors
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search)
    const authToken = urlParams.get('auth_token')
    const authErr = urlParams.get('auth_error')

    if (authToken) {
      window.history.replaceState({}, document.title, window.location.pathname)
      await authStore.handleOAuthToken(authToken)
      toast.success('Google Authentication Successful', 'Signed in via Google OAuth 2.0')
      await navigateTo('/workspace')
    } else if (authErr) {
      window.history.replaceState({}, document.title, window.location.pathname)
      openAuthModal('login')
      if (authErr === 'access_denied' || authErr === 'google_denied') {
        toast.warning('Authentication Cancelled', 'Google sign-in was cancelled.')
      } else {
        toast.error('Authentication Error', 'Failed to authenticate with Google.')
      }
    }
  }

  await Promise.all([
    accountStore.fetchInstitutions(),
    currencyStore.fetchRates('MYR'),
    authStore.fetchMe(),
  ])
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col selection:bg-emerald-500 selection:text-white transition-colors duration-200">
    <NuxtPage @open-auth="openAuthModal" />

    <!-- Global Authentication Modal -->
    <AuthModal
      :is-open="isAuthModalOpen"
      :initial-mode="authModalMode"
      @close="isAuthModalOpen = false"
    />

    <!-- Global Toast Notification Center -->
    <ToastContainer />
  </div>
</template>
