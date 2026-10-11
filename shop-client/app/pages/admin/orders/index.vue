<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const { t } = useI18n()
const { api } = useApi()

type OrderRow = {
  id: number
  order_no: string
  status: string
  grand_total: number
  user: { email: string } | null
}

const rows = ref<OrderRow[]>([])
const error = ref('')
const statusDraft = reactive<Record<number, string>>({})

async function load() {
  error.value = ''
  try {
    const res = await api<{ data: OrderRow[] }>('admin/orders')
    rows.value = res.data
    for (const row of res.data) {
      statusDraft[row.id] = row.status
    }
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function updateStatus(id: number) {
  error.value = ''
  try {
    await api(`admin/orders/${id}/status`, {
      method: 'PATCH',
      body: { status: statusDraft[id] },
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
    <h1>{{ t('admin.nav.orders') }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>

    <table v-if="rows.length" class="data-table">
      <thead>
        <tr>
          <th>{{ t('admin.orderNo') }}</th>
          <th>{{ t('auth.email') }}</th>
          <th>{{ t('admin.total') }}</th>
          <th>{{ t('admin.status') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.order_no }}</td>
          <td>{{ row.user?.email || '—' }}</td>
          <td>{{ row.grand_total }}</td>
          <td>
            <div class="actions">
              <input v-model="statusDraft[row.id]">
              <button type="button" class="secondary" @click="updateStatus(row.id)">
                {{ t('admin.save') }}
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
