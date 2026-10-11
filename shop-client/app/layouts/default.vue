<script setup lang="ts">
const { t } = useI18n()
const auth = useAuth()

onMounted(() => {
  auth.fetchMe()
})

async function onLogout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>

<template>
  <div class="shell">
    <header class="topbar">
      <NuxtLink class="brand" to="/">
        m<span>shop</span>
      </NuxtLink>
      <div class="actions">
        <LocaleSwitch />
        <NuxtLink to="/">
          {{ t('nav.catalog') }}
        </NuxtLink>
        <NuxtLink v-if="auth.user.value" to="/cart">
          {{ t('nav.cart') }}
        </NuxtLink>
        <NuxtLink v-if="auth.user.value" to="/orders">
          {{ t('nav.orders') }}
        </NuxtLink>
        <NuxtLink v-if="auth.user.value?.role === 'admin'" to="/admin">
          {{ t('admin.nav.dashboard') }}
        </NuxtLink>
        <NuxtLink v-if="auth.user.value?.role === 'responsible'" to="/responsible">
          Responsible
        </NuxtLink>
        <NuxtLink v-if="auth.user.value?.role === 'system'" to="/system">
          System
        </NuxtLink>
        <NuxtLink v-if="!auth.user.value" to="/login">
          {{ t('nav.login') }}
        </NuxtLink>
        <NuxtLink v-if="!auth.user.value" to="/register">
          {{ t('nav.register') }}
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
    <slot />
  </div>
</template>
