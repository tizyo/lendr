<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Staff Commissions</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Commission rules, tracking and approval</p>
        </div>
        <div class="flex gap-2">
          <button @click="openAddRule" class="lendr-btn-ghost text-sm">+ Rule</button>
          <button @click="approvePeriod" :disabled="approving" class="lendr-btn-primary">{{ approving ? 'Approving…' : 'Approve Period' }}</button>
        </div>
      </div>
    </template>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-neutral-200 mb-4">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px', activeTab === tab.key ? 'border-primary-500 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700']">
        {{ tab.label }}
      </button>
    </div>

    <!-- Commission Rules -->
    <div v-if="activeTab === 'rules'">
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingRules" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Trigger</th>
              <th class="px-4 py-3 text-left">Staff</th>
              <th class="px-4 py-3 text-left">Loan Type</th>
              <th class="px-4 py-3 text-left">Calc</th>
              <th class="px-4 py-3 text-right">Rate</th>
              <th class="px-4 py-3 text-right">Min Amount</th>
              <th class="px-4 py-3 text-center">Active</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="rules.length === 0"><td colspan="8" class="px-4 py-10 text-center text-neutral-400">No commission rules configured.</td></tr>
            <tr v-for="r in rules" :key="r.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-medium capitalize">{{ r.trigger.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-neutral-600">{{ r.user?.name ?? 'All Staff' }}</td>
              <td class="px-4 py-3 text-neutral-600">{{ r.loan_type?.name ?? 'All Types' }}</td>
              <td class="px-4 py-3 text-neutral-500 capitalize">{{ r.calc_type }}</td>
              <td class="px-4 py-3 text-right font-medium text-primary-600">
                {{ r.calc_type === 'percentage' ? r.rate + '%' : fmt(r.rate) }}
              </td>
              <td class="px-4 py-3 text-right text-neutral-500">{{ r.min_amount ? fmt(r.min_amount) : '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="r.is_active ? 'lendr-badge-success' : 'lendr-badge-neutral'" class="lendr-badge text-xs">{{ r.is_active ? 'On' : 'Off' }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="editRule(r)" class="text-primary-600 text-xs hover:underline mr-2">Edit</button>
                <button @click="deleteRule(r)" class="text-red-400 text-xs hover:text-red-600">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Commissions Earned -->
    <div v-if="activeTab === 'earned'">
      <div class="flex gap-2 mb-4">
        <input v-model="earnedMonth" type="month" @change="fetchCommissions" class="lendr-input text-sm" />
        <select v-model="earnedStatus" @change="fetchCommissions" class="lendr-input text-sm w-32">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="paid">Paid</option>
        </select>
      </div>
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingCommissions" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Staff</th>
              <th class="px-4 py-3 text-left">Trigger</th>
              <th class="px-4 py-3 text-left">Loan</th>
              <th class="px-4 py-3 text-right">Base Amount</th>
              <th class="px-4 py-3 text-right">Commission</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="commissions.length === 0"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">No commissions for this period.</td></tr>
            <tr v-for="c in commissions" :key="c.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-medium">{{ c.user?.name ?? `Staff #${c.user?.id}` }}</td>
              <td class="px-4 py-3 capitalize text-neutral-600">{{ c.trigger?.replace('_', ' ') }}</td>
              <td class="px-4 py-3 font-mono text-xs">{{ c.loan_number ?? '—' }}</td>
              <td class="px-4 py-3 text-right text-neutral-500">{{ fmt(c.base_amount) }}</td>
              <td class="px-4 py-3 text-right font-medium text-primary-600">{{ fmt(c.commission_amount) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="commStatusClass(c.status)" class="lendr-badge text-xs capitalize">{{ c.status }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button v-if="c.status === 'approved'" @click="markPaid([c.id])" class="text-green-600 text-xs hover:underline">Mark Paid</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Per-Staff Summary -->
    <div v-if="activeTab === 'summary'">
      <div class="flex gap-2 mb-4">
        <input v-model="summaryMonth" type="month" @change="fetchSummaries" class="lendr-input text-sm" />
      </div>
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingSummaries" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Staff</th>
              <th class="px-4 py-3 text-right">Total Earned</th>
              <th class="px-4 py-3 text-right">Approved</th>
              <th class="px-4 py-3 text-right">Paid</th>
              <th class="px-4 py-3 text-right">Pending</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="summaries.length === 0"><td colspan="5" class="px-4 py-10 text-center text-neutral-400">No data.</td></tr>
            <tr v-for="s in summaries" :key="s.user_id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-medium">{{ s.name }}</td>
              <td class="px-4 py-3 text-right">{{ fmt(s.total?.amount) }}</td>
              <td class="px-4 py-3 text-right text-green-600">{{ fmt(s.approved?.amount) }}</td>
              <td class="px-4 py-3 text-right text-primary-600">{{ fmt(s.paid?.amount) }}</td>
              <td class="px-4 py-3 text-right text-amber-600">{{ fmt(s.pending?.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Rule Modal -->
    <div v-if="showRuleModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="font-bold text-lg">{{ editingRule ? 'Edit' : 'Add' }} Commission Rule</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="lendr-label">Trigger Event</label>
            <select v-model="ruleForm.trigger" class="lendr-input w-full">
              <option value="disbursement">Loan Disbursement</option>
              <option value="repayment">Repayment</option>
              <option value="loan_completion">Loan Completion</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Calc Type</label>
            <select v-model="ruleForm.calc_type" class="lendr-input w-full">
              <option value="percentage">Percentage</option>
              <option value="flat">Flat Amount</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Staff Member (blank = all)</label>
            <select v-model="ruleForm.user_id" class="lendr-input w-full">
              <option :value="null">All Staff</option>
              <option v-for="s in staffList" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Loan Type (blank = all)</label>
            <select v-model="ruleForm.loan_type_id" class="lendr-input w-full">
              <option :value="null">All Types</option>
              <option v-for="lt in loanTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Rate {{ ruleForm.calc_type === 'percentage' ? '(%)' : '(Flat)' }}</label>
            <input v-model="ruleForm.rate" type="number" step="0.01" class="lendr-input w-full" />
          </div>
          <div>
            <label class="lendr-label">Min Amount (optional)</label>
            <input v-model="ruleForm.min_amount" type="number" class="lendr-input w-full" placeholder="Min loan amount" />
          </div>
          <div>
            <label class="lendr-label">Max Amount (optional)</label>
            <input v-model="ruleForm.max_amount" type="number" class="lendr-input w-full" placeholder="Max loan amount" />
          </div>
          <div>
            <label class="lendr-label">Notes (optional)</label>
            <input v-model="ruleForm.notes" type="text" class="lendr-input w-full" />
          </div>
          <div class="col-span-2 flex items-center gap-2">
            <input v-model="ruleForm.is_active" type="checkbox" id="ruleActive" class="rounded" />
            <label for="ruleActive" class="text-sm text-neutral-700">Active</label>
          </div>
        </div>
        <p v-if="ruleError" class="text-red-500 text-sm">{{ ruleError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showRuleModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="saveRule" :disabled="savingRule" class="lendr-btn-primary">{{ savingRule ? 'Saving…' : 'Save' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const activeTab = ref('rules')
const tabs = [
  { key: 'rules', label: 'Rules' },
  { key: 'earned', label: 'Earned' },
  { key: 'summary', label: 'Summary' },
]

const rules = ref([])
const commissions = ref([])
const summaries = ref([])
const staffList = ref([])
const loanTypes = ref([])
const loadingRules = ref(false)
const loadingCommissions = ref(false)
const loadingSummaries = ref(false)
const approving = ref(false)

const now = new Date()
const currentMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
const earnedMonth = ref(currentMonth)
const earnedStatus = ref('')
const summaryMonth = ref(currentMonth)

const showRuleModal = ref(false)
const editingRule = ref(null)
const savingRule = ref(false)
const ruleError = ref('')
const defaultForm = () => ({ trigger: 'disbursement', calc_type: 'percentage', user_id: null, loan_type_id: null, rate: 1, min_amount: '', max_amount: '', notes: '', is_active: true })
const ruleForm = ref(defaultForm())

async function fetchRules() {
  loadingRules.value = true
  try {
    const { data } = await axios.get('/api/v1/commission-rules')
    rules.value = data.data ?? []
  } finally { loadingRules.value = false }
}

async function fetchCommissions() {
  loadingCommissions.value = true
  try {
    const { data } = await axios.get('/api/v1/commissions', {
      params: { period: earnedMonth.value, status: earnedStatus.value || undefined }
    })
    commissions.value = data.data ?? []
  } finally { loadingCommissions.value = false }
}

async function fetchSummaries() {
  loadingSummaries.value = true
  try {
    const staffRes = await axios.get('/api/v1/staff')
    const list = staffRes.data.data ?? []
    const results = await Promise.all(
      list.map(s =>
        axios.get(`/api/v1/commissions/users/${s.id}/summary`, { params: { period: summaryMonth.value } })
          .then(r => ({ ...r.data.data, name: s.name }))
          .catch(() => null)
      )
    )
    summaries.value = results.filter(Boolean)
  } finally { loadingSummaries.value = false }
}

async function fetchStaffAndTypes() {
  const [staffRes, typesRes] = await Promise.all([
    axios.get('/api/v1/staff').catch(() => ({ data: { data: [] } })),
    axios.get('/api/v1/loan-types').catch(() => ({ data: { data: [] } })),
  ])
  staffList.value = staffRes.data.data ?? []
  loanTypes.value = typesRes.data.data ?? []
}

function openAddRule() {
  editingRule.value = null
  ruleForm.value = defaultForm()
  ruleError.value = ''
  showRuleModal.value = true
}

function editRule(r) {
  editingRule.value = r
  ruleForm.value = {
    trigger: r.trigger,
    calc_type: r.calc_type,
    user_id: r.user?.id ?? null,
    loan_type_id: r.loan_type?.id ?? null,
    rate: r.rate,
    min_amount: r.min_amount ?? '',
    max_amount: r.max_amount ?? '',
    notes: r.notes ?? '',
    is_active: r.is_active,
  }
  ruleError.value = ''
  showRuleModal.value = true
}

async function saveRule() {
  ruleError.value = ''
  savingRule.value = true
  const payload = {
    trigger:      ruleForm.value.trigger,
    calc_type:    ruleForm.value.calc_type,
    user_id:      ruleForm.value.user_id || null,
    loan_type_id: ruleForm.value.loan_type_id || null,
    rate:         ruleForm.value.rate,
    min_amount:   ruleForm.value.min_amount || null,
    max_amount:   ruleForm.value.max_amount || null,
    notes:        ruleForm.value.notes || null,
    is_active:    ruleForm.value.is_active,
  }
  try {
    if (editingRule.value) {
      await axios.put(`/api/v1/commission-rules/${editingRule.value.id}`, payload)
    } else {
      await axios.post('/api/v1/commission-rules', payload)
    }
    await fetchRules()
    showRuleModal.value = false
  } catch (e) { ruleError.value = e.response?.data?.message ?? 'Failed.' } finally { savingRule.value = false }
}

async function deleteRule(r) {
  if (!confirm('Remove this rule?')) return
  try { await axios.delete(`/api/v1/commission-rules/${r.id}`); await fetchRules() } catch {}
}

async function approvePeriod() {
  approving.value = true
  try {
    await axios.post('/api/v1/commissions/approve-period', { period: earnedMonth.value })
    await fetchCommissions()
  } finally { approving.value = false }
}

async function markPaid(ids) {
  try {
    await axios.post('/api/v1/commissions/mark-paid', { commission_ids: ids })
    await fetchCommissions()
  } catch {}
}

function commStatusClass(s) {
  const map = { pending: 'lendr-badge-neutral', approved: 'lendr-badge-warning', paid: 'lendr-badge-success' }
  return map[s] ?? 'lendr-badge-neutral'
}
function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString() }

onMounted(() => {
  fetchRules()
  fetchCommissions()
  fetchSummaries()
  fetchStaffAndTypes()
})
</script>
