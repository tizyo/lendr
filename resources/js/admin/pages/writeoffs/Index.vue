<template>
  <AppLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">Write-offs & Recovery</h1>
        <p class="text-sm text-neutral-500 mt-0.5">{{ writeoffs.total }} record{{ writeoffs.total !== 1 ? 's' : '' }}</p>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex gap-3">
      <input v-model="dateFrom" type="date" class="input" @change="reload" />
      <input v-model="dateTo"   type="date" class="input" @change="reload" />
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Loan</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Borrower</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Written-off</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Recovered</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Net Loss</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Recovery %</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Date</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="w in writeoffs.data" :key="w.id" class="hover:bg-neutral-25">
              <td class="px-5 py-3 font-mono text-xs font-semibold">{{ w.loan?.loan_number }}</td>
              <td class="px-5 py-3">{{ w.loan?.borrower?.first_name }} {{ w.loan?.borrower?.last_name }}</td>
              <td class="px-5 py-3 text-right text-red-700 font-medium">{{ fmt(w.written_off_amount) }}</td>
              <td class="px-5 py-3 text-right text-green-700">{{ fmt(w.total_recovered) }}</td>
              <td class="px-5 py-3 text-right font-semibold">{{ fmt(w.written_off_amount - w.total_recovered) }}</td>
              <td class="px-5 py-3 text-right">{{ w.recovery_rate }}%</td>
              <td class="px-5 py-3 text-neutral-500 text-xs">{{ w.written_off_at }}</td>
              <td class="px-5 py-3 text-right">
                <button @click="openRecovery(w)" class="text-xs text-primary-600 hover:underline">+ Recovery</button>
              </td>
            </tr>
            <tr v-if="!writeoffs.data?.length">
              <td colspan="8" class="px-5 py-10 text-center text-neutral-400">No write-offs found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recovery Modal -->
    <div v-if="recoveryModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Record Recovery — {{ recoveryModal.loan?.loan_number }}</h3>
        <div class="space-y-3">
          <div>
            <label class="label">Amount *</label>
            <input v-model="recovForm.amount" type="number" step="0.01" class="input w-full" />
          </div>
          <div>
            <label class="label">Method *</label>
            <select v-model="recovForm.method" class="input w-full">
              <option value="cash">Cash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="mobile_money">Mobile Money</option>
            </select>
          </div>
          <div>
            <label class="label">Reference</label>
            <input v-model="recovForm.reference" type="text" class="input w-full" />
          </div>
          <p v-if="recovError" class="text-sm text-red-600">{{ recovError }}</p>
        </div>
        <div class="flex gap-2 mt-4">
          <button @click="recoveryModal = null" class="btn-secondary flex-1">Cancel</button>
          <button @click="submitRecovery" :disabled="recovLoading" class="btn-primary flex-1">
            {{ recovLoading ? 'Saving…' : 'Record' }}
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

const props = defineProps({ writeoffs: Object })

const dateFrom = ref('')
const dateTo = ref('')
const recoveryModal = ref(null)
const recovForm = ref({ amount: '', method: 'cash', reference: '' })
const recovLoading = ref(false)
const recovError = ref('')

function fmt(v) {
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2 }).format(v)
}

function reload() {
  router.get(route('writeoffs.index'), { date_from: dateFrom.value, date_to: dateTo.value }, { preserveState: true, replace: true })
}

function openRecovery(w) {
  recoveryModal.value = w
  recovForm.value = { amount: '', method: 'cash', reference: '' }
  recovError.value = ''
}

async function submitRecovery() {
  recovLoading.value = true; recovError.value = ''
  try {
    await axios.post(`/api/v1/loans/${recoveryModal.value.loan_id}/recovery`, recovForm.value)
    recoveryModal.value = null
    router.reload()
  } catch (e) {
    recovError.value = e.response?.data?.message ?? 'Failed.'
  } finally {
    recovLoading.value = false
  }
}
</script>
