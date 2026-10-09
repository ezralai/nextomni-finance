import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface StatementImportResult {
  import_id: string
  filename: string
  account_id: string
  account_name: string
  total_rows: number
  imported_count: number
  duplicate_count: number
  status: string
  transactions: Array<{
    id: string
    date: string
    description: string
    amount: number
    type: string
  }>
}

export const useImportStore = defineStore('imports', () => {
  const config = useRuntimeConfig()
  const { token } = useApi()

  const isUploading = ref(false)
  const uploadResult = ref<StatementImportResult | null>(null)
  const error = ref<string | null>(null)

  const uploadStatement = async (accountId: string, file: File): Promise<StatementImportResult> => {
    isUploading.value = true
    error.value = null
    uploadResult.value = null

    try {
      const formData = new FormData()
      formData.append('account_id', accountId)
      formData.append('file', file)

      const response = await fetch(`${config.public.apiBase}/imports/statements`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        },
        body: formData,
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || 'Statement upload and reconciliation failed.')
      }

      uploadResult.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.message || 'Failed to upload statement'
      throw err
    } finally {
      isUploading.value = false
    }
  }

  return {
    isUploading,
    uploadResult,
    error,
    uploadStatement,
  }
})
