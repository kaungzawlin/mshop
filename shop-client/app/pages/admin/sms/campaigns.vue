<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})
</script>

<template>
  <AdminMessageCampaigns channel="sms" />
</template>
