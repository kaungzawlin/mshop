import type { H3Event } from 'h3'
import { createHash, randomBytes } from 'node:crypto'

export const AUTH_COOKIE = 'mshop_token'
export const XSRF_COOKIE = 'XSRF-TOKEN'

export function getAuthCookieName(event: H3Event): string {
  return useRuntimeConfig(event).authCookieName || AUTH_COOKIE
}

export function readAuthToken(event: H3Event): string | undefined {
  return getCookie(event, getAuthCookieName(event)) || undefined
}

export function setAuthToken(event: H3Event, token: string, maxAgeDays = 30): void {
  const secure = process.env.NODE_ENV === 'production'

  setCookie(event, getAuthCookieName(event), token, {
    httpOnly: true,
    secure,
    sameSite: 'lax',
    path: '/',
    maxAge: maxAgeDays * 24 * 60 * 60,
  })
}

export function clearAuthToken(event: H3Event): void {
  deleteCookie(event, getAuthCookieName(event), { path: '/' })
}

export function ensureCsrfCookie(event: H3Event): string {
  let token = getCookie(event, XSRF_COOKIE)

  if (!token) {
    token = randomBytes(32).toString('hex')
    setCookie(event, XSRF_COOKIE, token, {
      httpOnly: false,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'lax',
      path: '/',
      maxAge: 30 * 24 * 60 * 60,
    })
  }

  return token
}

export function assertCsrf(event: H3Event): void {
  const method = event.method.toUpperCase()

  if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    return
  }

  const cookieToken = getCookie(event, XSRF_COOKIE)
  const headerToken = getHeader(event, 'x-xsrf-token')
  const origin = getHeader(event, 'origin')
  const host = getHeader(event, 'host')

  if (origin && host) {
    try {
      const originHost = new URL(origin).host
      if (originHost !== host) {
        throw createError({
          statusCode: 403,
          statusMessage: 'CSRF mismatch',
          data: {
            message: 'Invalid request origin.',
            code: 'csrf_mismatch',
          },
        })
      }
    } catch (error: unknown) {
      if (typeof error === 'object' && error !== null && 'statusCode' in error) {
        throw error
      }

      throw createError({
        statusCode: 403,
        statusMessage: 'CSRF mismatch',
        data: {
          message: 'Invalid request origin.',
          code: 'csrf_mismatch',
        },
      })
    }
  }

  if (!cookieToken || !headerToken || !safeEqual(cookieToken, headerToken)) {
    throw createError({
      statusCode: 403,
      statusMessage: 'CSRF mismatch',
      data: {
        message: 'Missing or invalid CSRF token.',
        code: 'csrf_mismatch',
      },
    })
  }
}

const safe = {
  equal(a: string, b: string): boolean {
    const ha = createHash('sha256').update(a).digest()
    const hb = createHash('sha256').update(b).digest()

    if (ha.length !== hb.length) {
      return false
    }

    let out = 0
    for (let i = 0; i < ha.length; i += 1) {
      out |= ha[i] ^ hb[i]
    }

    return out === 0
  },
}
