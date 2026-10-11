// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  runtimeConfig: {
    apiBase: process.env.NUXT_API_BASE || 'http://localhost:8000',
    authCookieName: 'mshop_token',
    public: {
      appName: 'mshop',
    },
  },
  css: ['~/assets/css/main.css'],
})
