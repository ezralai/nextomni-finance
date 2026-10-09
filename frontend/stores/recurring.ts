import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Account } from './accounts'
import type { Category } from './transactions'

export interface RecurringTransaction {
  id: string
  user_id: string
  account_id: string
  category_id: string | null
  type: 'income' | 'expense'
  description: string
  notes: string | null
  amount: string
  amount_formatted: string
  currency: string
  frequency: 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly'
  start_date: string
  end_date: string | null
  next_due_date: string
  last_posted_at: string | null
  auto_post: boolean
  is_active: boolean
  account?: Account
  category?: Category
  created_at: string
}

export const useRecurringStore = defineStore('recurring', () => {
  const { request } = useApi()

  const recurringTransactions = ref<RecurringTransaction[]>([])
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)

  const fetchRecurring = async (filters: Record<string, any> = {}) => {
    isLoading.value = true
    error.value = null
    try {
      const res = await request<{ data: RecurringTransaction[] }>('/recurring-transactions', {
        method: 'GET',
        params: filters,
      })
      recurringTransactions.value = res.data
      return res.data
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to fetch recurring transactions'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const createRecurring = async (data: {
    account_id: string
    category_id?: string
    type: 'income' | 'expense'
    description: string
    amount: number | string
    frequency: string
    start_date: string
    end_date?: string
    auto_post?: boolean
    notes?: string
  }) => {
    isSubmitting.value = true
    error.value = null
    try {
      const res = await request<{ message: string; recurring_transaction: RecurringTransaction }>('/recurring-transactions', {
        method: 'POST',
        body: data,
      })
      recurringTransactions.value.unshift(res.recurring_transaction)
      return res.recurring_transaction
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to create recurring transaction'
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const updateRecurring = async (id: string, data: Partial<RecurringTransaction>) => {
    isSubmitting.value = true
    error.value = null
    try {
      const res = await request<{ message: string; recurring_transaction: RecurringTransaction }>(`/recurring-transactions/${id}`, {
        method: 'PUT',
        body: data,
      })
      const index = recurringTransactions.value.findIndex(r => r.id === id)
      if (index !== -1) {
        recurringTransactions.value[index] = res.recurring_transaction
      }
      return res.recurring_transaction
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to update recurring schedule'
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const deleteRecurring = async (id: string) => {
    isSubmitting.value = true
    error.value = null
    try {
      await request(`/recurring-transactions/${id}`, {
        method: 'DELETE',
      })
      recurringTransactions.value = recurringTransactions.value.filter(r => r.id !== id)
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to delete recurring schedule'
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    recurringTransactions,
    isLoading,
    isSubmitting,
    error,
    fetchRecurring,
    createRecurring,
    updateRecurring,
    deleteRecurring,
  }
})
