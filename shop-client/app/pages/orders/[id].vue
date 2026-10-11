<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const route = useRoute()
const orderId = computed(() => String(route.params.id))
const { t } = useI18n()
const { api } = useApi()

type OrderDetail = {
  order_no: string
  status: string
  payment_method: string
  payment_status: string
  grand_total: number
  address_snapshot: Record<string, string> | null
  items: { title: string, quantity: number, line_total: number }[]
}

const order = ref<OrderDetail | null>(null)
const error = ref('')

watch(orderId, async () => {
  error.value = ''
  try {
    const res = await api<{ data: OrderDetail }>(`orders/${orderId.value}`)
    order.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}, { immediate: true })
</script>

<template>
  <section class="panel stack">
    <p>
      <NuxtLink to="/orders">← {{ t('nav.orders') }}</NuxtLink>
    </p>
    <template v-if="order">
      <h1>{{ order.order_no }}</h1>
      <p class="muted">
        {{ order.status }} · {{ order.payment_method }} · {{ order.payment_status }} · {{ order.grand_total }} MMK
      </p>
      <h2>{{ t('shop.address') }}</h2>
      <pre v-if="order.address_snapshot" class="muted">{{ order.address_snapshot }}</pre>
      <h2>{{ t('shop.items') }}</h2>
      <ul class="row-list">
        <li v-for="(item, idx) in order.items" :key="idx">
          {{ item.title }} × {{ item.quantity }} · {{ item.line_total }} MMK
        </li>
      </ul>
    </template>
    <p v-if="error" class="error">
      {{ error }}
    </p>
  </section>
</template>
