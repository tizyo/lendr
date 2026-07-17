<template>
  <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">Agent / DSA Network</h1>
      <div class="flex gap-3">
        <button @click="activeTab = 'agents'" :class="tabClass('agents')">Agents</button>
        <button @click="activeTab = 'commissions'; loadCommissions()" :class="tabClass('commissions')">Commissions</button>
        <button @click="openCreate" class="bg-green-600 text-white rounded px-4 py-2 text-sm hover:bg-green-700">
          + New Agent
        </button>
      </div>
    </div>

    <!-- Agents Tab -->
    <div v-if="activeTab === 'agents'" class="bg-white border rounded-lg overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 text-left">Agent #</th>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Phone</th>
            <th class="px-4 py-3 text-left">Commission</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="a in agents.data" :key="a.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-green-700">{{ a.agent_number }}</td>
            <td class="px-4 py-3 font-medium">{{ a.first_name }} {{ a.last_name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ a.phone }}</td>
            <td class="px-4 py-3 text-gray-600">
              {{ a.commission_type === 'fixed' ? currency + ' ' + a.fixed_commission : a.commission_rate + '%' }}
            </td>
            <td class="px-4 py-3">
              <span :class="statusBadge(a.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ a.status }}</span>
            </td>
            <td class="px-4 py-3">
              <button @click="editAgent(a)" class="text-xs text-green-700 hover:underline">Edit</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Commissions Tab -->
    <div v-if="activeTab === 'commissions'" class="bg-white border rounded-lg overflow-hidden">
      <div class="p-4 border-b">
        <select v-model="commFilter" @change="loadCommissions" class="border rounded px-3 py-2 text-sm">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="paid">Paid</option>
          <option value="reversed">Reversed</option>
        </select>
      </div>
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 text-left">Agent</th>
            <th class="px-4 py-3 text-left">Loan #</th>
            <th class="px-4 py-3 text-right">Disbursed</th>
            <th class="px-4 py-3 text-right">Commission</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="c in commissions.data" :key="c.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ c.agent?.first_name }} {{ c.agent?.last_name }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ c.loan?.loan_number }}</td>
            <td class="px-4 py-3 text-right">{{ currency }} {{ formatNum(c.disbursed_amount) }}</td>
            <td class="px-4 py-3 text-right font-semibold text-green-700">{{ currency }} {{ formatNum(c.commission_amount) }}</td>
            <td class="px-4 py-3">
              <span :class="commBadge(c.status)" class="px-2 py-0.5 rounded-full text-xs capitalize">{{ c.status }}</span>
            </td>
            <td class="px-4 py-3 flex gap-2">
              <button v-if="c.status === 'pending'" @click="approveComm(c)" class="text-xs text-blue-700 hover:underline">Approve</button>
              <button v-if="c.status === 'approved'" @click="payComm(c)" class="text-xs text-green-700 hover:underline">Pay</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4">
        <h2 class="text-lg font-bold">{{ editing ? 'Edit Agent' : 'New Agent' }}</h2>
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
            <label class="block text-xs font-medium mb-1">Commission Type</label>
            <select v-model="form.commission_type" class="border rounded px-3 py-2 text-sm w-full">
              <option value="percentage">Percentage</option>
              <option value="fixed">Fixed</option>
            </select>
          </div>
          <div v-if="form.commission_type === 'percentage'">
            <label class="block text-xs font-medium mb-1">Commission Rate (%)</label>
            <input v-model="form.commission_rate" type="number" step="0.01" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div v-else>
            <label class="block text-xs font-medium mb-1">Fixed Amount</label>
            <input v-model="form.fixed_commission" type="number" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Status</label>
            <select v-model="form.status" class="border rounded px-3 py-2 text-sm w-full">
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
              <option value="terminated">Terminated</option>
            </select>
          </div>
        </div>
        <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="showModal = false" class="border rounded px-4 py-2 text-sm">Cancel</button>
          <button @click="saveAgent" :disabled="saving" class="bg-green-600 text-white rounded px-4 py-2 text-sm hover:bg-green-700 disabled:opacity-50">
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

const activeTab  = ref('agents')
const agents     = ref({ data: [] })
const commissions = ref({ data: [] })
const commFilter = ref('')
const showModal  = ref(false)
const editing    = ref(null)
const saving     = ref(false)
const error      = ref('')
const currency   = ref('ZMW')

const form = ref({ first_name: '', last_name: '', phone: '', commission_type: 'percentage', commission_rate: 5, fixed_commission: 0, status: 'active' })

function formatNum(n) { return Number(n).toLocaleString() }
function tabClass(t) { return activeTab.value === t ? 'bg-green-600 text-white rounded px-4 py-2 text-sm' : 'border rounded px-4 py-2 text-sm hover:bg-gray-50' }
function statusBadge(s) { return { active: 'bg-green-100 text-green-700', suspended: 'bg-yellow-100 text-yellow-700', terminated: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100 text-gray-600' }
function commBadge(s) { return { pending: 'bg-gray-100 text-gray-600', approved: 'bg-blue-100 text-blue-700', paid: 'bg-green-100 text-green-700', reversed: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100 text-gray-600' }

async function loadAgents() {
  const { data } = await axios.get('/api/v1/agents')
  agents.value = data.data
}

async function loadCommissions() {
  const { data } = await axios.get('/api/v1/agents/commissions', { params: { status: commFilter.value } })
  commissions.value = data.data
}

function openCreate() {
  editing.value = null
  form.value = { first_name: '', last_name: '', phone: '', commission_type: 'percentage', commission_rate: 5, fixed_commission: 0, status: 'active' }
  error.value = ''; showModal.value = true
}

function editAgent(a) {
  editing.value = a
  form.value = { first_name: a.first_name, last_name: a.last_name ?? '', phone: a.phone, commission_type: a.commission_type, commission_rate: a.commission_rate, fixed_commission: a.fixed_commission, status: a.status }
  error.value = ''; showModal.value = true
}

async function saveAgent() {
  saving.value = true; error.value = ''
  try {
    if (editing.value) {
      await axios.put(`/api/v1/agents/${editing.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/agents', form.value)
    }
    showModal.value = false; await loadAgents()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error saving.'
  } finally { saving.value = false }
}

async function approveComm(c) {
  await axios.post(`/api/v1/agent-commissions/${c.id}/approve`)
  await loadCommissions()
}

async function payComm(c) {
  const date = prompt('Payment date (YYYY-MM-DD):', new Date().toISOString().slice(0, 10))
  if (!date) return
  await axios.post(`/api/v1/agent-commissions/${c.id}/pay`, { paid_date: date })
  await loadCommissions()
}

onMounted(loadAgents)
</script>
