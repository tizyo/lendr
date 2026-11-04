<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Approval Workflows</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Configure approval rules and review pending requests</p>
        </div>
        <button v-if="can('approvals.manage')" @click="showWorkflowModal = true" class="lendr-btn-primary">+ New Rule</button>
      </div>
    </template>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-neutral-200 mb-4">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px', activeTab === tab.key ? 'border-primary-500 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700']">
        {{ tab.label }}
        <span v-if="tab.key === 'pending' && pendingCount > 0" class="ml-1.5 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">{{ pendingCount }}</span>
      </button>
    </div>

    <!-- Pending Requests -->
    <div v-if="activeTab === 'pending'">
      <div v-if="loadingPending" class="text-center py-10 text-neutral-400">Loading…</div>
      <div v-else-if="pending.length === 0" class="lendr-card p-10 text-center text-neutral-400">No pending approvals.</div>
      <div v-else class="space-y-3">
        <div v-for="req in pending" :key="req.id" class="lendr-card p-4 flex items-center gap-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="lendr-badge lendr-badge-warning text-xs">{{ req.entity_label }}</span>
              <a v-if="req.subject?.url" :href="req.subject.url" class="text-sm font-medium text-primary-600 hover:underline">{{ req.subject.title }}</a>
              <span v-else class="text-sm font-medium text-neutral-900">{{ req.subject?.title }}</span>
              <span v-if="req.subject?.amount != null" class="text-sm text-neutral-600">K {{ Number(req.subject.amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
            </div>
            <p class="text-xs text-neutral-500 mt-0.5">
              Submitted by {{ req.submitted_by_name ?? 'staff #' + req.submitted_by }} · {{ formatDate(req.created_at) }}
              · {{ approvalsSoFar(req) }} of {{ req.required_approvals ?? 1 }} approval{{ (req.required_approvals ?? 1) === 1 ? '' : 's' }}
              <template v-if="req.required_roles?.length"> · {{ req.required_roles.map(roleLabel).join(', ') }}</template>
            </p>
            <p v-if="req.notes" class="text-xs text-neutral-600 mt-1 italic">{{ req.notes }}</p>
          </div>
          <div v-if="can('approvals.act')" class="flex gap-2 shrink-0">
            <button @click="openDecision(req, 'approve')" class="px-3 py-1.5 rounded-lg bg-green-500 text-white text-xs font-medium hover:bg-green-600">Approve</button>
            <button @click="openDecision(req, 'reject')" class="px-3 py-1.5 rounded-lg bg-red-500 text-white text-xs font-medium hover:bg-red-600">Reject</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Workflow Rules -->
    <div v-if="activeTab === 'workflows'">
      <div v-if="loadingWorkflows" class="text-center py-10 text-neutral-400">Loading…</div>
      <div v-else-if="workflows.length === 0" class="lendr-card p-10 text-center text-neutral-400">No workflow rules configured.</div>
      <div v-else class="lendr-card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-left">Entity Type</th>
              <th class="px-4 py-3 text-left">Amount Range</th>
              <th class="px-4 py-3 text-left">Required Roles</th>
              <th class="px-4 py-3 text-right">Min Approvals</th>
              <th class="px-4 py-3 text-center">Active</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-for="wf in workflows" :key="wf.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-medium">{{ wf.name }}</td>
              <td class="px-4 py-3 text-neutral-600">{{ wf.entity_label ?? wf.entity_type }}</td>
              <td class="px-4 py-3 text-neutral-600 text-xs">
                {{ wf.min_amount ? 'K ' + Number(wf.min_amount).toLocaleString() : 'Any' }}
                {{ wf.max_amount ? ' – K ' + Number(wf.max_amount).toLocaleString() : '+' }}
              </td>
              <td class="px-4 py-3 text-neutral-600 text-xs">{{ (wf.required_roles ?? []).map(roleLabel).join(', ') }}</td>
              <td class="px-4 py-3 text-right">{{ wf.required_approvals }}</td>
              <td class="px-4 py-3 text-center">
                <button v-if="can('approvals.manage')" type="button" @click="toggleWorkflow(wf)"
                  :class="wf.is_active ? 'lendr-badge-success' : 'lendr-badge-neutral'" class="lendr-badge text-xs cursor-pointer"
                  :title="wf.is_active ? 'Click to switch off' : 'Click to switch on'">{{ wf.is_active ? 'Active' : 'Off' }}</button>
                <span v-else :class="wf.is_active ? 'lendr-badge-success' : 'lendr-badge-neutral'" class="lendr-badge text-xs">{{ wf.is_active ? 'Active' : 'Off' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Decision Modal -->
    <div v-if="decisionModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="font-bold text-lg capitalize">{{ decisionAction }} Request</h2>
        <textarea v-model="decisionNotes" class="lendr-input w-full" rows="3" placeholder="Add notes (optional)…"></textarea>
        <div class="flex gap-3 justify-end">
          <button @click="decisionModal = null" class="lendr-btn-ghost">Cancel</button>
          <button @click="submitDecision" :disabled="deciding"
            :class="decisionAction === 'approve' ? 'lendr-btn-primary' : 'bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium'">
            {{ deciding ? 'Processing…' : decisionAction === 'approve' ? 'Approve' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>

    <!-- New Workflow Modal -->
    <div v-if="showWorkflowModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="font-bold text-lg">New Approval Rule</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="lendr-label">Rule Name *</label>
            <input v-model="wfForm.name" class="lendr-input w-full" placeholder="e.g. Large Disbursement Approval" />
          </div>
          <div>
            <label class="lendr-label">Applies to *</label>
            <select v-model="wfForm.entity_type" class="lendr-input w-full">
              <option value="loan_approval">Loan approval</option>
              <option value="loan_disbursement">Loan disbursement</option>
              <option value="expense">Expense approval</option>
              <option value="write_off">Loan write-off</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Min Approvals</label>
            <input v-model.number="wfForm.required_approvals" type="number" min="1" class="lendr-input w-full" placeholder="1" />
          </div>
          <div>
            <label class="lendr-label">Min Amount</label>
            <input v-model.number="wfForm.min_amount" type="number" min="0" class="lendr-input w-full" placeholder="0" />
          </div>
          <div>
            <label class="lendr-label">Max Amount (blank = no limit)</label>
            <input v-model.number="wfForm.max_amount" type="number" min="0" class="lendr-input w-full" placeholder="Leave blank" />
          </div>
          <div class="col-span-2">
            <label class="lendr-label">Who can approve *</label>
            <div class="grid grid-cols-2 gap-2 mt-1">
              <label v-for="role in roleOptions" :key="role.value" class="flex items-center gap-2 text-sm text-neutral-700">
                <input type="checkbox" :value="role.value" v-model="wfForm.required_roles" class="rounded border-neutral-300" />
                {{ role.label }}
              </label>
            </div>
            <p class="text-xs text-neutral-500 mt-1">Each approval must come from a different person, and never the person who asked.</p>
          </div>
          <div class="col-span-2">
            <label class="lendr-label">Description</label>
            <input v-model="wfForm.description" class="lendr-input w-full" />
          </div>
        </div>
        <p v-if="wfError" class="text-red-500 text-sm">{{ wfError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showWorkflowModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="saveWorkflow" :disabled="savingWf" class="lendr-btn-primary">{{ savingWf ? 'Saving…' : 'Save Rule' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'
import { useCan } from '@/admin/composables/useCan.js'
import { pushToast } from '@/admin/composables/useToast.js'

const can = useCan()

const roleOptions = [
  { value: 'super_admin', label: 'Super Admin' },
  { value: 'branch_manager', label: 'Branch Manager' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'loan_officer', label: 'Loan Officer' },
  { value: 'cashier', label: 'Cashier' },
  { value: 'auditor', label: 'Auditor' },
]
const roleLabel = (value) => roleOptions.find(r => r.value === value)?.label ?? value
const approvalsSoFar = (req) => new Set((req.actions ?? []).filter(a => a.action === 'approve').map(a => a.actor_id)).size
const errorMessage = (e, fallback) => e.response?.data?.errors
  ? Object.values(e.response.data.errors).flat()[0]
  : (e.response?.data?.message ?? fallback)

const activeTab = ref('pending')
const tabs = [{ key: 'pending', label: 'Pending Requests' }, { key: 'workflows', label: 'Workflow Rules' }]

const pending = ref([])
const workflows = ref([])
const loadingPending = ref(false)
const loadingWorkflows = ref(false)
const pendingCount = computed(() => pending.value.length)

const decisionModal = ref(null)
const decisionAction = ref('')
const decisionNotes = ref('')
const deciding = ref(false)

const showWorkflowModal = ref(false)
const wfForm = ref({
  name: '',
  entity_type: 'loan_disbursement',
  min_amount: '',
  max_amount: '',
  required_approvals: 1,
  required_roles: ['branch_manager', 'super_admin'],
  description: '',
})
const savingWf = ref(false)
const wfError = ref('')

async function fetchPending() {
  loadingPending.value = true
  try {
    const { data } = await axios.get('/api/v1/approvals/pending')
    pending.value = data.data ?? []
  } finally { loadingPending.value = false }
}

async function fetchWorkflows() {
  loadingWorkflows.value = true
  try {
    const { data } = await axios.get('/api/v1/approvals/workflows')
    workflows.value = data.data ?? []
  } finally { loadingWorkflows.value = false }
}

function openDecision(req, action) {
  decisionModal.value = req
  decisionAction.value = action
  decisionNotes.value = ''
}

async function submitDecision() {
  deciding.value = true
  try {
    const url = `/api/v1/approvals/${decisionModal.value.id}/${decisionAction.value}`
    const { data } = await axios.post(url, { notes: decisionNotes.value })
    const updated = data.data?.request
    if (updated?.status === 'pending') {
      // Needs more approvers: keep it listed with the new count.
      pending.value = pending.value.map(r => r.id === updated.id ? { ...r, actions: updated.actions } : r)
      pushToast('success', 'Approval recorded. It still needs another approver.')
    } else {
      pending.value = pending.value.filter(r => r.id !== decisionModal.value.id)
      pushToast('success', decisionAction.value === 'approve' ? 'Request approved.' : 'Request rejected.')
    }
    decisionModal.value = null
  } catch (e) {
    pushToast('error', errorMessage(e, 'Could not record the decision.'))
  } finally { deciding.value = false }
}

async function toggleWorkflow(wf) {
  try {
    await axios.put(`/api/v1/approvals/workflows/${wf.id}`, { is_active: !wf.is_active })
    wf.is_active = !wf.is_active
  } catch (e) {
    pushToast('error', errorMessage(e, 'Could not update the rule.'))
  }
}

async function saveWorkflow() {
  wfError.value = ''
  if (!wfForm.value.required_roles.length) {
    wfError.value = 'Choose at least one role that can approve.'
    return
  }
  savingWf.value = true
  const payload = {
    name: wfForm.value.name,
    entity_type: wfForm.value.entity_type,
    min_amount: wfForm.value.min_amount || null,
    max_amount: wfForm.value.max_amount || null,
    required_approvals: wfForm.value.required_approvals,
    required_roles: wfForm.value.required_roles,
    description: wfForm.value.description || null,
  }
  try {
    await axios.post('/api/v1/approvals/workflows', payload)
    await fetchWorkflows()
    showWorkflowModal.value = false
  } catch (e) {
    wfError.value = errorMessage(e, 'Failed to save.')
  } finally { savingWf.value = false }
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '' }

onMounted(() => { fetchPending(); fetchWorkflows() })
</script>
