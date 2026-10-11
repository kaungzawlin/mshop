<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const { t } = useI18n()
const { api } = useApi()

type GroupRow = {
  group: string
  label: string
  enabled: boolean
}

const groups = ref<GroupRow[]>([])
const error = ref('')

onMounted(async () => {
  try {
    const res = await api<{ data: GroupRow[] }>('admin/settings')
    groups.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
})
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('admin.nav.settings') }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <ul v-if="groups.length" class="row-list">
      <li v-for="row in groups" :key="row.group">
        <NuxtLink :to="`/admin/settings/${row.group}`">
          {{ row.label }} ({{ row.group }}) · {{ row.enabled ? 'on' : 'off' }}
        </NuxtLink>
      </li>
    </ul>
    <p v-else class="muted">
      {{ t('admin.loading') }}
    </p>
  </section>
</template>
