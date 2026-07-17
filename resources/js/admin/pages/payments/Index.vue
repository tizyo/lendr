<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Payments</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ payments.total.toLocaleString() }} total payments</p>
        </div>
        <button @click="showRecord = true" class="btn-primary">+ Record Payment</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3 flex-wrap">
      <div class="flex-1 min-w-48">
        <input
          v-model="filters.search"
          type="search"
          placeholder="Search by receipt #, loan #, borrower…"
          class="input w-full"
          @input="debouncedSearch"
        />
      </div>
      <select v-model="filters.payment_method" class="input sm:w-44" @change="applyFilters">
        <option value="">All methods</option>
        <option value="cash">Cash</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="mobile_money">Mobile Money</option>
        <option value="cheque">Cheque</option>
      </select>
      <input v-model="filters.date_from" type="date" class="input sm:w-36" @change="applyFilters" />
      <input v-model="filters.date_to"   type="date" class="input sm:w-36" @change="applyFilters" />
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-neutral-500 text-xs uppercase tracking-wide">Receipt #</th>
              <th class="px-4 py-3 text-left font-medium text-neutral-500 text-xs uppercase tracking-wide">Loan #</th>
              <th class="px-4 py-3 text-left font-medium text-neutral-500 text-xs uppercase tracking-wide">Borrower</th>
              <th class="px-4 py-3 text-right font-medium text-neutral-500 text-xs uppercase tracking-wide">Amount</th>
              <th class="px-4 py-3 text-left font-medium text-neutral-500 text-xs uppercase tracking-wide hidden md:table-cell">Method</th>
              <th class="px-4 py-3 text-left font-medium text-neutral-500 text-xs uppercase tracking-wide">Date</th>
              <th class="px-4 py-3 text-left font-medium text-neutral-500 text-xs uppercase tracking-wide hidden lg:table-cell">Reference</th>
              <th class="px-4 py-3 text-left font-medium text-neutral-500 text-xs uppercase tracking-wide hidden lg:table-cell">Recorded By</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="payments.data.length === 0">
              <td colspan="9" class="px-4 py-10 text-center text-neutral-400">No payments found.</td>
            </tr>
            <tr v-for="pay in payments.data" :key="pay.id" class="hover:bg-neutral-50 transition-colors">
              <td class="px-4 py-3">
                <Link :href="route('payments.show', pay.id)" class="font-mono text-xs text-primary-600 hover:underline font-semibold">
                  {{ pay.receipt_number }}
                </Link>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ pay.loan_number }}</td>
              <td class="px-4 py-3 font-medium text-neutral-800">{{ pay.borrower_name }}</td>
              <td class="px-4 py-3 text-right tabular-nums font-semibold text-green-700">
                {{ pay.currency ?? 'ZMW' }} {{ pay.amount }}
              </td>
              <td class="px-4 py-3 text-neutral-600 hidden md:table-cell capitalize">{{ pay.payment_method?.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-neutral-600">{{ pay.payment_date }}</td>
              <td class="px-4 py-3 text-neutral-500 font-mono text-xs hidden lg:table-cell">{{ pay.reference ?? '—' }}</td>
              <td class="px-4 py-3 text-neutral-500 hidden lg:table-cell">{{ pay.recorded_by ?? '—' }}</td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('payments.show', pay.id)" class="text-xs text-neutral-500 hover:text-primary-600 transition">View →</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="payments.last_page > 1" class="px-4 py-3 border-t border-neutral-200 flex justify-between items-center text-sm text-neutral-600">
        <span>Page {{ payments.current_page }} of {{ payments.last_page }}</span>
        <div class="flex gap-2">
          <Link v-if="payments.prev_page_url" :href="payments.prev_page_url" class="btn-secondary btn-sm">← Prev</Link>
          <Link v-if="payments.next_page_url" :href="payments.next_page_url" class="btn-secondary btn-sm">Next →</Link>
        </div>
      </div>
    </div>

    <!-- Record Payment Modal -->
    <Transition name="modal">
      <div v-if="showRecord" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="closeRecord">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
          <div class="flex items-center justify-between px-6 py-4 border-b border-neutral-100">
            <h2 class="text-lg font-semibold text-neutral-900">Record Payment</h2>
            <button @click="closeRecord" class="text-neutral-400 hover:text-neutral-600 transition">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <!-- Loan lookup -->
            <div>
              <label class="label">Loan Number *</label>
              <div class="flex gap-2">
                <input
                  v-model="form.loan_search"
                  type="text"
                  class="input flex-1"
                  placeholder="LN-202601-00001"
                  @keyup.enter="lookupLoan"
                />
                <button @click="lookupLoan" :disabled="lookupLoading" class="btn-secondary px-4 shrink-0">
                  {{ lookupLoading ? '…' : 'Find' }}
                </button>
              </div>
              <p v-if="lookupError" class="text-xs text-red-600 mt-1">{{ lookupError }}</p>
            </div>

            <!-- Loan preview -->
            <div v-if="loanPreview" class="rounded-xl bg-primary-50 border border-primary-200 px-4 py-3 space-y-1">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-primary-900 text-sm">{{ loanPreview.borrower?.name }}</span>
                <span class="text-xs font-mono text-primary-700 bg-primary-100 px-2 py-0.5 rounded-full">{{ loanPreview.loan_number }}</span>
              </div>
              <div class="flex items-center gap-4 text-xs text-primary-700">
                <span>Outstanding: <strong>{{ loanPreview.outstanding_balance?.toLocaleString() }}</strong></span>
                <span>Status: <strong class="capitalize">{{ loanPreview.status_label }}</strong></span>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="label">Amount *</label>
                <input v-model="form.amount" type="number" step="0.01" min="0.01" class="input w-full" placeholder="0.00" />
              </div>
              <div>
                <label class="label">Payment Method *</label>
                <select v-model="form.payment_method" class="input w-full">
                  <option value="cash">Cash</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="airtel_money">Airtel Money</option>
                  <option value="mtn_momo">MTN MoMo</option>
                  <option value="zamtel_kwacha">Zamtel Kwacha</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              <div>
                <label class="label">Payment Date *</label>
                <input v-model="form.payment_date" type="date" class="input w-full" />
              </div>
              <div class="col-span-2">
                <label class="label">Reference / Receipt No.</label>
                <input v-model="form.reference" type="text" class="input w-full" placeholder="Bank ref, USSD code…" />
              </div>
              <div class="col-span-2">
                <label class="label">Notes</label>
                <textarea v-model="form.notes" class="input w-full h-16 resize-none" placeholder="Optional notes…"></textarea>
              </div>
            </div>

            <p v-if="submitError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ submitError }}</p>
          </div>

          <div class="flex gap-3 justify-end px-6 py-4 border-t border-neutral-100">
            <button @click="closeRecord" class="btn-secondary">Cancel</button>
            <button
              @click="submitPayment"
              :disabled="submitting || !form.loan_id || !form.amount"
              class="btn-primary"
            >
              {{ submitting ? 'Recording…' : 'Record Payment' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  payments: { type: Object, required: true },
  filters:  { type: Object, default: () => ({}) },
})

const filters = reactive({ ...props.filters })

let searchTimer = null
const debouncedSearch = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

const applyFilters = () => {
  router.get(route('payments.index'), filters, { preserveState: true, replace: true })
}

// ─── Record Payment Modal ─────────────────────────────────────────────────────
const showRecord   = ref(false)
const lookupLoading = ref(false)
const lookupError   = ref('')
const loanPreview   = ref(null)
const submitting    = ref(false)
const submitError   = ref('')

const form = reactive({
  loan_id:        null,
  loan_search:    '',
  amount:         '',
  payment_method: 'cash',
  payment_date:   new Date().toISOString().slice(0, 10),
  reference:      '',
  notes:          '',
})

function closeRecord() {
  showRecord.value   = false
  lookupError.value  = ''
  loanPreview.value  = null
  submitError.value  = ''
  Object.assign(form, {
    loan_id: null, loan_search: '', amount: '', payment_method: 'cash',
    payment_date: new Date().toISOString().slice(0, 10), reference: '', notes: '',
  })
}

async function lookupLoan() {
  if (!form.loan_search.trim()) return
  lookupLoading.value = true
  lookupError.value   = ''
  loanPreview.value   = null
  form.loan_id        = null
  try {
    const { data } = await axios.get(route('api.v1.loans.index'), {
      params: { search: form.loan_search.trim(), per_page: 1 },
    })
    // Response: { data: [...], meta: {...} }
    const match = Array.isArray(data.data) ? data.data[0] : null
    if (!match) {
      lookupError.value = 'No loan found with that number.'
      return
    }
    form.loan_id      = match.id
    loanPreview.value = match
  } catch {
    lookupError.value = 'Lookup failed. Try again.'
  } finally {
    lookupLoading.value = false
  }
}

async function submitPayment() {
  submitError.value = ''
  if (!form.loan_id)    { submitError.value = 'Please look up a loan first.'; return }
  if (!form.amount)     { submitError.value = 'Amount is required.'; return }
  submitting.value = true
  try {
    await axios.post(route('api.v1.payments.store'), {
      loan_id:        form.loan_id,
      amount:         form.amount,
      payment_method: form.payment_method,
      payment_date:   form.payment_date,
      reference:      form.reference,
      notes:          form.notes,
    })
    closeRecord()
    router.reload({ only: ['payments'] })
  } catch (e) {
    submitError.value = e.response?.data?.message ?? 'Payment recording failed.'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.modal-enter-active { transition: all 0.2s ease-out; }
.modal-leave-active { transition: all 0.15s ease-in; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .bg-white { transform: scale(0.97) translateY(4px); }
</style>
