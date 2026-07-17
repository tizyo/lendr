<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Savings Accounts</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ accounts.total }} account{{ accounts.total !== 1 ? 's' : '' }}</p>
        </div>
        <button @click="showCreate = true" class="btn-primary">+ Open Account</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <input v-model="search" type="text" placeholder="Search borrower…" class="input flex-1" @input="reload" />
      <select v-model="filterStatus" class="input w-40" @change="reload">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="dormant">Dormant</option>
        <option value="closed">Closed</option>
      </select>
      <select v-model="filterType" class="input w-40" @change="reload">
        <option value="">All Types</option>
        <option value="regular">Regular</option>
        <option value="fixed">Fixed Deposit</option>
        <option value="target">Target</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Account #</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Borrower</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Type</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Balance</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Interest Rate</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Opened</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="a in accounts.data" :key="a.id" class="hover:bg-neutral-25">
              <td class="px-5 py-3 font-mono text-xs font-semibold">{{ a.account_number }}</td>
              <td class="px-5 py-3">
                <div class="font-medium">{{ a.borrower?.first_name }} {{ a.borrower?.last_name }}</div>
                <div class="text-xs text-neutral-400">{{ a.borrower?.borrower_number }}</div>
              </td>
              <td class="px-5 py-3 capitalize">{{ a.type }}</td>
              <td class="px-5 py-3 text-right font-semibold">{{ fmt(a.balance) }}</td>
              <td class="px-5 py-3 text-right">{{ a.interest_rate }}% p.a.</td>
              <td class="px-5 py-3">
                <span :class="statusClass(a.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ a.status }}</span>
              </td>
              <td class="px-5 py-3 text-neutral-500 text-xs">{{ a.opened_date }}</td>
              <td class="px-5 py-3 text-right">
                <button @click="openDeposit(a)" class="text-xs text-primary-600 hover:underline mr-3">Deposit</button>
                <button @click="openWithdraw(a)" class="text-xs text-amber-600 hover:underline">Withdraw</button>
              </td>
            </tr>
            <tr v-if="!accounts.data?.length">
              <td colspan="8" class="px-5 py-10 text-center text-neutral-400">No savings accounts found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Transaction Modal -->
    <div v-if="txnModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 capitalize">{{ txnModal.type }} — {{ txnModal.account.account_number }}</h3>
        <div class="space-y-3">
          <div>
            <label class="label">Amount</label>
            <input v-model="txnForm.amount" type="number" step="0.01" class="input w-full" />
          </div>
          <div>
            <label class="label">Reference (optional)</label>
            <input v-model="txnForm.reference" type="text" class="input w-full" />
          </div>
          <div>
            <label class="label">Notes (optional)</label>
            <input v-model="txnForm.notes" type="text" class="input w-full" />
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

    <!-- Create Modal -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Open Savings Account</h3>
        <div class="space-y-3">
          <div>
            <label class="label">Borrower ID</label>
            <input v-model="createForm.borrower_id" type="number" class="input w-full" placeholder="Borrower ID" />
          </div>
          <div>
            <label class="label">Account Type</label>
            <select v-model="createForm.type" class="input w-full">
              <option value="regular">Regular</option>
              <option value="fixed">Fixed Deposit</option>
              <option value="target">Target</option>
            </select>
          </div>
          <div>
            <label class="label">Interest Rate (% p.a.)</label>
            <input v-model="createForm.interest_rate" type="number" step="0.01" class="input w-full" />
          </div>
          <p v-if="createError" class="text-sm text-red-600">{{ createError }}</p>
        </div>
        <div class="flex gap-2 mt-4">
          <button @click="showCreate = false" class="btn-secondary flex-1">Cancel</button>
          <button @click="submitCreate" :disabled="createLoading" class="btn-primary flex-1">
            {{ createLoading ? 'Opening…' : 'Open Account' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ accounts: Object })

const search = ref('')
const filterStatus = ref('')
const filterType = ref('')
const showCreate = ref(false)
const txnModal = ref(null)
const txnForm = ref({ amount: '', reference: '', notes: '' })
const txnLoading = ref(false)
const txnError = ref('')
const createForm = ref({ borrower_id: '', type: 'regular', interest_rate: 0 })
const createLoading = ref(false)
const createError = ref('')

function fmt(v) {
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2 }).format(v)
}

function statusClass(s) {
  return { active: 'bg-green-100 text-green-700', dormant: 'bg-yellow-100 text-yellow-700', closed: 'bg-neutral-100 text-neutral-500' }[s] ?? ''
}

function reload() {
  router.get(route('savings.index'), { search: search.value, status: filterStatus.value, type: filterType.value }, { preserveState: true, replace: true })
}

function openDeposit(a) { txnModal.value = { type: 'deposit', account: a }; txnForm.value = { amount: '', reference: '', notes: '' }; txnError.value = '' }
function openWithdraw(a) { txnModal.value = { type: 'withdraw', account: a }; txnForm.value = { amount: '', reference: '', notes: '' }; txnError.value = '' }

async function submitTxn() {
  txnLoading.value = true; txnError.value = ''
  try {
    await axios.post(`/api/v1/savings/${txnModal.value.account.id}/${txnModal.value.type}`, txnForm.value)
    txnModal.value = null
    router.reload()
  } catch (e) {
    txnError.value = e.response?.data?.message ?? 'Failed.'
  } finally {
    txnLoading.value = false
  }
}

async function submitCreate() {
  createLoading.value = true; createError.value = ''
  try {
    await axios.post('/api/v1/savings', createForm.value)
    showCreate.value = false
    router.reload()
  } catch (e) {
    createError.value = e.response?.data?.message ?? 'Failed.'
  } finally {
    createLoading.value = false
  }
}
</script>
