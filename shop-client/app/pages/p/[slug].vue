<script setup lang="ts">
const route = useRoute()
const slug = computed(() => String(route.params.slug))
const { t, locale } = useI18n()
const { api } = useApi()
const auth = useAuth()

type Variant = { id: number, sku: string, price: number, on_hand: number }
type Product = {
  title_en: string
  title_my: string
  description_en: string | null
  description_my: string | null
  variants: Variant[]
}

const product = ref<Product | null>(null)
const selectedVariantId = ref(0)
const quantity = ref(1)
const error = ref('')
const success = ref('')

function title(p: Product) {
  return locale.value === 'my' ? p.title_my : p.title_en
}

async function load() {
  error.value = ''
  try {
    const res = await api<{ data: Product }>(`catalog/products/${slug.value}`)
    product.value = res.data
    selectedVariantId.value = res.data.variants[0]?.id ?? 0
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function addToCart() {
  error.value = ''
  success.value = ''
  await auth.fetchMe()
  if (!auth.user.value) {
    await navigateTo(`/login?redirect=${encodeURIComponent(route.fullPath)}`)
    return
  }
  if (!selectedVariantId.value) {
    return
  }
  try {
    await api('cart', {
      method: 'PATCH',
      body: {
        items: [{ product_variant_id: selectedVariantId.value, quantity: quantity.value }],
        merge: true,
      },
    })
    success.value = t('shop.addedToCart')
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

watch(slug, load, { immediate: true })
</script>

<template>
  <section class="panel stack">
    <p>
      <NuxtLink to="/">← {{ t('nav.home') }}</NuxtLink>
    </p>
    <template v-if="product">
      <h1>{{ title(product) }}</h1>
      <p class="muted">
        {{ locale === 'my' ? product.description_my : product.description_en }}
      </p>
      <label>
        {{ t('shop.variant') }}
        <select v-model.number="selectedVariantId">
          <option v-for="variant in product.variants" :key="variant.id" :value="variant.id">
            {{ variant.sku }} · {{ variant.price }} MMK · {{ t('shop.stock') }} {{ variant.on_hand }}
          </option>
        </select>
      </label>
      <label>
        {{ t('shop.quantity') }}
        <input v-model.number="quantity" type="number" min="1">
      </label>
      <div class="actions">
        <button type="button" @click="addToCart">
          {{ t('shop.addToCart') }}
        </button>
        <NuxtLink class="button secondary" to="/cart">
          {{ t('nav.cart') }}
        </NuxtLink>
      </div>
    </template>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <p v-if="success" class="success">
      {{ success }}
    </p>
  </section>
</template>
