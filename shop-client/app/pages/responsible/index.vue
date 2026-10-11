<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['responsible'],
})

const { t } = useI18n()
const { api } = useApi()
const message = ref('')
const error = ref('')

onMounted(async () => {
  try {
    const res = await api<{ role: string, message?: string }>('responsible/ping')
    message.value = res.message || `OK · ${res.role}`
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
})
</script>

<template>
  <section class="panel stack">
    <h1>Responsible</h1>
    <p class="muted">
      {{ t('role.placeholder', { role: 'responsible' }) }}
    </p>
    <p v-if="message" class="success">
      {{ message }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>
  </section>
</template>
