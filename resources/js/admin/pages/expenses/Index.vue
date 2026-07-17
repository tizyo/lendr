<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Expenses</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ expenses.total.toLocaleString() }} total expenses</p>
        </div>
        <button class="btn-primary" @click="showCreate = true">+ New Expense</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3 flex-wrap">
      <div class="flex-1 min-w-48">
        <input
          v-model="filters.search"
          type="search"
          placeholder="Search by number, title, vendor…"
          class="input w-full"
          @input="debouncedSearch"
        />
      </div>
      <select v-model="filters.category_id" class="input sm:w-44" @change="applyFilters">
        <option value="">All categories</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
      </select>
      <select v-model="filters.status" class="input sm:w-44" @change="applyFilters">
        <option value="">All statuses</option>
        <option value="draft">Draft</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="paid">Paid</option>
      </select>
      <input v-model="filters.date_from" type="date" class="input sm:w-36" @change="applyFilters" />
      <input v-model="filters.date_to"   type="date" class="input sm:w-36" @change="applyFilters" />
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 border-b border-neutral-200">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Number</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Title</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Category</th>
            <th class="px-4 py-3 text-right font-medium text-neutral-500">Amount</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Date</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Status</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Submitted By</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="expenses.data.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-neutral-400">No expenses found.</td>
          </tr>
          <tr v-for="exp in expenses.data" :key="exp.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ exp.expense_number }}</td>
            <td class="px-4 py-3 font-medium text-neutral-800 max-w-48 truncate">{{ exp.title }}</td>
            <td class="px-4 py-3">
              <span v-if="exp.category" class="inline-flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full inline-block" :style="{ background: exp.category.colour || '#94a3b8' }"></span>
                {{ exp.category.name }}
              </span>
              <span v-else class="text-neutral-400">—</span>
            </td>
            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ exp.currency }} {{ fmt(exp.amount) }}</td>
            <td class="px-4 py-3 text-neutral-600">{{ exp.expense_date }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(exp.status)">
                {{ exp.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-neutral-600">{{ exp.submitted_by ?? '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <Link :href="route('expenses.show', exp.id)" class="text-xs text-primary-600 hover:underline">View</Link>
                <button
                  v-if="exp.status === 'pending'"
                  class="text-xs text-green-600 hover:underline"
                  @click="openApprove(exp)"
                >Approve</button>
                <button
                  v-if="exp.status === 'pending'"
                  class="text-xs text-red-600 hover:underline"
                  @click="openReject(exp)"
                >Reject</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="expenses.last_page > 1" class="px-4 py-3 border-t border-neutral-200 flex justify-between items-center text-sm text-neutral-600">
        <span>Page {{ expenses.current_page }} of {{ expenses.last_page }}</span>
        <div class="flex gap-2">
          <Link
            v-if="expenses.prev_page_url"
            :href="expenses.prev_page_url"
            class="btn-secondary btn-sm"
          >← Prev</Link>
          <Link
            v-if="expenses.next_page_url"
            :href="expenses.next_page_url"
            class="btn-secondary btn-sm"
          >Next →</Link>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h2 class="text-lg font-semibold mb-4">New Expense</h2>
        <div class="space-y-3">
          <div>
            <label class="label">Title *</label>
            <input v-model="form.title" type="text" class="input w-full" placeholder="Expense title" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label">Category *</label>
              <select v-model="form.expense_category_id" class="input w-full">
                <option value="">Select category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">Amount *</label>
              <input v-model="form.amount" type="number" step="0.01" min="0.01" class="input w-full" placeholder="0.00" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label">Expense Date *</label>
              <input v-model="form.expense_date" type="date" class="input w-full" />
            </div>
            <div>
              <label class="label">Payment Method</label>
              <input v-model="form.payment_method" type="text" class="input w-full" placeholder="e.g. Bank Transfer" />
            </div>
          </div>
          <div>
            <label class="label">Vendor</label>
            <input v-model="form.vendor" type="text" class="input w-full" placeholder="Vendor / payee name" />
          </div>
          <div>
            <label class="label">Receipt Reference</label>
            <input v-model="form.receipt_reference" type="text" class="input w-full" placeholder="Receipt / invoice number" />
          </div>
          <div>
            <label class="label">Description</label>
            <textarea v-model="form.description" rows="2" class="input w-full resize-none" placeholder="Optional notes"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
          <button class="btn-secondary" @click="showCreate = false">Cancel</button>
          <button class="btn-primary" :disabled="saving" @click="submitExpense">
            {{ saving ? 'Saving…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="approving" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
        <h2 class="text-lg font-semibold mb-2">Approve Expense?</h2>
        <p class="text-neutral-600 mb-4 text-sm">
          Approve <strong>{{ approving.expense_number }}</strong> for
          <strong>{{ approving.currency }} {{ fmt(approving.amount) }}</strong>?
          This will debit the fund balance.
        </p>
        <div class="flex justify-center gap-3">
          <button class="btn-secondary" @click="approving = null">Cancel</button>
          <button class="btn-primary bg-green-600 hover:bg-green-700" :disabled="saving" @click="confirmApprove">
            {{ saving ? 'Approving…' : 'Approve' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold mb-2">Reject Expense</h2>
        <p class="text-sm text-neutral-600 mb-3">Provide a reason for rejection.</p>
        <textarea v-model="rejectReason" rows="3" class="input w-full resize-none mb-4" placeholder="Rejection reason…"></textarea>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="rejecting = null">Cancel</button>
          <button class="btn-danger" :disabled="saving || !rejectReason" @click="confirmReject">
            {{ saving ? 'Rejecting…' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  expenses:   { type: Object, required: true },
  categories: { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({}) },
})

const filters = reactive({ ...props.filters })
const showCreate = ref(false)
const saving = ref(false)
const approving = ref(null)
const rejecting = ref(null)
const rejectReason = ref('')

const form = reactive({
  expense_category_id: '',
  title: '',
  description: '',
  amount: '',
  expense_date: new Date().toISOString().slice(0, 10),
  payment_method: '',
  vendor: '',
  receipt_reference: '',
})

const fmt = (n) => Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const statusClass = (status) => ({
  draft:    'bg-neutral-100 text-neutral-600',
  pending:  'bg-amber-100 text-amber-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
  paid:     'bg-blue-100 text-blue-700',
}[status] ?? 'bg-neutral-100 text-neutral-600')

let searchTimer = null
const debouncedSearch = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

const applyFilters = () => {
  router.get(route('expenses.index'), filters, { preserveState: true, replace: true })
}

const submitExpense = async () => {
  saving.value = true
  try {
    await axios.post(route('api.v1.expenses.store'), form)
    showCreate.value = false
    Object.assign(form, { expense_category_id: '', title: '', description: '', amount: '', payment_method: '', vendor: '', receipt_reference: '' })
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to create expense.')
  } finally {
    saving.value = false
  }
}

const openApprove = (exp) => { approving.value = exp }
const openReject  = (exp) => { rejecting.value = exp; rejectReason.value = '' }

const confirmApprove = async () => {
  saving.value = true
  try {
    await axios.post(route('api.v1.expenses.approve', approving.value.id))
    approving.value = null
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
    await axios.post(route('api.v1.expenses.reject', rejecting.value.id), { rejection_reason: rejectReason.value })
    rejecting.value = null
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to reject expense.')
  } finally {
    saving.value = false
  }
}
</script>
