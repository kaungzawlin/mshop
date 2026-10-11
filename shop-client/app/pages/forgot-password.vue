<script setup lang="ts">
definePageMeta({ middleware: ['guest'] })

const { t } = useI18n()
const { api } = useApi()

const email = ref('')
const message = ref('')
const error = ref('')
const pending = ref(false)

async function submit() {
  error.value = ''
  message.value = ''
  pending.value = true
  try {
    await api('auth/forgot-password', {
      method: 'POST',
      body: { email: email.value },
    })
    message.value = t('auth.resetSent')
  } catch (e: unknown) {
    const err = e as { message?: string }
    error.value = err.message || 'Request failed'
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <section class="panel stack" style="max-width: 420px; margin: 0 auto;">
    <h1>{{ t('auth.forgot') }}</h1>
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
      <button type="submit" :disabled="pending">
        {{ t('auth.sendReset') }}
      </button>
    </form>
  </section>
</template>
