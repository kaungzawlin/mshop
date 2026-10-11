<script setup lang="ts">
const props = defineProps<{
  channel: 'email' | 'sms'
}>()

const { t } = useI18n()
const { api } = useApi()

type Campaign = {
  id: number
  name: string
  status: string
  channel: string
  template_id: number
  stats: { total?: number, sent?: number, failed?: number }
}

type Template = {
  id: number
  name: string
  code: string
}

const campaigns = ref<Campaign[]>([])
const templates = ref<Template[]>([])
const error = ref('')
const success = ref('')
const loading = ref(false)

const createForm = reactive({
  template_id: 0,
  name: '',
})

const upload = reactive({
  campaignId: 0,
  file: null as File | null,
})

const schedule = reactive({
  campaignId: 0,
  scheduled_at: '',
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [campRes, tplRes] = await Promise.all([
      api<{ data: Campaign[] }>('admin/message-campaigns'),
      api<{ data: Template[] }>('admin/message-templates', { query: { channel: props.channel } }),
    ])
    campaigns.value = campRes.data.filter(c => c.channel === props.channel)
    templates.value = tplRes.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}

async function loadCampaignsOnly() {
  try {
    const campRes = await api<{ data: Campaign[] }>('admin/message-campaigns')
    campaigns.value = campRes.data.filter(row => row.channel === props.channel)
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function createCampaign() {
  error.value = ''
  success.value = ''
  try {
    await api('admin/message-campaigns', {
      method: 'POST',
      body: {
        template_id: createForm.template_id,
        name: createForm.name,
        channel: props.channel,
      },
    })
    success.value = t('admin.saved')
    createForm.name = ''
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  upload.file = input.files?.[0] ?? null
}

async function uploadRecipients() {
  error.value = ''
  success.value = ''
  if (!upload.campaignId || !upload.file) {
    error.value = t('admin.uploadRequired')
    return
  }
  const form = new FormData()
  form.append('file', upload.file)
  try {
    await api(`admin/message-campaigns/${upload.campaignId}/upload`, {
      method: 'POST',
      body: form,
    })
    success.value = t('admin.uploadOk')
    upload.file = null
    await loadCampaignsOnly()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function scheduleCampaign() {
  error.value = ''
  success.value = ''
  if (!schedule.campaignId) {
    error.value = t('admin.pickCampaign')
    return
  }
  try {
    await api(`admin/message-campaigns/${schedule.campaignId}/schedule`, {
      method: 'POST',
      body: schedule.scheduled_at ? { scheduled_at: schedule.scheduled_at } : {},
    })
    success.value = t('admin.scheduled')
    await loadCampaignsOnly()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(async () => {
  await load()
})
</script>

<template>
  <section class="panel stack">
    <h1>{{ channel === 'email' ? t('admin.nav.emailCampaigns') : t('admin.nav.smsCampaigns') }}</h1>
    <p v-if="loading" class="muted">
      {{ t('admin.loading') }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <p v-if="success" class="success">
      {{ success }}
    </p>

    <div class="actions">
      <button type="button" class="secondary" @click="load">
        {{ t('admin.refresh') }}
      </button>
    </div>

    <table v-if="campaigns.length" class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t('admin.name') }}</th>
          <th>{{ t('admin.status') }}</th>
          <th>{{ t('admin.recipients') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in campaigns" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.name }}</td>
          <td>{{ row.status }}</td>
          <td>{{ row.stats?.total ?? 0 }}</td>
        </tr>
      </tbody>
    </table>

    <h2>{{ t('admin.createCampaign') }}</h2>
    <form class="stack" @submit.prevent="createCampaign">
      <label>
        {{ t('admin.template') }}
        <select v-model.number="createForm.template_id" required>
          <option :value="0" disabled>
            {{ t('admin.pickTemplate') }}
          </option>
          <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">
            {{ tpl.name }} ({{ tpl.code }})
          </option>
        </select>
      </label>
      <label>
        {{ t('admin.name') }}
        <input v-model="createForm.name" required>
      </label>
      <button type="submit">
        {{ t('admin.create') }}
      </button>
    </form>

    <h2>{{ t('admin.uploadRecipients') }}</h2>
    <form class="stack" @submit.prevent="uploadRecipients">
      <label>
        {{ t('admin.campaignId') }}
        <input v-model.number="upload.campaignId" type="number" min="1" required>
      </label>
      <label>
        {{ t('admin.file') }}
        <input type="file" accept=".csv,.xlsx,.txt" @change="onFileChange">
      </label>
      <button type="submit">
        {{ t('admin.upload') }}
      </button>
    </form>

    <h2>{{ t('admin.scheduleCampaign') }}</h2>
    <form class="stack" @submit.prevent="scheduleCampaign">
      <label>
        {{ t('admin.campaignId') }}
        <input v-model.number="schedule.campaignId" type="number" min="1" required>
      </label>
      <label>
        {{ t('admin.scheduledAt') }}
        <input v-model="schedule.scheduled_at" type="datetime-local">
      </label>
      <p class="muted">
        {{ t('admin.scheduleHint') }}
      </p>
      <button type="submit">
        {{ t('admin.schedule') }}
      </button>
    </form>
  </section>
</template>
