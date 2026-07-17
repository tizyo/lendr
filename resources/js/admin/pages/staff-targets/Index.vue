<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Staff Targets</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Set monthly targets and track performance</p>
        </div>
        <button @click="openSet" class="lendr-btn-primary">+ Set Target</button>
      </div>
    </template>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-neutral-200 mb-4">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px', activeTab === tab.key ? 'border-primary-500 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700']">
        {{ tab.label }}
      </button>
    </div>

    <!-- Targets Tab -->
    <div v-if="activeTab === 'targets'">
      <div class="flex gap-2 mb-4">
        <input v-model="filterYear" type="number" min="2020" max="2100" class="lendr-input text-sm w-28" placeholder="Year" @change="fetchTargets" />
        <select v-model="filterMonth" @change="fetchTargets" class="lendr-input text-sm w-36">
          <option v-for="(m, i) in months" :key="i+1" :value="i+1">{{ m }}</option>
        </select>
      </div>
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingTargets" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Staff</th>
              <th class="px-4 py-3 text-right">Disbursement Target</th>
              <th class="px-4 py-3 text-right">Collection Target</th>
              <th class="px-4 py-3 text-right">New Borrowers</th>
              <th class="px-4 py-3 text-right">New Loans</th>
              <th class="px-4 py-3 text-center">Achievement</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="targets.length === 0"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">No targets set for this period.</td></tr>
            <tr v-for="t in targets" :key="t.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-medium">{{ t.user_name ?? `Staff #${t.user_id}` }}</td>
              <td class="px-4 py-3 text-right">{{ fmt(t.disbursement_target) }}</td>
              <td class="px-4 py-3 text-right">{{ fmt(t.collection_target) }}</td>
              <td class="px-4 py-3 text-right">{{ t.new_borrowers_target }}</td>
              <td class="px-4 py-3 text-right">{{ t.new_loans_target }}</td>
              <td class="px-4 py-3 text-center">
                <span v-if="t.achievement" :class="['lendr-badge text-xs', t.achievement.overall_pct >= 100 ? 'lendr-badge-success' : t.achievement.overall_pct >= 60 ? 'lendr-badge-warning' : 'lendr-badge-danger']">
                  {{ t.achievement.overall_pct ?? 0 }}%
                </span>
                <span v-else class="text-neutral-400">—</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="deleteTarget(t)" class="text-red-400 text-xs hover:text-red-600">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Performance Tab -->
    <div v-if="activeTab === 'performance'">
      <div class="flex gap-2 mb-4">
        <input v-model="perfYear" type="number" min="2020" max="2100" class="lendr-input text-sm w-28" placeholder="Year" @change="fetchPerformance" />
        <select v-model="perfMonth" @change="fetchPerformance" class="lendr-input text-sm w-36">
          <option v-for="(m, i) in months" :key="i+1" :value="i+1">{{ m }}</option>
        </select>
      </div>
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingPerf" class="p-10 text-center text-neutral-400">Loading…</div>
        <div v-else>
          <table class="w-full text-sm">
            <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
              <tr>
                <th class="px-4 py-3 text-left">Staff</th>
                <th class="px-4 py-3 text-right">Disbursed</th>
                <th class="px-4 py-3 text-right">Collected</th>
                <th class="px-4 py-3 text-right">New Borrowers</th>
                <th class="px-4 py-3 text-right">New Loans</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-if="team.length === 0"><td colspan="5" class="px-4 py-10 text-center text-neutral-400">No performance data available.</td></tr>
              <tr v-for="p in team" :key="p.user_id" class="hover:bg-neutral-50">
                <td class="px-4 py-3 font-medium">{{ p.user_name ?? `Staff #${p.user_id}` }}</td>
                <td class="px-4 py-3 text-right">{{ fmt(p.actuals?.disbursement_actual) }}</td>
                <td class="px-4 py-3 text-right">{{ fmt(p.actuals?.collection_actual) }}</td>
                <td class="px-4 py-3 text-right">{{ p.actuals?.new_borrowers_actual ?? 0 }}</td>
                <td class="px-4 py-3 text-right">{{ p.actuals?.new_loans_actual ?? 0 }}</td>
              </tr>
            </tbody>
          </table>
          <!-- Team totals -->
          <div v-if="totals" class="border-t p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="lendr-card p-3 border">
              <p class="text-xs text-neutral-500">Total Disbursed</p>
              <p class="font-bold text-lg mt-0.5">{{ fmt(totals.disbursement_actual) }}</p>
              <p class="text-xs text-neutral-400">Target: {{ fmt(totals.disbursement_target) }}</p>
            </div>
            <div class="lendr-card p-3 border">
              <p class="text-xs text-neutral-500">Total Collected</p>
              <p class="font-bold text-lg mt-0.5">{{ fmt(totals.collection_actual) }}</p>
              <p class="text-xs text-neutral-400">Target: {{ fmt(totals.collection_target) }}</p>
            </div>
            <div class="lendr-card p-3 border">
              <p class="text-xs text-neutral-500">New Borrowers</p>
              <p class="font-bold text-lg mt-0.5">{{ totals.new_borrowers_actual ?? 0 }}</p>
              <p class="text-xs text-neutral-400">Target: {{ totals.new_borrowers_target ?? 0 }}</p>
            </div>
            <div class="lendr-card p-3 border">
              <p class="text-xs text-neutral-500">New Loans</p>
              <p class="font-bold text-lg mt-0.5">{{ totals.new_loans_actual ?? 0 }}</p>
              <p class="text-xs text-neutral-400">Target: {{ totals.new_loans_target ?? 0 }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Set Target Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="font-bold text-lg">Set Staff Target</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="lendr-label">Staff Member *</label>
            <select v-model="form.user_id" class="lendr-input w-full">
              <option value="">Select staff…</option>
              <option v-for="s in staffList" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Year *</label>
            <input v-model="form.period_year" type="number" min="2020" max="2100" class="lendr-input w-full" />
          </div>
          <div>
            <label class="lendr-label">Month *</label>
            <select v-model="form.period_month" class="lendr-input w-full">
              <option v-for="(m, i) in months" :key="i+1" :value="i+1">{{ m }}</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Disbursement Target (K)</label>
            <input v-model="form.disbursement_target" type="number" step="0.01" class="lendr-input w-full" placeholder="0" />
          </div>
          <div>
            <label class="lendr-label">Collection Target (K)</label>
            <input v-model="form.collection_target" type="number" step="0.01" class="lendr-input w-full" placeholder="0" />
          </div>
          <div>
            <label class="lendr-label">New Borrowers Target</label>
            <input v-model="form.new_borrowers_target" type="number" class="lendr-input w-full" placeholder="0" />
          </div>
          <div>
            <label class="lendr-label">New Loans Target</label>
            <input v-model="form.new_loans_target" type="number" class="lendr-input w-full" placeholder="0" />
          </div>
          <div class="col-span-2">
            <label class="lendr-label">Notes (optional)</label>
            <input v-model="form.notes" class="lendr-input w-full" />
          </div>
        </div>
        <p v-if="formError" class="text-red-500 text-sm">{{ formError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="saveTarget" :disabled="saving" class="lendr-btn-primary">{{ saving ? 'Saving…' : 'Set Target' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const activeTab = ref('targets')
const tabs = [{ key: 'targets', label: 'Targets' }, { key: 'performance', label: 'Performance' }]

const now = new Date()
const targets = ref([])
const team = ref([])
const totals = ref(null)
const staffList = ref([])
const loadingTargets = ref(false)
const loadingPerf = ref(false)

const filterYear = ref(now.getFullYear())
const filterMonth = ref(now.getMonth() + 1)
const perfYear = ref(now.getFullYear())
const perfMonth = ref(now.getMonth() + 1)

const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const defaultForm = () => ({
  user_id: '',
  period_year: now.getFullYear(),
  period_month: now.getMonth() + 1,
  disbursement_target: '',
  collection_target: '',
  new_borrowers_target: '',
  new_loans_target: '',
  notes: '',
})
const form = ref(defaultForm())

async function fetchTargets() {
  loadingTargets.value = true
  try {
    const { data } = await axios.get('/api/v1/staff-targets', {
      params: { year: filterYear.value, month: filterMonth.value }
    })
    targets.value = data.data?.targets ?? []
  } finally { loadingTargets.value = false }
}

async function fetchPerformance() {
  loadingPerf.value = true
  try {
    const { data } = await axios.get('/api/v1/staff-targets/performance', {
      params: { year: perfYear.value, month: perfMonth.value }
    })
    team.value = data.data?.team ?? []
    totals.value = data.data?.totals ?? null
  } finally { loadingPerf.value = false }
}

async function fetchStaff() {
  try {
    const { data } = await axios.get('/api/v1/staff')
    staffList.value = data.data ?? []
  } catch {}
}

function openSet() {
  form.value = defaultForm()
  formError.value = ''
  showModal.value = true
}

async function saveTarget() {
  formError.value = ''
  saving.value = true
  const payload = {
    user_id:              form.value.user_id,
    period_year:          Number(form.value.period_year),
    period_month:         Number(form.value.period_month),
    disbursement_target:  form.value.disbursement_target || null,
    collection_target:    form.value.collection_target || null,
    new_borrowers_target: form.value.new_borrowers_target || null,
    new_loans_target:     form.value.new_loans_target || null,
    notes:                form.value.notes || null,
  }
  try {
    await axios.post('/api/v1/staff-targets', payload)
    await fetchTargets()
    showModal.value = false
  } catch (e) { formError.value = e.response?.data?.message ?? 'Failed.' } finally { saving.value = false }
}

async function deleteTarget(t) {
  if (!confirm('Remove this target?')) return
  try {
    await axios.delete(`/api/v1/staff-targets/${t.id}`)
    await fetchTargets()
  } catch {}
}

function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString() }

onMounted(() => { fetchTargets(); fetchPerformance(); fetchStaff() })
</script>
