<script setup lang="ts">
definePageMeta({ middleware: ['guest'] })

const { t } = useI18n()
const route = useRoute()
const auth = useAuth()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const password = ref('')
const error = ref('')
const pending = ref(false)

onMounted(() => {
  if (route.query.blocked === '1') {
    error.value = t('auth.blocked')
  }
})

async function submit() {
  error.value = ''
  pending.value = true
  try {
    const user = await auth.login({ email: email.value, password: password.value })
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : auth.homeFor(user.role)
    await navigateTo(redirect)
  } catch (e: unknown) {
    const err = e as { code?: string, message?: string }
    if (err.code === 'account_blocked') {
      error.value = t('auth.blocked')
    } else if (err.code === 'too_many_requests') {
      error.value = t('auth.tooMany')
    } else if (err.code === 'invalid_credentials') {
      error.value = t('auth.invalidCredentials')
    } else {
      error.value = err.message || t('auth.invalidCredentials')
    }
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <section class="panel stack" style="max-width: 420px; margin: 0 auto;">
    <h1>{{ t('nav.login') }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <form class="stack" @submit.prevent="submit">
      <label>
        {{ t('auth.email') }}
        <input v-model="email" type="email" required autocomplete="email">
      </label>
      <label>
        {{ t('auth.password') }}
        <input v-model="password" type="password" required autocomplete="current-password">
      </label>
      <button type="submit" :disabled="pending">
        {{ t('nav.login') }}
      </button>
    </form>
    <div class="actions">
      <NuxtLink to="/forgot-password">
        {{ t('auth.forgot') }}
      </NuxtLink>
      <NuxtLink to="/register">
        {{ t('nav.register') }}
      </NuxtLink>
    </div>
  </section>
</template>
