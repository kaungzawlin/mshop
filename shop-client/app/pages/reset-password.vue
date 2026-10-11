<script setup lang="ts">
definePageMeta({ middleware: ['guest'] })

const { t } = useI18n()
const route = useRoute()
const { api } = useApi()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const token = ref(typeof route.query.token === 'string' ? route.query.token : '')
const password = ref('')
const passwordConfirmation = ref('')
const message = ref('')
const error = ref('')
const pending = ref(false)

async function submit() {
  error.value = ''
  message.value = ''
  pending.value = true
  try {
    await api('auth/reset-password', {
      method: 'POST',
      body: {
        email: email.value,
        token: token.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      },
    })
    message.value = t('auth.resetOk')
    await navigateTo('/login')
  } catch (e: unknown) {
    const err = e as { message?: string }
    error.value = err.message || 'Reset failed'
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <section class="panel stack" style="max-width: 420px; margin: 0 auto;">
    <h1>{{ t('auth.reset') }}</h1>
    <p class="muted">
      {{ t('auth.passwordHint') }}
    </p>
    <p v-if="message" class="success">
      {{ message }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <form class="stack" @submit.prevent="submit">
      <label>
        {{ t('auth.email') }}
        <input v-model="email" type="email" required>
      </label>
      <label>
        {{ t('auth.password') }}
        <input v-model="password" type="password" required minlength="8">
      </label>
      <label>
        {{ t('auth.passwordConfirm') }}
        <input v-model="passwordConfirmation" type="password" required minlength="8">
      </label>
      <button type="submit" :disabled="pending || !token">
        {{ t('auth.reset') }}
      </button>
    </form>
  </section>
</template>
