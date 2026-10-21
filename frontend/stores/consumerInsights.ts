import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useAuthStore } from './auth'

export interface UserPersona {
  id: string
  title: string
  badge: string
  description: string
  traits: string[]
  confidence: number
  tip: string
}

export interface PaydayDynamics {
  detected_cycle: string
  surge_velocity_ratio: number
  post_payday_daily_avg: number
  mid_month_daily_avg: number
  recommendation: string
}

export interface BenchmarkCategory {
  key: string
  name: string
  user_amount: number
  user_pct: number
  peer_pct: number
  z_score: number
  status: 'healthy' | 'elevated' | 'warning' | 'optimal'
  advice: string
}

export interface DemographicBenchmark {
  cohort_label: string
  categories: BenchmarkCategory[]
}

export interface MalaysianInsightsData {
  consent_granted: boolean
  state_code?: string
  income_tier?: string
  currency?: string
  monthly_income_avg?: number
  monthly_expense_avg?: number
  user_persona?: UserPersona
  payday_dynamics?: PaydayDynamics
  demographic_benchmark?: DemographicBenchmark
  message?: string
}

export const useConsumerInsightsStore = defineStore('consumerInsights', () => {
  const config = useRuntimeConfig()
  const apiBase = config.public.apiBase
  const authStore = useAuthStore()

  const insights = ref<MalaysianInsightsData | null>(null)
  const isLoading = ref(false)
  const isUpdatingConsent = ref(false)
  const error = ref<string | null>(null)

  const fetchInsights = async (force: boolean = false) => {
    if (insights.value && !force) return

    isLoading.value = true
    error.value = null

    try {
      const res = await $fetch<{ status: string; data: MalaysianInsightsData }>(
        `${apiBase}/api/v1/analytics/malaysian-insights`,
        {
          headers: authStore.authHeaders,
        }
      )
      insights.value = res.data
    } catch (err: any) {
      error.value = err?.data?.message || 'Failed to fetch Malaysian consumer insights'
    } finally {
      isLoading.value = false
    }
  }

  const updateConsent = async (payload: { is_opted_in?: boolean; state_code?: string; income_tier?: string }) => {
    isUpdatingConsent.value = true
    error.value = null

    try {
      await $fetch<{ status: string; data: any }>(
        `${apiBase}/api/v1/analytics/malaysian-insights/consent`,
        {
          method: 'POST',
          headers: authStore.authHeaders,
          body: payload,
        }
      )
      await fetchInsights(true)
    } catch (err: any) {
      error.value = err?.data?.message || 'Failed to update PDPA preferences'
      throw err
    } finally {
      isUpdatingConsent.value = false
    }
  }

  return {
    insights,
    isLoading,
    isUpdatingConsent,
    error,
    fetchInsights,
    updateConsent,
  }
})
