<template>
  <AppLayout>
    <template #header>
      <h1 class="text-2xl font-bold text-neutral-900">KYC Documents</h1>
      <p class="text-sm text-neutral-500 mt-0.5">{{ documents.total.toLocaleString() }} total documents</p>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3 flex-wrap">
      <div class="flex-1 min-w-48">
        <input
          v-model="filters.search"
          type="search"
          placeholder="Search borrower name or number…"
          class="input w-full"
          @input="debouncedSearch"
        />
      </div>
      <select v-model="filters.document_type" class="input sm:w-44" @change="applyFilters">
        <option value="">All types</option>
        <option value="national_id_front">National ID Front</option>
        <option value="national_id_back">National ID Back</option>
        <option value="passport">Passport</option>
        <option value="drivers_licence">Driver's Licence</option>
        <option value="utility_bill">Utility Bill</option>
        <option value="bank_statement">Bank Statement</option>
        <option value="selfie">Selfie</option>
        <option value="other">Other</option>
      </select>
      <select v-model="filters.status" class="input sm:w-36" @change="applyFilters">
        <option value="">All statuses</option>
        <option value="pending">Pending</option>
        <option value="verified">Verified</option>
        <option value="rejected">Rejected</option>
        <option value="expired">Expired</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 border-b border-neutral-200">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Borrower</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Document Type</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Status</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Uploaded</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Expires</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="documents.data.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-neutral-400">No documents found.</td>
          </tr>
          <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3">
              <Link :href="route('borrowers.show', doc.borrower.id)" class="font-medium text-primary-600 hover:underline">
                {{ doc.borrower.full_name }}
              </Link>
              <p class="text-xs text-neutral-400 font-mono">{{ doc.borrower.borrower_number }}</p>
            </td>
            <td class="px-4 py-3 text-neutral-700 capitalize">{{ doc.document_type.replace(/_/g, ' ') }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(doc.status)">
                {{ doc.status_label }}
              </span>
            </td>
            <td class="px-4 py-3 text-neutral-500 text-xs">{{ doc.created_at }}</td>
            <td class="px-4 py-3 text-neutral-500 text-xs">{{ doc.expires_at ?? '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <a :href="doc.file_url" target="_blank" class="text-xs text-neutral-500 hover:text-neutral-800 hover:underline">View</a>
                <button
                  v-if="doc.status === 'pending'"
                  class="text-xs text-green-600 hover:underline"
                  @click="openApprove(doc)"
                >Approve</button>
                <button
                  v-if="doc.status === 'pending'"
                  class="text-xs text-red-600 hover:underline"
                  @click="openReject(doc)"
                >Reject</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="documents.last_page > 1" class="px-4 py-3 border-t border-neutral-200 flex justify-between items-center text-sm text-neutral-600">
        <span>Page {{ documents.current_page }} of {{ documents.last_page }}</span>
        <div class="flex gap-2">
          <Link v-if="documents.prev_page_url" :href="documents.prev_page_url" class="btn-secondary btn-sm">← Prev</Link>
          <Link v-if="documents.next_page_url" :href="documents.next_page_url" class="btn-secondary btn-sm">Next →</Link>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="approving" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
        <h2 class="text-lg font-semibold mb-2">Approve Document?</h2>
        <p class="text-sm text-neutral-600 mb-4">
          Approve <strong>{{ approving.document_type.replace(/_/g, ' ') }}</strong> for
          <strong>{{ approving.borrower.full_name }}</strong>?
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
        <h2 class="text-lg font-semibold mb-2">Reject Document</h2>
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
  documents: { type: Object, required: true },
  filters:   { type: Object, default: () => ({}) },
})

const filters     = reactive({ ...props.filters })
const saving      = ref(false)
const approving   = ref(null)
const rejecting   = ref(null)
const rejectReason = ref('')

const statusClass = (status) => ({
  pending:  'bg-amber-100 text-amber-700',
  verified: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
  expired:  'bg-neutral-100 text-neutral-500',
}[status] ?? 'bg-neutral-100 text-neutral-600')

let searchTimer = null
const debouncedSearch = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

const applyFilters = () => {
  router.get(route('kyc.index'), filters, { preserveState: true, replace: true })
}

const openApprove = (doc) => { approving.value = doc }
const openReject  = (doc) => { rejecting.value = doc; rejectReason.value = '' }

const confirmApprove = async () => {
  saving.value = true
  try {
    await axios.put(route('api.v1.kyc.review', approving.value.id), { action: 'approve' })
    approving.value = null
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to approve document.')
  } finally {
    saving.value = false
  }
}

const confirmReject = async () => {
  if (!rejectReason.value) return
  saving.value = true
  try {
    await axios.put(route('api.v1.kyc.review', rejecting.value.id), {
      action:           'reject',
      rejection_reason: rejectReason.value,
    })
    rejecting.value = null
    router.reload()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to reject document.')
  } finally {
    saving.value = false
  }
}
</script>
