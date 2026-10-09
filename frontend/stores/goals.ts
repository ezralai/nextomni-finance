import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Account } from './accounts'

export interface Goal {
  id: string
  user_id: string
  account_id: string | null
  name: string
  description: string | null
  target_amount: string
  target_amount_raw: string
  target_amount_formatted: string
  current_amount: string
  current_amount_raw: string
  current_amount_formatted: string
  currency: string
  target_date: string | null
  color: string
  icon: string
  status: 'in_progress' | 'achieved' | 'paused'
  account?: Account
  created_at: string
}

export interface GoalWithProgress {
  goal: Goal
  target_amount: string
  target_amount_formatted: string
  current_amount: string
  current_amount_formatted: string
  remaining_amount: string
  remaining_amount_formatted: string
  percent_achieved: number
  days_remaining: number | null
  months_remaining: number | null
  monthly_required: string
  monthly_required_formatted: string
  is_achieved: boolean
}

export interface GoalsSummary {
  total_goals: number
  total_target: string
  total_target_formatted: string
  total_saved: string
  total_saved_formatted: string
  overall_percent: number
  currency: string
}

export const useGoalStore = defineStore('goals', () => {
  const { request } = useApi()

  const goals = ref<GoalWithProgress[]>([])
  const summary = ref<GoalsSummary | null>(null)
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)

  const fetchGoals = async (filters: Record<string, any> = {}) => {
    isLoading.value = true
    error.value = null
    try {
      const res = await request<{ goals: GoalWithProgress[]; summary: GoalsSummary }>('/goals', {
        method: 'GET',
        params: filters,
      })
      goals.value = res.goals
      summary.value = res.summary
      return res
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to fetch financial goals'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const createGoal = async (data: {
    name: string
    target_amount: string | number
    current_amount?: string | number
    target_date?: string
    account_id?: string
    color?: string
    icon?: string
    description?: string
  }) => {
    isSubmitting.value = true
    error.value = null
    try {
      const res = await request<{ message: string; goal: Goal }>('/goals', {
        method: 'POST',
        body: data,
      })
      await fetchGoals()
      return res.goal
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to create financial goal'
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const updateGoal = async (id: string, data: Partial<Goal>) => {
    isSubmitting.value = true
    error.value = null
    try {
      const res = await request<{ message: string; goal: Goal }>(`/goals/${id}`, {
        method: 'PUT',
        body: data,
      })
      await fetchGoals()
      return res.goal
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to update financial goal'
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const deleteGoal = async (id: string) => {
    isSubmitting.value = true
    error.value = null
    try {
      await request(`/goals/${id}`, {
        method: 'DELETE',
      })
      goals.value = goals.value.filter(g => g.goal.id !== id)
      await fetchGoals()
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to delete financial goal'
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const contribute = async (id: string, amount: string | number) => {
    isSubmitting.value = true
    error.value = null
    try {
      const res = await request<{ message: string; goal: Goal }>(`/goals/${id}/contribute`, {
        method: 'POST',
        body: { amount },
      })
      await fetchGoals()
      return res.goal
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to contribute to goal'
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    goals,
    summary,
    isLoading,
    isSubmitting,
    error,
    fetchGoals,
    createGoal,
    updateGoal,
    deleteGoal,
    contribute,
  }
})
