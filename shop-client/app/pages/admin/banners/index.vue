<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const { t } = useI18n()
const { api } = useApi()

type Banner = {
  id: number
  title: string
  subtitle?: string | null
  image_url?: string | null
  link_url?: string | null
  locale?: string | null
  sort_order: number
  is_active: boolean
  starts_at?: string | null
  ends_at?: string | null
}

const rows = ref<Banner[]>([])
const error = ref('')
const success = ref('')
const form = reactive({
  title: '',
  subtitle: '',
  image_url: '',
  link_url: '',
  locale: '',
  sort_order: 0,
  is_active: true,
  starts_at: '',
  ends_at: '',
})

async function load() {
  error.value = ''
  try {
    const res = await api<{ data: Banner[] }>('admin/banners')
    rows.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function createBanner() {
  error.value = ''
  success.value = ''
  try {
    await api('admin/banners', {
      method: 'POST',
      body: {
        title: form.title,
        subtitle: form.subtitle || null,
        image_url: form.image_url || null,
        link_url: form.link_url || null,
        locale: form.locale || null,
        sort_order: Number(form.sort_order) || 0,
        is_active: form.is_active,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
      },
    })
    success.value = t('admin.saved')
    form.title = ''
    form.subtitle = ''
    form.image_url = ''
    form.link_url = ''
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

async function removeBanner(id: number) {
  error.value = ''
  try {
    await api(`admin/banners/${id}`, { method: 'DELETE' })
    await load()
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}

onMounted(load)
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('admin.nav.banners') }}</h1>
    <p class="muted">
      {{ t('admin.bannersHint') }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <p v-if="success" class="success">
      {{ success }}
    </p>

    <form class="stack" @submit.prevent="createBanner">
      <label>
        {{ t('admin.name') }}
        <input v-model="form.title" required>
      </label>
      <label>
        Subtitle
        <input v-model="form.subtitle">
      </label>
      <label>
        Image URL
        <input v-model="form.image_url">
      </label>
      <label>
        Link URL
        <input v-model="form.link_url">
      </label>
      <label>
        Locale
        <select v-model="form.locale">
          <option value="">
            Both
          </option>
          <option value="my">
            MY
          </option>
          <option value="en">
            EN
          </option>
        </select>
      </label>
      <label>
        Starts at
        <input v-model="form.starts_at" type="datetime-local">
      </label>
      <label>
        Ends at
        <input v-model="form.ends_at" type="datetime-local">
      </label>
      <label>
        Sort
        <input v-model.number="form.sort_order" type="number" min="0">
      </label>
      <label>
        <span>Active</span>
        <input v-model="form.is_active" type="checkbox">
      </label>
      <button type="submit">
        {{ t('admin.create') }}
      </button>
    </form>

    <table v-if="rows.length" class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Schedule</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.id }}</td>
          <td>
            {{ row.title }}
            <div class="muted">
              {{ row.is_active ? 'active' : 'off' }} · {{ row.locale || 'both' }}
            </div>
          </td>
          <td class="muted">
            {{ row.starts_at || '—' }} → {{ row.ends_at || '—' }}
          </td>
          <td>
            <button type="button" class="secondary" @click="removeBanner(row.id)">
              Delete
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
