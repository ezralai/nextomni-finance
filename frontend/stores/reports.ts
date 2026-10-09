import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface StatementSummary {
  statement_metadata: {
    platform: string
    specification: string
    generated_at: string
    user: {
      id: string
      name: string
      email: string
      base_currency: string
    }
    total_records: number
  }
  financial_summary: {
    total_inflows: string
    total_inflows_formatted: string
    total_outflows: string
    total_outflows_formatted: string
    net_cash_flow: string
    net_cash_flow_formatted: string
    currency: string
  }
  transactions: any[]
}

export const useReportStore = defineStore('reports', () => {
  const config = useRuntimeConfig()
  const { token } = useApi()
  const isExporting = ref(false)
  const error = ref<string | null>(null)

  const downloadCsv = async (filters: Record<string, any> = {}) => {
    isExporting.value = true
    error.value = null

    try {
      const queryParams = new URLSearchParams()
      Object.entries(filters).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') {
          queryParams.append(k, String(v))
        }
      })

      const queryStr = queryParams.toString() ? `?${queryParams.toString()}` : ''
      const url = `${config.public.apiBase}/reports/transactions/csv${queryStr}`

      const response = await fetch(url, {
        method: 'GET',
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      })

      if (!response.ok) {
        throw new Error(`Export failed with HTTP ${response.status}`)
      }

      const blob = await response.blob()
      const downloadUrl = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = downloadUrl
      a.download = `NextOmni_Financial_Statement_${new Date().toISOString().slice(0, 10)}.csv`
      document.body.appendChild(a)
      a.click()
      a.remove()
      window.URL.revokeObjectURL(downloadUrl)
    } catch (err: any) {
      error.value = err.message || 'Failed to download CSV export'
      throw err
    } finally {
      isExporting.value = false
    }
  }

  const downloadJson = async (filters: Record<string, any> = {}) => {
    isExporting.value = true
    error.value = null

    try {
      const { request } = useApi()
      const data = await request<StatementSummary>('/reports/transactions/json', {
        method: 'GET',
        params: filters,
      })

      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
      const downloadUrl = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = downloadUrl
      a.download = `NextOmni_Financial_Statement_${new Date().toISOString().slice(0, 10)}.json`
      document.body.appendChild(a)
      a.click()
      a.remove()
      window.URL.revokeObjectURL(downloadUrl)

      return data
    } catch (err: any) {
      error.value = err.message || 'Failed to download JSON statement'
      throw err
    } finally {
      isExporting.value = false
    }
  }

  return {
    isExporting,
    error,
    downloadCsv,
    downloadJson,
  }
})
