<script setup lang="ts">
const route = useRoute()
const slug = computed(() => String(route.params.slug))
const { t, locale } = useI18n()
const { api } = useApi()

type Category = { id: number, slug: string, name_en: string, name_my: string }
type Product = { id: number, slug: string, title_en: string, title_my: string, variants: { price: number }[] }

const category = ref<Category | null>(null)
const products = ref<Product[]>([])
const error = ref('')

function title(product: Product) {
  return locale.value === 'my' ? product.title_my : product.title_en
}

async function load() {
  error.value = ''
  try {
    const cats = await api<{ data: Category[] }>('catalog/categories')
    category.value = cats.data.find(c => c.slug === slug.value) || null
    if (!category.value) {
      error.value = t('shop.notFound')
      return
    }
    const res = await api<{ data: Product[] }>('catalog/products', {
      query: { category_id: category.value.id },
    })
    products.value = res.data
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
    <h1>{{ category ? (locale === 'my' ? category.name_my : category.name_en) : slug }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <ul v-if="products.length" class="row-list">
      <li v-for="product in products" :key="product.id">
        <NuxtLink :to="`/p/${product.slug}`">
          {{ title(product) }}
        </NuxtLink>
        <span class="muted" v-if="product.variants[0]"> · {{ product.variants[0].price }} MMK</span>
      </li>
    </ul>
    <p v-else-if="!error" class="muted">
      {{ t('admin.empty') }}
    </p>
  </section>
</template>
