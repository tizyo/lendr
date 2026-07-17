<template>
  <AppLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">Bulk Loan Operations</h1>
        <p class="text-sm text-neutral-500 mt-0.5">Approve or disburse multiple loans at once</p>
      </div>
    </template>

    <div class="space-y-6">

      <!-- Result banner -->
      <div v-if="resultMsg" class="p-4 rounded-xl text-sm font-medium" :class="resultError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'">
        {{ resultMsg }}
        <ul v-if="resultErrors.length" class="mt-1 space-y-0.5 font-normal text-xs">
          <li v-for="(e, i) in resultErrors" :key="i">• {{ e }}</li>
        </ul>
      </div>

      <!-- Filters -->
      <div class="lendr-card p-4 flex flex-wrap gap-3 items-center">
        <div class="flex items-center gap-2">
          <label class="text-sm text-neutral-500">Show:</label>
          <select v-model="statusFilter" class="input text-sm">
            <option value="">All pending</option>
            <option value="submitted">Submitted (awaiting approval)</option>
            <option value="approved">Approved (awaiting disbursement)</option>
          </select>
        </div>
        <div class="ml-auto flex items-center gap-2">
          <button
            v-if="can.approve"
            @click="bulkApprove"
            :disabled="!selectedSubmitted.length || working"
            class="btn-success text-sm disabled:opacity-50"
          >
            Approve {{ selectedSubmitted.length ? `(${selectedSubmitted.length})` : '' }} Selected
          </button>
          <button
            v-if="can.disburse"
            @click="showDisburseModal = true"
            :disabled="!selectedApproved.length || working"
            class="btn-primary text-sm disabled:opacity-50"
          >
            Disburse {{ selectedApproved.length ? `(${selectedApproved.length})` : '' }} Selected
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="lendr-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="px-4 py-3">
                  <input type="checkbox" @change="toggleAll" :checked="allSelected" class="rounded text-primary-600" />
                </th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Loan</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Borrower</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Product</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Principal</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Applied</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="loan in filteredLoans" :key="loan.id" :class="selected.includes(loan.id) ? 'bg-primary-50/30' : 'hover:bg-neutral-50/50'">
                <td class="px-4 py-3">
                  <input type="checkbox" :value="loan.id" v-model="selected" class="rounded text-primary-600" />
                </td>
                <td class="px-4 py-3 font-mono text-xs text-neutral-700">{{ loan.loan_number }}</td>
                <td class="px-4 py-3">
                  <p class="font-medium text-neutral-800">{{ loan.borrower_name }}</p>
                  <p class="text-xs text-neutral-400">{{ loan.borrower_number }}</p>
                </td>
                <td class="px-4 py-3 text-neutral-600">{{ loan.loan_type }}</td>
                <td class="px-4 py-3 text-right font-semibold text-neutral-900">K {{ loan.principal_amount }}</td>
                <td class="px-4 py-3">
                  <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                    :class="loan.status === 'submitted' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'">
                    {{ loan.status_label }}
                  </span>
                </td>
                <td class="px-4 py-3 text-neutral-500">{{ loan.application_date }}</td>
              </tr>
              <tr v-if="!filteredLoans.length">
                <td colspan="7" class="px-4 py-8 text-center text-neutral-400">No loans pending in this category.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Disburse modal -->
    <div v-if="showDisburseModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-base font-bold text-neutral-900">Disburse {{ selectedApproved.length }} Loan(s)</h3>
        <div>
          <label class="label">Disbursement Method *</label>
          <select v-model="disburseForm.method" class="input w-full">
            <option value="mobile_money">Mobile Money</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cash">Cash</option>
            <option value="cheque">Cheque</option>
          </select>
        </div>
        <div>
          <label class="label">Disbursement Date *</label>
          <input type="date" v-model="disburseForm.date" class="input w-full" :max="today" />
        </div>
        <div class="flex gap-3 pt-2">
          <button @click="bulkDisburse" :disabled="!disburseForm.method || !disburseForm.date || working" class="btn-primary disabled:opacity-50">
            {{ working ? 'Processing…' : 'Confirm Disburse' }}
          </button>
          <button @click="showDisburseModal = false" class="btn-secondary">Cancel</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  loans: Array,
  can: Object,
})

const selected         = ref([])
const statusFilter     = ref('')
const working          = ref(false)
const resultMsg        = ref('')
const resultError      = ref(false)
const resultErrors     = ref([])
const showDisburseModal = ref(false)
const today = new Date().toISOString().slice(0, 10)

const disburseForm = ref({ method: 'mobile_money', date: today })

const filteredLoans = computed(() => {
  if (!statusFilter.value) return props.loans
  return props.loans.filter(l => l.status === statusFilter.value)
})

const selectedSubmitted = computed(() =>
  selected.value.filter(id => props.loans.find(l => l.id === id)?.status === 'submitted')
)

const selectedApproved = computed(() =>
  selected.value.filter(id => props.loans.find(l => l.id === id)?.status === 'approved')
)

const allSelected = computed(() =>
  filteredLoans.value.length > 0 && filteredLoans.value.every(l => selected.value.includes(l.id))
)

function toggleAll(e) {
  if (e.target.checked) {
    filteredLoans.value.forEach(l => { if (!selected.value.includes(l.id)) selected.value.push(l.id) })
  } else {
    const ids = filteredLoans.value.map(l => l.id)
    selected.value = selected.value.filter(id => !ids.includes(id))
  }
}

async function bulkApprove() {
  if (!selectedSubmitted.value.length) return
  working.value   = true
  resultMsg.value = ''
  resultErrors.value = []
  try {
    const { data } = await axios.post(route('bulk.loans.approve'), { loan_ids: selectedSubmitted.value })
    resultMsg.value   = data.message
    resultError.value = false
    resultErrors.value = data.errors ?? []
    selected.value = selected.value.filter(id => !selectedSubmitted.value.includes(id))
    // update status locally
    selectedSubmitted.value.forEach(id => {
      const l = props.loans.find(l => l.id === id)
      if (l) { l.status = 'approved'; l.status_label = 'Approved' }
    })
  } catch (e) {
    resultMsg.value   = e.response?.data?.message ?? 'Approval failed.'
    resultError.value = true
  } finally {
    working.value = false
  }
}

async function bulkDisburse() {
  if (!selectedApproved.value.length || !disburseForm.value.method || !disburseForm.value.date) return
  working.value   = true
  resultMsg.value = ''
  resultErrors.value = []
  try {
    const { data } = await axios.post(route('bulk.loans.disburse'), {
      loan_ids:            selectedApproved.value,
      disbursement_method: disburseForm.value.method,
      disbursement_date:   disburseForm.value.date,
    })
    resultMsg.value    = data.message
    resultError.value  = false
    resultErrors.value = data.errors ?? []
    showDisburseModal.value = false
    // remove disbursed loans from list
    selectedApproved.value.forEach(id => {
      const idx = props.loans.findIndex(l => l.id === id)
      if (idx !== -1) props.loans.splice(idx, 1)
    })
    selected.value = []
  } catch (e) {
    resultMsg.value   = e.response?.data?.message ?? 'Disbursement failed.'
    resultError.value = true
  } finally {
    working.value = false
  }
}
</script>
