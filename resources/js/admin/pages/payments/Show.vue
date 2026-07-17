<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <Link :href="route('payments.index')" class="text-neutral-400 hover:text-neutral-600">← Back</Link>
          <h1 class="text-2xl font-bold text-neutral-900">{{ payment.receipt_number }}</h1>
        </div>
        <button class="btn-danger" @click="openReverse">Reverse Payment</button>
      </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main -->
      <div class="lg:col-span-2 space-y-6">
        <div class="lendr-card p-6">
          <h2 class="text-base font-semibold text-neutral-800 mb-4">Payment Details</h2>
          <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
              <dt class="text-neutral-500">Amount</dt>
              <dd class="font-bold text-2xl text-neutral-900">ZMW {{ payment.amount }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Date</dt>
              <dd class="font-medium text-neutral-800">{{ payment.payment_date }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Method</dt>
              <dd class="font-medium text-neutral-800">{{ payment.payment_method }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Reference</dt>
              <dd class="font-medium text-neutral-800">{{ payment.reference || '—' }}</dd>
            </div>
          </dl>
        </div>

        <!-- Allocation -->
        <div class="lendr-card p-6">
          <h2 class="text-base font-semibold text-neutral-800 mb-4">Allocation Breakdown</h2>
          <div class="space-y-3">
            <div v-for="row in allocation" :key="row.label" class="flex justify-between items-center text-sm py-2 border-b border-neutral-100 last:border-0">
              <span class="text-neutral-600">{{ row.label }}</span>
              <span class="font-semibold tabular-nums text-neutral-900">ZMW {{ row.value }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <div class="lendr-card p-5">
          <h2 class="text-sm font-semibold text-neutral-700 mb-3">Loan</h2>
          <dl class="space-y-2 text-sm">
            <div>
              <dt class="text-neutral-500">Loan Number</dt>
              <dd>
                <Link :href="route('loans.show', payment.loan_id)" class="font-mono text-primary-600 hover:underline">
                  {{ payment.loan_number }}
                </Link>
              </dd>
            </div>
            <div>
              <dt class="text-neutral-500">Loan Type</dt>
              <dd class="font-medium text-neutral-800">{{ payment.loan_type }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Borrower</dt>
              <dd class="font-medium text-neutral-800">{{ payment.borrower_name }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Borrower #</dt>
              <dd class="font-mono text-xs text-neutral-600">{{ payment.borrower_number }}</dd>
            </div>
          </dl>
        </div>

        <div class="lendr-card p-5">
          <h2 class="text-sm font-semibold text-neutral-700 mb-3">Meta</h2>
          <dl class="space-y-2 text-sm">
            <div>
              <dt class="text-neutral-500">Recorded By</dt>
              <dd class="font-medium text-neutral-800">{{ payment.recorded_by ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Source</dt>
              <dd class="text-neutral-700">{{ payment.source ?? 'Manual' }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Created At</dt>
              <dd class="text-neutral-700">{{ payment.created_at }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>

    <!-- Reverse Modal -->
    <div v-if="showReverse" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold mb-2">Reverse Payment?</h2>
        <p class="text-sm text-neutral-600 mb-3">This will void the payment record. Provide a reason.</p>
        <textarea v-model="reverseReason" rows="3" class="input w-full resize-none mb-4" placeholder="Reason for reversal…"></textarea>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="showReverse = false">Cancel</button>
          <button class="btn-danger" :disabled="saving || !reverseReason" @click="confirmReverse">
            {{ saving ? 'Reversing…' : 'Reverse' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  payment: { type: Object, required: true },
})

const showReverse = ref(false)
const reverseReason = ref('')
const saving = ref(false)

const allocation = computed(() => [
  { label: 'Principal',  value: props.payment.principal_allocated },
  { label: 'Interest',   value: props.payment.interest_allocated },
  { label: 'Penalties',  value: props.payment.penalty_allocated },
])

const openReverse = () => { showReverse.value = true; reverseReason.value = '' }

const confirmReverse = async () => {
  if (!reverseReason.value) return
  saving.value = true
  try {
    await axios.delete(route('api.v1.payments.destroy', props.payment.id), {
      data: { reason: reverseReason.value },
    })
    showReverse.value = false
    router.visit(route('payments.index'))
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to reverse payment.')
  } finally {
    saving.value = false
  }
}
</script>
