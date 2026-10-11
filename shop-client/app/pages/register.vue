<script setup lang="ts">
definePageMeta({ middleware: ['guest'] })

const { t, locale } = useI18n()
const route = useRoute()
const auth = useAuth()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const pending = ref(false)

async function submit() {
  error.value = ''
  pending.value = true
  try {
    const user = await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      locale: locale.value,
    })
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : auth.homeFor(user.role)
    await navigateTo(redirect)
  } catch (e: unknown) {
    const err = e as { code?: string, message?: string }
    if (err.code === 'email_taken') {
      error.value = t('auth.emailTaken')
    } else {
      error.value = err.message || 'Validation failed'
    }
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <section class="panel stack" style="max-width: 420px; margin: 0 auto;">
    <h1>{{ t('nav.register') }}</h1>
    <p class="muted">
      {{ t('auth.passwordHint') }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
      <NuxtLink v-if="error === t('auth.emailTaken')" :to="`/login?email=${encodeURIComponent(email)}`">
        {{ t('auth.loginInstead') }}
      </NuxtLink>
    </p>
    <form class="stack" @submit.prevent="submit">
      <label>
        {{ t('auth.name') }}
        <input v-model="name" type="text" required autocomplete="name">
      </label>
      <label>
        {{ t('auth.email') }}
        <input v-model="email" type="email" required autocomplete="email">
      </label>
      <label>
        {{ t('auth.password') }}
        <input v-model="password" type="password" required minlength="8" autocomplete="new-password">
      </label>
      <label>
        {{ t('auth.passwordConfirm') }}
        <input v-model="passwordConfirmation" type="password" required minlength="8" autocomplete="new-password">
      </label>
      <button type="submit" :disabled="pending">
        {{ t('nav.register') }}
      </button>
    </form>
  </section>
</template>
