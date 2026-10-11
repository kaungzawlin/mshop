export type AuthUser = {
  id: number
  name: string
  email: string
  locale: string
  role: string
  status: string
  created_at?: string
}

const roleHome: Record<string, string> = {
  admin: '/admin',
  reseller: '/reseller',
  rider: '/rider',
  ops: '/ops',
  finance: '/finance',
  customer: '/',
}

export function useAuth() {
  const user = useState<AuthUser | null>('auth-user', () => null)
  const loaded = useState<boolean>('auth-loaded', () => false)
  const { api } = useApi()
  const { locale, setLocale } = useI18n()

  async function fetchMe(force = false): Promise<AuthUser | null> {
    if (loaded.value && !force) {
      return user.value
    }

    try {
      const res = await api<{ user: AuthUser }>('auth/me')
      user.value = res.user
      if (res.user.locale && res.user.locale !== locale.value) {
        await setLocale(res.user.locale)
      }
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }

    return user.value
  }

  async function login(payload: { email: string, password: string }): Promise<AuthUser> {
    const res = await api<{ user: AuthUser }>('auth/login', {
      method: 'POST',
      body: payload,
    })
    user.value = res.user
    loaded.value = true
    if (res.user.locale) {
      await setLocale(res.user.locale)
    }
    return res.user
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
    locale?: string
  }): Promise<AuthUser> {
    const res = await api<{ user: AuthUser }>('auth/register', {
      method: 'POST',
      body: {
        ...payload,
        locale: payload.locale || locale.value || 'my',
      },
    })
    user.value = res.user
    loaded.value = true
    return res.user
  }

  async function logout(): Promise<void> {
    try {
      await api('auth/logout', { method: 'POST' })
    } finally {
      user.value = null
      loaded.value = true
    }
  }

  async function logoutAll(): Promise<void> {
    try {
      await api('auth/logout-all', { method: 'POST' })
    } finally {
      user.value = null
      loaded.value = true
    }
  }

  function homeFor(role: string): string {
    return roleHome[role] || '/'
  }

  return {
    user,
    loaded,
    fetchMe,
    login,
    register,
    logout,
    logoutAll,
    homeFor,
  }
}
