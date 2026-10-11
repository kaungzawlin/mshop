<script setup lang="ts">
const { locale, locales, setLocale } = useI18n()
const auth = useAuth()
const { api } = useApi()

async function switchLocale(code: string) {
  if (code === locale.value) {
    return
  }
  await setLocale(code)
  if (auth.user.value) {
    try {
      const res = await api<{ user: { locale: string } }>('me/locale', {
        method: 'PATCH',
        body: { locale: code },
      })
      if (auth.user.value) {
        auth.user.value.locale = res.user.locale
      }
    } catch {
      // Guest preference still updated locally.
    }
  }
}
</script>

<template>
  <div class="locale-switch" role="group" aria-label="Language">
    <button
      v-for="item in locales"
      :key="item.code"
      type="button"
      :class="{ 'is-active': locale === item.code }"
      :aria-pressed="locale === item.code"
      @click="switchLocale(item.code)"
    >
      {{ item.code.toUpperCase() }}
    </button>
  </div>
</template>
