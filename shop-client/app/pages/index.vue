<script setup lang="ts">
const { t, locale } = useI18n()
const auth = useAuth()
const { api } = useApi()

type Category = { slug: string, name_en: string, name_my: string }
type Banner = {
  id: number
  title: string
  subtitle?: string | null
  image_url?: string | null
  link_url?: string | null
}

const categories = ref<Category[]>([])
const banners = ref<Banner[]>([])

await auth.fetchMe()

onMounted(async () => {
  try {
    const [catRes, banRes] = await Promise.all([
      api<{ data: Category[] }>('catalog/categories'),
      api<{ data: Banner[] }>(`catalog/banners?locale=${locale.value}`),
    ])
    categories.value = catRes.data
    banners.value = banRes.data
  } catch {
    categories.value = []
    banners.value = []
  }
})

const hero = computed(() => banners.value[0] || null)
</script>

<template>
  <div class="stack">
    <section v-if="hero" class="hero-banner">
      <img v-if="hero.image_url" :src="hero.image_url" :alt="hero.title">
      <div class="copy">
        <h2>{{ hero.title }}</h2>
        <p v-if="hero.subtitle" class="muted">
          {{ hero.subtitle }}
        </p>
        <div v-if="hero.link_url" class="actions" style="margin-top: 1rem">
          <a class="button" :href="hero.link_url">
            {{ t('home.shopNow') }}
          </a>
        </div>
      </div>
    </section>

    <section class="panel stack">
      <h1>{{ t('brand') }}</h1>
      <p class="muted">
        {{ t('tagline') }}
      </p>
      <div class="actions">
        <NuxtLink class="button" to="/register">
          {{ t('home.ctaRegister') }}
        </NuxtLink>
        <NuxtLink class="button secondary" to="/login">
          {{ t('home.ctaLogin') }}
        </NuxtLink>
      </div>
      <p v-if="auth.user.value" class="muted">
        {{ auth.user.value.name }} · {{ auth.user.value.role }}
      </p>
    </section>

    <section class="panel stack">
      <h2>{{ t('nav.catalog') }}</h2>
      <div v-if="categories.length" class="category-grid">
        <NuxtLink
          v-for="cat in categories"
          :key="cat.slug"
          class="category-card"
          :to="`/c/${cat.slug}`"
        >
          {{ locale === 'my' ? cat.name_my : cat.name_en }}
        </NuxtLink>
      </div>
      <p v-else class="muted">
        {{ t('admin.empty') }}
      </p>
    </section>
  </div>
</template>
