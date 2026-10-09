import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface ExchangeRateItem {
  base_currency: string
  target_currency: string
  rate: string
  effective_date: string
}

export interface ConversionResult {
  from_currency: string
  to_currency: string
  rate: string
  original_amount: string
  original_formatted: string
  converted_amount: string
  converted_formatted: string
}

export const useCurrencyStore = defineStore('currencies', () => {
  const { request } = useApi()

  const rates = ref<ExchangeRateItem[]>([])
  const isLoading = ref(false)
  const isConverting = ref(false)
  const error = ref<string | null>(null)

  const fetchRates = async (base: string = 'MYR') => {
    isLoading.value = true
    error.value = null
    try {
      const response = await request<{
        base_currency: string
        rates: ExchangeRateItem[]
      }>(`/currencies/rates?base=${base}`)

      rates.value = response.rates
      return response.rates
    } catch (err: any) {
      error.value = err?.data?.message || err?.message || 'Failed to fetch rates.'
      return []
    } finally {
      isLoading.value = false
    }
  }

  const convert = async (amount: number | string, from: string, to: string): Promise<ConversionResult> => {
    isConverting.value = true
    error.value = null
    try {
      const response = await request<ConversionResult>('/currencies/convert', {
        method: 'POST',
        body: {
          amount: Number(amount),
          from_currency: from,
          to_currency: to,
        },
      })
      return response
    } catch (err: any) {
      const msg = err?.data?.message || err?.message || 'Currency conversion failed.'
      error.value = msg
      throw new Error(msg)
    } finally {
      isConverting.value = false
    }
  }

  return {
    rates,
    isLoading,
    isConverting,
    error,
    fetchRates,
    convert,
  }
})
