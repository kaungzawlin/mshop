<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const { t } = useI18n()
const { api } = useApi()

type OrderRow = {
  id: number
  order_no: string
  status: string
  grand_total: number
  created_at: string | null
}

const rows = ref<OrderRow[]>([])
const error = ref('')

onMounted(async () => {
  try {
    const res = await api<{ data: OrderRow[] }>('orders')
    rows.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
})
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('nav.orders') }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <ul v-if="rows.length" class="row-list">
      <li v-for="row in rows" :key="row.id">
        <NuxtLink :to="`/orders/${row.id}`">
          {{ row.order_no }} · {{ row.status }} · {{ row.grand_total }} MMK
        </NuxtLink>
      </li>
    </ul>
    <p v-else class="muted">
      {{ t('admin.empty') }}
    </p>
  </section>
</template>
