import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Account } from './accounts'

export interface Category {
  id: string
  parent_id: string | null
  name: string
  slug: string
  icon: string | null
  color: string | null
  type: string
  is_active: boolean
  children?: Category[]
}

export interface Transaction {
  id: string
  user_id: string
  account_id: string
  category_id: string | null
  type: 'income' | 'expense' | 'transfer'
  amount: string
  amount_formatted: string
  currency: string
  transacted_at: string
  description: string
  notes: string | null
  reference_number: string | null
  status: string
  account?: Account
  category?: Category
  created_at: string
}

export interface Transfer {
  id: string
  source_account_id: string
  destination_account_id: string
  amount: string
  fee: string
  amount_formatted: string
  fee_formatted: string
  currency: string
  transacted_at: string
  notes: string | null
  source_account?: Account
  destination_account?: Account
}

export const useTransactionStore = defineStore('transactions', () => {
  const { request } = useApi()

  const transactions = ref<Transaction[]>([])
  const categories = ref<Category[]>([])
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)
  const meta = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  })

  const fetchCategories = async () => {
    try {
      const response = await request<{ categories: Category[] }>('/categories')
      categories.value = response.categories
      return response.categories
    } catch (err: any) {
      console.error('Failed to fetch categories:', err)
      return []
    }
  }

  const fetchTransactions = async (filters: {
    account_id?: string
    category_id?: string
    type?: string
    search?: string
    page?: number
  } = {}) => {
    isLoading.value = true
    error.value = null
    try {
      const queryParams = new URLSearchParams()
      if (filters.account_id) queryParams.set('account_id', filters.account_id)
      if (filters.category_id) queryParams.set('category_id', filters.category_id)
      if (filters.type) queryParams.set('type', filters.type)
      if (filters.search) queryParams.set('search', filters.search)
      if (filters.page) queryParams.set('page', filters.page.toString())

      const queryString = queryParams.toString() ? `?${queryParams.toString()}` : ''
      const response = await request<{
        data: Transaction[]
        meta: {
          current_page: number
          last_page: number
          per_page: number
          total: number
        }
      }>(`/transactions${queryString}`)

      transactions.value = response.data
      meta.value = {
        currentPage: response.meta.current_page,
        lastPage: response.meta.last_page,
        total: response.meta.total,
      }
      return response
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to fetch transactions.'
      return null
    } finally {
      isLoading.value = false
    }
  }

  const createTransaction = async (payload: {
    account_id: string
    category_id?: string
    type: 'income' | 'expense'
    amount: number | string
    description: string
    transacted_at?: string
    notes?: string
    reference_number?: string
  }) => {
    isSubmitting.value = true
    error.value = null
    try {
      const response = await request<{
        message: string
        transaction: Transaction
      }>('/transactions', {
        method: 'POST',
        body: payload,
      })

      // Refresh transactions
      await fetchTransactions()
      return response.transaction
    } catch (err: any) {
      const msg = err?.data?.message || err?.message || 'Failed to record transaction.'
      error.value = msg
      throw new Error(msg)
    } finally {
      isSubmitting.value = false
    }
  }

  const createTransfer = async (payload: {
    source_account_id: string
    destination_account_id: string
    amount: number | string
    fee?: number | string
    transacted_at?: string
    notes?: string
  }) => {
    isSubmitting.value = true
    error.value = null
    try {
      const response = await request<{
        message: string
        transfer: Transfer
      }>('/transfers', {
        method: 'POST',
        body: payload,
      })

      // Refresh transactions
      await fetchTransactions()
      return response.transfer
    } catch (err: any) {
      const msg = err?.data?.message || err?.message || 'Failed to execute transfer.'
      error.value = msg
      throw new Error(msg)
    } finally {
      isSubmitting.value = false
    }
  }

  const deleteTransaction = async (id: string) => {
    try {
      await request(`/transactions/${id}`, {
        method: 'DELETE',
      })
      await fetchTransactions()
    } catch (err: any) {
      const msg = err?.data?.message || err?.message || 'Failed to delete transaction.'
      error.value = msg
      throw new Error(msg)
    }
  }

  return {
    transactions,
    categories,
    isLoading,
    isSubmitting,
    error,
    meta,
    fetchCategories,
    fetchTransactions,
    createTransaction,
    createTransfer,
    deleteTransaction,
  }
})
