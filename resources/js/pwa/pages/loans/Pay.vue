<template>
  <PwaLayout title="Make Payment" :show-back="true">
    <div class="px-4 py-6 space-y-5 max-w-lg mx-auto">

      <!-- Loading loan / gateways -->
      <div v-if="loading" class="flex justify-center py-16">
        <svg class="w-8 h-8 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
      </div>

      <template v-else-if="loan">

        <!-- Loan summary card -->
        <div class="bg-emerald-600 rounded-2xl p-5 text-white">
          <p class="text-emerald-100 text-xs uppercase tracking-wide">{{ loan.loan_number }}</p>
          <h2 class="text-xl font-bold mt-0.5">{{ loan.loan_type }}</h2>
          <div class="mt-4 flex items-end justify-between">
            <div>
              <p class="text-emerald-200 text-xs uppercase tracking-wide">Outstanding</p>
              <p class="text-3xl font-bold mt-0.5">K {{ fmt(loan.outstanding_balance) }}</p>
            </div>
            <div class="text-right">
              <p class="text-emerald-200 text-xs uppercase tracking-wide">Status</p>
              <p class="text-sm font-semibold mt-0.5 capitalize">{{ loan.status }}</p>
            </div>
          </div>
        </div>

        <!-- ── No gateways configured ── -->
        <div v-if="providers.length === 0" class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center space-y-2">
          <svg class="w-10 h-10 text-amber-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p class="font-semibold text-amber-800 text-sm">Mobile Money Not Available</p>
          <p class="text-xs text-amber-700">
            No mobile money gateways are configured for this account. Please contact your loan officer to make a payment.
          </p>
        </div>

        <template v-else>

          <!-- ── Processing / polling state ── -->
          <div v-if="intent" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">

            <!-- Pending / processing -->
            <div v-if="intent.status === 'pending' || intent.status === 'processing'" class="text-center space-y-4">
              <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-8 h-8 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
              </div>
              <div>
                <p class="text-base font-bold text-gray-900">Waiting for Approval</p>
                <p class="text-sm text-gray-500 mt-1">
                  Check your phone for a <strong class="capitalize">{{ providerLabel(intent.provider) }}</strong> prompt
                  and enter your PIN to authorise the <strong>K {{ fmt(intent.amount) }}</strong> payment.
                </p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-500 text-left space-y-1">
                <div class="flex justify-between">
                  <span>Reference</span>
                  <span class="font-mono font-medium text-gray-700">{{ intent.reference }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Provider</span>
                  <span class="font-medium text-gray-700 capitalize">{{ providerLabel(intent.provider) }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Phone</span>
                  <span class="font-medium text-gray-700">{{ form.phone }}</span>
                </div>
                <div v-if="intent.expires_at" class="flex justify-between">
                  <span>Expires</span>
                  <span class="font-medium text-gray-700">{{ expiresLabel }}</span>
                </div>
              </div>
              <p class="text-xs text-gray-400">Checking status automatically…</p>
            </div>

            <!-- Completed / success -->
            <div v-else-if="intent.status === 'completed'" class="text-center space-y-4">
              <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
              </div>
              <div>
                <p class="text-base font-bold text-gray-900">Payment Confirmed!</p>
                <p class="text-sm text-gray-500 mt-1">
                  Your payment of <strong>K {{ fmt(intent.amount) }}</strong> has been received and applied to your loan.
                </p>
              </div>
              <button
                @click="$inertia.visit(route('pwa.loans.show', loanId))"
                class="w-full py-3 rounded-xl bg-emerald-600 text-white font-semibold text-sm"
              >
                Back to Loan
              </button>
            </div>

            <!-- Failed / expired -->
            <div v-else class="text-center space-y-4">
              <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </div>
              <div>
                <p class="text-base font-bold text-gray-900">
                  {{ intent.status === 'expired' ? 'Request Expired' : 'Payment Failed' }}
                </p>
                <p class="text-sm text-gray-500 mt-1">
                  {{ intent.status === 'expired'
                    ? 'The payment prompt was not approved in time.'
                    : 'The payment could not be processed. Please try again.' }}
                </p>
              </div>
              <button @click="retryPayment" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-semibold text-sm">
                Try Again
              </button>
            </div>

          </div>

          <!-- ── Payment form ── -->
          <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-5">
            <p class="text-sm font-semibold text-gray-800">Payment Details</p>

            <!-- Amount -->
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Amount (ZMW)</label>
              <input
                v-model.number="form.amount"
                type="number"
                step="0.01"
                :min="1"
                :max="loan.outstanding_balance"
                class="field"
                placeholder="0.00"
              />
              <button
                @click="form.amount = loan.outstanding_balance"
                class="mt-1.5 text-xs text-emerald-600 font-medium"
              >
                Pay full balance (K {{ fmt(loan.outstanding_balance) }})
              </button>
              <!-- Next instalment shortcut -->
              <button
                v-if="nextInstalment"
                @click="form.amount = nextInstalment.outstanding"
                class="ml-3 mt-1.5 text-xs text-blue-600 font-medium"
              >
                Pay next instalment (K {{ fmt(nextInstalment.outstanding) }})
              </button>
              <p v-if="errors.amount" class="mt-1 text-xs text-red-600">{{ errors.amount }}</p>
            </div>

            <!-- Phone number -->
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Mobile Money Phone Number</label>
              <input
                v-model="form.phone"
                type="tel"
                class="field"
                placeholder="e.g. 0971234567"
              />
              <p class="mt-1 text-xs text-gray-400">The number that will receive the payment PIN prompt.</p>
              <p v-if="errors.phone" class="mt-1 text-xs text-red-600">{{ errors.phone }}</p>
            </div>

            <!-- Provider selection -->
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-2">Mobile Money Provider</label>
              <div class="grid grid-cols-2 gap-2">
                <button
                  v-for="p in providers"
                  :key="p.value"
                  @click="form.provider = p.value"
                  type="button"
                  class="py-3 px-4 rounded-xl border-2 text-sm font-medium transition"
                  :class="form.provider === p.value
                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                    : 'border-gray-200 text-gray-700 hover:border-gray-300'"
                >
                  {{ p.label }}
                </button>
              </div>
              <p v-if="errors.provider" class="mt-1 text-xs text-red-600">{{ errors.provider }}</p>
            </div>

            <!-- Error -->
            <div v-if="submitError" class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700">
              {{ submitError }}
            </div>

            <!-- How it works -->
            <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-500 space-y-1">
              <p class="font-medium text-gray-700 mb-1">How it works:</p>
              <p>1. Select your provider and enter your number</p>
              <p>2. Tap "Send Payment Request"</p>
              <p>3. A PIN prompt will appear on your phone — approve it</p>
              <p>4. Your loan balance updates automatically</p>
            </div>

            <!-- Submit -->
            <button
              @click="submit"
              :disabled="submitting || !form.amount || !form.phone || !form.provider"
              class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition disabled:opacity-50 flex items-center justify-center gap-2"
            >
              <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ submitting ? 'Sending…' : 'Send Payment Request' }}
            </button>
          </div>

        </template>
      </template>

      <!-- Loan not found / error -->
      <div v-else class="py-16 text-center text-gray-400 text-sm">
        Unable to load loan details.
      </div>

    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

const props = defineProps({
  loanId: { type: [Number, String], required: true },
})

const auth = usePwaAuthStore()

// ─── State ────────────────────────────────────────────────────────────────────
const loan       = ref(null)
const providers  = ref([])     // only configured gateways
const loading    = ref(true)
const submitting = ref(false)
const submitError = ref('')
const intent     = ref(null)   // active MobileMoneyIntent being polled

const form = reactive({ amount: '', phone: '', provider: '' })
const errors = reactive({ amount: '', phone: '', provider: '' })

// Polling interval handle
let pollTimer = null

// ─── Computed ─────────────────────────────────────────────────────────────────
const nextInstalment = computed(() => {
  if (! loan.value?.schedule) return null
  return loan.value.schedule.find(s => !s.is_paid && s.outstanding > 0) ?? null
})

const expiresLabel = computed(() => {
  if (! intent.value?.expires_at) return null
  const secs = Math.max(0, Math.round((new Date(intent.value.expires_at) - Date.now()) / 1000))
  if (secs <= 0) return 'Expired'
  const m = Math.floor(secs / 60)
  const s = secs % 60
  return m > 0 ? `${m}m ${s}s` : `${s}s`
})

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  loading.value = true
  try {
    const [loanRes, gatewaysRes, meRes] = await Promise.all([
      axios.get(`/api/v1/me/loans/${props.loanId}`),
      axios.get('/api/v1/me/payment-gateways'),
      axios.get('/api/v1/me'),
    ])
    loan.value      = loanRes.data.data
    providers.value = gatewaysRes.data.data ?? []
    form.phone      = meRes.data.data?.phone ?? ''

    // Pre-fill amount: next instalment if available, else full outstanding
    const next = loan.value?.schedule?.find(s => !s.is_paid && s.outstanding > 0)
    form.amount = next ? next.outstanding : (loan.value?.outstanding_balance ?? '')

    // Auto-select provider if only one is available
    if (providers.value.length === 1) {
      form.provider = providers.value[0].value
    }
  } catch {
    auth.clearAuth()
    router.visit(route('pwa.auth.login'))
  } finally {
    loading.value = false
  }
})

onUnmounted(() => clearInterval(pollTimer))

// ─── Submit ───────────────────────────────────────────────────────────────────
async function submit() {
  Object.assign(errors, { amount: '', phone: '', provider: '' })
  submitError.value = ''

  if (!form.amount || form.amount <= 0) { errors.amount = 'Enter a valid amount.'; return }
  if (!form.phone)                       { errors.phone = 'Enter your mobile money phone number.'; return }
  if (!form.provider)                    { errors.provider = 'Select a provider.'; return }

  submitting.value = true
  try {
    const res = await axios.post(`/api/v1/me/loans/${props.loanId}/initiate-payment`, {
      amount:   Number(form.amount),
      phone:    form.phone,
      provider: form.provider,
    })

    intent.value = res.data.data
    startPolling()
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) {
      if (errs.amount)   errors.amount   = errs.amount[0]
      if (errs.phone)    errors.phone    = errs.phone[0]
      if (errs.provider) errors.provider = errs.provider[0]
    } else {
      submitError.value = e.response?.data?.message ?? 'Failed to send payment request. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}

// ─── Polling ──────────────────────────────────────────────────────────────────
function startPolling() {
  clearInterval(pollTimer)
  pollTimer = setInterval(async () => {
    if (!intent.value) { clearInterval(pollTimer); return }

    // Stop polling terminal states
    if (['completed', 'failed', 'cancelled'].includes(intent.value.status)) {
      clearInterval(pollTimer)
      return
    }

    // Stop if expired
    if (intent.value.expires_at && new Date(intent.value.expires_at) < new Date()) {
      intent.value = { ...intent.value, status: 'expired' }
      clearInterval(pollTimer)
      return
    }

    try {
      const res = await axios.get(
        `/api/v1/me/loans/${props.loanId}/payment-status/${intent.value.reference}`
      )
      intent.value = res.data.data

      if (['completed', 'failed', 'cancelled', 'expired'].includes(intent.value.status)) {
        clearInterval(pollTimer)
      }
    } catch {
      // Silent fail — keep polling
    }
  }, 4000) // poll every 4 seconds
}

function retryPayment() {
  clearInterval(pollTimer)
  intent.value = null
  Object.assign(errors, { amount: '', phone: '', provider: '' })
  submitError.value = ''
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const fmt = (n) => Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const providerLabel = (v) => providers.value.find(p => p.value === v)?.label ?? v
</script>

<style scoped>
.field {
  @apply w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400;
}
</style>
