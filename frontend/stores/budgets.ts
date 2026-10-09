import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Category } from './transactions'

export interface Budget {
  id: string
  user_id: string
  category_id: string
  amount: string
  amount_formatted: string
  currency: string
  period: string
  start_date: string
  end_date: string
  rollover_enabled: boolean
  notes: string | null
  category?: Category
}

export interface BudgetProgressItem {
  budget: Budget
  category: Category | null
  amount: string
  amount_formatted: string
  spent: string
  spent_formatted: string
  remaining: string
  remaining_formatted: string
  percent_used: number
  is_over_budget: boolean
  status: 'healthy' | 'warning' | 'exceeded'
}

export interface BudgetSummary {
  total_budgeted: string
  total_budgeted_formatted: string
  total_spent: string
  total_spent_formatted: string
  total_remaining: string
  total_remaining_formatted: string
  overall_percent_used: number
  currency: string
}

export interface CashFlowAnalytics {
  period: {
    start_date: string
    end_date: string
  }
  summary: {
    total_income: string
    total_income_formatted: string
    total_expense: string
    total_expense_formatted: string
    net_cash_flow: string
    net_cash_flow_formatted: string
    savings_rate: number
    currency: string
  }
  categories: Array<{
    name: string
    color: string
    icon: string
    amount: string
    amount_formatted: string
    percentage: number
  }>
  daily_trend: Array<{
    date: string
    amount: string
    amount_formatted: string
  }>
}

export const useBudgetStore = defineStore('budgets', () => {
  const { request } = useApi()

  const budgets = ref<BudgetProgressItem[]>([])
  const summary = ref<BudgetSummary | null>(null)
  const cashFlow = ref<CashFlowAnalytics | null>(null)
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)

  const fetchBudgets = async (date?: string) => {
    isLoading.value = true
    error.value = null
    try {
      const url = date ? `/budgets?date=${date}` : '/budgets'
      const response = await request<{
        budgets: BudgetProgressItem[]
        summary: BudgetSummary
      }>(url)

      budgets.value = response.budgets
      summary.value = response.summary
      return response
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to fetch budgets.'
      return null
    } finally {
      isLoading.value = false
    }
  }

  const fetchCashFlow = async (startDate?: string, endDate?: string) => {
    try {
      const params = new URLSearchParams()
      if (startDate) params.set('start_date', startDate)
      if (endDate) params.set('end_date', endDate)
      const queryString = params.toString() ? `?${params.toString()}` : ''

      const response = await request<CashFlowAnalytics>(`/analytics/cash-flow${queryString}`)
      cashFlow.value = response
      return response
    } catch (err: any) {
      console.error('Failed to fetch cash flow:', err)
      return null
    }
  }

  const createBudget = async (payload: {
    category_id: string
    amount: number | string
    period?: string
    start_date: string
    end_date: string
    rollover_enabled?: boolean
    notes?: string
  }) => {
    isSubmitting.value = true
    error.value = null
    try {
      const response = await request<{
        message: string
        budget: Budget
      }>('/budgets', {
        method: 'POST',
        body: payload,
      })

      await fetchBudgets()
      return response.budget
    } catch (err: any) {
      const msg = err?.data?.message || err?.message || 'Failed to create budget.'
      error.value = msg
      throw new Error(msg)
    } finally {
      isSubmitting.value = false
    }
  }

  const deleteBudget = async (id: string) => {
    try {
      await request(`/budgets/${id}`, {
        method: 'DELETE',
      })
      await fetchBudgets()
    } catch (err: any) {
      const msg = err?.data?.message || err?.message || 'Failed to delete budget.'
      error.value = msg
      throw new Error(msg)
    }
  }

  return {
    budgets,
    summary,
    cashFlow,
    isLoading,
    isSubmitting,
    error,
    fetchBudgets,
    fetchCashFlow,
    createBudget,
    deleteBudget,
  }
})
