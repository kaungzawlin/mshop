export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuth()
  await auth.fetchMe()

  if (!auth.user.value) {
    return navigateTo(`/login?redirect=${encodeURIComponent(to.fullPath)}`)
  }

  const allowed = (to.meta.roles as string[] | undefined) || []
  if (allowed.length && !allowed.includes(auth.user.value.role)) {
    return navigateTo('/forbidden')
  }
})
