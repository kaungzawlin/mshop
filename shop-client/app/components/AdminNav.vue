<script setup lang="ts">
const { t } = useI18n()
</script>

<template>
  <nav class="admin-nav" aria-label="Admin">
    <NuxtLink to="/admin">
      {{ t('admin.nav.dashboard') }}
    </NuxtLink>
    <NuxtLink to="/admin/settings">
      {{ t('admin.nav.settings') }}
    </NuxtLink>
    <NuxtLink to="/admin/banners">
      {{ t('admin.nav.banners') }}
    </NuxtLink>
    <NuxtLink to="/admin/docs">
      {{ t('admin.nav.apiDocs') }}
    </NuxtLink>
    <NuxtLink to="/admin/email/templates">
      {{ t('admin.nav.emailTemplates') }}
    </NuxtLink>
    <NuxtLink to="/admin/email/campaigns">
      {{ t('admin.nav.emailCampaigns') }}
    </NuxtLink>
    <NuxtLink to="/admin/email/logs">
      {{ t('admin.nav.emailLogs') }}
    </NuxtLink>
    <NuxtLink to="/admin/sms/templates">
      {{ t('admin.nav.smsTemplates') }}
    </NuxtLink>
    <NuxtLink to="/admin/sms/campaigns">
      {{ t('admin.nav.smsCampaigns') }}
    </NuxtLink>
    <NuxtLink to="/admin/sms/logs">
      {{ t('admin.nav.smsLogs') }}
    </NuxtLink>
    <NuxtLink to="/admin/catalog/categories">
      {{ t('admin.nav.categories') }}
    </NuxtLink>
    <NuxtLink to="/admin/catalog/products">
      {{ t('admin.nav.products') }}
    </NuxtLink>
    <NuxtLink to="/admin/orders">
      {{ t('admin.nav.orders') }}
    </NuxtLink>
    <NuxtLink to="/admin/resellers">
      {{ t('admin.nav.resellers') }}
    </NuxtLink>
  </nav>
</template>
