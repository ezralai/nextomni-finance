import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface User {
  id: string | number
  name: string
  email: string
  avatar?: string | null
  base_currency: string
  has_password?: boolean
  created_at?: string
  updated_at?: string
}

interface AuthResponse {
  message: string
  user: User
  token: string
  token_type: string
}

export const useAuthStore = defineStore('auth', () => {
  const { request, token } = useApi()
  const user = ref<User | null>(null)
  const isInitializing = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  const fetchMe = async () => {
    if (!token.value) {
      user.value = null
      return null
    }

    try {
      isInitializing.value = true
      const response = await request<{ user: User }>('/auth/me')
      user.value = response.user
      return response.user
    } catch {
      token.value = null
      user.value = null
      return null
    } finally {
      isInitializing.value = false
    }
  }

  const register = async (payload: {
    name: string
    email: string
    password: string
    base_currency?: string
  }) => {
    const response = await request<AuthResponse>('/auth/register', {
      method: 'POST',
      body: payload,
    })

    token.value = response.token
    user.value = response.user
    return response
  }

  const login = async (payload: { email: string; password: string }) => {
    const response = await request<AuthResponse>('/auth/login', {
      method: 'POST',
      body: payload,
    })

    token.value = response.token
    user.value = response.user
    return response
  }

  const updateProfile = async (payload: { name?: string; base_currency?: string }) => {
    const response = await request<{ message: string; user: User }>('/auth/profile', {
      method: 'PUT',
      body: payload,
    })

    user.value = response.user
    return response
  }

  const handleOAuthToken = async (newToken: string) => {
    token.value = newToken
    return await fetchMe()
  }

  const demoLogin = async () => {
    const demoEmail = 'demo@nextomni.com'
    const demoPass = 'NextOmniDemo2026!'

    try {
      return await login({ email: demoEmail, password: demoPass })
    } catch {
      return await register({
        name: 'Demo Investor',
        email: demoEmail,
        password: demoPass,
        base_currency: 'MYR',
      })
    }
  }

  const logout = async () => {
    try {
      if (token.value) {
        await request('/auth/logout', { method: 'POST' })
      }
    } catch {
      // Ignore network errors on logout
    } finally {
      token.value = null
      user.value = null
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    isInitializing,
    fetchMe,
    register,
    login,
    demoLogin,
    handleOAuthToken,
    updateProfile,
    logout,
  }
})
