import en from '~/locales/en'
import my from '~/locales/my'

type Dict = typeof en
type LocaleCode = 'my' | 'en'

const dictionaries: Record<LocaleCode, Dict> = { en, my }

function lookup(dict: Dict, key: string): string | undefined {
  return key.split('.').reduce<unknown>((acc, part) => {
    if (acc && typeof acc === 'object' && part in acc) {
      return (acc as Record<string, unknown>)[part]
    }
    return undefined
  }, dict) as string | undefined
}

export function useI18n() {
  const locale = useState<LocaleCode>('locale', () => {
    if (import.meta.client) {
      const saved = localStorage.getItem('mshop_locale')
      if (saved === 'en' || saved === 'my') {
        return saved
      }
    }
    return 'my'
  })

  const locales = [
    { code: 'my' as const, name: 'မြန်မာ' },
    { code: 'en' as const, name: 'English' },
  ]

  function t(key: string, params: Record<string, string> = {}): string {
    const raw = lookup(dictionaries[locale.value], key) || lookup(dictionaries.en, key) || key
    return Object.entries(params).reduce(
      (text, [name, value]) => text.replaceAll(`{${name}}`, value),
      raw,
    )
  }

  async function setLocale(code: string) {
    if (code !== 'my' && code !== 'en') {
      return
    }
    locale.value = code
    if (import.meta.client) {
      localStorage.setItem('mshop_locale', code)
      document.cookie = `mshop_locale=${code}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`
    }
  }

  return {
    locale,
    locales,
    t,
    setLocale,
  }
}
