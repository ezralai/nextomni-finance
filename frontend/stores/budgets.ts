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
  runway?: {
    liquid_balance: string
    liquid_balance_formatted: string
    daily_burn_rate: string
    monthly_burn_rate: string
    runway_months: number
    health_tier: 'robust' | 'healthy' | 'caution' | 'critical'
    health_label: string
  }
  top_merchants?: Array<{
    name: string
    count: number
    amount: string
    amount_formatted: string
    percentage: number
  }>
  spending_patterns?: {
    weekday_expense: string
    weekday_formatted: string
    weekday_percent: number
    weekday_daily_avg: number
    weekend_expense: string
    weekend_formatted: string
    weekend_percent: number
    weekend_daily_avg: number
  }
  anomalies?: Array<{
    id: string
    description: string
    amount: string
    amount_formatted: string
    category: string
    date: string
    z_score: number
    multiplier: number
    severity: 'high' | 'medium'
    reason: string
  }>
  health_score?: FinancialHealthScoreData
}

export interface HealthScorePillar {
  name: string
  score: number
  weight: number
  weighted_score: number
  metric_label: string
  status: string
}

export interface HealthScoreDiagnostic {
  pillar: string
  type: 'positive' | 'warning' | 'critical'
  message: string
}

export interface FinancialHealthScoreData {
  score: number
  grade: 'A' | 'B' | 'C' | 'D' | 'F'
  tier: string
  tier_color: string
  pillars: {
    liquidity: HealthScorePillar
    savings: HealthScorePillar
    debt_liability: HealthScorePillar
    volatility: HealthScorePillar
    concentration: HealthScorePillar
  }
  diagnostics: HealthScoreDiagnostic[]
  recommendations: string[]
}

export interface MlForecastItem {
  month: string
  predicted_expense: number
  confidence_level: number
  method: string
  lower_bound_80?: number
  upper_bound_80?: number
  lower_bound_95?: number
  upper_bound_95?: number
  margin_of_error_80?: number
  margin_of_error_95?: number
}

export interface MlAnomalyItem {
  id: string
  description: string
  amount: number
  category: string
  date: string
  z_score?: number
  reason?: string
}

export interface MlRecurringItem {
  merchant: string
  category: string
  cadence: string
  cost_type: string
  average_amount: number
  monthly_equivalent: number
  annual_projected: number
  confidence: number
  last_billed_date: string
  next_expected_date: string
}

export interface MlCategoryForecastItem {
  category: string
  historical_monthly_avg: number
  projected_monthly_spend: number
  forecast_amount?: number
  historical_mean?: number
  budget?: number
  target_budget?: number | null
  projected_variance?: number | null
  budget_utilization_pct?: number | null
  budget_status?: string
  projected_overrun_risk?: boolean
  trend_slope: 'Surging' | 'Declining' | 'Stable' | string
  trend?: string
}

export interface SpendingPersonaData {
  id: string
  title: string
  badge: string
  icon: string
  description: string
  traits: string[]
  financial_tip: string
  confidence: number
  features?: {
    dining_ratio: number
    groceries_ratio: number
    transport_ratio: number
    shopping_ratio: number
    weekend_skew: number
    savings_rate: number
    total_income: number
    total_expense: number
  }
}

export interface MlIntelligenceData {
  status: string
  duration_ms?: number
  records_analyzed: number
  summary?: {
    total_income: number
    total_expenses: number
    net_savings: number
    savings_rate_pct: number
  }
  anomalies: MlAnomalyItem[]
  forecast: MlForecastItem[]
  category_forecast?: {
    forecast_period?: string
    total_projected_expense?: number
    categories_at_risk_count?: number
    categories?: MlCategoryForecastItem[]
  }
  recurring: MlRecurringItem[]
  spending_persona?: SpendingPersonaData
}

export const useBudgetStore = defineStore('budgets', () => {
  const { request } = useApi()

  const budgets = ref<BudgetProgressItem[]>([])
  const summary = ref<BudgetSummary | null>(null)
  const cashFlow = ref<CashFlowAnalytics | null>(null)
  const mlIntelligence = ref<MlIntelligenceData | null>(null)
  const isMlLoading = ref(false)
  const mlError = ref<string | null>(null)
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

  const fetchMlIntelligence = async (forceRefresh: boolean = false) => {
    isMlLoading.value = true
    mlError.value = null
    try {
      const url = forceRefresh ? '/analytics/ml-intelligence?refresh=1' : '/analytics/ml-intelligence'
      const response = await request<{ status: string; data: MlIntelligenceData }>(url)
      mlIntelligence.value = response.data
      return response.data
    } catch (err: any) {
      console.error('Failed to fetch ML intelligence:', err)
      mlError.value = err?.data?.message || err?.message || 'Failed to generate predictive intelligence.'
      return null
    } finally {
      isMlLoading.value = false
    }
  }

  return {
    budgets,
    summary,
    cashFlow,
    mlIntelligence,
    isMlLoading,
    mlError,
    isLoading,
    isSubmitting,
    error,
    fetchBudgets,
    fetchCashFlow,
    fetchMlIntelligence,
    createBudget,
    deleteBudget,
  }
})
