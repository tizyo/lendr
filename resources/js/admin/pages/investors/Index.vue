<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Investor Portal</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Manage investors and fund allocations</p>
        </div>
        <button @click="openCreate" class="lendr-btn-primary">+ Add Investor</button>
      </div>
    </template>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-4">
      <div class="lendr-card p-4" v-for="stat in portfolioStats" :key="stat.label">
        <p class="text-xs text-neutral-500">{{ stat.label }}</p>
        <p class="text-2xl font-bold mt-1">{{ stat.value }}</p>
      </div>
    </div>

    <!-- Investors table -->
    <div class="lendr-card overflow-hidden">
      <div class="p-4 border-b border-neutral-100">
        <input v-model="search" @input="debouncedSearch" class="lendr-input w-64" placeholder="Search investors…" />
      </div>
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Investor</th>
            <th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-right">Total Allocated</th>
            <th class="px-4 py-3 text-right">Allocations</th>
            <th class="px-4 py-3 text-right">Total Returns</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="loading"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">Loading…</td></tr>
          <tr v-else-if="investors.length === 0"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">No investors found.</td></tr>
          <tr v-for="inv in investors" :key="inv.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3">
              <p class="font-medium">{{ inv.name }}</p>
              <p class="text-xs text-neutral-500">{{ inv.email }}</p>
            </td>
            <td class="px-4 py-3 capitalize text-neutral-600">{{ inv.type }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ formatAmt(inv.total_allocated) }}</td>
            <td class="px-4 py-3 text-right">{{ inv.allocations_count ?? 0 }}</td>
            <td class="px-4 py-3 text-right">{{ formatAmt(inv.total_returns) }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="inv.status === 'active' ? 'lendr-badge-success' : 'lendr-badge-neutral'" class="lendr-badge text-xs capitalize">{{ inv.status }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="viewAllocations(inv)" class="text-primary-600 text-xs hover:underline">Allocations</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Allocations Modal -->
    <div v-if="allocationsInvestor" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto">
        <div class="p-5 border-b flex justify-between items-center sticky top-0 bg-white">
          <h2 class="font-bold text-lg">{{ allocationsInvestor.name }} — Allocations</h2>
          <button @click="allocationsInvestor = null" class="text-neutral-400 hover:text-neutral-700 text-xl">&times;</button>
        </div>
        <div class="p-5">
          <div v-if="loadingAllocs" class="text-center py-8 text-neutral-400">Loading…</div>
          <table v-else class="w-full text-sm">
            <thead class="text-neutral-500 text-xs uppercase border-b">
              <tr>
                <th class="pb-2 text-left">Loan</th>
                <th class="pb-2 text-right">Allocated</th>
                <th class="pb-2 text-right">Expected Return</th>
                <th class="pb-2 text-right">Actual Return</th>
                <th class="pb-2 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-if="allocations.length === 0"><td colspan="5" class="py-8 text-center text-neutral-400">No allocations yet.</td></tr>
              <tr v-for="a in allocations" :key="a.id">
                <td class="py-2 text-neutral-700">{{ a.loan_number ?? `Loan #${a.loan_id}` }}</td>
                <td class="py-2 text-right">{{ formatAmt(a.allocated_amount) }}</td>
                <td class="py-2 text-right">{{ formatAmt(a.expected_return) }}</td>
                <td class="py-2 text-right">{{ formatAmt(a.actual_return) }}</td>
                <td class="py-2 text-center"><span class="lendr-badge lendr-badge-neutral text-xs capitalize">{{ a.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="font-bold text-lg">Add Investor</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2"><label class="lendr-label">Name *</label><input v-model="form.name" class="lendr-input w-full" /></div>
          <div><label class="lendr-label">Email *</label><input v-model="form.email" type="email" class="lendr-input w-full" /></div>
          <div><label class="lendr-label">Phone</label><input v-model="form.phone" class="lendr-input w-full" /></div>
          <div>
            <label class="lendr-label">Type *</label>
            <select v-model="form.type" class="lendr-input w-full">
              <option value="individual">Individual</option>
              <option value="institution">Institution</option>
            </select>
          </div>
          <div><label class="lendr-label">Country</label><input v-model="form.country" class="lendr-input w-full" /></div>
          <div class="col-span-2"><label class="lendr-label">Notes</label><textarea v-model="form.notes" class="lendr-input w-full" rows="2"></textarea></div>
        </div>
        <p v-if="formError" class="text-red-500 text-sm">{{ formError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showCreateModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="createInvestor" :disabled="saving" class="lendr-btn-primary">{{ saving ? 'Saving…' : 'Create' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const investors = ref([])
const loading = ref(false)
const search = ref('')
const portfolio = ref(null)
const showCreateModal = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', email: '', phone: '', type: 'individual', country: '', notes: '' })
const allocationsInvestor = ref(null)
const allocations = ref([])
const loadingAllocs = ref(false)

const portfolioStats = computed(() => [
  { label: 'Total Investors', value: portfolio.value?.investor_count ?? investors.value.length },
  { label: 'Total Deployed', value: formatAmt(portfolio.value?.total_deployed ?? 0) },
  { label: 'Active Allocations', value: portfolio.value?.active_allocations ?? 0 },
])

async function fetchInvestors() {
  loading.value = true
  try {
    const [invRes, portRes] = await Promise.all([
      axios.get('/api/v1/investors', { params: { search: search.value || undefined } }),
      axios.get('/api/v1/investors/portfolio'),
    ])
    investors.value = invRes.data.data ?? []
    portfolio.value = portRes.data.data
  } finally { loading.value = false }
}

let sTimeout = null
function debouncedSearch() { clearTimeout(sTimeout); sTimeout = setTimeout(fetchInvestors, 400) }

async function viewAllocations(inv) {
  allocationsInvestor.value = inv
  loadingAllocs.value = true
  allocations.value = []
  try {
    const { data } = await axios.get(`/api/v1/investors/${inv.id}/allocations`)
    allocations.value = data.data ?? []
  } finally { loadingAllocs.value = false }
}

function openCreate() {
  form.value = { name: '', email: '', phone: '', type: 'individual', country: '', notes: '' }
  formError.value = ''
  showCreateModal.value = true
}

async function createInvestor() {
  saving.value = true; formError.value = ''
  try {
    await axios.post('/api/v1/investors', form.value)
    await fetchInvestors()
    showCreateModal.value = false
  } catch (e) { formError.value = e.response?.data?.message ?? 'Failed.' } finally { saving.value = false }
}

function formatAmt(n) { return 'K ' + Number(n ?? 0).toLocaleString() }
onMounted(() => fetchInvestors())
</script>
