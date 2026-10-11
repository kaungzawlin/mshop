<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})
</script>

<template>
  <AdminMessageLogs channel="sms" />
</template>
