<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['admin'],
  layout: 'admin',
})

const { t } = useI18n()

useHead({
  link: [{ rel: 'stylesheet', href: 'https://unpkg.com/swagger-ui-dist@5/swagger-ui.css' }],
})

onMounted(() => {
  const script = document.createElement('script')
  script.src = 'https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js'
  script.onload = () => {
    // @ts-expect-error CDN global
    window.SwaggerUIBundle({
      url: '/api/admin/openapi.json',
      dom_id: '#swagger-ui',
      deepLinking: true,
      persistAuthorization: true,
    })
  }
  document.body.appendChild(script)
})
</script>

<template>
  <section class="panel stack">
    <h1>{{ t('admin.nav.apiDocs') }}</h1>
    <p class="muted">
      {{ t('admin.apiDocsHint') }}
    </p>
    <div class="actions">
      <a class="button secondary" href="/api/admin/openapi.json" target="_blank" rel="noopener">
        openapi.json
      </a>
      <a class="button secondary" href="/docs/mshop-api-guide.docx" download>
        {{ t('admin.downloadDocx') }}
      </a>
    </div>
    <div id="swagger-ui" class="swagger-host" />
  </section>
</template>

<style scoped>
.swagger-host {
  background: #fff;
  border-radius: 14px;
  overflow: auto;
  min-height: 60vh;
}
</style>
