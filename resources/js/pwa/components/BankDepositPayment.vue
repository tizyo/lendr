<template>
  <div class="space-y-5">

    <!-- Where to pay -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
      <p class="text-sm font-semibold text-gray-800">1. Deposit at the bank</p>

      <dl class="bg-gray-50 rounded-xl divide-y divide-gray-100 text-sm">
        <div v-for="row in bankRows" :key="row.label" class="flex justify-between gap-4 px-4 py-2.5">
          <dt class="text-gray-500">{{ row.label }}</dt>
          <dd class="font-medium text-gray-900 text-right">{{ row.value }}</dd>
        </div>
      </dl>

      <div class="rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50 px-4 py-3 flex items-center justify-between gap-3">
        <div>
          <p class="text-xs text-emerald-700">Use this as the deposit reference</p>
          <p class="font-mono text-lg font-bold text-emerald-900 tracking-wide">{{ reference }}</p>
        </div>
        <button type="button" @click="copyReference" class="text-xs font-semibold text-emerald-700 px-3 py-1.5 rounded-lg bg-white border border-emerald-200">
          {{ copied ? 'Copied' : 'Copy' }}
        </button>
      </div>
    </div>

    <!-- Upload slip -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
      <p class="text-sm font-semibold text-gray-800">2. Upload your deposit slip</p>

      <div v-if="done" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-800">
        Thanks — we'll confirm your deposit once we've checked it with the bank. You'll get a notification and a receipt.
      </div>

      <template v-else>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Amount deposited (ZMW)</label>
          <input v-model.number="form.amount" type="number" step="0.01" min="1" class="field" placeholder="0.00" />
          <p v-if="errors.amount" class="mt-1 text-xs text-red-600">{{ errors.amount }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date paid</label>
            <input v-model="form.paid_on" type="date" :max="today" class="field" />
            <p v-if="errors.paid_on" class="mt-1 text-xs text-red-600">{{ errors.paid_on }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Slip / transaction no. <span class="text-gray-400">(optional)</span></label>
            <input v-model="form.bank_reference" type="text" maxlength="100" class="field" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Photo or PDF of the slip</label>
          <label class="flex items-center justify-center gap-2 w-full py-4 rounded-xl border-2 border-dashed text-sm cursor-pointer"
                 :class="form.file ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-gray-200 text-gray-500'">
            <input type="file" accept="image/*,application/pdf" capture="environment" class="hidden" @change="pickFile" />
            {{ form.file ? form.file.name : 'Take a photo or choose a file' }}
          </label>
          <p v-if="errors.file" class="mt-1 text-xs text-red-600">{{ errors.file }}</p>
        </div>

        <div v-if="submitError" class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700">{{ submitError }}</div>

        <button @click="submit" :disabled="submitting || !form.amount || !form.paid_on || !form.file"
                class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition disabled:opacity-50">
          {{ submitting ? 'Uploading…' : 'Submit deposit slip' }}
        </button>
      </template>
    </div>

    <!-- Earlier submissions for this loan -->
    <div v-if="history.length" class="space-y-2">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide px-1">Your bank deposits</p>
      <div v-for="p in history" :key="p.id" class="bg-white rounded-xl border border-gray-100 p-3 flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-semibold text-gray-900">K {{ fmt(p.amount) }} <span class="text-xs font-normal text-gray-400">· {{ p.paid_on }}</span></p>
          <p v-if="p.status === 'rejected' && p.rejection_reason" class="text-xs text-red-600 mt-0.5">{{ p.rejection_reason }}</p>
        </div>
        <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-medium" :class="statusClass(p.status)">{{ statusLabel(p.status) }}</span>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  loanId:        { type: [Number, String], required: true },
  bank:          { type: Object, required: true },
  reference:     { type: String, required: true },
  defaultAmount: { type: [Number, String], default: '' },
})

const today = new Date().toISOString().slice(0, 10)
const form = reactive({ amount: props.defaultAmount || '', paid_on: today, bank_reference: '', file: null })
const errors = reactive({ amount: '', paid_on: '', file: '' })
const submitting = ref(false)
const submitError = ref('')
const done = ref(false)
const copied = ref(false)
const history = ref([])

const bankRows = computed(() => [
  { label: 'Bank', value: props.bank.bank_name },
  { label: 'Account name', value: props.bank.account_name },
  { label: 'Account number', value: props.bank.account_number },
  { label: 'Branch', value: [props.bank.branch, props.bank.branch_code].filter(Boolean).join(' · ') },
].filter(r => r.value))

onMounted(loadHistory)

async function loadHistory() {
  try {
    const { data } = await axios.get('/api/v1/me/payment-proofs')
    history.value = (data.data ?? []).filter(p => p.loan_number === props.reference)
  } catch {
    // non-critical
  }
}

function pickFile(e) {
  form.file = e.target.files?.[0] ?? null
}

async function copyReference() {
  try {
    await navigator.clipboard.writeText(props.reference)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // clipboard unavailable — the reference is visible anyway
  }
}

async function submit() {
  Object.assign(errors, { amount: '', paid_on: '', file: '' })
  submitError.value = ''
  submitting.value = true

  const body = new FormData()
  body.append('amount', form.amount)
  body.append('paid_on', form.paid_on)
  if (form.bank_reference) body.append('bank_reference', form.bank_reference)
  body.append('file', form.file)

  try {
    await axios.post(`/api/v1/me/loans/${props.loanId}/payment-proofs`, body)
    done.value = true
    loadHistory()
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) {
      errors.amount  = errs.amount?.[0] ?? ''
      errors.paid_on = errs.paid_on?.[0] ?? ''
      errors.file    = errs.file?.[0] ?? ''
      submitError.value = errs.loan?.[0] ?? ''
    } else {
      submitError.value = e.response?.data?.message ?? 'Upload failed. Please check your connection and try again.'
    }
  } finally {
    submitting.value = false
  }
}

const fmt = (n) => Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const statusLabel = (s) => ({ pending: 'Checking', approved: 'Confirmed', rejected: 'Not confirmed' }[s] ?? s)
const statusClass = (s) => ({
  pending:  'bg-amber-100 text-amber-700',
  approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-red-100 text-red-700',
}[s] ?? 'bg-gray-100 text-gray-600')
</script>

<style scoped>
@reference "../../../css/app.css";
.field {
  @apply w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400;
}
</style>
