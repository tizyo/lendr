<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">IFRS9 Provisioning</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Manage provision rates and run portfolio provisioning</p>
        </div>
        <div class="flex gap-2">
          <button @click="seedRates" :disabled="seeding" class="lendr-btn-ghost text-sm">{{ seeding ? 'Seeding…' : 'Seed Defaults' }}</button>
          <button @click="runPortfolio" :disabled="running" class="lendr-btn-primary">{{ running ? 'Running…' : 'Run Portfolio' }}</button>
        </div>
      </div>
    </template>

    <!-- Summary Cards -->
    <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
      <div class="lendr-card p-4">
        <p class="text-xs text-neutral-500">Total Loans</p>
        <p class="text-2xl font-bold mt-1">{{ summary.total_loans }}</p>
      </div>
      <div class="lendr-card p-4">
        <p class="text-xs text-neutral-500">Outstanding</p>
        <p class="text-2xl font-bold mt-1">{{ fmt(summary.total_outstanding) }}</p>
      </div>
      <div class="lendr-card p-4">
        <p class="text-xs text-neutral-500">Total Provision</p>
        <p class="text-2xl font-bold mt-1 text-amber-600">{{ fmt(summary.total_provision) }}</p>
      </div>
      <div class="lendr-card p-4">
        <p class="text-xs text-neutral-500">Coverage Ratio</p>
        <p class="text-2xl font-bold mt-1">{{ summary.coverage_ratio ?? 0 }}%</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-neutral-200 mb-4">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px', activeTab === tab.key ? 'border-primary-500 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700']">
        {{ tab.label }}
      </button>
    </div>

    <!-- Provision Rates -->
    <div v-if="activeTab === 'rates'">
      <div class="lendr-card overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
          <span class="text-sm font-medium text-neutral-700">Stage Rates</span>
          <button @click="openAddRate" class="lendr-btn-primary text-xs px-3 py-1.5">+ Add Rate</button>
        </div>
        <div v-if="loadingRates" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Stage</th>
              <th class="px-4 py-3 text-left">DPD From</th>
              <th class="px-4 py-3 text-left">DPD To</th>
              <th class="px-4 py-3 text-right">Rate %</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="rates.length === 0"><td colspan="5" class="px-4 py-10 text-center text-neutral-400">No rates configured. Click "Seed Defaults" to get started.</td></tr>
            <tr v-for="r in rates" :key="r.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-medium">Stage {{ r.stage }}</td>
              <td class="px-4 py-3 text-neutral-600">{{ r.dpd_from }} days</td>
              <td class="px-4 py-3 text-neutral-600">{{ r.dpd_to !== null ? r.dpd_to + ' days' : '∞' }}</td>
              <td class="px-4 py-3 text-right font-medium text-amber-600">{{ r.provision_rate }}%</td>
              <td class="px-4 py-3 text-right">
                <button @click="editRate(r)" class="text-primary-600 text-xs hover:underline mr-3">Edit</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Portfolio Provisions -->
    <div v-if="activeTab === 'portfolio'">
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingPortfolio" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Loan</th>
              <th class="px-4 py-3 text-left">Borrower</th>
              <th class="px-4 py-3 text-right">Outstanding</th>
              <th class="px-4 py-3 text-right">DPD</th>
              <th class="px-4 py-3 text-center">Stage</th>
              <th class="px-4 py-3 text-right">Provision</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="portfolio.length === 0"><td colspan="6" class="px-4 py-10 text-center text-neutral-400">No provisions recorded. Run portfolio to calculate.</td></tr>
            <tr v-for="p in portfolio" :key="p.loan_id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-mono text-xs">{{ p.loan_number }}</td>
              <td class="px-4 py-3 text-neutral-700">{{ p.borrower_name }}</td>
              <td class="px-4 py-3 text-right">{{ fmt(p.outstanding) }}</td>
              <td class="px-4 py-3 text-right" :class="p.max_dpd > 30 ? 'text-red-600 font-semibold' : ''">{{ p.max_dpd }}</td>
              <td class="px-4 py-3 text-center"><span class="lendr-badge lendr-badge-neutral text-xs">Stage {{ p.stage }}</span></td>
              <td class="px-4 py-3 text-right font-medium text-amber-600">{{ fmt(p.provision_amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Rate Modal -->
    <div v-if="showRateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="font-bold text-lg">{{ editingRate ? 'Edit' : 'Add' }} Provision Rate</h2>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="lendr-label">Stage (1–3)</label><input v-model.number="rateForm.stage" type="number" min="1" max="3" class="lendr-input w-full" placeholder="1, 2, or 3" /></div>
          <div><label class="lendr-label">Stage Label</label><input v-model="rateForm.stage_label" class="lendr-input w-full" placeholder="e.g. Stage 1" /></div>
          <div><label class="lendr-label">DPD From</label><input v-model.number="rateForm.dpd_from" type="number" class="lendr-input w-full" /></div>
          <div><label class="lendr-label">DPD To (blank = ∞)</label><input v-model="rateForm.dpd_to" type="number" class="lendr-input w-full" placeholder="Leave blank for no limit" /></div>
          <div class="col-span-2"><label class="lendr-label">Provision Rate %</label><input v-model.number="rateForm.provision_rate" type="number" step="0.01" class="lendr-input w-full" /></div>
        </div>
        <p v-if="rateError" class="text-red-500 text-sm">{{ rateError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showRateModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="saveRate" :disabled="savingRate" class="lendr-btn-primary">{{ savingRate ? 'Saving…' : 'Save' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const activeTab = ref('rates')
const tabs = [
  { key: 'rates', label: 'Provision Rates' },
  { key: 'portfolio', label: 'Portfolio Provisions' },
]

const rates = ref([])
const portfolio = ref([])
const summary = ref(null)
const loadingRates = ref(false)
const loadingPortfolio = ref(false)
const seeding = ref(false)
const running = ref(false)

const showRateModal = ref(false)
const editingRate = ref(null)
const rateForm = ref({ stage: 1, stage_label: 'Stage 1', dpd_from: 0, dpd_to: '', provision_rate: 1 })
const rateError = ref('')
const savingRate = ref(false)

async function fetchRates() {
  loadingRates.value = true
  try {
    const { data } = await axios.get('/api/v1/provisioning/rates')
    rates.value = data.data ?? []
  } finally { loadingRates.value = false }
}

async function fetchPortfolio() {
  loadingPortfolio.value = true
  try {
    const { data } = await axios.get('/api/v1/provisioning/summary')
    summary.value = data.data?.summary ?? null
    portfolio.value = data.data?.loans ?? []
  } finally { loadingPortfolio.value = false }
}

async function seedRates() {
  seeding.value = true
  try {
    await axios.post('/api/v1/provisioning/rates/seed')
    await fetchRates()
  } finally { seeding.value = false }
}

async function runPortfolio() {
  running.value = true
  try {
    await axios.post('/api/v1/provisioning/run')
    await fetchPortfolio()
    activeTab.value = 'portfolio'
  } finally { running.value = false }
}

function openAddRate() {
  editingRate.value = null
  rateForm.value = { stage: 1, stage_label: 'Stage 1', dpd_from: 0, dpd_to: '', provision_rate: 1 }
  rateError.value = ''
  showRateModal.value = true
}

function editRate(r) {
  editingRate.value = r
  rateForm.value = { stage: r.stage, stage_label: r.stage_label, dpd_from: r.dpd_from, dpd_to: r.dpd_to ?? '', provision_rate: r.provision_rate }
  rateError.value = ''
  showRateModal.value = true
}

async function saveRate() {
  rateError.value = ''
  savingRate.value = true
  const payload = { ...rateForm.value, dpd_to: rateForm.value.dpd_to !== '' ? Number(rateForm.value.dpd_to) : null }
  try {
    if (editingRate.value) {
      await axios.put(`/api/v1/provisioning/rates/${editingRate.value.id}`, payload)
    } else {
      await axios.post('/api/v1/provisioning/rates', payload)
    }
    await fetchRates()
    showRateModal.value = false
  } catch (e) { rateError.value = e.response?.data?.message ?? 'Failed.' } finally { savingRate.value = false }
}

function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }

onMounted(() => { fetchRates(); fetchPortfolio() })
</script>
