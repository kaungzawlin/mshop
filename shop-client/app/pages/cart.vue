<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const { t, locale } = useI18n()
const { api } = useApi()

type CartItem = {
  id: number
  product_variant_id: number
  quantity: number
  line_total: number
  product: { title_en: string, title_my: string } | null
  variant: { sku: string, price: number } | null
}

type Cart = {
  items: CartItem[]
  subtotal: number
  item_count: number
}

const cart = ref<Cart | null>(null)
const error = ref('')

function title(item: CartItem) {
  if (!item.product) {
    return item.variant?.sku || '—'
  }
  return locale.value === 'my' ? item.product.title_my : item.product.title_en
}

async function load() {
  error.value = ''
  try {
    const res = await api<{ data: Cart }>('cart')
    cart.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function updateLine(item: CartItem, quantity: number) {
  error.value = ''
  try {
    const res = await api<{ data: Cart }>('cart', {
      method: 'PATCH',
      body: {
        items: [{ product_variant_id: item.product_variant_id, quantity }],
        merge: false,
      },
    })
    cart.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(load)
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('nav.cart') }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>

    <table v-if="cart?.items.length" class="data-table">
      <thead>
        <tr>
          <th>{{ t('admin.name') }}</th>
          <th>{{ t('shop.quantity') }}</th>
          <th>{{ t('admin.total') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in cart.items" :key="item.id">
          <td>{{ title(item) }}</td>
          <td>
            <input
              :value="item.quantity"
              type="number"
              min="0"
              @change="updateLine(item, Number(($event.target as HTMLInputElement).value))"
            >
          </td>
          <td>{{ item.line_total }} MMK</td>
        </tr>
      </tbody>
    </table>
    <p v-else class="muted">
      {{ t('shop.cartEmpty') }}
    </p>

    <p v-if="cart" class="muted">
      {{ t('shop.subtotal') }} · {{ cart.subtotal }} MMK · {{ cart.item_count }} {{ t('shop.items') }}
    </p>

    <div class="actions">
      <button type="button" class="secondary" @click="load">
        {{ t('admin.refresh') }}
      </button>
      <NuxtLink class="button" to="/checkout">
        {{ t('nav.checkout') }}
      </NuxtLink>
    </div>
  </section>
</template>
