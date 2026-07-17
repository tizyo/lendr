<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
          <Link :href="route('borrowers.index')" class="text-neutral-400 hover:text-neutral-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
          </Link>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="text-2xl font-bold text-neutral-900">{{ borrower.full_name }}</h1>
              <span v-if="borrower.is_blacklisted" class="lendr-badge-danger">Blacklisted</span>
              <span v-else-if="!borrower.is_active" class="lendr-badge-neutral">Inactive</span>
              <span v-if="borrower.kyc_verified" class="lendr-badge-info">KYC Verified</span>
            </div>
            <p class="text-sm text-neutral-500 mt-0.5">{{ borrower.borrower_number }}</p>
          </div>
        </div>
        <div class="flex gap-2 flex-wrap">
          <button
            v-if="can('borrowers.update')"
            @click="showBlacklistModal = true"
            class="btn-secondary text-sm"
            :class="borrower.is_blacklisted ? 'text-green-700' : 'text-red-700'"
          >
            {{ borrower.is_blacklisted ? 'Remove Blacklist' : 'Blacklist' }}
          </button>
          <Link :href="route('borrowers.edit', borrower.id)" class="btn-secondary">Edit</Link>
          <Link :href="route('loans.create', { borrower_id: borrower.id })" class="btn-primary">+ New Loan</Link>
        </div>
      </div>
    </template>

    <!-- Stats bar -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Total Loans</p>
        <p class="text-2xl font-bold text-neutral-900 mt-1">{{ borrower.loans_count }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Active Loans</p>
        <p class="text-2xl font-bold text-primary-600 mt-1">{{ borrower.active_loans_count }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Total Borrowed</p>
        <p class="text-lg font-bold text-neutral-900 mt-1">K {{ borrower.total_borrowed }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Outstanding</p>
        <p
          class="text-lg font-bold mt-1"
          :class="parseFloat(borrower.outstanding_balance) > 0 ? 'text-red-600' : 'text-green-600'"
        >
          K {{ borrower.outstanding_balance }}
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left column: credit score + personal details -->
      <div class="space-y-6">

        <!-- Credit Score Ring -->
        <div class="lendr-card p-5">
          <h2 class="text-sm font-semibold text-neutral-800 mb-4">Credit Score</h2>
          <div class="flex flex-col items-center">
            <div class="relative w-36 h-36">
              <svg class="w-full h-full -rotate-90" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="50" fill="none" stroke="#f3f4f6" stroke-width="12"/>
                <circle
                  cx="60" cy="60" r="50" fill="none"
                  :stroke="creditScoreColor"
                  stroke-width="12"
                  stroke-linecap="round"
                  :stroke-dasharray="`${creditScoreArc} 314.16`"
                  class="transition-all duration-700"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-bold text-neutral-900">{{ borrower.credit_score ?? '—' }}</span>
                <span class="text-xs font-medium mt-0.5" :style="{ color: creditScoreColor }">{{ creditScoreLabel }}</span>
              </div>
            </div>
            <div class="mt-3 flex items-center gap-2 w-full max-w-[144px] text-xs text-neutral-400">
              <span>300</span>
              <div class="flex-1 h-1.5 rounded-full bg-gradient-to-r from-red-400 via-yellow-400 to-green-500"></div>
              <span>850</span>
            </div>
          </div>
        </div>

        <!-- Personal details -->
        <div class="lendr-card p-5">
          <h2 class="text-sm font-semibold text-neutral-800 mb-4">Personal Details</h2>
          <dl class="space-y-3">
            <InfoRow label="Phone" :value="borrower.phone" />
            <InfoRow label="Alt Phone" :value="borrower.phone_alt" />
            <InfoRow label="Email" :value="borrower.email" />
            <InfoRow label="Gender" :value="capitalize(borrower.gender)" />
            <InfoRow label="Date of Birth" :value="borrower.date_of_birth" />
            <InfoRow label="NRC Number" :value="borrower.national_id" />
            <InfoRow label="Occupation" :value="borrower.occupation" />
            <InfoRow label="Employer" :value="borrower.employer" />
            <InfoRow label="City" :value="borrower.city" />
            <InfoRow label="Province" :value="borrower.province" />
            <InfoRow label="Address" :value="borrower.address" />
          </dl>
          <div v-if="borrower.next_of_kin_name" class="mt-4 pt-4 border-t border-neutral-100">
            <h3 class="text-xs font-semibold text-neutral-500 uppercase mb-3">Next of Kin</h3>
            <dl class="space-y-3">
              <InfoRow label="Name" :value="borrower.next_of_kin_name" />
              <InfoRow label="Phone" :value="borrower.next_of_kin_phone" />
              <InfoRow label="Relationship" :value="borrower.next_of_kin_relationship" />
            </dl>
          </div>
          <div v-if="borrower.is_blacklisted" class="mt-4 pt-4 border-t border-neutral-100">
            <h3 class="text-xs font-semibold text-red-500 uppercase mb-1">Blacklist Reason</h3>
            <p class="text-sm text-neutral-700">{{ borrower.blacklist_reason || 'No reason provided.' }}</p>
          </div>
        </div>
      </div>

      <!-- Right column: tabbed panel -->
      <div class="lg:col-span-2">
        <!-- Tab bar -->
        <div class="flex border-b border-neutral-200 mb-4 gap-0.5">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px whitespace-nowrap"
            :class="activeTab === tab.id
              ? 'border-primary-600 text-primary-700'
              : 'border-transparent text-neutral-500 hover:text-neutral-700'"
          >
            {{ tab.label }}
            <span
              v-if="tab.count != null"
              class="ml-1.5 px-1.5 py-0.5 rounded-full text-[11px] bg-neutral-100 text-neutral-600"
            >{{ tab.count }}</span>
          </button>
        </div>

        <!-- ── Loans Tab ── -->
        <div v-show="activeTab === 'loans'" class="lendr-card overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-5 py-2.5 text-xs font-semibold text-neutral-500 uppercase">Loan #</th>
                <th class="text-left px-5 py-2.5 text-xs font-semibold text-neutral-500 uppercase hidden md:table-cell">Type</th>
                <th class="text-right px-5 py-2.5 text-xs font-semibold text-neutral-500 uppercase">Amount</th>
                <th class="text-left px-5 py-2.5 text-xs font-semibold text-neutral-500 uppercase">Status</th>
                <th class="px-5 py-2.5"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="loan in borrower.loans" :key="loan.id" class="hover:bg-neutral-50">
                <td class="px-5 py-3">
                  <p class="font-medium text-neutral-800">{{ loan.loan_number }}</p>
                  <p class="text-xs text-neutral-400">{{ loan.date }}</p>
                </td>
                <td class="px-5 py-3 hidden md:table-cell text-neutral-600">{{ loan.type }}</td>
                <td class="px-5 py-3 text-right font-semibold text-neutral-800">K {{ loan.amount }}</td>
                <td class="px-5 py-3">
                  <span :class="loanStatusClass(loan.status_color)">{{ loan.status_label }}</span>
                </td>
                <td class="px-5 py-3 text-right">
                  <Link :href="route('loans.show', loan.id)" class="text-xs text-primary-600 hover:text-primary-800 font-medium">View</Link>
                </td>
              </tr>
              <tr v-if="!borrower.loans?.length">
                <td colspan="5" class="px-5 py-10 text-center text-neutral-400">No loans yet</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ── KYC Documents Tab ── -->
        <div v-show="activeTab === 'kyc'" class="space-y-3">
          <div
            v-for="doc in borrower.kyc_documents"
            :key="doc.id"
            class="lendr-card p-4 flex items-start justify-between gap-4"
          >
            <div class="flex items-start gap-3 min-w-0">
              <div
                class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                :class="doc.mime_type?.includes('pdf') ? 'bg-red-50' : 'bg-blue-50'"
              >
                <svg v-if="doc.mime_type?.includes('pdf')" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <svg v-else class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium text-neutral-800 capitalize">
                  {{ doc.document_type.replace(/_/g, ' ') }}
                </p>
                <p class="text-xs text-neutral-500 mt-0.5">
                  Uploaded {{ doc.created_at }} · {{ formatFileSize(doc.file_size) }}
                </p>
                <p v-if="doc.rejection_reason" class="text-xs text-red-600 mt-1">
                  Reason: {{ doc.rejection_reason }}
                </p>
                <p v-if="doc.reviewed_by" class="text-xs text-neutral-400 mt-0.5">
                  Reviewed by {{ doc.reviewed_by }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <span :class="{
                'lendr-badge-success': doc.status === 'verified',
                'lendr-badge-warning': doc.status === 'pending',
                'lendr-badge-danger':  doc.status === 'rejected',
                'lendr-badge-neutral': doc.status === 'expired',
              }">{{ doc.status_label }}</span>

              <template v-if="can('kyc.review') && doc.status === 'pending'">
                <button
                  @click="reviewDoc(doc, 'approve')"
                  :disabled="kycReviewing === doc.id"
                  class="px-2.5 py-1 text-xs font-medium bg-green-600 hover:bg-green-700 text-white rounded-lg transition disabled:opacity-50"
                >Approve</button>
                <button
                  @click="openRejectModal(doc)"
                  :disabled="kycReviewing === doc.id"
                  class="px-2.5 py-1 text-xs font-medium bg-red-600 hover:bg-red-700 text-white rounded-lg transition disabled:opacity-50"
                >Reject</button>
              </template>
            </div>
          </div>
          <div v-if="!borrower.kyc_documents?.length" class="lendr-card p-10 text-center text-neutral-400 text-sm">
            No KYC documents uploaded yet.
          </div>
        </div>

        <!-- ── Notes Tab ── -->
        <div v-show="activeTab === 'notes'" class="space-y-4">
          <div v-if="can('borrowers.update')" class="lendr-card p-4">
            <textarea
              v-model="noteText"
              rows="3"
              class="input w-full resize-none"
              placeholder="Add a note about this borrower…"
              maxlength="1000"
            ></textarea>
            <div class="flex justify-between items-center mt-2">
              <span class="text-xs text-neutral-400">{{ noteText.length }}/1000</span>
              <button
                @click="submitNote"
                :disabled="!noteText.trim() || savingNote"
                class="btn-primary text-sm py-1.5 px-4 disabled:opacity-50"
              >
                {{ savingNote ? 'Saving…' : 'Add Note' }}
              </button>
            </div>
          </div>

          <div v-if="loadingNotes" class="py-6 text-center text-sm text-neutral-400">Loading…</div>

          <div v-for="note in notes" :key="note.id" class="lendr-card p-4">
            <p class="text-sm text-neutral-800 whitespace-pre-wrap">{{ note.note }}</p>
            <p class="text-xs text-neutral-400 mt-2">{{ note.added_by }} · {{ formatDate(note.created_at) }}</p>
          </div>
          <div v-if="!notes.length && !loadingNotes" class="lendr-card p-10 text-center text-neutral-400 text-sm">
            No notes yet.
          </div>
        </div>

        <!-- ── Activity Tab ── -->
        <div v-show="activeTab === 'activity'" class="lendr-card overflow-hidden">
          <div class="divide-y divide-neutral-50">
            <div v-for="log in borrower.activity_log" :key="log.id" class="px-5 py-3 flex items-start gap-3">
              <div class="w-2 h-2 rounded-full bg-primary-400 mt-1.5 shrink-0"></div>
              <div>
                <p class="text-sm text-neutral-700">
                  <span class="font-medium">{{ log.causer }}</span> {{ log.description }}
                </p>
                <p class="text-xs text-neutral-400 mt-0.5">{{ log.created_at }}</p>
              </div>
            </div>
            <div v-if="!borrower.activity_log?.length" class="px-5 py-10 text-center text-neutral-400 text-sm">
              No activity recorded.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Blacklist Modal ── -->
    <Transition name="fade">
      <div v-if="showBlacklistModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
          <h3 class="text-lg font-semibold text-neutral-900 mb-4">
            {{ borrower.is_blacklisted ? 'Remove from Blacklist' : 'Blacklist Borrower' }}
          </h3>
          <textarea
            v-if="!borrower.is_blacklisted"
            v-model="blacklistReason"
            rows="3"
            class="input w-full resize-none mb-4"
            placeholder="Reason for blacklisting (optional)…"
          ></textarea>
          <p v-else class="text-sm text-neutral-600 mb-4">
            Are you sure you want to remove <strong>{{ borrower.full_name }}</strong> from the blacklist?
          </p>
          <div class="flex justify-end gap-2">
            <button @click="showBlacklistModal = false" class="btn-secondary">Cancel</button>
            <button
              @click="submitBlacklist"
              :disabled="blacklistSaving"
              class="btn-primary disabled:opacity-50"
              :class="!borrower.is_blacklisted ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' : ''"
            >
              {{ blacklistSaving ? 'Saving…' : (borrower.is_blacklisted ? 'Remove Blacklist' : 'Blacklist') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Reject KYC Modal ── -->
    <Transition name="fade">
      <div v-if="rejectModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
          <h3 class="text-lg font-semibold text-neutral-900 mb-2">Reject Document</h3>
          <p class="text-sm text-neutral-600 mb-3">
            Document: <strong class="capitalize">{{ rejectModal.doc?.document_type?.replace(/_/g, ' ') }}</strong>
          </p>
          <textarea
            v-model="rejectModal.reason"
            rows="3"
            class="input w-full resize-none mb-4"
            placeholder="Reason for rejection (required)…"
          ></textarea>
          <div class="flex justify-end gap-2">
            <button @click="rejectModal.show = false" class="btn-secondary">Cancel</button>
            <button
              @click="reviewDoc(rejectModal.doc, 'reject')"
              :disabled="!rejectModal.reason.trim() || kycReviewing === rejectModal.doc?.id"
              class="px-4 py-2 text-sm font-medium bg-red-600 hover:bg-red-700 text-white rounded-lg transition disabled:opacity-50"
            >
              Confirm Rejection
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import InfoRow from '@/admin/components/ui/InfoRow.vue'

const props = defineProps({ borrower: Object })
const page  = usePage()

function can(permission) {
  return page.props.auth?.user?.permissions?.includes(permission)
}

// ─── Tabs ─────────────────────────────────────────────────────
const activeTab = ref('loans')
const tabs = computed(() => [
  { id: 'loans',    label: 'Loans',         count: props.borrower.loans_count ?? 0 },
  { id: 'kyc',      label: 'KYC Documents', count: props.borrower.kyc_documents?.length ?? 0 },
  { id: 'notes',    label: 'Notes' },
  { id: 'activity', label: 'Activity' },
])

// ─── Credit Score ─────────────────────────────────────────────
const creditScoreArc = computed(() => {
  const score = props.borrower.credit_score
  if (!score) return 0
  return ((score - 300) / 550) * 314.16
})

const creditScoreColor = computed(() => {
  const score = props.borrower.credit_score
  if (!score) return '#d1d5db'
  if (score < 550) return '#ef4444'
  if (score < 650) return '#f59e0b'
  if (score < 750) return '#3b82f6'
  return '#22c55e'
})

const creditScoreLabel = computed(() => {
  const score = props.borrower.credit_score
  if (!score) return 'No data'
  if (score < 550) return 'Poor'
  if (score < 650) return 'Fair'
  if (score < 750) return 'Good'
  return 'Excellent'
})

// ─── Loan status badge ────────────────────────────────────────
function loanStatusClass(color) {
  const map = {
    green:  'lendr-badge-success',
    blue:   'lendr-badge-info',
    yellow: 'lendr-badge-warning',
    red:    'lendr-badge-danger',
    gray:   'lendr-badge-neutral',
  }
  return map[color] ?? 'lendr-badge-neutral'
}

// ─── Notes ────────────────────────────────────────────────────
const notes        = ref([])
const loadingNotes = ref(false)
const noteText     = ref('')
const savingNote   = ref(false)

async function fetchNotes() {
  loadingNotes.value = true
  try {
    const { data } = await axios.get(`/api/v1/borrowers/${props.borrower.id}/notes`)
    notes.value = data.data
  } finally {
    loadingNotes.value = false
  }
}

async function submitNote() {
  if (!noteText.value.trim()) return
  savingNote.value = true
  try {
    await axios.post(`/api/v1/borrowers/${props.borrower.id}/notes`, { note: noteText.value })
    noteText.value = ''
    await fetchNotes()
  } finally {
    savingNote.value = false
  }
}

watch(activeTab, (tab) => {
  if (tab === 'notes' && !notes.value.length) fetchNotes()
})

// ─── KYC Review ───────────────────────────────────────────────
const kycReviewing = ref(null)
const rejectModal  = ref({ show: false, doc: null, reason: '' })

function openRejectModal(doc) {
  rejectModal.value = { show: true, doc, reason: '' }
}

async function reviewDoc(doc, action) {
  kycReviewing.value = doc.id
  try {
    const payload = { action }
    if (action === 'reject') payload.rejection_reason = rejectModal.value.reason
    await axios.put(`/api/v1/kyc/${doc.id}/review`, payload)
    rejectModal.value.show = false
    router.reload({ only: ['borrower'] })
  } finally {
    kycReviewing.value = null
  }
}

// ─── Blacklist ────────────────────────────────────────────────
const showBlacklistModal = ref(false)
const blacklistReason    = ref('')
const blacklistSaving    = ref(false)

async function submitBlacklist() {
  blacklistSaving.value = true
  try {
    await axios.post(`/api/v1/borrowers/${props.borrower.id}/blacklist`, {
      reason: blacklistReason.value || null,
    })
    showBlacklistModal.value = false
    blacklistReason.value    = ''
    router.reload({ only: ['borrower'] })
  } finally {
    blacklistSaving.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────
function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : '—'
}

function formatFileSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024)    return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
