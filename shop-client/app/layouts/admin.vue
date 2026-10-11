<script setup lang="ts">
const { t, locale, locales, setLocale } = useI18n()
const auth = useAuth()
const { api } = useApi()

onMounted(() => {
  auth.fetchMe()
})

async function switchLocale(code: string) {
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
      // Guest preference cookie still updated by i18n.
    }
  }
}

async function onLogout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>

<template>
  <div class="shell">
    <header class="topbar">
      <NuxtLink class="brand" to="/">
        {{ t('brand') }}
      </NuxtLink>
      <div class="actions">
        <select
          :value="locale"
          aria-label="Language"
          @change="switchLocale(($event.target as HTMLSelectElement).value)"
        >
          <option
            v-for="item in locales"
            :key="item.code"
            :value="item.code"
          >
            {{ item.name }}
          </option>
        </select>
        <NuxtLink to="/admin">
          {{ t('admin.nav.dashboard') }}
        </NuxtLink>
        <button
          v-if="auth.user.value"
          type="button"
          class="secondary"
          @click="onLogout"
        >
          {{ t('nav.logout') }}
        </button>
      </div>
    </header>
    <AdminNav />
    <slot />
  </div>
</template>
