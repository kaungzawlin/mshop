<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const { t } = useI18n()
const { api } = useApi()

type Product = {
  id: number
  title_en: string
  slug: string
  listing_status: string
  is_active: boolean
}

const rows = ref<Product[]>([])
const error = ref('')
const success = ref('')

const form = reactive({
  seller_user_id: 1,
  title_en: '',
  title_my: '',
  slug: '',
  sku: '',
  price: 0,
})

async function load() {
  error.value = ''
  try {
    const res = await api<{ data: Product[] }>('admin/products')
    rows.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function createProduct() {
  error.value = ''
  success.value = ''
  try {
    await api('admin/products', {
      method: 'POST',
      body: {
        seller_user_id: form.seller_user_id,
        title_en: form.title_en,
        title_my: form.title_my,
        slug: form.slug,
        variants: [{
          sku: form.sku,
          price: form.price,
          on_hand: 0,
        }],
      },
    })
    success.value = t('admin.saved')
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function approve(id: number) {
  error.value = ''
  try {
    await api(`admin/products/${id}/approve`, { method: 'POST' })
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(load)
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('admin.nav.products') }}</h1>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <p v-if="success" class="success">
      {{ success }}
    </p>

    <table v-if="rows.length" class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>{{ t('admin.name') }}</th>
          <th>{{ t('admin.slug') }}</th>
          <th>{{ t('admin.status') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.title_en }}</td>
          <td>{{ row.slug }}</td>
          <td>{{ row.listing_status }}</td>
          <td>
            <button
              v-if="row.listing_status !== 'approved'"
              type="button"
              class="secondary"
              @click="approve(row.id)"
            >
              {{ t('admin.approve') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <h2>{{ t('admin.create') }}</h2>
    <form class="stack" @submit.prevent="createProduct">
      <label>
        seller_user_id
        <input v-model.number="form.seller_user_id" type="number" min="1" required>
      </label>
      <label>
        title_en
        <input v-model="form.title_en" required>
      </label>
      <label>
        title_my
        <input v-model="form.title_my" required>
      </label>
      <label>
        slug
        <input v-model="form.slug" required>
      </label>
      <label>
        sku
        <input v-model="form.sku" required>
      </label>
      <label>
        price (MMK)
        <input v-model.number="form.price" type="number" min="0" required>
      </label>
      <button type="submit">
        {{ t('admin.create') }}
      </button>
    </form>
  </section>
</template>
