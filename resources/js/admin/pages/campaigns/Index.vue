<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Campaigns</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Create and manage SMS / Email marketing campaigns</p>
        </div>
        <button @click="openCreate" class="lendr-btn-primary">+ New Campaign</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="flex gap-2 mb-4">
      <select v-model="filterStatus" @change="fetchCampaigns" class="lendr-input text-sm w-40">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="scheduled">Scheduled</option>
        <option value="sending">Sending</option>
        <option value="sent">Sent</option>
        <option value="failed">Failed</option>
      </select>
      <select v-model="filterType" @change="fetchCampaigns" class="lendr-input text-sm w-32">
        <option value="">All Types</option>
        <option value="sms">SMS</option>
        <option value="email">Email</option>
      </select>
    </div>

    <!-- Campaigns Table -->
    <div class="lendr-card overflow-hidden">
      <div v-if="loading" class="p-10 text-center text-neutral-400">Loading…</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Campaign</th>
            <th class="px-4 py-3 text-left">Channel</th>
            <th class="px-4 py-3 text-right">Recipients</th>
            <th class="px-4 py-3 text-right">Sent</th>
            <th class="px-4 py-3 text-right">Opens</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="campaigns.length === 0"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">No campaigns found.</td></tr>
          <tr v-for="c in campaigns" :key="c.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3">
              <p class="font-medium">{{ c.name }}</p>
              <p class="text-xs text-neutral-500 truncate max-w-xs">{{ c.subject ?? '' }}</p>
            </td>
            <td class="px-4 py-3">
              <span class="lendr-badge lendr-badge-neutral text-xs capitalize">{{ c.type }}</span>
            </td>
            <td class="px-4 py-3 text-right">{{ c.total_recipients ?? 0 }}</td>
            <td class="px-4 py-3 text-right">{{ c.sent_count ?? 0 }}</td>
            <td class="px-4 py-3 text-right">{{ c.opened_count ?? 0 }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="statusClass(c.status)" class="lendr-badge text-xs capitalize">{{ c.status }}</span>
            </td>
            <td class="px-4 py-3 text-right flex gap-2 justify-end">
              <button v-if="c.status === 'draft'" @click="dispatch(c)" :disabled="dispatching === c.id"
                class="text-green-600 text-xs hover:underline">
                {{ dispatching === c.id ? 'Sending…' : 'Dispatch' }}
              </button>
              <button @click="viewStats(c)" class="text-primary-600 text-xs hover:underline">Stats</button>
              <button v-if="c.status === 'draft'" @click="editCampaign(c)" class="text-neutral-500 text-xs hover:underline">Edit</button>
            </td>
          </tr>
        </tbody>
      </table>
      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="p-4 border-t flex justify-between items-center text-sm text-neutral-500">
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <div class="flex gap-2">
          <button @click="page--; fetchCampaigns()" :disabled="meta.current_page <= 1" class="lendr-btn-ghost text-xs px-3 py-1">Prev</button>
          <button @click="page++; fetchCampaigns()" :disabled="meta.current_page >= meta.last_page" class="lendr-btn-ghost text-xs px-3 py-1">Next</button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="font-bold text-lg">{{ editingCampaign ? 'Edit' : 'New' }} Campaign</h2>
        <div class="space-y-3">
          <div>
            <label class="lendr-label">Campaign Name *</label>
            <input v-model="form.name" class="lendr-input w-full" placeholder="e.g. March Payment Reminder" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="lendr-label">Channel</label>
              <select v-model="form.type" class="lendr-input w-full">
                <option value="sms">SMS</option>
                <option value="email">Email</option>
              </select>
            </div>
            <div>
              <label class="lendr-label">Target Audience</label>
              <select v-model="form.target_segment" class="lendr-input w-full">
                <option value="all_borrowers">All Borrowers</option>
                <option value="active_borrowers">Active Borrowers</option>
                <option value="overdue_borrowers">Overdue Borrowers</option>
                <option value="custom">Custom List</option>
              </select>
            </div>
          </div>
          <div v-if="form.type === 'email'">
            <label class="lendr-label">Subject</label>
            <input v-model="form.subject" class="lendr-input w-full" />
          </div>
          <div>
            <label class="lendr-label">{{ form.type === 'email' ? 'Body' : 'Message' }} *</label>
            <textarea v-model="form.content" class="lendr-input w-full" rows="4"
              :placeholder="form.type === 'sms' ? 'Dear {name}, your loan payment of K{amount} is due…' : 'Email body…'"></textarea>
            <p class="text-xs text-neutral-400 mt-1">Variables: {name}, {amount}, {loan_number}, {due_date}</p>
          </div>
          <div>
            <label class="lendr-label">Scheduled At (optional)</label>
            <input v-model="form.scheduled_at" type="datetime-local" class="lendr-input w-full" />
          </div>
        </div>
        <p v-if="formError" class="text-red-500 text-sm">{{ formError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="saveCampaign" :disabled="saving" class="lendr-btn-primary">{{ saving ? 'Saving…' : editingCampaign ? 'Update' : 'Create' }}</button>
        </div>
      </div>
    </div>

    <!-- Stats Modal -->
    <div v-if="statsModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-bold text-lg">Campaign Stats — {{ statsModal.name }}</h2>
          <button @click="statsModal = null" class="text-neutral-400 hover:text-neutral-700 text-xl">&times;</button>
        </div>
        <div v-if="loadingStats" class="py-8 text-center text-neutral-400">Loading…</div>
        <div v-else-if="stats" class="grid grid-cols-2 gap-4">
          <div class="lendr-card p-4"><p class="text-xs text-neutral-500">Recipients</p><p class="text-2xl font-bold mt-1">{{ stats.total_recipients ?? 0 }}</p></div>
          <div class="lendr-card p-4"><p class="text-xs text-neutral-500">Sent</p><p class="text-2xl font-bold mt-1 text-green-600">{{ stats.sent ?? 0 }}</p></div>
          <div class="lendr-card p-4"><p class="text-xs text-neutral-500">Opens</p><p class="text-2xl font-bold mt-1">{{ stats.opened ?? 0 }}</p></div>
          <div class="lendr-card p-4"><p class="text-xs text-neutral-500">Open Rate</p><p class="text-2xl font-bold mt-1">{{ stats.open_rate ?? 0 }}%</p></div>
          <div class="lendr-card p-4 col-span-2"><p class="text-xs text-neutral-500">Failed</p><p class="text-2xl font-bold mt-1 text-red-500">{{ stats.failed ?? 0 }}</p></div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const campaigns = ref([])
const loading = ref(false)
const meta = ref(null)
const page = ref(1)
const filterStatus = ref('')
const filterType = ref('')
const dispatching = ref(null)

const showModal = ref(false)
const editingCampaign = ref(null)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', type: 'sms', target_segment: 'all_borrowers', subject: '', content: '', scheduled_at: '' })

const statsModal = ref(null)
const stats = ref(null)
const loadingStats = ref(false)

async function fetchCampaigns() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/campaigns', {
      params: { status: filterStatus.value || undefined, type: filterType.value || undefined, page: page.value }
    })
    campaigns.value = data.data ?? []
    meta.value = data.meta ?? null
  } finally { loading.value = false }
}

function openCreate() {
  editingCampaign.value = null
  form.value = { name: '', type: 'sms', target_segment: 'all_borrowers', subject: '', content: '', scheduled_at: '' }
  formError.value = ''
  showModal.value = true
}

function editCampaign(c) {
  editingCampaign.value = c
  form.value = { name: c.name, type: c.type, target_segment: c.target_segment, subject: c.subject ?? '', content: '', scheduled_at: c.scheduled_at ?? '' }
  formError.value = ''
  showModal.value = true
}

async function saveCampaign() {
  formError.value = ''
  saving.value = true
  const payload = { ...form.value, scheduled_at: form.value.scheduled_at || null }
  try {
    if (editingCampaign.value) {
      await axios.put(`/api/v1/campaigns/${editingCampaign.value.id}`, payload)
    } else {
      await axios.post('/api/v1/campaigns', payload)
    }
    await fetchCampaigns()
    showModal.value = false
  } catch (e) { formError.value = e.response?.data?.message ?? 'Failed.' } finally { saving.value = false }
}

async function dispatch(c) {
  dispatching.value = c.id
  try {
    await axios.post(`/api/v1/campaigns/${c.id}/dispatch`)
    await fetchCampaigns()
  } finally { dispatching.value = null }
}

async function viewStats(c) {
  statsModal.value = c
  loadingStats.value = true
  stats.value = null
  try {
    const { data } = await axios.get(`/api/v1/campaigns/${c.id}/stats`)
    stats.value = data.data ?? null
  } finally { loadingStats.value = false }
}

function statusClass(s) {
  const map = { draft: 'lendr-badge-neutral', scheduled: 'lendr-badge-warning', sending: 'lendr-badge-warning', sent: 'lendr-badge-success', failed: 'lendr-badge-danger' }
  return map[s] ?? 'lendr-badge-neutral'
}

onMounted(() => fetchCampaigns())
</script>
