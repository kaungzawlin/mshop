<script setup lang="ts">
const props = defineProps<{
  channel: 'email' | 'sms'
}>()

const { t } = useI18n()
const { api } = useApi()

type Template = {
  id: number
  code: string
  name: string
  subject: string | null
  body: string
  is_system: boolean
}

const templates = ref<Template[]>([])
const error = ref('')
const success = ref('')
const loading = ref(false)

const createForm = reactive({
  code: '',
  name: '',
  subject: '',
  body: '',
})

const sample = reactive({
  templateId: 0,
  to: '',
  variablesJson: '{}',
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api<{ data: Template[] }>('admin/message-templates', {
      query: { channel: props.channel },
    })
    templates.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}

async function createTemplate() {
  error.value = ''
  success.value = ''
  try {
    await api('admin/message-templates', {
      method: 'POST',
      body: {
        channel: props.channel,
        code: createForm.code,
        name: createForm.name,
        subject: props.channel === 'email' ? createForm.subject || null : null,
        body: createForm.body,
      },
    })
    success.value = t('admin.saved')
    createForm.code = ''
    createForm.name = ''
    createForm.subject = ''
    createForm.body = ''
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function sendSample() {
  error.value = ''
  success.value = ''
  if (!sample.templateId) {
    error.value = t('admin.pickTemplate')
    return
  }
  let variables: Record<string, string> = {}
  try {
    variables = JSON.parse(sample.variablesJson || '{}') as Record<string, string>
  } catch {
    error.value = t('admin.invalidJson')
    return
  }
  try {
    await api(`admin/message-templates/${sample.templateId}/sample`, {
      method: 'POST',
      body: { to: sample.to, variables },
    })
    success.value = t('admin.sampleSent')
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(load)
</script>

<template>
  <section class="panel stack">
    <h1>{{ channel === 'email' ? t('admin.nav.emailTemplates') : t('admin.nav.smsTemplates') }}</h1>
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

    <table v-if="templates.length" class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t('admin.code') }}</th>
          <th>{{ t('admin.name') }}</th>
          <th>{{ t('admin.system') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in templates" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.code }}</td>
          <td>{{ row.name }}</td>
          <td>{{ row.is_system ? 'yes' : 'no' }}</td>
        </tr>
      </tbody>
    </table>
    <p v-else class="muted">
      {{ t('admin.empty') }}
    </p>

    <h2>{{ t('admin.createTemplate') }}</h2>
    <form class="stack" @submit.prevent="createTemplate">
      <label>
        {{ t('admin.code') }}
        <input v-model="createForm.code" required>
      </label>
      <label>
        {{ t('admin.name') }}
        <input v-model="createForm.name" required>
      </label>
      <label v-if="channel === 'email'">
        {{ t('admin.subject') }}
        <input v-model="createForm.subject">
      </label>
      <label>
        {{ t('admin.body') }}
        <textarea v-model="createForm.body" required />
      </label>
      <button type="submit">
        {{ t('admin.create') }}
      </button>
    </form>

    <h2>{{ t('admin.sampleSend') }}</h2>
    <form class="stack" @submit.prevent="sendSample">
      <label>
        {{ t('admin.templateId') }}
        <input v-model.number="sample.templateId" type="number" min="1" required>
      </label>
      <label>
        {{ channel === 'email' ? t('auth.email') : t('admin.phone') }}
        <input v-model="sample.to" required>
      </label>
      <label>
        {{ t('admin.variablesJson') }}
        <textarea v-model="sample.variablesJson" />
      </label>
      <button type="submit">
        {{ t('admin.sendSample') }}
      </button>
    </form>
  </section>
</template>
