<template>
  <PwaLayout title="Apply for a Loan" :show-back="true">
    <div class="px-4 py-6 max-w-lg mx-auto">

      <!-- Step indicator -->
      <div class="flex items-center justify-between mb-8">
        <template v-for="(label, idx) in stepLabels" :key="idx">
          <div class="flex flex-col items-center gap-1">
            <div
              class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-colors"
              :class="step > idx + 1 ? 'bg-emerald-500 text-white'
                     : step === idx + 1 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100'
                     : 'bg-gray-100 text-gray-400'"
            >
              <CheckIcon v-if="step > idx + 1" class="w-4 h-4" />
              <span v-else>{{ idx + 1 }}</span>
            </div>
            <span class="text-xs" :class="step === idx + 1 ? 'text-emerald-700 font-medium' : 'text-gray-400'">
              {{ label }}
            </span>
          </div>
          <div v-if="idx < stepLabels.length - 1" class="flex-1 h-0.5 mx-2 mb-4" :class="step > idx + 1 ? 'bg-emerald-400' : 'bg-gray-200'" />
        </template>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loadingProducts" class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-20 bg-gray-100 rounded-xl animate-pulse" />
      </div>

      <!-- Error state -->
      <div v-else-if="loadError" class="py-10 text-center text-red-500 text-sm">
        Failed to load loan products. <button @click="loadProducts" class="underline">Retry</button>
      </div>

      <template v-else>
        <!-- ─── Step 1: Choose product ─────────────────────────── -->
        <div v-if="step === 1" class="space-y-4">
          <h2 class="text-lg font-semibold text-gray-900">Choose a Loan Product</h2>
          <div
            v-for="type in products"
            :key="type.id"
            class="border-2 rounded-xl p-4 cursor-pointer transition-colors"
            :class="form.loan_type_id === type.id ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 bg-white hover:border-emerald-300'"
            @click="selectType(type)"
          >
            <div class="flex items-center justify-between mb-1">
              <h3 class="font-semibold text-gray-900">{{ type.name }}</h3>
              <span class="text-xs font-mono text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ type.code }}</span>
            </div>
            <p v-if="type.description" class="text-xs text-gray-500 mb-2">{{ type.description }}</p>
            <div class="flex flex-wrap gap-2 text-xs">
              <span v-if="type.requires_collateral" class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Requires collateral</span>
              <span v-if="type.requires_guarantor" class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Requires guarantor</span>
              <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ type.plans.length }} plan{{ type.plans.length !== 1 ? 's' : '' }}</span>
            </div>
          </div>
          <div v-if="!products.length" class="py-10 text-center text-gray-400 text-sm">
            No loan products available at this time.
          </div>
        </div>

        <!-- ─── Step 2: Configure loan ─────────────────────────── -->
        <div v-if="step === 2" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900">Configure Your Loan</h2>

          <!-- Plan selection -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Select Plan</label>
            <div class="space-y-2">
              <div
                v-for="plan in selectedType.plans"
                :key="plan.id"
                class="border-2 rounded-xl p-3 cursor-pointer transition-colors"
                :class="form.loan_plan_id === plan.id ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 bg-white hover:border-emerald-300'"
                @click="selectPlan(plan)"
              >
                <div class="flex items-center justify-between">
                  <span class="font-medium text-gray-900 text-sm">{{ plan.name }}</span>
                  <span class="text-sm font-bold text-emerald-700">{{ plan.interest_rate }}% / {{ plan.interest_period }}</span>
                </div>
                <div class="mt-1 grid grid-cols-3 gap-2 text-xs text-gray-500">
                  <span>{{ plan.min_tenure }}–{{ plan.max_tenure }} {{ plan.tenure_type }}</span>
                  <span>K {{ fmtK(plan.min_amount) }}–{{ fmtK(plan.max_amount) }}</span>
                  <span class="capitalize">{{ plan.repayment_schedule?.replace('_', ' ') }}</span>
                </div>
              </div>
            </div>
            <p v-if="errors.loan_plan_id" class="mt-1 text-xs text-red-600">{{ errors.loan_plan_id }}</p>
          </div>

          <template v-if="selectedPlan">
            <!-- Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Loan Amount (K {{ fmtK(selectedPlan.min_amount) }} – {{ fmtK(selectedPlan.max_amount) }})
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">K</span>
                <input
                  v-model.number="form.principal_amount"
                  type="number"
                  :min="selectedPlan.min_amount"
                  :max="selectedPlan.max_amount"
                  step="1"
                  class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-400"
                />
              </div>
              <p v-if="errors.principal_amount" class="mt-1 text-xs text-red-600">{{ errors.principal_amount }}</p>
            </div>

            <!-- Tenure -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Tenure ({{ selectedPlan.min_tenure }}–{{ selectedPlan.max_tenure }} {{ selectedPlan.tenure_type }})
              </label>
              <div class="flex items-center gap-3">
                <input
                  v-model.number="form.tenure"
                  type="range"
                  :min="selectedPlan.min_tenure"
                  :max="selectedPlan.max_tenure"
                  class="flex-1 accent-emerald-500"
                />
                <span class="text-sm font-semibold text-emerald-700 w-24 text-right">
                  {{ form.tenure }} {{ selectedPlan.tenure_type }}
                </span>
              </div>
              <p v-if="errors.tenure" class="mt-1 text-xs text-red-600">{{ errors.tenure }}</p>
            </div>

            <!-- Estimate banner -->
            <div v-if="form.principal_amount > 0" class="bg-emerald-50 rounded-xl p-4 space-y-2 text-sm">
              <p class="font-semibold text-emerald-800">Loan Estimate</p>
              <div class="grid grid-cols-2 gap-2 text-emerald-900">
                <span class="text-gray-500">Interest</span>
                <span class="font-medium text-right">{{ estimate.interest_rate }}% / {{ selectedPlan.interest_period }}</span>
                <span class="text-gray-500">Est. Total Repayable</span>
                <span class="font-bold text-right">K {{ fmtAmount(estimate.total) }}</span>
              </div>
              <p class="text-xs text-gray-400">* Estimate only. Final amount calculated on approval.</p>
            </div>

            <!-- Loan Purpose -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Loan Purpose</label>
              <textarea
                v-model="form.loan_purpose"
                rows="2"
                placeholder="Briefly describe the purpose of this loan…"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400"
              />
            </div>
          </template>
        </div>

        <!-- ─── Step 3: Additional details ─────────────────────── -->
        <div v-if="step === 3" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900">Additional Details</h2>

          <!-- Collateral -->
          <div v-if="selectedType?.requires_collateral">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Collateral Description <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="form.collateral_description"
              rows="2"
              placeholder="Describe the collateral offered…"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400"
            />
            <p v-if="errors.collateral_description" class="mt-1 text-xs text-red-600">{{ errors.collateral_description }}</p>
          </div>

          <!-- Guarantor -->
          <div v-if="selectedType?.requires_guarantor">
            <p class="text-sm font-medium text-gray-700 mb-2">Guarantor Details <span class="text-red-500">*</span></p>
            <div class="space-y-3">
              <input v-model="form.guarantor_name" type="text" placeholder="Full name" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" />
              <input v-model="form.guarantor_phone" type="tel" placeholder="Phone number" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" />
              <input v-model="form.guarantor_relationship" type="text" placeholder="Relationship (e.g. Spouse, Sibling)" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" />
            </div>
          </div>

          <!-- Summary card -->
          <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm border border-gray-200">
            <p class="font-semibold text-gray-900 mb-1">Application Summary</p>
            <div class="grid grid-cols-2 gap-y-1.5">
              <span class="text-gray-500">Product</span><span class="font-medium text-right">{{ selectedType?.name }}</span>
              <span class="text-gray-500">Plan</span><span class="font-medium text-right">{{ selectedPlan?.name }}</span>
              <span class="text-gray-500">Amount</span><span class="font-bold text-emerald-700 text-right">K {{ fmtAmount(form.principal_amount) }}</span>
              <span class="text-gray-500">Tenure</span><span class="font-medium text-right">{{ form.tenure }} {{ selectedPlan?.tenure_type }}</span>
              <span class="text-gray-500">Rate</span><span class="font-medium text-right">{{ selectedPlan?.interest_rate }}% / {{ selectedPlan?.interest_period }}</span>
            </div>
          </div>

          <!-- Server error -->
          <div v-if="submitError" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
            {{ submitError }}
          </div>
        </div>

        <!-- ─── Navigation buttons ──────────────────────────────── -->
        <div class="mt-8 flex gap-3">
          <button
            v-if="step > 1"
            @click="step--"
            class="flex-1 py-3.5 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition"
          >
            Back
          </button>
          <button
            v-if="step < 3"
            @click="nextStep"
            :disabled="!canProceed"
            class="flex-1 py-3.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition disabled:opacity-50"
          >
            Continue
          </button>
          <button
            v-if="step === 3"
            @click="submit"
            :disabled="submitting"
            class="flex-1 py-3.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            {{ submitting ? 'Submitting…' : 'Submit Application' }}
          </button>
        </div>
      </template>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import { CheckIcon } from '@heroicons/vue/24/solid'
import axios from 'axios'

const stepLabels   = ['Product', 'Configure', 'Review']
const step         = ref(1)
const products     = ref([])
const loadingProducts = ref(true)
const loadError    = ref(false)

const selectedType = ref(null)
const selectedPlan = ref(null)

const form = reactive({
  loan_type_id: null,
  loan_plan_id: null,
  principal_amount: 0,
  tenure: 1,
  loan_purpose: '',
  collateral_description: '',
  guarantor_name: '',
  guarantor_phone: '',
  guarantor_relationship: '',
})

const errors     = reactive({})
const submitting = ref(false)
const submitError = ref('')

async function loadProducts() {
  loadingProducts.value = true
  loadError.value = false
  try {
    const res = await axios.get('/api/v1/me/loan-products')
    products.value = res.data.data
  } catch {
    loadError.value = true
  } finally {
    loadingProducts.value = false
  }
}

onMounted(loadProducts)

function selectType(type) {
  form.loan_type_id = type.id
  selectedType.value = type
  form.loan_plan_id = null
  selectedPlan.value = null
}

function selectPlan(plan) {
  form.loan_plan_id = plan.id
  selectedPlan.value = plan
  form.principal_amount = plan.min_amount
  form.tenure = plan.min_tenure
}

const canProceed = computed(() => {
  if (step.value === 1) return !!form.loan_type_id
  if (step.value === 2) return !!form.loan_plan_id && form.principal_amount > 0 && form.tenure >= 1
  return true
})

const estimate = computed(() => {
  if (!selectedPlan.value || !form.principal_amount) return { interest_rate: 0, total: 0 }
  const p = form.principal_amount
  const r = selectedPlan.value.interest_rate / 100
  const t = form.tenure
  // Simple flat rate estimate
  const interest = p * r * t
  return {
    interest_rate: selectedPlan.value.interest_rate,
    total: p + interest,
  }
})

function fmtAmount(n) {
  return Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function fmtK(n) {
  return Number(n).toLocaleString('en-ZM', { maximumFractionDigits: 0 })
}

function nextStep() {
  if (canProceed.value) step.value++
}

async function submit() {
  submitting.value = true
  submitError.value = ''
  Object.keys(errors).forEach(k => { errors[k] = '' })
  try {
    const res = await axios.post('/api/v1/me/loans/apply', {
      loan_type_id:           form.loan_type_id,
      loan_plan_id:           form.loan_plan_id,
      principal_amount:       form.principal_amount,
      tenure:                 form.tenure,
      loan_purpose:           form.loan_purpose || undefined,
      collateral_description: form.collateral_description || undefined,
      guarantor_name:         form.guarantor_name || undefined,
      guarantor_phone:        form.guarantor_phone || undefined,
      guarantor_relationship: form.guarantor_relationship || undefined,
    })
    const loan = res.data.data
    router.visit(route('pwa.loans.show', { id: loan.id }), {
      data: { applied: '1' },
    })
  } catch (e) {
    if (e.response?.status === 422) {
      Object.assign(errors, e.response.data.errors ?? {})
      // Go back to step 2 if there are field errors
      if (errors.principal_amount || errors.tenure || errors.loan_plan_id) step.value = 2
    } else {
      submitError.value = e.response?.data?.message ?? 'Failed to submit application. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}
</script>
