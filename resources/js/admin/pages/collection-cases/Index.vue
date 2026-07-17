<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Collection Cases</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Escalation rules and automated collection case management</p>
        </div>
        <button @click="openAddRule" class="lendr-btn-primary">+ Escalation Rule</button>
      </div>
    </template>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-neutral-200 mb-4">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px', activeTab === tab.key ? 'border-primary-500 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700']">
        {{ tab.label }}
        <span v-if="tab.key === 'cases' && openCases > 0" class="ml-1.5 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">{{ openCases }}</span>
      </button>
    </div>

    <!-- Escalation Rules -->
    <div v-if="activeTab === 'rules'">
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingRules" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-right">DPD Trigger</th>
              <th class="px-4 py-3 text-left">Action</th>
              <th class="px-4 py-3 text-left">Assign To</th>
              <th class="px-4 py-3 text-center">Active</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="rules.length === 0"><td colspan="6" class="px-4 py-10 text-center text-neutral-400">No escalation rules configured.</td></tr>
            <tr v-for="r in rules" :key="r.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-medium">{{ r.name }}</td>
              <td class="px-4 py-3 text-right font-semibold text-red-600">{{ r.dpd_threshold }}+ days</td>
              <td class="px-4 py-3 capitalize text-neutral-600">{{ r.action?.replace('_', ' ') }}</td>
              <td class="px-4 py-3 capitalize text-neutral-500">{{ r.assigned_to ?? '—' }}</td>
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

    <!-- Open Cases -->
    <div v-if="activeTab === 'cases'">
      <div class="flex gap-2 mb-4">
        <select v-model="caseStatus" @change="fetchCases" class="lendr-input text-sm w-36">
          <option value="">All Status</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="written_off">Written Off</option>
        </select>
      </div>
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingCases" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Loan</th>
              <th class="px-4 py-3 text-left">Borrower</th>
              <th class="px-4 py-3 text-right">Outstanding</th>
              <th class="px-4 py-3 text-right">DPD</th>
              <th class="px-4 py-3 text-left">Assigned To</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="cases.length === 0"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">No collection cases found.</td></tr>
            <tr v-for="c in cases" :key="c.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-mono text-xs">{{ c.loan?.loan_number ?? '—' }}</td>
              <td class="px-4 py-3 text-neutral-700">{{ c.borrower?.full_name ?? '—' }}</td>
              <td class="px-4 py-3 text-right font-medium">{{ fmt(c.outstanding_balance) }}</td>
              <td class="px-4 py-3 text-right font-bold text-red-600">{{ c.dpd_at_creation }}</td>
              <td class="px-4 py-3 text-neutral-500 capitalize">{{ c.assigned_to ?? 'Unassigned' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="caseStatusClass(c.status)" class="lendr-badge text-xs capitalize">{{ c.status?.replace('_', ' ') }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="openCaseDetail(c)" class="text-primary-600 text-xs hover:underline">Manage</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Case Detail Drawer -->
    <div v-if="activeCase" class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4 max-h-[85vh] overflow-y-auto">
        <div class="flex justify-between items-center">
          <h2 class="font-bold text-lg">Case — {{ activeCase.loan?.loan_number ?? `#${activeCase.id}` }}</h2>
          <button @click="activeCase = null" class="text-neutral-400 hover:text-neutral-700 text-xl">&times;</button>
        </div>
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div><p class="text-xs text-neutral-500">Borrower</p><p class="font-medium">{{ activeCase.borrower?.full_name ?? '—' }}</p></div>
          <div><p class="text-xs text-neutral-500">Outstanding</p><p class="font-medium">{{ fmt(activeCase.outstanding_balance) }}</p></div>
          <div><p class="text-xs text-neutral-500">DPD at Escalation</p><p class="font-bold text-red-600">{{ activeCase.dpd_at_creation }} days</p></div>
          <div><p class="text-xs text-neutral-500">Status</p><p class="capitalize">{{ activeCase.status?.replace('_', ' ') }}</p></div>
        </div>
        <!-- Update Status -->
        <div>
          <label class="lendr-label">Update Status</label>
          <div class="flex gap-2">
            <select v-model="caseUpdate" class="lendr-input flex-1 text-sm">
              <option value="">No change</option>
              <option value="promised">Promised to Pay</option>
              <option value="escalated">Escalated</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
            <button @click="updateCase" :disabled="updatingCase" class="lendr-btn-primary text-sm">{{ updatingCase ? '…' : 'Update' }}</button>
          </div>
        </div>
        <!-- Promise to Pay -->
        <div>
          <h3 class="font-semibold text-sm mb-2">Promises to Pay</h3>
          <div v-if="loadingPromises" class="text-center py-3 text-neutral-400 text-sm">Loading…</div>
          <div v-else-if="promises.length === 0" class="text-neutral-400 text-sm py-2">No promises recorded.</div>
          <div v-else class="space-y-2">
            <div v-for="p in promises" :key="p.id" class="flex items-center justify-between text-sm border rounded-lg p-2">
              <span class="text-neutral-700">{{ p.promise_date }} — {{ fmt(p.promise_amount) }}</span>
              <span :class="p.status === 'fulfilled' ? 'text-green-600' : 'text-amber-600'" class="text-xs font-medium capitalize">{{ p.status }}</span>
            </div>
          </div>
          <div class="flex gap-2 mt-2">
            <input v-model="newPromise.promise_date" type="date" class="lendr-input text-sm flex-1" />
            <input v-model="newPromise.promise_amount" type="number" placeholder="Amount" class="lendr-input text-sm w-32" />
            <button @click="addPromise" class="lendr-btn-primary text-sm">Add</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Rule Modal -->
    <div v-if="showRuleModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="font-bold text-lg">{{ editingRule ? 'Edit' : 'Add' }} Escalation Rule</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2"><label class="lendr-label">Rule Name *</label><input v-model="ruleForm.name" class="lendr-input w-full" /></div>
          <div><label class="lendr-label">DPD Threshold (days)</label><input v-model="ruleForm.dpd_threshold" type="number" class="lendr-input w-full" /></div>
          <div>
            <label class="lendr-label">Action</label>
            <select v-model="ruleForm.action" class="lendr-input w-full">
              <option value="assign_collector">Assign Collector</option>
              <option value="field_visit">Field Visit</option>
              <option value="legal_action">Legal Action</option>
              <option value="write_off_notice">Write-Off Notice</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Assign To (User ID, optional)</label>
            <input v-model="ruleForm.assigned_to" type="number" class="lendr-input w-full" placeholder="User ID or leave blank" />
          </div>
          <div class="flex items-end pb-1"><label class="flex items-center gap-2 cursor-pointer"><input v-model="ruleForm.is_active" type="checkbox" class="rounded" /><span class="text-sm">Active</span></label></div>
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
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const activeTab = ref('rules')
const tabs = [{ key: 'rules', label: 'Escalation Rules' }, { key: 'cases', label: 'Open Cases' }]

const rules = ref([])
const cases = ref([])
const loadingRules = ref(false)
const loadingCases = ref(false)
const caseStatus = ref('open')

const openCases = computed(() => cases.value.filter(c => c.status === 'open').length)

const activeCase = ref(null)
const promises = ref([])
const loadingPromises = ref(false)
const caseUpdate = ref('')
const updatingCase = ref(false)
const newPromise = ref({ promise_date: new Date().toISOString().slice(0, 10), promise_amount: '' })

const showRuleModal = ref(false)
const editingRule = ref(null)
const savingRule = ref(false)
const ruleError = ref('')
const ruleForm = ref({ name: '', dpd_threshold: 30, action: 'assign_collector', assigned_to: '', is_active: true })

async function fetchRules() {
  loadingRules.value = true
  try {
    const { data } = await axios.get('/api/v1/escalation-rules')
    rules.value = data.data ?? []
  } finally { loadingRules.value = false }
}

async function fetchCases() {
  loadingCases.value = true
  try {
    const { data } = await axios.get('/api/v1/collection-cases', { params: { status: caseStatus.value || undefined } })
    cases.value = data.data ?? []
  } finally { loadingCases.value = false }
}

async function openCaseDetail(c) {
  activeCase.value = c
  caseUpdate.value = ''
  loadingPromises.value = true
  promises.value = []
  try {
    const { data } = await axios.get(`/api/v1/collection-cases/${c.id}/promises`)
    promises.value = data.data ?? []
  } finally { loadingPromises.value = false }
}

async function updateCase() {
  if (!caseUpdate.value) return
  updatingCase.value = true
  try {
    await axios.put(`/api/v1/collection-cases/${activeCase.value.id}`, { status: caseUpdate.value })
    await fetchCases()
    activeCase.value = null
  } finally { updatingCase.value = false }
}

async function addPromise() {
  if (!newPromise.value.promise_amount) return
  try {
    await axios.post(`/api/v1/collection-cases/${activeCase.value.id}/promises`, { ...newPromise.value })
    const { data } = await axios.get(`/api/v1/collection-cases/${activeCase.value.id}/promises`)
    promises.value = data.data ?? []
    newPromise.value.amount = ''
  } catch {}
}

function openAddRule() {
  editingRule.value = null
  ruleForm.value = { name: '', dpd_threshold: 30, action: 'assign_collector', assigned_to: '', is_active: true }
  ruleError.value = ''
  showRuleModal.value = true
}

function editRule(r) {
  editingRule.value = r
  ruleForm.value = { name: r.name, dpd_threshold: r.dpd_threshold, action: r.action, assigned_to: r.assigned_to ?? '', is_active: r.is_active }
  ruleError.value = ''
  showRuleModal.value = true
}

async function saveRule() {
  ruleError.value = ''
  savingRule.value = true
  const payload = { ...ruleForm.value, assigned_to: ruleForm.value.assigned_to || null }
  try {
    if (editingRule.value) {
      await axios.put(`/api/v1/escalation-rules/${editingRule.value.id}`, payload)
    } else {
      await axios.post('/api/v1/escalation-rules', payload)
    }
    await fetchRules()
    showRuleModal.value = false
  } catch (e) { ruleError.value = e.response?.data?.message ?? 'Failed.' } finally { savingRule.value = false }
}

async function deleteRule(r) {
  if (!confirm('Remove this rule?')) return
  try { await axios.delete(`/api/v1/escalation-rules/${r.id}`); await fetchRules() } catch {}
}

function caseStatusClass(s) {
  const map = { open: 'lendr-badge-danger', in_progress: 'lendr-badge-warning', resolved: 'lendr-badge-success', written_off: 'lendr-badge-neutral' }
  return map[s] ?? 'lendr-badge-neutral'
}
function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString() }

onMounted(() => { fetchRules(); fetchCases() })
</script>
