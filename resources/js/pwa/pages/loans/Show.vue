<template>
  <PwaLayout :title="loan?.loan_number ?? 'Loan Details'" :show-back="true">
    <div class="px-4 py-6 space-y-5 max-w-lg mx-auto">

      <!-- Loading -->
      <div v-if="loading" class="space-y-4">
        <div class="h-32 bg-gray-100 rounded-xl animate-pulse" />
        <div class="h-48 bg-gray-100 rounded-xl animate-pulse" />
      </div>

      <!-- Error -->
      <div v-else-if="error" class="py-12 text-center text-red-500 text-sm">
        {{ error }}
        <button @click="load" class="block mx-auto mt-2 underline">Retry</button>
      </div>

      <template v-else-if="loan">

        <!-- Success banner (just applied) -->
        <div v-if="applied" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-start gap-3">
          <div class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
            <CheckCircleIcon class="w-5 h-5 text-emerald-600" />
          </div>
          <div>
            <p class="font-semibold text-emerald-800 text-sm">Application submitted!</p>
            <p class="text-xs text-emerald-700 mt-0.5">We'll review your application and notify you of the outcome.</p>
          </div>
        </div>

        <!-- Status header card -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <div class="flex items-start justify-between mb-4">
            <div>
              <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Loan Number</p>
              <p class="text-lg font-bold text-gray-900">{{ loan.loan_number }}</p>
            </div>
            <span
              class="text-xs px-3 py-1.5 rounded-full font-semibold"
              :class="statusClass(loan.status)"
            >{{ loan.status_label }}</span>
          </div>

          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-xs text-gray-400 uppercase tracking-wide">Principal</p>
              <p class="font-bold text-gray-900 mt-0.5">K {{ fmt(loan.principal_amount) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 uppercase tracking-wide">Outstanding</p>
              <p class="font-bold text-emerald-600 mt-0.5">K {{ fmt(loan.outstanding_balance) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 uppercase tracking-wide">Interest Rate</p>
              <p class="font-medium text-gray-700 mt-0.5">{{ loan.interest_rate }}% / {{ loan.interest_period }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 uppercase tracking-wide">Tenure</p>
              <p class="font-medium text-gray-700 mt-0.5">{{ loan.tenure }} {{ loan.tenure_type }}</p>
            </div>
            <div v-if="loan.disbursement_date">
              <p class="text-xs text-gray-400 uppercase tracking-wide">Disbursed</p>
              <p class="font-medium text-gray-700 mt-0.5">{{ loan.disbursement_date }}</p>
            </div>
            <div v-if="loan.maturity_date">
              <p class="text-xs text-gray-400 uppercase tracking-wide">Maturity</p>
              <p class="font-medium text-gray-700 mt-0.5">{{ loan.maturity_date }}</p>
            </div>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex gap-3">
          <button
            v-if="['active', 'disbursed', 'overdue'].includes(loan.status) && loan.outstanding_balance > 0"
            @click="$inertia.visit(route('pwa.loans.pay', loan.id))"
            class="flex-1 py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition flex items-center justify-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            Make a Payment
          </button>

          <!-- Download Statement -->
          <a
            href="/api/v1/me/statement/pdf"
            target="_blank"
            class="px-4 py-3.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 font-semibold text-sm transition hover:bg-emerald-100 flex items-center gap-2 shrink-0"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Statement
          </a>
        </div>

        <!-- Fees breakdown -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
          <p class="text-sm font-semibold text-gray-800 mb-3">Loan Breakdown</p>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-gray-600">
              <span>Principal</span>
              <span class="font-medium">K {{ fmt(loan.principal_amount) }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
              <span>Interest</span>
              <span class="font-medium">K {{ fmt(loan.interest_amount) }}</span>
            </div>
            <div v-if="loan.processing_fee > 0" class="flex justify-between text-gray-600">
              <span>Processing Fee</span>
              <span class="font-medium">K {{ fmt(loan.processing_fee) }}</span>
            </div>
            <div v-if="loan.insurance_fee > 0" class="flex justify-between text-gray-600">
              <span>Insurance Fee</span>
              <span class="font-medium">K {{ fmt(loan.insurance_fee) }}</span>
            </div>
            <div class="flex justify-between font-bold text-gray-900 border-t border-gray-100 pt-2 mt-1">
              <span>Total Repayable</span>
              <span>K {{ fmt(loan.total_payable) }}</span>
            </div>
          </div>
        </div>

        <!-- Repayment schedule -->
        <div v-if="loan.schedule?.length" class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-100">
            <p class="text-sm font-semibold text-gray-800">Repayment Schedule</p>
            <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ loan.repayment_schedule?.replace('_', ' ') }} payments</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                  <th class="px-3 py-2.5 text-left text-gray-500 font-medium">#</th>
                  <th class="px-3 py-2.5 text-left text-gray-500 font-medium">Due Date</th>
                  <th class="px-3 py-2.5 text-right text-gray-500 font-medium">Amount</th>
                  <th class="px-3 py-2.5 text-right text-gray-500 font-medium">Balance</th>
                  <th class="px-3 py-2.5 text-center text-gray-500 font-medium">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr
                  v-for="row in loan.schedule"
                  :key="row.instalment_number"
                  class="transition-colors"
                  :class="row.days_overdue > 0 ? 'bg-red-50' : row.is_paid ? 'bg-emerald-50/50' : ''"
                >
                  <td class="px-3 py-2.5 text-gray-500">{{ row.instalment_number }}</td>
                  <td class="px-3 py-2.5 text-gray-700">{{ row.due_date }}</td>
                  <td class="px-3 py-2.5 text-right font-medium text-gray-900">K {{ fmt(row.total_due) }}</td>
                  <td class="px-3 py-2.5 text-right text-gray-600">K {{ fmt(row.outstanding) }}</td>
                  <td class="px-3 py-2.5 text-center">
                    <span
                      v-if="row.is_paid"
                      class="inline-block bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full text-xs font-medium"
                    >Paid</span>
                    <span
                      v-else-if="row.days_overdue > 0"
                      class="inline-block bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full text-xs font-medium"
                    >{{ row.days_overdue }}d late</span>
                    <span
                      v-else
                      class="inline-block bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full text-xs font-medium"
                    >Due</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- No schedule yet (submitted/approved but not disbursed) -->
        <div v-else-if="['submitted', 'approved', 'draft'].includes(loan.status)" class="bg-gray-50 rounded-xl p-5 text-center text-sm text-gray-500">
          <p class="font-medium mb-1">Repayment schedule not yet generated</p>
          <p class="text-xs text-gray-400">Schedule will be available once the loan is disbursed.</p>
        </div>

      </template>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import { CheckCircleIcon } from '@heroicons/vue/24/solid'
import axios from 'axios'

const props = defineProps({
  loanId: { type: [Number, String], required: true },
})

const page    = usePage()
const loan    = ref(null)
const loading = ref(true)
const error   = ref('')
const applied = computed(() => {
  const url = new URL(window.location.href)
  return url.searchParams.get('applied') === '1'
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await axios.get(`/api/v1/me/loans/${props.loanId}`)
    loan.value = res.data.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load loan details.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

function fmt(n) {
  return Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusClass(status) {
  const map = {
    draft:       'bg-gray-100 text-gray-600',
    submitted:   'bg-blue-100 text-blue-700',
    approved:    'bg-amber-100 text-amber-700',
    disbursed:   'bg-teal-100 text-teal-700',
    active:      'bg-emerald-100 text-emerald-700',
    completed:   'bg-green-100 text-green-700',
    denied:      'bg-red-100 text-red-700',
    defaulted:   'bg-red-200 text-red-800',
    written_off: 'bg-gray-200 text-gray-600',
    frozen:      'bg-purple-100 text-purple-700',
  }
  return map[status] ?? 'bg-gray-100 text-gray-500'
}
</script>
