<template>
  <AppLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">Bank Deposits</h1>
        <p class="text-sm text-neutral-500 mt-0.5">
          Deposit slips uploaded by borrowers. Check each one against the bank statement — approving records the payment and sends the receipt.
        </p>
      </div>
    </template>

    <!-- Status tabs -->
    <div class="flex gap-1 mb-4 border-b border-neutral-200">
      <button v-for="t in tabs" :key="t.id" @click="setStatus(t.id)"
              class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition"
              :class="status === t.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-neutral-500 hover:text-neutral-700'">
        {{ t.label }}
        <span v-if="t.id === 'pending' && pendingCount" class="ml-1.5 px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs">{{ pendingCount }}</span>
      </button>
    </div>

    <div v-if="loading" class="lendr-card p-10 text-center text-sm text-neutral-400">Loading…</div>

    <div v-else-if="!proofs.length" class="lendr-card p-10 text-center">
      <p class="text-sm text-neutral-500">{{ status === 'pending' ? 'No deposits waiting for review.' : 'Nothing here yet.' }}</p>
    </div>

    <div v-else class="space-y-3">
      <div v-for="p in proofs" :key="p.id" class="lendr-card p-5 flex flex-col md:flex-row md:items-center gap-4">
        <div class="flex-1 min-w-0 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-2 text-sm">
          <div>
            <p class="text-xs text-neutral-400">Borrower</p>
            <p class="font-medium text-neutral-900 truncate">{{ p.borrower?.name }}</p>
            <p class="text-xs text-neutral-500">{{ p.borrower?.phone }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-400">Loan</p>
            <p class="font-medium text-neutral-900">{{ p.loan?.loan_number }}</p>
            <p class="text-xs text-neutral-500">Owes K {{ fmt(p.loan?.outstanding) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-400">Deposited</p>
            <p class="font-semibold text-neutral-900">K {{ fmt(p.amount) }}</p>
            <p class="text-xs text-neutral-500">{{ p.paid_on }}<span v-if="p.bank_reference"> · Ref {{ p.bank_reference }}</span></p>
          </div>
          <div>
            <p class="text-xs text-neutral-400">Status</p>
            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(p.status)">{{ statusLabel(p.status) }}</span>
            <p v-if="p.receipt_number" class="text-xs text-neutral-500 mt-0.5">Receipt {{ p.receipt_number }}</p>
            <p v-if="p.rejection_reason" class="text-xs text-red-600 mt-0.5">{{ p.rejection_reason }}</p>
            <p v-if="p.reviewed_by" class="text-xs text-neutral-400 mt-0.5">by {{ p.reviewed_by }}</p>
          </div>
        </div>

        <div class="flex gap-2 shrink-0">
          <button @click="viewSlip(p)" class="btn-secondary text-sm">View slip</button>
          <template v-if="p.status === 'pending'">
            <button @click="openApprove(p)" class="btn-primary text-sm">Approve</button>
            <button @click="openReject(p)" class="text-sm px-3 py-2 rounded-lg text-red-600 hover:bg-red-50">Reject</button>
          </template>
        </div>
      </div>
    </div>

    <!-- Approve -->
    <div v-if="approving" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="approving = null">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-neutral-900">Record this deposit as a payment</h2>
        <p class="text-sm text-neutral-500">
          Enter the amount and date exactly as they appear on the bank statement for {{ approving.loan?.loan_number }}.
        </p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">Amount (ZMW)</label>
            <input v-model.number="approveForm.amount" type="number" step="0.01" min="0.01" class="input w-full" />
          </div>
          <div>
            <label class="label">Date received</label>
            <input v-model="approveForm.paid_on" type="date" class="input w-full" />
          </div>
        </div>
        <label class="flex items-start gap-2 text-sm text-neutral-700">
          <input v-model="approveForm.confirmed" type="checkbox" class="mt-0.5 rounded" />
          I've found this deposit on the bank statement.
        </label>
        <p v-if="actionError" class="text-sm text-red-600">{{ actionError }}</p>
        <div class="flex justify-end gap-2 pt-2">
          <button @click="approving = null" class="btn-secondary">Cancel</button>
          <button @click="approve" :disabled="busy || !approveForm.confirmed || !approveForm.amount" class="btn-primary">
            {{ busy ? 'Recording…' : 'Record payment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reject -->
    <div v-if="rejecting" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="rejecting = null">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-neutral-900">Reject this deposit slip</h2>
        <p class="text-sm text-neutral-500">The borrower is notified with your reason, so make it something they can act on.</p>
        <textarea v-model="rejectReason" rows="3" maxlength="500" class="input w-full"
                  placeholder="e.g. We can't find this deposit on our statement — please check the account number." />
        <p v-if="actionError" class="text-sm text-red-600">{{ actionError }}</p>
        <div class="flex justify-end gap-2 pt-2">
          <button @click="rejecting = null" class="btn-secondary">Cancel</button>
          <button @click="reject" :disabled="busy || !rejectReason.trim()" class="btn-primary bg-red-600 hover:bg-red-700">
            {{ busy ? 'Rejecting…' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const tabs = [
  { id: 'pending',  label: 'To review' },
  { id: 'approved', label: 'Approved' },
  { id: 'rejected', label: 'Rejected' },
  { id: 'all',      label: 'All' },
]

const status = ref('pending')
const proofs = ref([])
const pendingCount = ref(0)
const loading = ref(false)
const busy = ref(false)
const actionError = ref('')

const approving = ref(null)
const approveForm = reactive({ amount: '', paid_on: '', confirmed: false })
const rejecting = ref(null)
const rejectReason = ref('')

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(route('api.v1.payment-proofs.index'), { params: { status: status.value } })
    proofs.value = data.data.data
    pendingCount.value = data.data.pending_count
  } finally {
    loading.value = false
  }
}

function setStatus(id) {
  status.value = id
  load()
}

async function viewSlip(p) {
  // Fetched with the staff session, then shown from memory — the file has no public URL.
  const { data } = await axios.get(p.file_url, { responseType: 'blob' })
  window.open(URL.createObjectURL(data), '_blank')
}

function openApprove(p) {
  actionError.value = ''
  Object.assign(approveForm, { amount: p.amount, paid_on: p.paid_on, confirmed: false })
  approving.value = p
}

async function approve() {
  busy.value = true
  actionError.value = ''
  try {
    await axios.post(route('api.v1.payment-proofs.approve', approving.value.id), {
      amount: approveForm.amount,
      paid_on: approveForm.paid_on,
    })
    approving.value = null
    load()
  } catch (e) {
    actionError.value = firstError(e)
  } finally {
    busy.value = false
  }
}

function openReject(p) {
  actionError.value = ''
  rejectReason.value = ''
  rejecting.value = p
}

async function reject() {
  busy.value = true
  actionError.value = ''
  try {
    await axios.post(route('api.v1.payment-proofs.reject', rejecting.value.id), { reason: rejectReason.value })
    rejecting.value = null
    load()
  } catch (e) {
    actionError.value = firstError(e)
  } finally {
    busy.value = false
  }
}

function firstError(e) {
  const errors = e.response?.data?.errors
  return errors ? Object.values(errors)[0][0] : (e.response?.data?.message ?? 'Something went wrong.')
}

const fmt = (n) => Number(n ?? 0).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const statusLabel = (s) => ({ pending: 'To review', approved: 'Approved', rejected: 'Rejected' }[s] ?? s)
const statusClass = (s) => ({
  pending:  'bg-amber-100 text-amber-700',
  approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-red-100 text-red-700',
}[s] ?? 'bg-neutral-100 text-neutral-600')
</script>
