<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <Link :href="route('expenses.index')" class="text-neutral-400 hover:text-neutral-600">← Back</Link>
            <h1 class="text-2xl font-bold text-neutral-900">{{ expense.expense_number }}</h1>
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusClass(expense.status)">
              {{ expense.status }}
            </span>
          </div>
          <p class="text-sm text-neutral-500 mt-0.5 ml-16">{{ expense.title }}</p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="expense.status === 'pending'"
            class="btn-primary bg-green-600 hover:bg-green-700"
            @click="openApprove"
          >Approve</button>
          <button
            v-if="expense.status === 'pending'"
            class="btn-danger"
            @click="openReject"
          >Reject</button>
          <button
            v-if="expense.status === 'draft' || expense.status === 'rejected'"
            class="btn-secondary"
            @click="submitForApproval"
          >Submit for Approval</button>
        </div>
      </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main details -->
      <div class="lg:col-span-2 space-y-6">
        <div class="lendr-card p-6">
          <h2 class="text-base font-semibold text-neutral-800 mb-4">Expense Details</h2>
          <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
              <dt class="text-neutral-500">Amount</dt>
              <dd class="font-semibold text-lg text-neutral-900">{{ expense.currency }} {{ fmt(expense.amount) }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Date</dt>
              <dd class="font-medium text-neutral-800">{{ expense.expense_date }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Category</dt>
              <dd class="font-medium text-neutral-800">
                <span v-if="expense.category" class="inline-flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full" :style="{ background: expense.category.colour || '#94a3b8' }"></span>
                  {{ expense.category.name }}
                </span>
                <span v-else class="text-neutral-400">—</span>
              </dd>
            </div>
            <div>
              <dt class="text-neutral-500">Payment Method</dt>
              <dd class="font-medium text-neutral-800">{{ expense.payment_method || '—' }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Vendor</dt>
              <dd class="font-medium text-neutral-800">{{ expense.vendor || '—' }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Receipt Reference</dt>
              <dd class="font-medium text-neutral-800">{{ expense.receipt_reference || '—' }}</dd>
            </div>
          </dl>
          <div v-if="expense.description" class="mt-4 pt-4 border-t border-neutral-100">
            <dt class="text-neutral-500 text-sm mb-1">Description</dt>
            <dd class="text-sm text-neutral-800">{{ expense.description }}</dd>
          </div>
          <div v-if="expense.rejection_reason" class="mt-4 p-3 bg-red-50 border border-red-100 rounded-lg">
            <p class="text-xs font-medium text-red-700 mb-1">Rejection Reason</p>
            <p class="text-sm text-red-800">{{ expense.rejection_reason }}</p>
          </div>
        </div>

        <!-- Documents -->
        <div class="lendr-card p-6">
          <h2 class="text-base font-semibold text-neutral-800 mb-4">Documents</h2>
          <div v-if="expense.documents.length === 0" class="text-sm text-neutral-400">No documents attached.</div>
          <ul v-else class="divide-y divide-neutral-100">
            <li v-for="doc in expense.documents" :key="doc.id" class="py-3 flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-neutral-800">{{ doc.file_name }}</p>
                <p class="text-xs text-neutral-400">{{ doc.mime_type }} · {{ fmtSize(doc.file_size) }}</p>
              </div>
              <a :href="doc.file_path" target="_blank" class="text-xs text-primary-600 hover:underline">Download</a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <div class="lendr-card p-5">
          <h2 class="text-sm font-semibold text-neutral-700 mb-3">Workflow</h2>
          <dl class="space-y-3 text-sm">
            <div>
              <dt class="text-neutral-500">Submitted By</dt>
              <dd class="font-medium text-neutral-800">{{ expense.submitted_by?.name ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Submitted At</dt>
              <dd class="text-neutral-700">{{ expense.submitted_at ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Approved By</dt>
              <dd class="font-medium text-neutral-800">{{ expense.approved_by?.name ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Approved At</dt>
              <dd class="text-neutral-700">{{ expense.approved_at ?? '—' }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApprove" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
        <h2 class="text-lg font-semibold mb-2">Approve Expense?</h2>
        <p class="text-neutral-600 mb-4 text-sm">
          Approve <strong>{{ expense.expense_number }}</strong> for
          <strong>{{ expense.currency }} {{ fmt(expense.amount) }}</strong>?
          This will debit the fund balance.
        </p>
        <div class="flex justify-center gap-3">
          <button class="btn-secondary" @click="showApprove = false">Cancel</button>
          <button class="btn-primary bg-green-600 hover:bg-green-700" :disabled="saving" @click="confirmApprove">
            {{ saving ? 'Approving…' : 'Approve' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showReject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold mb-2">Reject Expense</h2>
        <p class="text-sm text-neutral-600 mb-3">Provide a reason for rejection.</p>
        <textarea v-model="rejectReason" rows="3" class="input w-full resize-none mb-4" placeholder="Rejection reason…"></textarea>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="showReject = false">Cancel</button>
          <button class="btn-danger" :disabled="saving || !rejectReason" @click="confirmReject">
            {{ saving ? 'Rejecting…' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  expense: { type: Object, required: true },
})

const saving = ref(false)
const showApprove = ref(false)
const showReject  = ref(false)
const rejectReason = ref('')

const fmt = (n) => Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtSize = (bytes) => {
  if (!bytes) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

const statusClass = (status) => ({
  draft:    'bg-neutral-100 text-neutral-600',
  pending:  'bg-amber-100 text-amber-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
  paid:     'bg-blue-100 text-blue-700',
}[status] ?? 'bg-neutral-100 text-neutral-600')

const openApprove = () => { showApprove.value = true }
const openReject  = () => { showReject.value = true; rejectReason.value = '' }

const submitForApproval = async () => {
  saving.value = true
  try {
    await axios.post(route('api.v1.expenses.submit', props.expense.id))
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to submit expense.')
  } finally {
    saving.value = false
  }
}

const confirmApprove = async () => {
  saving.value = true
  try {
    await axios.post(route('api.v1.expenses.approve', props.expense.id))
    showApprove.value = false
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to approve expense.')
  } finally {
    saving.value = false
  }
}

const confirmReject = async () => {
  if (!rejectReason.value) return
  saving.value = true
  try {
    await axios.post(route('api.v1.expenses.reject', props.expense.id), { rejection_reason: rejectReason.value })
    showReject.value = false
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to reject expense.')
  } finally {
    saving.value = false
  }
}
</script>
