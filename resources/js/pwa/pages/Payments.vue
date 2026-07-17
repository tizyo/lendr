<template>
  <PwaLayout title="Payments" :show-back="true">
    <div class="px-4 py-6 space-y-4">

      <div v-if="loading" class="flex justify-center py-12">
        <svg class="w-8 h-8 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
      </div>

      <div v-else-if="!payments.length" class="py-16 text-center text-gray-400 text-sm">
        No payment history yet.
      </div>

      <div
        v-for="payment in payments"
        :key="payment.id"
        class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-between gap-3"
      >
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 bg-emerald-50 rounded-full flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900">K {{ payment.amount }}</p>
            <p class="text-xs text-gray-500 mt-0.5 truncate">
              {{ payment.loan_number }} · {{ payment.method }}
            </p>
          </div>
        </div>
        <div class="text-right shrink-0">
          <p class="text-xs text-gray-400">{{ payment.paid_at }}</p>
          <p class="text-[11px] font-mono text-gray-400 mt-0.5">{{ payment.payment_number }}</p>
        </div>
      </div>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

const auth = usePwaAuthStore()
const payments = ref([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/me/payments')
    payments.value = data.data ?? []
  } catch {
    auth.clearAuth()
    router.visit(route('pwa.auth.login'))
  } finally {
    loading.value = false
  }
})
</script>
