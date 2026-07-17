<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link :href="route('loans.index')" class="text-neutral-400 hover:text-neutral-600">
          ← Loans
        </Link>
        <span class="text-neutral-300">/</span>
        <h1 class="text-xl font-bold text-neutral-900">New Loan Application</h1>
      </div>
    </template>

    <form @submit.prevent="submit" class="max-w-3xl space-y-6">

      <!-- Borrower -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">Borrower</h2>

        <div v-if="selectedBorrower" class="flex items-start justify-between bg-neutral-50 rounded-lg p-4 mb-3">
          <div>
            <p class="font-semibold text-neutral-900">{{ selectedBorrower.full_name ?? (selectedBorrower.first_name + ' ' + selectedBorrower.last_name) }}</p>
            <p class="text-sm text-neutral-500">{{ selectedBorrower.borrower_number }} · {{ selectedBorrower.phone }}</p>
            <div class="flex gap-2 mt-1">
              <span v-if="selectedBorrower.is_blacklisted" class="text-xs text-red-600 font-medium">⚠ Blacklisted</span>
              <span v-if="selectedBorrower.kyc_verified" class="text-xs text-green-600 font-medium">✓ KYC Verified</span>
            </div>
          </div>
          <button type="button" @click="selectedBorrower = null; form.borrower_id = ''" class="text-neutral-400 hover:text-neutral-600 text-sm">
            Change
          </button>
        </div>

        <div v-else>
          <div class="relative">
            <input
              v-model="borrowerSearch"
              type="text"
              placeholder="Search borrower by name, phone, or ID…"
              class="input w-full"
              @input="searchBorrowers"
            />
            <div v-if="borrowerResults.length" class="absolute top-full left-0 right-0 bg-white border border-neutral-200 rounded-lg shadow-lg z-10 mt-1 max-h-60 overflow-y-auto">
              <button
                v-for="b in borrowerResults"
                :key="b.id"
                type="button"
                @click="selectBorrower(b)"
                class="w-full text-left px-4 py-3 hover:bg-neutral-50 border-b border-neutral-50 last:border-0"
              >
                <p class="font-medium text-neutral-900">{{ b.first_name }} {{ b.last_name }}</p>
                <p class="text-xs text-neutral-500">{{ b.borrower_number }} · {{ b.phone }}</p>
              </button>
            </div>
          </div>
          <p v-if="errors.borrower_id" class="text-red-600 text-xs mt-1">{{ errors.borrower_id }}</p>
        </div>
      </div>

      <!-- Loan Product -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">Loan Product</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="label">Loan Type <span class="text-red-500">*</span></label>
            <select v-model="form.loan_type_id" @change="onTypeChange" class="input w-full" :class="{ 'border-red-400': errors.loan_type_id }">
              <option value="">Select type…</option>
              <option v-for="t in loanTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <p v-if="errors.loan_type_id" class="text-red-600 text-xs mt-1">{{ errors.loan_type_id }}</p>
          </div>

          <div>
            <label class="label">Loan Plan <span class="text-red-500">*</span></label>
            <select v-model="form.loan_plan_id" @change="onPlanChange" class="input w-full" :class="{ 'border-red-400': errors.loan_plan_id }" :disabled="!form.loan_type_id">
              <option value="">Select plan…</option>
              <option v-for="p in selectedTypePlans" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <p v-if="errors.loan_plan_id" class="text-red-600 text-xs mt-1">{{ errors.loan_plan_id }}</p>
          </div>
        </div>

        <!-- Plan summary -->
        <div v-if="selectedPlan" class="mt-4 p-3 bg-blue-50 rounded-lg text-sm text-blue-800 grid grid-cols-2 sm:grid-cols-4 gap-2">
          <div><span class="font-medium">Rate:</span> {{ selectedPlan.interest_rate }}% {{ selectedPlan.interest_type }}</div>
          <div><span class="font-medium">Tenure:</span> {{ selectedPlan.min_tenure }}–{{ selectedPlan.max_tenure }} {{ selectedPlan.tenure_type }}</div>
          <div><span class="font-medium">Amount:</span> K{{ formatNum(selectedPlan.min_amount) }}–K{{ formatNum(selectedPlan.max_amount) }}</div>
          <div><span class="font-medium">Schedule:</span> {{ selectedPlan.repayment_schedule }}</div>
        </div>
      </div>

      <!-- Loan Details -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">Loan Details</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="label">Principal Amount (ZMW) <span class="text-red-500">*</span></label>
            <input v-model="form.principal_amount" type="number" step="0.01" min="0" class="input w-full" :class="{ 'border-red-400': errors.principal_amount }" @blur="recalculate" />
            <p v-if="errors.principal_amount" class="text-red-600 text-xs mt-1">{{ errors.principal_amount }}</p>
          </div>

          <div>
            <label class="label">Tenure ({{ selectedPlan?.tenure_type || 'months' }}) <span class="text-red-500">*</span></label>
            <input v-model="form.tenure" type="number" min="1" class="input w-full" :class="{ 'border-red-400': errors.tenure }" @blur="recalculate" />
            <p v-if="errors.tenure" class="text-red-600 text-xs mt-1">{{ errors.tenure }}</p>
          </div>

          <div>
            <label class="label">Application Date <span class="text-red-500">*</span></label>
            <input v-model="form.application_date" type="date" class="input w-full" :class="{ 'border-red-400': errors.application_date }" />
            <p v-if="errors.application_date" class="text-red-600 text-xs mt-1">{{ errors.application_date }}</p>
          </div>
        </div>

        <!-- Calculation preview -->
        <div v-if="calculation" class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-neutral-50 rounded-lg p-3 text-center">
            <p class="text-xs text-neutral-500">Interest</p>
            <p class="text-base font-semibold text-neutral-900">K {{ formatNum(calculation.interest_amount) }}</p>
          </div>
          <div class="bg-neutral-50 rounded-lg p-3 text-center">
            <p class="text-xs text-neutral-500">Processing Fee</p>
            <p class="text-base font-semibold text-neutral-900">K {{ formatNum(calculation.processing_fee) }}</p>
          </div>
          <div class="bg-neutral-50 rounded-lg p-3 text-center">
            <p class="text-xs text-neutral-500">Insurance Fee</p>
            <p class="text-base font-semibold text-neutral-900">K {{ formatNum(calculation.insurance_fee) }}</p>
          </div>
          <div class="bg-primary-50 rounded-lg p-3 text-center border border-primary-100">
            <p class="text-xs text-primary-600">Total Payable</p>
            <p class="text-base font-bold text-primary-700">K {{ formatNum(calculation.total_payable) }}</p>
          </div>
        </div>

        <div class="mt-4">
          <label class="label">Loan Purpose</label>
          <textarea v-model="form.loan_purpose" rows="2" class="input w-full" placeholder="Describe the purpose of this loan…"></textarea>
        </div>
      </div>

      <!-- Collateral & Guarantor (shown only if type requires) -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">Collateral & Guarantor</h2>

        <div class="mb-4">
          <label class="label">Collateral Description</label>
          <textarea v-model="form.collateral_description" rows="2" class="input w-full" placeholder="Describe any collateral provided…"></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="label">Guarantor Name</label>
            <input v-model="form.guarantor_name" type="text" class="input w-full" />
          </div>
          <div>
            <label class="label">Guarantor Phone</label>
            <input v-model="form.guarantor_phone" type="tel" class="input w-full" />
          </div>
          <div>
            <label class="label">Relationship</label>
            <input v-model="form.guarantor_relationship" type="text" class="input w-full" placeholder="e.g. Spouse, Sibling…" />
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="lendr-card p-6">
        <label class="label">Internal Notes</label>
        <textarea v-model="form.notes" rows="3" class="input w-full" placeholder="Any additional notes for the loan officer…"></textarea>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 justify-end">
        <Link :href="route('loans.index')" class="btn-secondary">Cancel</Link>
        <button type="submit" :disabled="submitting" class="btn-primary">
          {{ submitting ? 'Submitting…' : 'Submit Application' }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  borrower: Object,
  loanTypes: Array,
})

const errors = ref({})
const submitting = ref(false)
const borrowerSearch = ref('')
const borrowerResults = ref([])
const selectedBorrower = ref(props.borrower || null)
const calculation = ref(null)
let calcTimeout = null

const form = ref({
  borrower_id: props.borrower?.id || '',
  loan_type_id: '',
  loan_plan_id: '',
  principal_amount: '',
  tenure: '',
  application_date: new Date().toISOString().slice(0, 10),
  loan_purpose: '',
  collateral_description: '',
  guarantor_name: '',
  guarantor_phone: '',
  guarantor_relationship: '',
  notes: '',
})

const selectedTypePlans = computed(() => {
  if (!form.value.loan_type_id) return []
  const type = props.loanTypes?.find(t => t.id == form.value.loan_type_id)
  return type?.plans ?? []
})

const selectedPlan = computed(() => {
  if (!form.value.loan_plan_id) return null
  return selectedTypePlans.value.find(p => p.id == form.value.loan_plan_id)
})

function onTypeChange() {
  form.value.loan_plan_id = ''
  calculation.value = null
}

function onPlanChange() {
  calculation.value = null
  if (selectedPlan.value) {
    form.value.tenure = selectedPlan.value.min_tenure
  }
  recalculate()
}

function recalculate() {
  if (!selectedPlan.value || !form.value.principal_amount || !form.value.tenure) return
  clearTimeout(calcTimeout)
  calcTimeout = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('api.v1.loan-plans.calculate', selectedPlan.value.id), {
        params: {
          principal: form.value.principal_amount,
          tenure: form.value.tenure,
          disbursement_date: new Date().toISOString().slice(0, 10),
        },
      })
      calculation.value = data.data
    } catch {}
  }, 300)
}

async function searchBorrowers() {
  if (borrowerSearch.value.length < 2) { borrowerResults.value = []; return }
  try {
    const { data } = await axios.get(route('api.v1.borrowers.index'), {
      params: { search: borrowerSearch.value, per_page: 8 },
    })
    borrowerResults.value = data.data
  } catch {}
}

function selectBorrower(b) {
  selectedBorrower.value = b
  form.value.borrower_id = b.id
  borrowerSearch.value = ''
  borrowerResults.value = []
}

function formatNum(n) {
  return Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function submit() {
  submitting.value = true
  errors.value = {}
  try {
    router.post(route('loans.store'), form.value, {
      onError: (e) => { errors.value = e },
      onFinish: () => { submitting.value = false },
    })
  } catch {
    submitting.value = false
  }
}
</script>
