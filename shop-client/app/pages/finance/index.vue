<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['finance'],
})

const { t } = useI18n()
const { api } = useApi()

type Payment = {
  id: number
  amount: number
  status: string
  order: { order_no: string, user: { email: string } | null } | null
}

const payments = ref<Payment[]>([])
const error = ref('')
const ping = ref<{ ok?: boolean } | null>(null)

async function load() {
  try {
    const res = await api<{ data: Payment[] }>('finance/payments/pending')
    payments.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function verify(id: number) {
  error.value = ''
  try {
    await api(`finance/payments/${id}/verify`, { method: 'POST' })
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(async () => {
  try {
    ping.value = await api<{ ok: boolean }>('finance/ping')
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
})
</script>

<template>
  <section class="panel stack">
    <h1>finance</h1>
    <p v-if="ping?.ok" class="success">
      ping ok
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>

    <h2>{{ t('finance.pendingPayments') }}</h2>
    <table v-if="payments.length" class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t('admin.orderNo') }}</th>
          <th>{{ t('auth.email') }}</th>
          <th>{{ t('admin.total') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in payments" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.order?.order_no || '—' }}</td>
          <td>{{ row.order?.user?.email || '—' }}</td>
          <td>{{ row.amount }}</td>
          <td>
            <button type="button" class="secondary" @click="verify(row.id)">
              {{ t('finance.verify') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else class="muted">
      {{ t('admin.empty') }}
    </p>
  </section>
</template>
