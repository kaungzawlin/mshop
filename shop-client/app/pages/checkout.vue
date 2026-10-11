<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const { t } = useI18n()
const { api } = useApi()

type Address = {
  id: number
  recipient_name: string
  phone: string
  region: string
  township: string
  address_line: string
  is_default: boolean
}

type Quote = {
  subtotal: number
  shipping_total: number
  discount_total: number
  grand_total: number
  payment_methods: string[]
}

const addresses = ref<Address[]>([])
const quote = ref<Quote | null>(null)
const error = ref('')
const success = ref('')

const form = reactive({
  address_id: 0,
  payment_method: '',
  points_to_redeem: 0,
  wallet_amount: 0,
})

const newAddress = reactive({
  recipient_name: '',
  phone: '',
  region: '',
  township: '',
  address_line: '',
  is_default: true,
})

async function load() {
  error.value = ''
  try {
    const [addrRes, quoteRes] = await Promise.all([
      api<{ data: Address[] }>('addresses'),
      api<{ data: Quote }>('checkout/quote', { method: 'POST' }),
    ])
    addresses.value = addrRes.data
    quote.value = quoteRes.data
    if (!form.address_id && addrRes.data[0]) {
      form.address_id = addrRes.data.find(a => a.is_default)?.id ?? addrRes.data[0].id
    }
    if (!form.payment_method && quoteRes.data.payment_methods[0]) {
      form.payment_method = quoteRes.data.payment_methods[0]
    }
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function refreshQuote() {
  try {
    const quoteRes = await api<{ data: Quote }>('checkout/quote', { method: 'POST' })
    quote.value = quoteRes.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function addAddress() {
  error.value = ''
  try {
    const res = await api<{ data: Address }>('addresses', {
      method: 'POST',
      body: { ...newAddress },
    })
    addresses.value.push(res.data)
    form.address_id = res.data.id
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function placeOrder() {
  error.value = ''
  success.value = ''
  try {
    const res = await api<{ data: { id: number, order_no: string } }>('orders', {
      method: 'POST',
      body: {
        address_id: form.address_id,
        payment_method: form.payment_method,
        points_to_redeem: form.points_to_redeem || undefined,
        wallet_amount: form.wallet_amount || undefined,
      },
    })
    success.value = `${t('shop.orderPlaced')} ${res.data.order_no}`
    await navigateTo(`/orders/${res.data.id}`)
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(load)
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('nav.checkout') }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <p v-if="success" class="success">
      {{ success }}
    </p>

    <div v-if="quote" class="stack">
      <p class="muted">
        {{ t('shop.subtotal') }} {{ quote.subtotal }} · {{ t('shop.shipping') }} {{ quote.shipping_total }} · {{ t('shop.grandTotal') }} {{ quote.grand_total }} MMK
      </p>
      <button type="button" class="secondary" @click="refreshQuote">
        {{ t('admin.refresh') }} {{ t('shop.quote') }}
      </button>
    </div>

    <h2>{{ t('shop.address') }}</h2>
    <label v-if="addresses.length">
      {{ t('shop.selectAddress') }}
      <select v-model.number="form.address_id">
        <option v-for="addr in addresses" :key="addr.id" :value="addr.id">
          {{ addr.recipient_name }} · {{ addr.township }}
        </option>
      </select>
    </label>

    <form class="stack" @submit.prevent="addAddress">
      <h3>{{ t('shop.newAddress') }}</h3>
      <label>{{ t('shop.recipient') }} <input v-model="newAddress.recipient_name" required></label>
      <label>{{ t('admin.phone') }} <input v-model="newAddress.phone" required></label>
      <label>{{ t('shop.region') }} <input v-model="newAddress.region" required></label>
      <label>{{ t('shop.township') }} <input v-model="newAddress.township" required></label>
      <label>{{ t('shop.addressLine') }} <input v-model="newAddress.address_line" required></label>
      <button type="submit" class="secondary">
        {{ t('shop.saveAddress') }}
      </button>
    </form>

    <h2>{{ t('shop.payment') }}</h2>
    <label>
      {{ t('shop.paymentMethod') }}
      <select v-model="form.payment_method">
        <option v-for="method in quote?.payment_methods || []" :key="method" :value="method">
          {{ method }}
        </option>
      </select>
    </label>
    <label>
      {{ t('shop.pointsRedeem') }}
      <input v-model.number="form.points_to_redeem" type="number" min="0">
    </label>
    <label>
      {{ t('shop.walletAmount') }}
      <input v-model.number="form.wallet_amount" type="number" min="0">
    </label>

    <button type="button" :disabled="!form.address_id || !form.payment_method" @click="placeOrder">
      {{ t('shop.placeOrder') }}
    </button>
  </section>
</template>
