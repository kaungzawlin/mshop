<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const { t } = useI18n()
const { api } = useApi()

type ResellerRow = {
  id: number
  shop_name: string
  status: string
  user: { email: string, name: string } | null
}

const rows = ref<ResellerRow[]>([])
const error = ref('')
const suspendReason = reactive<Record<number, string>>({})

async function load() {
  error.value = ''
  try {
    const res = await api<{ data: ResellerRow[] }>('admin/resellers')
    rows.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function approve(id: number) {
  error.value = ''
  try {
    await api(`admin/resellers/${id}/approve`, { method: 'POST' })
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function suspend(id: number) {
  error.value = ''
  try {
    await api(`admin/resellers/${id}/suspend`, {
      method: 'POST',
      body: { reason: suspendReason[id] || 'Suspended by admin' },
    })
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(load)
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('admin.nav.resellers') }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>

    <table v-if="rows.length" class="data-table">
      <thead>
        <tr>
          <th>{{ t('admin.shop') }}</th>
          <th>{{ t('auth.email') }}</th>
          <th>{{ t('admin.status') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.shop_name }}</td>
          <td>{{ row.user?.email || '—' }}</td>
          <td>{{ row.status }}</td>
          <td>
            <div class="actions">
              <button type="button" class="secondary" @click="approve(row.id)">
                {{ t('admin.approve') }}
              </button>
              <input v-model="suspendReason[row.id]" :placeholder="t('admin.reason')">
              <button type="button" class="secondary" @click="suspend(row.id)">
                {{ t('admin.suspend') }}
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else class="muted">
      {{ t('admin.empty') }}
    </p>
  </section>
</template>
