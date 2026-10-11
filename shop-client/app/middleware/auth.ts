export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuth()
  await auth.fetchMe()

  if (!auth.user.value) {
    return navigateTo(`/login?redirect=${encodeURIComponent(useRoute().fullPath)}`)
  }
})
