<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['system'],
})

const { t } = useI18n()
const { api } = useApi()
const message = ref('')
const error = ref('')

onMounted(async () => {
  try {
    const res = await api<{ role: string, message?: string }>('system/ping')
    message.value = res.message || `OK · ${res.role}`
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
})
</script>

<template>
  <section class="panel stack">
    <h1>System</h1>
    <p class="muted">
      {{ t('role.placeholder', { role: 'system' }) }}
    </p>
    <p class="muted">
      System-run account for automation and operational jobs.
    </p>
    <p v-if="message" class="success">
      {{ message }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>
  </section>
</template>
