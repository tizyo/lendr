<template>
  <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">CRM — Lead Management</h1>
      <div class="flex gap-3">
        <button @click="activeTab = 'pipeline'" :class="tabClass('pipeline')">Pipeline</button>
        <button @click="activeTab = 'leads'" :class="tabClass('leads')">All Leads</button>
        <button @click="openCreate" class="bg-green-600 text-white rounded px-4 py-2 text-sm hover:bg-green-700">
          + New Lead
        </button>
      </div>
    </div>

    <!-- Pipeline View -->
    <div v-if="activeTab === 'pipeline'" class="grid grid-cols-5 gap-4">
      <div v-for="stage in pipeline" :key="stage.status" class="bg-white border rounded-lg p-4">
        <h3 class="font-semibold text-sm capitalize text-gray-700 mb-2">{{ stage.status }}</h3>
        <p class="text-2xl font-bold text-green-700">{{ stage.count }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ currency }} {{ formatNum(stage.amount) }}</p>
      </div>
    </div>

    <!-- Leads List -->
    <div v-if="activeTab === 'leads'" class="bg-white border rounded-lg overflow-hidden">
      <div class="p-4 border-b flex gap-3 items-end flex-wrap">
        <input v-model="filters.search" @keyup.enter="loadLeads" placeholder="Search name, phone…"
          class="border rounded px-3 py-2 text-sm w-56" />
        <select v-model="filters.status" @change="loadLeads" class="border rounded px-3 py-2 text-sm">
          <option value="">All Statuses</option>
          <option v-for="s in statuses" :key="s" :value="s" class="capitalize">{{ s }}</option>
        </select>
        <button @click="loadLeads" class="border rounded px-4 py-2 text-sm hover:bg-gray-50">Refresh</button>
      </div>

      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 text-left">Lead #</th>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Phone</th>
            <th class="px-4 py-3 text-left">Source</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Follow-up</th>
            <th class="px-4 py-3 text-left">Assigned</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="lead in leads.data" :key="lead.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-green-700">{{ lead.lead_number }}</td>
            <td class="px-4 py-3 font-medium">{{ lead.first_name }} {{ lead.last_name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ lead.phone }}</td>
            <td class="px-4 py-3 text-gray-500 capitalize">{{ lead.source ?? '—' }}</td>
            <td class="px-4 py-3">
              <span :class="statusBadge(lead.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                {{ lead.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ lead.follow_up_date ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ lead.assigned_to?.name ?? '—' }}</td>
            <td class="px-4 py-3 flex gap-2">
              <button @click="editLead(lead)" class="text-xs text-green-700 hover:underline">Edit</button>
              <button v-if="lead.status !== 'converted'" @click="convertLead(lead)"
                class="text-xs text-blue-700 hover:underline">Convert</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4">
        <h2 class="text-lg font-bold">{{ editing ? 'Edit Lead' : 'New Lead' }}</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium mb-1">First Name *</label>
            <input v-model="form.first_name" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Last Name</label>
            <input v-model="form.last_name" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Phone *</label>
            <input v-model="form.phone" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Requested Amount</label>
            <input v-model="form.requested_amount" type="number" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Source</label>
            <select v-model="form.source" class="border rounded px-3 py-2 text-sm w-full">
              <option value="">— Select —</option>
              <option v-for="s in sources" :key="s" :value="s" class="capitalize">{{ s.replace('_',' ') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Status</label>
            <select v-model="form.status" class="border rounded px-3 py-2 text-sm w-full">
              <option v-for="s in statuses" :key="s" :value="s" class="capitalize">{{ s }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium mb-1">Follow-up Date</label>
            <input v-model="form.follow_up_date" type="date" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium mb-1">Notes</label>
            <textarea v-model="form.notes" rows="2" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
        </div>
        <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="showModal = false" class="border rounded px-4 py-2 text-sm">Cancel</button>
          <button @click="saveLead" :disabled="saving" class="bg-green-600 text-white rounded px-4 py-2 text-sm hover:bg-green-700 disabled:opacity-50">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const activeTab = ref('leads')
const leads     = ref({ data: [] })
const pipeline  = ref([])
const showModal = ref(false)
const editing   = ref(null)
const saving    = ref(false)
const error     = ref('')
const currency  = ref('ZMW')

const statuses = ['new', 'contacted', 'qualified', 'converted', 'lost']
const sources  = ['walk_in', 'referral', 'social_media', 'website', 'agent', 'staff', 'campaign', 'other']

const filters = ref({ search: '', status: '' })
const form    = ref({ first_name: '', last_name: '', phone: '', requested_amount: '', source: '', status: 'new', follow_up_date: '', notes: '' })

function formatNum(n) { return Number(n).toLocaleString() }

function tabClass(tab) {
  return activeTab.value === tab
    ? 'bg-green-600 text-white rounded px-4 py-2 text-sm'
    : 'border rounded px-4 py-2 text-sm hover:bg-gray-50'
}

function statusBadge(s) {
  const map = { new: 'bg-gray-100 text-gray-700', contacted: 'bg-blue-100 text-blue-700', qualified: 'bg-yellow-100 text-yellow-700', converted: 'bg-green-100 text-green-700', lost: 'bg-red-100 text-red-700' }
  return map[s] ?? 'bg-gray-100 text-gray-700'
}

async function loadLeads() {
  const { data } = await axios.get('/api/v1/leads', { params: filters.value })
  leads.value = data.data
}

async function loadPipeline() {
  const { data } = await axios.get('/api/v1/leads/pipeline')
  pipeline.value = Object.entries(data.data).map(([status, v]) => ({ status, ...v }))
}

function openCreate() {
  editing.value = null
  form.value = { first_name: '', last_name: '', phone: '', requested_amount: '', source: '', status: 'new', follow_up_date: '', notes: '' }
  error.value = ''
  showModal.value = true
}

function editLead(lead) {
  editing.value = lead
  form.value = { first_name: lead.first_name, last_name: lead.last_name ?? '', phone: lead.phone, requested_amount: lead.requested_amount ?? '', source: lead.source ?? '', status: lead.status, follow_up_date: lead.follow_up_date ?? '', notes: lead.notes ?? '' }
  error.value = ''
  showModal.value = true
}

async function saveLead() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await axios.put(`/api/v1/leads/${editing.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/leads', form.value)
    }
    showModal.value = false
    await loadLeads()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error saving lead.'
  } finally {
    saving.value = false
  }
}

async function convertLead(lead) {
  if (!confirm(`Convert ${lead.first_name} ${lead.last_name} to a borrower?`)) return
  try {
    await axios.post(`/api/v1/leads/${lead.id}/convert`, {
      first_name: lead.first_name, last_name: lead.last_name, phone: lead.phone
    })
    await loadLeads()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Conversion failed.')
  }
}

onMounted(() => {
  loadLeads()
  loadPipeline()
})
</script>
