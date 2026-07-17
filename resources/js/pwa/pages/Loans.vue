<template>
  <PwaLayout title="My Loans" :show-back="true">
    <div class="px-4 py-6 space-y-4">

      <!-- Apply CTA -->
      <Link :href="route('pwa.loans.apply')" class="flex items-center justify-between bg-emerald-600 text-white rounded-xl px-4 py-3.5 shadow-sm hover:bg-emerald-700 transition">
        <div>
          <p class="font-semibold text-sm">Apply for a New Loan</p>
          <p class="text-xs text-emerald-100 mt-0.5">Browse products and submit your application</p>
        </div>
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </Link>

      <div v-if="loading" class="flex justify-center py-12">
        <svg class="w-8 h-8 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
      </div>

      <div v-else-if="!loans.length" class="py-10 text-center text-gray-400 text-sm">
        No loans yet. Apply for your first loan above.
      </div>

      <Link
        v-for="loan in loans"
        :key="loan.id"
        :href="route('pwa.loans.show', { id: loan.id })"
        class="block bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3 hover:border-emerald-200 transition-colors"
      >
        <div class="flex items-center justify-between">
          <p class="text-sm font-bold text-gray-900">{{ loan.loan_number }}</p>
          <span
            class="text-xs px-2.5 py-1 rounded-full font-semibold"
            :class="statusClass(loan.status)"
          >{{ loan.status_label }}</span>
        </div>
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Principal</p>
            <p class="font-semibold text-gray-900 mt-0.5">K {{ loan.principal }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Outstanding</p>
            <p class="font-semibold text-gray-900 mt-0.5">K {{ loan.outstanding }}</p>
          </div>
          <div v-if="loan.type">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Type</p>
            <p class="text-gray-700 mt-0.5">{{ loan.type }}</p>
          </div>
          <div v-if="loan.disbursed_at">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Disbursed</p>
            <p class="text-gray-700 mt-0.5">{{ loan.disbursed_at }}</p>
          </div>
        </div>
      </Link>

      <button
        v-if="nextPage"
        @click="loadMore"
        :disabled="loadingMore"
        class="w-full py-3 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition disabled:opacity-50"
      >
        {{ loadingMore ? 'Loading…' : 'Load more' }}
      </button>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

const auth = usePwaAuthStore()
const loans = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const nextPage = ref(null)

function statusClass(status) {
  const map = {
    active:    'bg-emerald-100 text-emerald-700',
    disbursed: 'bg-blue-100 text-blue-700',
    completed: 'bg-gray-100 text-gray-600',
    overdue:   'bg-red-100 text-red-700',
    defaulted: 'bg-red-100 text-red-700',
    draft:     'bg-gray-100 text-gray-500',
    pending:   'bg-amber-100 text-amber-700',
  }
  return map[status] ?? 'bg-gray-100 text-gray-600'
}

async function fetchLoans(page = 1) {
  const { data } = await axios.get('/api/v1/me/loans', { params: { page, per_page: 10 } })
  return data
}

onMounted(async () => {
  loading.value = true
  try {
    const data = await fetchLoans()
    loans.value = data.data ?? []
    nextPage.value = data.links?.next ? 2 : null
  } catch {
    auth.clearAuth()
    router.visit(route('pwa.auth.login'))
  } finally {
    loading.value = false
  }
})

async function loadMore() {
  if (!nextPage.value) return
  loadingMore.value = true
  try {
    const data = await fetchLoans(nextPage.value)
    loans.value.push(...(data.data ?? []))
    nextPage.value = data.links?.next ? nextPage.value + 1 : null
  } finally {
    loadingMore.value = false
  }
}
</script>
