<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
          <Link :href="route('savings.index')" class="text-neutral-400 hover:text-neutral-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
          </Link>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-2xl font-bold text-neutral-900">{{ account.account_number }}</h1>
              <span :class="statusClass(account.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ account.status }}</span>
            </div>
            <p class="text-sm text-neutral-500 mt-0.5 capitalize">{{ account.type }} savings account</p>
          </div>
        </div>
        <div class="flex gap-2">
          <button v-if="account.status === 'active'" @click="openTxn('deposit')" class="btn-primary">+ Deposit</button>
          <button v-if="account.status === 'active'" @click="openTxn('withdraw')" class="btn-secondary">Withdraw</button>
        </div>
      </div>
    </template>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Balance</p>
        <p class="text-2xl font-bold text-primary-600 mt-1">K {{ fmt(account.balance) }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Interest Rate</p>
        <p class="text-2xl font-bold text-neutral-900 mt-1">{{ account.interest_rate }}%</p>
        <p class="text-xs text-neutral-400">per annum</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Transactions</p>
        <p class="text-2xl font-bold text-neutral-900 mt-1">{{ account.transactions?.length ?? 0 }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Opened</p>
        <p class="text-lg font-bold text-neutral-900 mt-1">{{ account.opened_date }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Borrower Info -->
      <div class="lendr-card p-5">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Account Holder</h2>
        <div class="space-y-3 text-sm">
          <div>
            <p class="text-xs text-neutral-500">Name</p>
            <p class="font-medium">{{ account.borrower?.first_name }} {{ account.borrower?.last_name }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Borrower #</p>
            <p class="font-mono">{{ account.borrower?.borrower_number }}</p>
          </div>
          <div v-if="account.borrower?.phone">
            <p class="text-xs text-neutral-500">Phone</p>
            <p>{{ account.borrower.phone }}</p>
          </div>
          <div v-if="account.borrower?.email">
            <p class="text-xs text-neutral-500">Email</p>
            <p class="truncate">{{ account.borrower.email }}</p>
          </div>
          <div v-if="account.target_amount">
            <p class="text-xs text-neutral-500">Target Amount</p>
            <p class="font-medium">K {{ fmt(account.target_amount) }}</p>
            <div class="mt-1 bg-neutral-100 rounded-full h-2">
              <div class="bg-primary-500 h-2 rounded-full" :style="{ width: Math.min(100, (account.balance / account.target_amount) * 100).toFixed(1) + '%' }"></div>
            </div>
            <p class="text-xs text-neutral-400 mt-0.5">{{ ((account.balance / account.target_amount) * 100).toFixed(1) }}% of target</p>
          </div>
          <div v-if="account.maturity_date">
            <p class="text-xs text-neutral-500">Maturity Date</p>
            <p>{{ account.maturity_date }}</p>
          </div>
          <div v-if="account.notes">
            <p class="text-xs text-neutral-500">Notes</p>
            <p class="text-neutral-600">{{ account.notes }}</p>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-neutral-100">
          <Link :href="route('borrowers.show', account.borrower_id)" class="text-xs text-primary-600 hover:underline">
            View Borrower Profile →
          </Link>
        </div>
      </div>

      <!-- Transactions -->
      <div class="lg:col-span-2 lendr-card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-100">
          <h2 class="text-sm font-semibold text-neutral-800">Transaction History</h2>
          <p class="text-xs text-neutral-400 mt-0.5">Last 50 transactions</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-neutral-100 bg-neutral-50">
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Date</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Type</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Amount</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Balance After</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Reference</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-if="!account.transactions?.length">
                <td colspan="5" class="px-5 py-10 text-center text-neutral-400">No transactions yet.</td>
              </tr>
              <tr v-for="t in account.transactions" :key="t.id" class="hover:bg-neutral-25">
                <td class="px-5 py-3 text-neutral-500 text-xs whitespace-nowrap">{{ t.transaction_date }}</td>
                <td class="px-5 py-3">
                  <span :class="txnTypeClass(t.type)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ t.type }}</span>
                </td>
                <td class="px-5 py-3 text-right font-medium" :class="t.type === 'withdraw' ? 'text-red-600' : 'text-green-700'">
                  {{ t.type === 'withdraw' ? '−' : '+' }} K {{ fmt(t.amount) }}
                </td>
                <td class="px-5 py-3 text-right text-neutral-700">K {{ fmt(t.balance_after) }}</td>
                <td class="px-5 py-3 text-neutral-500 text-xs">{{ t.reference ?? t.notes ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Transaction Modal -->
    <div v-if="txnModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 capitalize">{{ txnModal }} — {{ account.account_number }}</h3>
        <div class="space-y-3">
          <div>
            <label class="lendr-label">Amount *</label>
            <input v-model="txnForm.amount" type="number" step="0.01" class="lendr-input w-full"
              :placeholder="txnModal === 'withdraw' ? `Max: K ${fmt(account.balance)}` : 'Enter amount'" />
          </div>
          <div>
            <label class="lendr-label">Reference (optional)</label>
            <input v-model="txnForm.reference" type="text" class="lendr-input w-full" />
          </div>
          <div>
            <label class="lendr-label">Notes (optional)</label>
            <input v-model="txnForm.notes" type="text" class="lendr-input w-full" />
          </div>
          <p v-if="txnError" class="text-sm text-red-600">{{ txnError }}</p>
        </div>
        <div class="flex gap-2 mt-4">
          <button @click="txnModal = null" class="btn-secondary flex-1">Cancel</button>
          <button @click="submitTxn" :disabled="txnLoading" class="btn-primary flex-1">
            {{ txnLoading ? 'Processing…' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ account: Object })

const txnModal = ref(null)
const txnForm  = ref({ amount: '', reference: '', notes: '' })
const txnLoading = ref(false)
const txnError   = ref('')

function fmt(v) {
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0)
}

function statusClass(s) {
  return {
    active:  'bg-green-100 text-green-700',
    dormant: 'bg-yellow-100 text-yellow-700',
    closed:  'bg-neutral-100 text-neutral-500',
  }[s] ?? ''
}

function txnTypeClass(t) {
  return {
    deposit:  'bg-green-100 text-green-700',
    withdraw: 'bg-red-100 text-red-700',
    interest: 'bg-blue-100 text-blue-700',
  }[t] ?? 'bg-neutral-100 text-neutral-600'
}

function openTxn(type) {
  txnModal.value = type
  txnForm.value  = { amount: '', reference: '', notes: '' }
  txnError.value = ''
}

async function submitTxn() {
  txnLoading.value = true
  txnError.value   = ''
  try {
    await axios.post(`/api/v1/savings/${props.account.id}/${txnModal.value}`, txnForm.value)
    txnModal.value = null
    router.reload()
  } catch (e) {
    txnError.value = e.response?.data?.message ?? 'Failed.'
  } finally {
    txnLoading.value = false
  }
}
</script>
