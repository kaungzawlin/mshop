<script setup lang="ts">
const props = defineProps<{
  channel: 'email' | 'sms'
}>()

const { t } = useI18n()
const { api } = useApi()

type LogRow = {
  id: number
  to_address: string
  status: string
  subject: string | null
  created_at: string | null
}

const logs = ref<LogRow[]>([])
const meta = ref<{ total?: number } | null>(null)
const error = ref('')
const loading = ref(false)
const page = ref(1)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api<{ data: LogRow[], meta: { total: number, current_page: number, last_page: number } }>(
      'admin/message-logs',
      { query: { channel: props.channel, page: page.value } },
    )
    logs.value = res.data
    meta.value = res.meta
  } catch (e: unknown) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="panel stack">
    <h1>{{ channel === 'email' ? t('admin.nav.emailLogs') : t('admin.nav.smsLogs') }}</h1>
    <p v-if="loading" class="muted">
      {{ t('admin.loading') }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <p v-if="meta" class="muted">
      {{ t('admin.total') }} · {{ meta.total }}
    </p>

    <div class="actions">
      <button type="button" class="secondary" @click="load">
        {{ t('admin.refresh') }}
      </button>
    </div>

    <table v-if="logs.length" class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t('admin.to') }}</th>
          <th>{{ t('admin.status') }}</th>
          <th>{{ t('admin.subject') }}</th>
          <th>{{ t('admin.created') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in logs" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.to_address }}</td>
          <td>{{ row.status }}</td>
          <td>{{ row.subject || '—' }}</td>
          <td>{{ row.created_at || '—' }}</td>
        </tr>
      </tbody>
    </table>
    <p v-else class="muted">
      {{ t('admin.empty') }}
    </p>
  </section>
</template>
