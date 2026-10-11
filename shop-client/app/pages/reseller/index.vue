<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['reseller'],
})

const { t } = useI18n()
const { api } = useApi()

type Listing = {
  id: number
  title_en: string
  slug: string
  listing_status: string
}

const listings = ref<Listing[]>([])
const error = ref('')
const ping = ref<{ ok?: boolean } | null>(null)

onMounted(async () => {
  try {
    ping.value = await api<{ ok: boolean }>('reseller/ping')
    const res = await api<{ data: Listing[] }>('reseller/listings')
    listings.value = res.data
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
})
</script>

<template>
  <section class="panel stack">
    <h1>reseller</h1>
    <p class="muted">
      {{ t('role.placeholder', { role: 'reseller' }) }}
    </p>
    <p v-if="ping?.ok" class="success">
      ping ok
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>

    <h2>{{ t('reseller.listings') }}</h2>
    <ul v-if="listings.length" class="row-list">
      <li v-for="row in listings" :key="row.id">
        {{ row.title_en }} ({{ row.slug }}) · {{ row.listing_status }}
      </li>
    </ul>
    <p v-else class="muted">
      {{ t('admin.empty') }}
    </p>
  </section>
</template>
