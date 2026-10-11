<script setup lang="ts">
const { t } = useI18n()
</script>

<template>
  <section class="panel stack">
    <h1>403</h1>
    <p class="error">
      {{ t('auth.noAccess') }}
    </p>
    <NuxtLink to="/">
      {{ t('nav.home') }}
    </NuxtLink>
  </section>
</template>
