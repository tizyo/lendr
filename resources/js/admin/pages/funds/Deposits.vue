<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Capital Deposits</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ deposits.total.toLocaleString() }} total deposits</p>
        </div>
        <button class="btn-primary" @click="showCreate = true">+ New Deposit</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <div class="flex-1">
        <input
          v-model="filters.search"
          type="search"
          placeholder="Search by reference or source…"
          class="input w-full"
          @input="debouncedSearch"
        />
      </div>
      <select v-model="filters.status" class="input sm:w-44" @change="applyFilters">
        <option value="">All statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 border-b border-neutral-200">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Reference</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Source</th>
            <th class="px-4 py-3 text-right font-medium text-neutral-500">Amount</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Date</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Method</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Status</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Deposited By</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-for="dep in deposits.data" :key="dep.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ dep.reference }}</td>
            <td class="px-4 py-3 font-medium text-neutral-800">{{ dep.source }}</td>
            <td class="px-4 py-3 text-right font-semibold text-neutral-800">{{ fmt(dep.amount) }}</td>
            <td class="px-4 py-3 text-neutral-600">{{ dep.deposit_date }}</td>
            <td class="px-4 py-3 text-neutral-500 capitalize">{{ dep.payment_method.replace('_', ' ') }}</td>
            <td class="px-4 py-3">
              <span
                class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="{
                  'bg-amber-100 text-amber-700': dep.status === 'pending',
                  'bg-emerald-100 text-emerald-700': dep.status === 'approved',
                  'bg-red-100 text-red-600': dep.status === 'rejected',
                }"
              >
                {{ dep.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-neutral-500 text-xs">{{ dep.deposited_by ?? '—' }}</td>
            <td class="px-4 py-3">
              <div v-if="dep.status === 'pending'" class="flex gap-2">
                <button
                  class="text-xs text-emerald-600 hover:text-emerald-700 font-medium"
                  @click="openApprove(dep)"
                >
                  Approve
                </button>
                <button
                  class="text-xs text-red-500 hover:text-red-600 font-medium"
                  @click="openReject(dep)"
                >
                  Reject
                </button>
              </div>
              <span v-else class="text-xs text-neutral-400">—</span>
            </td>
          </tr>
          <tr v-if="!deposits.data.length">
            <td colspan="8" class="px-4 py-10 text-center text-neutral-400">No deposits found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="deposits.last_page > 1" class="flex justify-center gap-1 mt-4">
      <Link
        v-for="link in deposits.links"
        :key="link.label"
        :href="link.url ?? ''"
        :class="[
          'px-3 py-1.5 text-sm rounded',
          link.active ? 'bg-sky-600 text-white' : 'bg-white border border-neutral-200 text-neutral-600',
          !link.url ? 'opacity-40 pointer-events-none' : 'hover:bg-neutral-50',
        ]"
        v-html="link.label"
      />
    </div>

    <!-- Create Deposit Modal -->
    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-neutral-800 mb-4">Record Capital Deposit</h2>
        <form class="space-y-4" @submit.prevent="submitDeposit">
          <div>
            <label class="label">Source / Investor</label>
            <input v-model="form.source" class="input w-full" placeholder="e.g. ABC Capital Ltd" required />
          </div>
          <div>
            <label class="label">Amount (ZMW)</label>
            <input v-model="form.amount" type="number" step="0.01" min="0.01" class="input w-full" required />
          </div>
          <div>
            <label class="label">Payment Method</label>
            <select v-model="form.payment_method" class="input w-full" required>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cash">Cash</option>
              <option value="cheque">Cheque</option>
            </select>
          </div>
          <div>
            <label class="label">Bank Reference</label>
            <input v-model="form.bank_reference" class="input w-full" placeholder="Transaction ref (optional)" />
          </div>
          <div>
            <label class="label">Deposit Date</label>
            <input v-model="form.deposit_date" type="date" class="input w-full" required />
          </div>
          <div>
            <label class="label">Notes</label>
            <textarea v-model="form.notes" class="input w-full" rows="2" />
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="showCreate = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving…' : 'Save Deposit' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="approving" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold text-neutral-800 mb-2">Approve Deposit</h2>
        <p class="text-sm text-neutral-500 mb-4">
          Approve <strong>{{ approving.reference }}</strong> for
          <strong>ZMW {{ fmt(approving.amount) }}</strong>?
          This will credit the fund balance immediately.
        </p>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="approving = null">Cancel</button>
          <button class="btn-primary bg-emerald-600 hover:bg-emerald-700" :disabled="saving" @click="confirmApprove">
            {{ saving ? 'Approving…' : 'Yes, Approve' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold text-neutral-800 mb-2">Reject Deposit</h2>
        <p class="text-sm text-neutral-500 mb-3">Provide a reason for rejecting <strong>{{ rejecting.reference }}</strong>.</p>
        <textarea v-model="rejectReason" class="input w-full mb-4" rows="3" placeholder="Reason for rejection…" required />
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="rejecting = null">Cancel</button>
          <button class="btn-primary bg-red-600 hover:bg-red-700" :disabled="saving || !rejectReason" @click="confirmReject">
            {{ saving ? 'Rejecting…' : 'Reject Deposit' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  deposits: Object,
  filters: Object,
})

const filters = reactive({ ...props.filters })

const showCreate = ref(false)
const saving = ref(false)
const approving = ref(null)
const rejecting = ref(null)
const rejectReason = ref('')

const form = reactive({
  source: '',
  amount: '',
  payment_method: 'bank_transfer',
  bank_reference: '',
  deposit_date: new Date().toISOString().slice(0, 10),
  notes: '',
})

const fmt = (n) => Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

let searchTimer = null
const debouncedSearch = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

const applyFilters = () => {
  router.get(route('funds.deposits.index'), filters, { preserveState: true, replace: true })
}

const submitDeposit = async () => {
  saving.value = true
  try {
    await axios.post(route('api.v1.funds.deposits.store'), form)
    showCreate.value = false
    Object.assign(form, { source: '', amount: '', payment_method: 'bank_transfer', bank_reference: '', notes: '' })
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to save deposit.')
  } finally {
    saving.value = false
  }
}

const openApprove = (dep) => { approving.value = dep }
const openReject  = (dep) => { rejecting.value = dep; rejectReason.value = '' }

const confirmApprove = async () => {
  saving.value = true
  try {
    await axios.post(route('api.v1.funds.deposits.approve', approving.value.id))
    approving.value = null
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to approve deposit.')
  } finally {
    saving.value = false
  }
}

const confirmReject = async () => {
  if (!rejectReason.value) return
  saving.value = true
  try {
    await axios.post(route('api.v1.funds.deposits.reject', rejecting.value.id), { reason: rejectReason.value })
    rejecting.value = null
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to reject deposit.')
  } finally {
    saving.value = false
  }
}
</script>
