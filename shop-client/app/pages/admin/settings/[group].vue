<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const route = useRoute()
const group = computed(() => String(route.params.group))
const { t } = useI18n()
const { api } = useApi()

type FieldDef = { type: string, secret?: boolean }
type SettingsPayload = {
  label: string
  fields: Record<string, FieldDef>
  values: Record<string, string | number | boolean | null>
}

const payload = ref<SettingsPayload | null>(null)
const form = reactive<Record<string, string | number | boolean>>({})
const error = ref('')
const success = ref('')
const testResult = ref('')

async function load() {
  error.value = ''
  try {
    const res = await api<SettingsPayload & { group: string }>(`admin/settings/${group.value}`)
    payload.value = {
      label: res.label,
      fields: res.fields,
      values: res.values,
    }
    for (const [key, value] of Object.entries(res.values)) {
      form[key] = value as string | number | boolean
    }
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function save() {
  error.value = ''
  success.value = ''
  try {
    const body: Record<string, unknown> = {}
    if (!payload.value) {
      return
    }
    for (const key of Object.keys(payload.value.fields)) {
      if (key in form) {
        body[key] = form[key]
      }
    }
    await api(`admin/settings/${group.value}`, { method: 'PUT', body })
    success.value = t('admin.saved')
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function testConnection() {
  error.value = ''
  testResult.value = ''
  try {
    const res = await api<{ ok: boolean, message: string }>(`admin/settings/${group.value}/test`, {
      method: 'POST',
      body: { ...form },
    })
    testResult.value = res.message
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

watch(group, load, { immediate: true })
</script>

<template>
  <section class="panel stack">
    <p>
      <NuxtLink to="/admin/settings">← {{ t('admin.nav.settings') }}</NuxtLink>
    </p>
    <h1>{{ payload?.label || group }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <p v-if="success" class="success">
      {{ success }}
    </p>
    <p v-if="testResult" class="muted">
      {{ testResult }}
    </p>

    <form v-if="payload" class="stack" @submit.prevent="save">
      <label v-for="(field, key) in payload.fields" :key="key">
        {{ key }}
        <input
          v-if="field.type === 'boolean'"
          type="checkbox"
          :checked="Boolean(form[key])"
          @change="form[key] = ($event.target as HTMLInputElement).checked"
        >
        <input
          v-else-if="field.type === 'integer'"
          v-model.number="form[key]"
          type="number"
        >
        <input
          v-else
          v-model="form[key]"
          type="text"
          :autocomplete="field.secret ? 'off' : undefined"
        >
      </label>
      <div class="actions">
        <button type="submit">
          {{ t('admin.save') }}
        </button>
        <button type="button" class="secondary" @click="testConnection">
          {{ t('admin.test') }}
        </button>
      </div>
    </form>
  </section>
</template>
