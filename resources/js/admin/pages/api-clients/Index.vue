<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">API Clients</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Manage Open Banking API credentials and access logs</p>
        </div>
        <button @click="openCreate" class="lendr-btn-primary">+ New Client</button>
      </div>
    </template>

    <!-- Clients Table -->
    <div class="lendr-card overflow-hidden mb-4">
      <div v-if="loading" class="p-10 text-center text-neutral-400">Loading…</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Client Name</th>
            <th class="px-4 py-3 text-left">Scopes</th>
            <th class="px-4 py-3 text-right">Rate Limit/min</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="clients.length === 0"><td colspan="5" class="px-4 py-10 text-center text-neutral-400">No API clients yet.</td></tr>
          <tr v-for="c in clients" :key="c.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3">
              <p class="font-medium">{{ c.name }}</p>
              <p class="text-xs text-neutral-400 font-mono">{{ c.client_key_preview ?? '—' }}</p>
            </td>
            <td class="px-4 py-3 text-neutral-500 text-xs">
              <span v-for="s in (c.scopes ?? [])" :key="s" class="inline-block mr-1 px-1.5 py-0.5 bg-neutral-100 rounded font-mono">{{ s }}</span>
            </td>
            <td class="px-4 py-3 text-right">{{ c.rate_limit_per_minute }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="c.is_active ? 'lendr-badge-success' : 'lendr-badge-neutral'" class="lendr-badge text-xs">{{ c.is_active ? 'Active' : 'Revoked' }}</span>
            </td>
            <td class="px-4 py-3 text-right flex gap-3 justify-end">
              <button @click="viewLogs(c)" class="text-primary-600 text-xs hover:underline">Logs</button>
              <button @click="rotateKey(c)" class="text-amber-600 text-xs hover:underline">Rotate Key</button>
              <button @click="toggleClient(c)" class="text-neutral-500 text-xs hover:underline">{{ c.is_active ? 'Revoke' : 'Enable' }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Access Logs -->
    <div v-if="logsClient" class="lendr-card overflow-hidden">
      <div class="p-4 border-b flex justify-between items-center">
        <span class="font-medium text-sm">Access Logs — {{ logsClient.name }}</span>
        <button @click="logsClient = null" class="text-neutral-400 hover:text-neutral-700 text-xs">Close</button>
      </div>
      <div v-if="loadingLogs" class="p-8 text-center text-neutral-400">Loading…</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Time</th>
            <th class="px-4 py-3 text-left">Method</th>
            <th class="px-4 py-3 text-left">Endpoint</th>
            <th class="px-4 py-3 text-right">Status</th>
            <th class="px-4 py-3 text-right">Duration (ms)</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="logs.length === 0"><td colspan="5" class="px-4 py-8 text-center text-neutral-400">No recent logs.</td></tr>
          <tr v-for="l in logs" :key="l.created_at + l.endpoint" class="hover:bg-neutral-50">
            <td class="px-4 py-3 text-xs text-neutral-500">{{ formatDate(l.created_at) }}</td>
            <td class="px-4 py-3 font-mono text-xs font-bold">{{ l.method }}</td>
            <td class="px-4 py-3 font-mono text-xs truncate max-w-xs">{{ l.endpoint }}</td>
            <td class="px-4 py-3 text-right" :class="l.status_code >= 400 ? 'text-red-600 font-semibold' : 'text-green-600'">{{ l.status_code }}</td>
            <td class="px-4 py-3 text-right text-neutral-500">{{ l.response_time_ms ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- New Key Toast -->
    <div v-if="newKey" class="fixed bottom-4 right-4 bg-neutral-900 text-white px-5 py-4 rounded-xl shadow-xl z-50 max-w-md">
      <p class="text-xs text-neutral-400 mb-1">Copy your new API key — it won't be shown again:</p>
      <p class="font-mono text-sm text-green-400 break-all select-all">{{ newKey }}</p>
      <button @click="newKey = ''" class="mt-3 text-xs text-neutral-400 hover:text-white">Dismiss</button>
    </div>

    <!-- Create Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="font-bold text-lg">New API Client</h2>
        <div class="space-y-3">
          <div><label class="lendr-label">Client Name *</label><input v-model="form.name" class="lendr-input w-full" /></div>
          <div>
            <label class="lendr-label">Allowed Scopes</label>
            <div class="grid grid-cols-2 gap-2 mt-1">
              <label v-for="scope in availableScopes" :key="scope" class="flex items-center gap-2 text-sm">
                <input type="checkbox" :value="scope" v-model="form.scopes" class="rounded" />
                <span class="font-mono text-xs">{{ scope }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="lendr-label">Rate Limit (requests/minute)</label>
            <input v-model.number="form.rate_limit_per_minute" type="number" min="1" max="1000" class="lendr-input w-full" placeholder="60" />
          </div>
        </div>
        <p v-if="formError" class="text-red-500 text-sm">{{ formError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="createClient" :disabled="saving" class="lendr-btn-primary">{{ saving ? 'Creating…' : 'Create' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const availableScopes = ['loan_apply', 'loan_status', 'payment_initiate', 'products_read']

const clients = ref([])
const loading = ref(false)
const logsClient = ref(null)
const logs = ref([])
const loadingLogs = ref(false)
const newKey = ref('')

const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', scopes: ['products_read'], rate_limit_per_minute: 60 })

async function fetchClients() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/api-clients')
    clients.value = data.data ?? []
  } finally { loading.value = false }
}

async function viewLogs(c) {
  logsClient.value = c
  loadingLogs.value = true
  logs.value = []
  try {
    const { data } = await axios.get(`/api/v1/api-clients/${c.id}/logs`)
    logs.value = data.data?.logs ?? []
  } finally { loadingLogs.value = false }
}

async function rotateKey(c) {
  if (!confirm(`Rotate key for "${c.name}"? The old key will stop working immediately.`)) return
  try {
    const { data } = await axios.post(`/api/v1/api-clients/${c.id}/rotate-key`)
    newKey.value = data.data?.client_key ?? ''
    await fetchClients()
  } catch {}
}

async function toggleClient(c) {
  try {
    await axios.put(`/api/v1/api-clients/${c.id}`, { is_active: !c.is_active })
    await fetchClients()
  } catch {}
}

function openCreate() {
  form.value = { name: '', scopes: ['products_read'], rate_limit_per_minute: 60 }
  formError.value = ''
  showModal.value = true
}

async function createClient() {
  formError.value = ''
  saving.value = true
  try {
    const { data } = await axios.post('/api/v1/api-clients', {
      name: form.value.name,
      scopes: form.value.scopes,
      rate_limit_per_minute: form.value.rate_limit_per_minute,
    })
    newKey.value = data.data?.client?.client_key ?? ''
    await fetchClients()
    showModal.value = false
  } catch (e) { formError.value = e.response?.data?.message ?? 'Failed.' } finally { saving.value = false }
}

function formatDate(d) { return d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—' }

onMounted(() => fetchClients())
</script>
