type ApiError = {
  message?: string
  code?: string
  errors?: Record<string, string[]>
}

type ApiQueryValue = string | number | boolean | undefined | null

export type ApiOptions = {
  method?: string
  body?: Record<string, unknown> | FormData
  query?: Record<string, ApiQueryValue>
}

function readCookie(name: string): string | undefined {
  if (!import.meta.client) {
    return undefined
  }

  const match = document.cookie
    .split('; ')
    .find(row => row.startsWith(`${name}=`))

  return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : undefined
}

function buildQuery(query?: Record<string, ApiQueryValue>): Record<string, string> | undefined {
  if (!query) {
    return undefined
  }

  const params: Record<string, string> = {}
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== '') {
      params[key] = String(value)
    }
  }

  return Object.keys(params).length ? params : undefined
}

export function useApi() {
  async function ensureCsrf(): Promise<string | undefined> {
    await $fetch('/api/csrf')
    return readCookie('XSRF-TOKEN')
  }

  async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
    const method = (options.method || 'GET').toUpperCase()
    const headers: Record<string, string> = {
      Accept: 'application/json',
    }

    const body = options.body
    const isFormData = typeof FormData !== 'undefined' && body instanceof FormData

    if (body !== undefined && !isFormData && method !== 'GET') {
      headers['Content-Type'] = 'application/json'
    }

    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      const xsrf = (await ensureCsrf()) || readCookie('XSRF-TOKEN')
      if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf
      }
    }

    try {
      return await $fetch<T>(`/api/${path.replace(/^\//, '')}`, {
        method: method as 'GET',
        body: method === 'GET' ? undefined : body,
        headers,
        query: buildQuery(options.query),
      })
    } catch (error: unknown) {
      const err = error as { data?: ApiError, statusCode?: number }
      const data = err.data || {}

      if (data.code === 'unauthenticated' || data.code === 'account_blocked' || err.statusCode === 401) {
        const auth = useAuth()
        auth.user.value = null
        if (import.meta.client && !path.startsWith('auth/login')) {
          const redirect = encodeURIComponent(useRoute().fullPath)
          await navigateTo(`/login?redirect=${redirect}${data.code === 'account_blocked' ? '&blocked=1' : ''}`)
        }
      }

      throw Object.assign(new Error(data.message || 'Request failed'), {
        code: data.code,
        errors: data.errors,
        statusCode: err.statusCode,
      })
    }
  }

  return { api, ensureCsrf }
}
