import {
  assertCsrf,
  clearAuthToken,
  ensureCsrfCookie,
  readAuthToken,
  setAuthToken,
} from '../../utils/auth'

type ApiEnvelope = Record<string, unknown> & {
  token?: string
  user?: Record<string, unknown>
  code?: string
  message?: string
}

export default defineEventHandler(async (event) => {
  ensureCsrfCookie(event)
  assertCsrf(event)

  const config = useRuntimeConfig(event)
  const path = getRouterParam(event, 'path') || ''
  const url = getRequestURL(event)
  const target = `${config.apiBase}/api/${path}${url.search}`
  const method = event.method.toUpperCase()
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }

  const token = readAuthToken(event)
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const forwardedFor = getHeader(event, 'x-forwarded-for') || getRequestIP(event) || ''
  if (forwardedFor) {
    headers['X-Forwarded-For'] = forwardedFor
  }

  let body: string | undefined
  if (!['GET', 'HEAD'].includes(method)) {
    body = JSON.stringify(await readBody(event).catch(() => ({})))
  }

  let upstream: Response
  try {
    upstream = await fetch(target, {
      method,
      headers,
      body,
    })
  } catch {
    throw createError({
      statusCode: 502,
      statusMessage: 'Bad Gateway',
      data: {
        message: 'API is unreachable.',
        code: 'bad_gateway',
      },
    })
  }

  const text = await upstream.text()
  let payload: ApiEnvelope | null = null

  if (text) {
    try {
      payload = JSON.parse(text) as ApiEnvelope
    } catch {
      payload = { message: text }
    }
  }

  if (upstream.status === 401) {
    clearAuthToken(event)
  }

  const authPaths = new Set(['auth/login', 'auth/register'])
  if (upstream.ok && payload?.token && authPaths.has(path)) {
    setAuthToken(event, payload.token)
    const { token: _token, ...rest } = payload
    payload = rest
  }

  if (path === 'auth/logout' || path === 'auth/logout-all') {
    if (upstream.status === 204 || upstream.ok) {
      clearAuthToken(event)
    }
  }

  setResponseStatus(event, upstream.status)

  if (upstream.status === 204 || payload === null) {
    return null
  }

  return payload
})
