<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Interest Accrual</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Daily accrual monitoring and manual run</p>
        </div>
        <div class="flex gap-2 items-center">
          <input v-model="runDate" type="date" class="lendr-input text-sm" />
          <button @click="runAccrual" :disabled="running" class="lendr-btn-primary">{{ running ? 'Running…' : 'Run Accrual' }}</button>
        </div>
      </div>
    </template>

    <!-- Summary Cards -->
    <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
      <div class="lendr-card p-4">
        <p class="text-xs text-neutral-500">Active Loans</p>
        <p class="text-2xl font-bold mt-1">{{ summary.active_loans ?? 0 }}</p>
      </div>
      <div class="lendr-card p-4">
        <p class="text-xs text-neutral-500">Accrued (Month)</p>
        <p class="text-2xl font-bold mt-1 text-primary-600">{{ fmt(summary.total_accrued_month) }}</p>
      </div>
      <div class="lendr-card p-4">
        <p class="text-xs text-neutral-500">Non-Performing</p>
        <p class="text-2xl font-bold mt-1 text-red-600">{{ summary.non_performing ?? 0 }}</p>
      </div>
      <div class="lendr-card p-4">
        <p class="text-xs text-neutral-500">Accrued (Total)</p>
        <p class="text-2xl font-bold mt-1">{{ fmt(summary.total_accrued_all) }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-2 mb-4">
      <input v-model="filterDate" @change="fetchAccruals" type="date" class="lendr-input text-sm" />
      <select v-model="filterStatus" @change="fetchAccruals" class="lendr-input text-sm w-40">
        <option value="">All Status</option>
        <option value="performing">Performing</option>
        <option value="non_performing">Non-Performing</option>
      </select>
    </div>

    <!-- Accruals Table -->
    <div class="lendr-card overflow-hidden">
      <div v-if="loading" class="p-10 text-center text-neutral-400">Loading…</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Loan</th>
            <th class="px-4 py-3 text-left">Borrower</th>
            <th class="px-4 py-3 text-right">Principal</th>
            <th class="px-4 py-3 text-right">Rate %</th>
            <th class="px-4 py-3 text-right">Accrued</th>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-center">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="accruals.length === 0"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">No accruals found for selected filters.</td></tr>
          <tr v-for="a in accruals" :key="a.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3 font-mono text-xs">{{ a.loan_number ?? `#${a.loan_id}` }}</td>
            <td class="px-4 py-3 text-neutral-700">{{ a.borrower_name ?? '—' }}</td>
            <td class="px-4 py-3 text-right">{{ fmt(a.principal_outstanding) }}</td>
            <td class="px-4 py-3 text-right">{{ a.daily_rate }}%</td>
            <td class="px-4 py-3 text-right font-medium text-primary-600">{{ fmt(a.accrued_amount) }}</td>
            <td class="px-4 py-3 text-neutral-500 text-xs">{{ a.accrual_date }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="a.is_non_performing ? 'lendr-badge-danger' : 'lendr-badge-success'" class="lendr-badge text-xs">
                {{ a.is_non_performing ? 'Non-Performing' : 'Performing' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="p-4 border-t flex justify-between items-center text-sm text-neutral-500">
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <div class="flex gap-2">
          <button @click="page--; fetchAccruals()" :disabled="meta.current_page <= 1" class="lendr-btn-ghost text-xs px-3 py-1">Prev</button>
          <button @click="page++; fetchAccruals()" :disabled="meta.current_page >= meta.last_page" class="lendr-btn-ghost text-xs px-3 py-1">Next</button>
        </div>
      </div>
    </div>

    <!-- Run Result Toast -->
    <div v-if="runResult" class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-3 rounded-xl shadow-lg text-sm z-50">
      {{ runResult }}
      <button @click="runResult = ''" class="ml-3 opacity-70 hover:opacity-100">&times;</button>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const accruals = ref([])
const loading = ref(false)
const summary = ref(null)
const meta = ref(null)
const page = ref(1)

const filterDate = ref('')
const filterStatus = ref('')
const runDate = ref(new Date().toISOString().slice(0, 10))
const running = ref(false)
const runResult = ref('')

async function fetchAccruals() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/interest-accrual', {
      params: {
        date_from: filterDate.value || undefined,
        date_to: filterDate.value || undefined,
        status: filterStatus.value || undefined,
        page: page.value,
      }
    })
    accruals.value = data.data ?? []
    meta.value = data.meta ?? null
  } finally { loading.value = false }
}

async function fetchSummary() {
  try {
    const { data } = await axios.get('/api/v1/interest-accrual/summary')
    summary.value = data.data ?? null
  } catch {}
}

async function runAccrual() {
  running.value = true
  runResult.value = ''
  try {
    const { data } = await axios.post('/api/v1/interest-accrual/run', { date: runDate.value })
    runResult.value = `Accrual complete: ${data.data?.processed ?? 0} loans processed.`
    await fetchAccruals()
    await fetchSummary()
  } finally { running.value = false }
}

function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }

onMounted(() => { fetchAccruals(); fetchSummary() })
</script>
