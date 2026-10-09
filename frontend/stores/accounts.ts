import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface Institution {
  id: string
  name: string
  code: string
  country: string
  logo_url: string | null
  website: string | null
  is_active: boolean
}

export interface Account {
  id: string
  user_id: string
  institution_id: string
  name: string
  account_type: 'bank' | 'credit_card' | 'e_wallet' | 'cash' | 'investment' | 'loan'
  currency: string
  opening_balance: string
  current_balance: string
  current_balance_formatted: string
  account_number_masked: string | null
  notes: string | null
  is_active: boolean
  institution?: Institution
  created_at?: string
  updated_at?: string
}

export interface AccountSummary {
  net_worth: string
  net_worth_formatted: string
  currency: string
  breakdown: {
    bank: string
    e_wallet: string
    cash: string
    credit_card: string
    investment: string
    loan: string
  }
}

export const useAccountStore = defineStore('accounts', () => {
  const { request } = useApi()

  const accounts = ref<Account[]>([])
  const institutions = ref<Institution[]>([])
  const summary = ref<AccountSummary | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const fetchInstitutions = async () => {
    try {
      const response = await request<{ data: Institution[] }>('/institutions')
      institutions.value = response.data
      return response.data
    } catch (err: any) {
      console.error('Failed to fetch institutions:', err)
      return []
    }
  }

  const fetchAccounts = async () => {
    isLoading.value = true
    error.value = null
    try {
      const response = await request<{
        accounts: Account[]
        summary: AccountSummary
      }>('/accounts')

      accounts.value = response.accounts
      summary.value = response.summary
      return response
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to load accounts.'
      return null
    } finally {
      isLoading.value = false
    }
  }

  const createAccount = async (payload: {
    institution_id: string
    name: string
    account_type: string
    currency?: string
    opening_balance: number | string
    account_number_masked?: string
    notes?: string
  }) => {
    const response = await request<{
      message: string
      account: Account
    }>('/accounts', {
      method: 'POST',
      body: payload,
    })

    // Refresh summary and list after creation
    await fetchAccounts()
    return response.account
  }

  const updateAccount = async (
    id: string,
    payload: {
      name?: string
      account_type?: string
      account_number_masked?: string
      notes?: string
      is_active?: boolean
    }
  ) => {
    const response = await request<{
      message: string
      account: Account
    }>(`/accounts/${id}`, {
      method: 'PUT',
      body: payload,
    })

    await fetchAccounts()
    return response.account
  }

  const deleteAccount = async (id: string) => {
    await request(`/accounts/${id}`, {
      method: 'DELETE',
    })

    await fetchAccounts()
  }

  return {
    accounts,
    institutions,
    summary,
    isLoading,
    error,
    fetchInstitutions,
    fetchAccounts,
    createAccount,
    updateAccount,
    deleteAccount,
  }
})
