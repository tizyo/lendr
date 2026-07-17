<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Outbound Webhooks</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ endpoints.length }} endpoint{{ endpoints.length !== 1 ? 's' : '' }}</p>
        </div>
        <button @click="showCreate = true" class="lendr-btn-primary">+ Add Endpoint</button>
      </div>
    </template>

    <!-- Endpoints -->
    <div class="space-y-3">
      <div v-for="ep in endpoints" :key="ep.id" class="lendr-card p-5">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span :class="ep.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'"
                    class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ ep.is_active ? 'Active' : 'Inactive' }}
              </span>
              <span v-if="ep.failure_count > 0" class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                {{ ep.failure_count }} failures
              </span>
            </div>
            <p class="font-mono text-sm font-medium truncate">{{ ep.url }}</p>
            <p class="text-xs text-neutral-400 mt-0.5">{{ ep.description }}</p>
            <div class="flex flex-wrap gap-1 mt-2">
              <span v-for="ev in ep.events" :key="ev" class="px-1.5 py-0.5 bg-neutral-100 rounded text-xs font-mono">{{ ev }}</span>
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="toggleActive(ep)" class="text-xs text-neutral-500 hover:text-neutral-700">
              {{ ep.is_active ? 'Disable' : 'Enable' }}
            </button>
            <button @click="confirmDelete(ep)" class="text-xs text-red-500 hover:text-red-700">Delete</button>
          </div>
        </div>
        <div class="mt-3 flex items-center gap-4 text-xs text-neutral-400">
          <span>Last triggered: {{ ep.last_triggered_at ?? 'Never' }}</span>
          <span>{{ ep.deliveries_count }} deliveries</span>
        </div>
      </div>

      <div v-if="!endpoints.length" class="lendr-card p-10 text-center text-neutral-400">
        No webhook endpoints configured. Add one to receive real-time event notifications.
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg">
        <h3 class="text-lg font-semibold mb-4">Add Webhook Endpoint</h3>
        <div class="space-y-3">
          <div>
            <label class="label">Endpoint URL *</label>
            <input v-model="form.url" type="url" class="lendr-input w-full" placeholder="https://your-app.com/webhook" />
          </div>
          <div>
            <label class="lendr-label">Description</label>
            <input v-model="form.description" type="text" class="lendr-input w-full" />
          </div>
          <div>
            <label class="lendr-label">Subscribe to Events *</label>
            <div class="grid grid-cols-2 gap-2 mt-1">
              <label v-for="ev in availableEvents" :key="ev" class="flex items-center gap-2 text-sm">
                <input type="checkbox" :value="ev" v-model="form.events" class="rounded" />
                <span class="font-mono text-xs">{{ ev }}</span>
              </label>
            </div>
          </div>
          <p v-if="createError" class="text-sm text-red-600">{{ createError }}</p>
        </div>
        <div class="flex gap-2 mt-4">
          <button @click="showCreate = false" class="lendr-btn-ghost flex-1">Cancel</button>
          <button @click="submitCreate" :disabled="creating" class="lendr-btn-primary flex-1">
            {{ creating ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ endpoints: Array, availableEvents: Array })

const showCreate = ref(false)
const form = ref({ url: '', description: '', events: [] })
const creating = ref(false)
const createError = ref('')

async function submitCreate() {
  creating.value = true; createError.value = ''
  try {
    await axios.post('/api/v1/webhook-endpoints', form.value)
    showCreate.value = false
    router.reload()
  } catch (e) {
    createError.value = e.response?.data?.message ?? 'Failed.'
  } finally {
    creating.value = false
  }
}

async function toggleActive(ep) {
  await axios.put(`/api/v1/webhook-endpoints/${ep.id}`, { is_active: !ep.is_active })
  router.reload()
}

async function confirmDelete(ep) {
  if (!confirm(`Delete endpoint ${ep.url}?`)) return
  await axios.delete(`/api/v1/webhook-endpoints/${ep.id}`)
  router.reload()
}
</script>
