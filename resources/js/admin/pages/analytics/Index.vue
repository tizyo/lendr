<template>
  <div class="max-w-7xl mx-auto py-8 px-4 space-y-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">Advanced Analytics</h1>
      <div class="flex gap-2 flex-wrap">
        <button v-for="tab in tabs" :key="tab.key" @click="switchTab(tab.key)" :class="tabClass(tab.key)">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Portfolio Trend -->
    <div v-if="activeTab === 'portfolio_trend'" class="space-y-4">
      <div class="flex gap-3 items-end">
        <div>
          <label class="block text-xs font-medium mb-1">Months</label>
          <select v-model="months" @change="loadPortfolioTrend" class="border rounded px-3 py-2 text-sm">
            <option value="6">6</option><option value="12">12</option><option value="24">24</option>
          </select>
        </div>
      </div>
      <div class="bg-white border rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
            <tr>
              <th class="px-4 py-3 text-left">Month</th>
              <th class="px-4 py-3 text-right">Disbursed</th>
              <th class="px-4 py-3 text-right">Outstanding</th>
              <th class="px-4 py-3 text-right">New Borrowers</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="r in portfolioTrend" :key="r.month" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium">{{ r.month }}</td>
              <td class="px-4 py-3 text-right">{{ currency }} {{ formatNum(r.disbursed) }}</td>
              <td class="px-4 py-3 text-right">{{ currency }} {{ formatNum(r.outstanding) }}</td>
              <td class="px-4 py-3 text-right">{{ r.new_borrowers }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Demographics -->
    <div v-if="activeTab === 'demographics'" class="grid grid-cols-3 gap-6">
      <div class="bg-white border rounded-lg p-4">
        <h3 class="font-semibold text-sm mb-3">By Gender</h3>
        <div v-for="(count, gender) in demographics.by_gender" :key="gender" class="flex justify-between text-sm py-1 border-b last:border-0">
          <span class="capitalize text-gray-600">{{ gender }}</span>
          <span class="font-semibold">{{ count }}</span>
        </div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <h3 class="font-semibold text-sm mb-3">Top Cities</h3>
        <div v-for="r in demographics.by_city" :key="r.city" class="flex justify-between text-sm py-1 border-b last:border-0">
          <span class="text-gray-600">{{ r.city }}</span>
          <span class="font-semibold">{{ r.count }}</span>
        </div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <h3 class="font-semibold text-sm mb-3">Top Occupations</h3>
        <div v-for="r in demographics.by_occupation" :key="r.occupation" class="flex justify-between text-sm py-1 border-b last:border-0">
          <span class="text-gray-600">{{ r.occupation }}</span>
          <span class="font-semibold">{{ r.count }}</span>
        </div>
      </div>
    </div>

    <!-- Cohort Analysis -->
    <div v-if="activeTab === 'cohort'" class="bg-white border rounded-lg overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 text-left">Cohort</th>
            <th class="px-4 py-3 text-right">Loans</th>
            <th class="px-4 py-3 text-right">Disbursed</th>
            <th class="px-4 py-3 text-right">Collected</th>
            <th class="px-4 py-3 text-right">Collection Rate</th>
            <th class="px-4 py-3 text-right">Completed</th>
            <th class="px-4 py-3 text-right">Defaulted</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="r in cohort" :key="r.cohort" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ r.cohort }}</td>
            <td class="px-4 py-3 text-right">{{ r.loan_count }}</td>
            <td class="px-4 py-3 text-right">{{ currency }} {{ formatNum(r.total_disbursed) }}</td>
            <td class="px-4 py-3 text-right">{{ currency }} {{ formatNum(r.total_collected) }}</td>
            <td class="px-4 py-3 text-right">
              <span :class="r.collection_rate >= 80 ? 'text-green-700' : r.collection_rate >= 50 ? 'text-yellow-700' : 'text-red-700'">
                {{ r.collection_rate != null ? r.collection_rate + '%' : '—' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right text-green-700">{{ r.completed_count }}</td>
            <td class="px-4 py-3 text-right text-red-700">{{ r.defaulted_count }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Officer League -->
    <div v-if="activeTab === 'officer_league'" class="space-y-4">
      <div class="flex gap-3 items-end">
        <div>
          <label class="block text-xs font-medium mb-1">From</label>
          <input v-model="dateFrom" type="date" class="border rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">To</label>
          <input v-model="dateTo" type="date" class="border rounded px-3 py-2 text-sm" />
        </div>
        <button @click="loadOfficerLeague" class="border rounded px-4 py-2 text-sm hover:bg-gray-50">Apply</button>
      </div>
      <div class="bg-white border rounded-lg overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Officer</th>
              <th class="px-4 py-3 text-left">Role</th>
              <th class="px-4 py-3 text-right">Loans Created</th>
              <th class="px-4 py-3 text-right">Loans Disbursed</th>
              <th class="px-4 py-3 text-right">Amount Disbursed</th>
              <th class="px-4 py-3 text-right">Collected</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="(r, i) in officerLeague" :key="r.officer_id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-bold text-gray-400">{{ i + 1 }}</td>
              <td class="px-4 py-3 font-medium">{{ r.officer_name }}</td>
              <td class="px-4 py-3 text-gray-500 capitalize">{{ r.role }}</td>
              <td class="px-4 py-3 text-right">{{ r.loans_created }}</td>
              <td class="px-4 py-3 text-right">{{ r.loans_disbursed }}</td>
              <td class="px-4 py-3 text-right font-semibold text-green-700">{{ currency }} {{ formatNum(r.amount_disbursed) }}</td>
              <td class="px-4 py-3 text-right">{{ currency }} {{ formatNum(r.amount_collected) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Geographic -->
    <div v-if="activeTab === 'geographic'" class="bg-white border rounded-lg overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 text-left">City</th>
            <th class="px-4 py-3 text-right">Active Loans</th>
            <th class="px-4 py-3 text-right">Disbursed</th>
            <th class="px-4 py-3 text-right">Outstanding</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="r in geographic" :key="r.city" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ r.city }}</td>
            <td class="px-4 py-3 text-right">{{ r.loan_count }}</td>
            <td class="px-4 py-3 text-right">{{ currency }} {{ formatNum(r.disbursed) }}</td>
            <td class="px-4 py-3 text-right">{{ currency }} {{ formatNum(r.outstanding) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const activeTab = ref('portfolio_trend')
const currency  = ref('ZMW')
const months    = ref('12')
const dateFrom  = ref(new Date(new Date().setDate(1)).toISOString().slice(0, 10))
const dateTo    = ref(new Date().toISOString().slice(0, 10))

const portfolioTrend = ref([])
const demographics   = ref({ by_gender: {}, by_city: [], by_occupation: [] })
const cohort         = ref([])
const officerLeague  = ref([])
const geographic     = ref([])

const tabs = [
  { key: 'portfolio_trend', label: 'Portfolio Trend' },
  { key: 'demographics',    label: 'Demographics' },
  { key: 'cohort',          label: 'Cohort Analysis' },
  { key: 'officer_league',  label: 'Officer League' },
  { key: 'geographic',      label: 'Geographic' },
]

function formatNum(n) { return Number(n || 0).toLocaleString() }
function tabClass(t) { return activeTab.value === t ? 'bg-green-600 text-white rounded px-3 py-2 text-sm' : 'border rounded px-3 py-2 text-sm hover:bg-gray-50' }

async function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'portfolio_trend') await loadPortfolioTrend()
  else if (tab === 'demographics') await loadDemographics()
  else if (tab === 'cohort') await loadCohort()
  else if (tab === 'officer_league') await loadOfficerLeague()
  else if (tab === 'geographic') await loadGeographic()
}

async function loadPortfolioTrend() {
  const { data } = await axios.get('/api/v1/reports/portfolio_trend', { params: { months: months.value } })
  portfolioTrend.value = data.data.rows
}

async function loadDemographics() {
  const { data } = await axios.get('/api/v1/reports/demographics')
  demographics.value = data.data
}

async function loadCohort() {
  const { data } = await axios.get('/api/v1/reports/cohort', { params: { months: months.value } })
  cohort.value = data.data.rows
}

async function loadOfficerLeague() {
  const { data } = await axios.get('/api/v1/reports/officer_league', { params: { date_from: dateFrom.value, date_to: dateTo.value } })
  officerLeague.value = data.data.rows
}

async function loadGeographic() {
  const { data } = await axios.get('/api/v1/reports/geographic')
  geographic.value = data.data.rows
}

onMounted(loadPortfolioTrend)
</script>
