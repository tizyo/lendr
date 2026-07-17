<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Loan Penalties</h1>
          <p class="text-sm text-neutral-500 mt-0.5">View and manage overdue penalties</p>
        </div>
        <div class="flex gap-2 items-center">
          <input v-model="runDate" type="date" class="lendr-input text-sm" />
          <button @click="runPenalties" :disabled="running" class="lendr-btn-primary">{{ running ? 'Running…' : 'Apply Penalties' }}</button>
        </div>
      </div>
    </template>

    <!-- Filters -->
    <div class="flex gap-2 mb-4">
      <select v-model="filterStatus" @change="fetchPenalties" class="lendr-input text-sm w-40">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="paid">Paid</option>
        <option value="waived">Waived</option>
        <option value="partial_waiver">Partial Waiver</option>
      </select>
    </div>

    <!-- Penalties Table -->
    <div class="lendr-card overflow-hidden">
      <div v-if="loading" class="p-10 text-center text-neutral-400">Loading…</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Loan</th>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-right">Days Overdue</th>
            <th class="px-4 py-3 text-right">Overdue Amt</th>
            <th class="px-4 py-3 text-right">Penalty</th>
            <th class="px-4 py-3 text-right">Waived</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="penalties.length === 0"><td colspan="8" class="px-4 py-10 text-center text-neutral-400">No penalties found.</td></tr>
          <tr v-for="p in penalties" :key="p.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3 font-mono text-xs">Loan #{{ p.loan_id }}</td>
            <td class="px-4 py-3 text-neutral-500 text-xs">{{ p.penalty_date }}</td>
            <td class="px-4 py-3 text-right text-red-600 font-semibold">{{ p.days_overdue }}</td>
            <td class="px-4 py-3 text-right">{{ fmt(p.overdue_amount) }}</td>
            <td class="px-4 py-3 text-right font-medium text-amber-600">{{ fmt(p.penalty_amount) }}</td>
            <td class="px-4 py-3 text-right text-green-600">{{ p.waived_amount > 0 ? fmt(p.waived_amount) : '—' }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="statusClass(p.status)" class="lendr-badge text-xs capitalize">{{ p.status?.replace('_', ' ') }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button v-if="p.status === 'pending' || p.status === 'partial_waiver'" @click="openWaive(p)"
                class="text-primary-600 text-xs hover:underline">Waive</button>
            </td>
          </tr>
        </tbody>
      </table>
      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="p-4 border-t flex justify-between items-center text-sm text-neutral-500">
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <div class="flex gap-2">
          <button @click="page--; fetchPenalties()" :disabled="meta.current_page <= 1" class="lendr-btn-ghost text-xs px-3 py-1">Prev</button>
          <button @click="page++; fetchPenalties()" :disabled="meta.current_page >= meta.last_page" class="lendr-btn-ghost text-xs px-3 py-1">Next</button>
        </div>
      </div>
    </div>

    <!-- Run Result Toast -->
    <div v-if="runResult" class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-3 rounded-xl shadow-lg text-sm z-50">
      {{ runResult }}
      <button @click="runResult = ''" class="ml-3 opacity-70 hover:opacity-100">&times;</button>
    </div>

    <!-- Waive Modal -->
    <div v-if="waiveModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="font-bold text-lg">Waive Penalty</h2>
        <p class="text-sm text-neutral-600">Penalty amount: <strong>{{ fmt(waiveModal.penalty_amount) }}</strong></p>
        <div class="space-y-3">
          <div>
            <label class="lendr-label">Waiver Amount *</label>
            <input v-model.number="waiveForm.amount" type="number" step="0.01" :max="waiveModal.penalty_amount" class="lendr-input w-full" />
          </div>
          <div>
            <label class="lendr-label">Reason *</label>
            <textarea v-model="waiveForm.reason" class="lendr-input w-full" rows="3"></textarea>
          </div>
        </div>
        <p v-if="waiveError" class="text-red-500 text-sm">{{ waiveError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="waiveModal = null" class="lendr-btn-ghost">Cancel</button>
          <button @click="submitWaive" :disabled="waiving" class="lendr-btn-primary">{{ waiving ? 'Processing…' : 'Waive' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const penalties = ref([])
const loading = ref(false)
const meta = ref(null)
const page = ref(1)
const filterStatus = ref('')
const running = ref(false)
const runDate = ref(new Date().toISOString().slice(0, 10))
const runResult = ref('')

const waiveModal = ref(null)
const waiveForm = ref({ amount: 0, reason: '' })
const waiveError = ref('')
const waiving = ref(false)

async function fetchPenalties() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/penalties', {
      params: { status: filterStatus.value || undefined, page: page.value }
    })
    penalties.value = data.data ?? []
    meta.value = data.meta ?? null
  } finally { loading.value = false }
}

async function runPenalties() {
  running.value = true
  runResult.value = ''
  try {
    const { data } = await axios.post('/api/v1/penalties/run', { date: runDate.value })
    const r = data.data
    runResult.value = `Done: ${r?.applied ?? 0} applied, ${r?.skipped ?? 0} skipped.`
    await fetchPenalties()
  } finally { running.value = false }
}

function openWaive(p) {
  waiveModal.value = p
  waiveForm.value = { amount: p.penalty_amount - p.waived_amount, reason: '' }
  waiveError.value = ''
}

async function submitWaive() {
  waiveError.value = ''
  waiving.value = true
  try {
    await axios.post(`/api/v1/penalties/${waiveModal.value.id}/waive`, waiveForm.value)
    await fetchPenalties()
    waiveModal.value = null
  } catch (e) {
    waiveError.value = e.response?.data?.message ?? 'Failed.'
  } finally { waiving.value = false }
}

function statusClass(s) {
  const map = { pending: 'lendr-badge-warning', paid: 'lendr-badge-success', waived: 'lendr-badge-neutral', partial_waiver: 'lendr-badge-neutral' }
  return map[s] ?? 'lendr-badge-neutral'
}
function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }

onMounted(() => fetchPenalties())
</script>
