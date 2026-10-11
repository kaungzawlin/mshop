<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['rider'],
})

const { t } = useI18n()
const { api } = useApi()

type Shipment = {
  id: number
  status: string
  order_id: number
}

const shipments = ref<Shipment[]>([])
const error = ref('')
const ping = ref<{ ok?: boolean } | null>(null)

onMounted(async () => {
  try {
    ping.value = await api<{ ok: boolean }>('rider/ping')
    const res = await api<{ data: Shipment[] }>('rider/shipments')
    shipments.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
})
</script>

<template>
  <section class="panel stack">
    <h1>rider</h1>
    <p class="muted">
      {{ t('role.placeholder', { role: 'rider' }) }}
    </p>
    <p v-if="ping?.ok" class="success">
      ping ok
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>

    <h2>{{ t('rider.shipments') }}</h2>
    <table v-if="shipments.length" class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t('admin.status') }}</th>
          <th>order_id</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in shipments" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.status }}</td>
          <td>{{ row.order_id }}</td>
        </tr>
      </tbody>
    </table>
    <p v-else class="muted">
      {{ t('admin.empty') }}
    </p>
  </section>
</template>
