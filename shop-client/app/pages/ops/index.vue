<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
  roles: ['ops'],
})

const { t } = useI18n()
const { api } = useApi()

const error = ref('')
const success = ref('')
const ping = ref<{ ok?: boolean } | null>(null)

const assignForm = reactive({
  shipment_id: 0,
  rider_id: 0,
  note: '',
})

onMounted(async () => {
  try {
    ping.value = await api<{ ok: boolean }>('ops/ping')
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
})

async function assignShipment() {
  error.value = ''
  success.value = ''
  try {
    await api(`ops/shipments/${assignForm.shipment_id}/assign`, {
      method: 'POST',
      body: {
        rider_id: assignForm.rider_id,
        note: assignForm.note || undefined,
      },
    })
    success.value = t('ops.assigned')
  } catch (e: unknown) {
    error.value = (e as Error).message
  }
}
</script>

<template>
  <section class="panel stack">
    <h1>ops</h1>
    <p class="muted">
      {{ t('ops.hint') }}
    </p>
    <p v-if="ping?.ok" class="success">
      ping ok
    </p>
    <p v-if="error" class="error">
      {{ error }}
    </p>
    <p v-if="success" class="success">
      {{ success }}
    </p>

    <h2>{{ t('ops.assignShipment') }}</h2>
    <form class="stack" @submit.prevent="assignShipment">
      <label>
        shipment_id
        <input v-model.number="assignForm.shipment_id" type="number" min="1" required>
      </label>
      <label>
        rider_id (user)
        <input v-model.number="assignForm.rider_id" type="number" min="1" required>
      </label>
      <label>
        note
        <input v-model="assignForm.note">
      </label>
      <button type="submit">
        {{ t('ops.assign') }}
      </button>
    </form>
  </section>
</template>
