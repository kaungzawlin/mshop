<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const { t } = useI18n()
const { api } = useApi()

const stats = ref<{ customers?: number, orders?: number }>({})
const error = ref('')

onMounted(async () => {
  try {
    const [customers, orders] = await Promise.all([
      api<{ meta: { total: number } }>('admin/customers'),
      api<{ meta: { total: number } }>('admin/orders'),
    ])
    stats.value = { customers: customers.meta.total, orders: orders.meta.total }
  } catch (e: unknown) {
    error.value = (e as Error).message || t('auth.noAccess')
  }
})
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('admin.nav.dashboard') }}</h1>
    <p class="muted">
      {{ t('admin.dashboardHint') }}
    </p>
    <p v-if="stats.customers !== undefined" class="success">
      {{ t('admin.customers') }} · {{ stats.customers }} · {{ t('admin.nav.orders') }} · {{ stats.orders }}
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>

    <ul class="row-list">
      <li><NuxtLink to="/admin/settings">{{ t('admin.nav.settings') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/banners">{{ t('admin.nav.banners') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/docs">{{ t('admin.nav.apiDocs') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/email/templates">{{ t('admin.nav.emailTemplates') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/email/campaigns">{{ t('admin.nav.emailCampaigns') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/sms/templates">{{ t('admin.nav.smsTemplates') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/catalog/categories">{{ t('admin.nav.categories') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/catalog/products">{{ t('admin.nav.products') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/orders">{{ t('admin.nav.orders') }}</NuxtLink></li>
      <li><NuxtLink to="/admin/resellers">{{ t('admin.nav.resellers') }}</NuxtLink></li>
    </ul>
  </section>
</template>
