<script setup lang="ts">
const props = defineProps<{
  role: string
  pingPath: string
}>()

const { t } = useI18n()
const auth = useAuth()
const { api } = useApi()

const ping = ref<{ ok?: boolean, role?: string } | null>(null)
const error = ref('')

onMounted(async () => {
  try {
    ping.value = await api<{ ok: boolean, role: string }>(props.pingPath)
  } catch (e: unknown) {
    const err = e as { message?: string }
    error.value = err.message || t('auth.noAccess')
  }
})

async function logoutAll() {
  await auth.logoutAll()
  await navigateTo('/login')
}
</script>

<template>
  <section class="panel stack">
    <h1>{{ role }}</h1>
    <p class="muted">
      {{ t('role.placeholder', { role }) }}
    </p>
    <p>
      <strong>{{ t('role.comingSoon') }}</strong>
    </p>
    <p v-if="ping" class="success">
      ping ok · {{ ping.role }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <div class="actions">
      <button type="button" class="secondary" @click="auth.logout().then(() => navigateTo('/login'))">
        {{ t('nav.logout') }}
      </button>
      <button type="button" class="secondary" @click="logoutAll">
        {{ t('nav.logoutAll') }}
      </button>
    </div>
  </section>
</template>
