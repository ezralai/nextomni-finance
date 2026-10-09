export const useApi = () => {
  const config = useRuntimeConfig()
  const apiBase = config.public.apiBase
  const token = useCookie<string | null>('nextomni_token', {
    maxAge: 60 * 60 * 24 * 30, // 30 days
    sameSite: 'lax',
  })

  const request = async <T>(
    endpoint: string,
    options: {
      method?: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH'
      body?: any
      params?: any
      headers?: Record<string, string>
    } = {}
  ): Promise<T> => {
    const url = `${apiBase}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`

    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...options.headers,
    }

    if (token.value) {
      headers.Authorization = `Bearer ${token.value}`
    }

    return await $fetch<T>(url, {
      method: options.method || 'GET',
      body: options.body,
      params: options.params,
      headers,
    })
  }

  return {
    request,
    token,
  }
}
