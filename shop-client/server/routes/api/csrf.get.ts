import { ensureCsrfCookie } from '../../utils/auth'

export default defineEventHandler((event) => {
  const token = ensureCsrfCookie(event)

  return { ok: true, token }
})
