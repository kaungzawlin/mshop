<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const { t } = useI18n()
const { api } = useApi()

type Category = {
  id: number
  name_en: string
  name_my: string
  slug: string
  is_active: boolean
}

const rows = ref<Category[]>([])
const error = ref('')
const success = ref('')

const form = reactive({
  name_en: '',
  name_my: '',
  slug: '',
  sort_order: 0,
  is_active: true,
})

async function load() {
  error.value = ''
  try {
    const res = await api<{ data: Category[] }>('admin/categories')
    rows.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function createCategory() {
  error.value = ''
  success.value = ''
  try {
    await api('admin/categories', {
      method: 'POST',
      body: { ...form },
    })
    success.value = t('admin.saved')
    form.name_en = ''
    form.name_my = ''
    form.slug = ''
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(load)
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('admin.nav.categories') }}</h1>
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
          <th>EN</th>
          <th>MY</th>
          <th>{{ t('admin.slug') }}</th>
          <th>{{ t('admin.active') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.name_en }}</td>
          <td>{{ row.name_my }}</td>
          <td>{{ row.slug }}</td>
          <td>{{ row.is_active ? 'yes' : 'no' }}</td>
        </tr>
      </tbody>
    </table>

    <h2>{{ t('admin.create') }}</h2>
    <form class="stack" @submit.prevent="createCategory">
      <label>
        name_en
        <input v-model="form.name_en" required>
      </label>
      <label>
        name_my
        <input v-model="form.name_my" required>
      </label>
      <label>
        slug
        <input v-model="form.slug" required>
      </label>
      <label>
        sort_order
        <input v-model.number="form.sort_order" type="number" min="0">
      </label>
      <label>
        <input v-model="form.is_active" type="checkbox">
        {{ t('admin.active') }}
      </label>
      <button type="submit">
        {{ t('admin.create') }}
      </button>
    </form>
  </section>
</template>
