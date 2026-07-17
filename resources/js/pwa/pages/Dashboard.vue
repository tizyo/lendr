<template>
  <PwaLayout title="LENDR" :show-back="false">
    <div class="px-4 py-6 space-y-5">

      <!-- Greeting card -->
      <div class="bg-gradient-to-br from-emerald-600 to-teal-600 rounded-2xl p-5 text-white">
        <p class="text-emerald-100 text-sm">Good {{ greeting }},</p>
        <h2 class="text-xl font-bold mt-0.5">{{ auth.borrower?.first_name ?? 'Borrower' }}</h2>
        <div class="mt-4 flex items-end justify-between">
          <div>
            <p class="text-emerald-200 text-xs uppercase tracking-wide">Outstanding Balance</p>
            <p class="text-3xl font-bold mt-0.5">K {{ summary.outstanding_balance ?? '0.00' }}</p>
          </div>
          <div class="text-right">
            <p class="text-emerald-200 text-xs uppercase tracking-wide">Active Loans</p>
            <p class="text-3xl font-bold mt-0.5">{{ summary.active_loans ?? 0 }}</p>
          </div>
        </div>
      </div>

      <!-- KYC alert banner -->
      <div
        v-if="!auth.borrower?.kyc_verified"
        @click="$inertia.visit(route('pwa.kyc.onboarding'))"
        class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-xl p-4 cursor-pointer"
      >
        <div class="w-9 h-9 bg-amber-100 rounded-lg flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-amber-800">Complete your KYC</p>
          <p class="text-xs text-amber-600 mt-0.5">Verify your identity to apply for loans</p>
        </div>
        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </div>

      <!-- Quick Actions -->
      <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h3>
        <div class="grid grid-cols-5 gap-3">
          <button
            v-for="action in quickActions"
            :key="action.label"
            @click="$inertia.visit(route(action.route))"
            class="flex flex-col items-center gap-2 p-3 bg-white rounded-xl border border-gray-100 shadow-sm hover:bg-gray-50 transition"
          >
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="action.bg">
              <svg class="w-5 h-5" :class="action.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="action.icon"></svg>
            </div>
            <span class="text-xs font-medium text-gray-700 text-center leading-tight">{{ action.label }}</span>
          </button>
        </div>
      </div>

      <!-- Recent Loans -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-gray-700">Recent Loans</h3>
          <button @click="$inertia.visit(route('pwa.loans'))" class="text-xs text-emerald-600 font-medium">See all</button>
        </div>
        <div class="space-y-3">
          <div
            v-for="loan in recentLoans"
            :key="loan.id"
            class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-between"
          >
            <div>
              <p class="text-sm font-semibold text-gray-900">{{ loan.loan_number }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ loan.type }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold text-gray-900">K {{ loan.principal }}</p>
              <span
                class="text-xs px-2 py-0.5 rounded-full font-medium"
                :class="loanStatusClass(loan.status)"
              >{{ loan.status_label }}</span>
            </div>
          </div>
          <div v-if="!recentLoans.length && !loading" class="bg-white rounded-xl border border-gray-100 p-6 text-center text-gray-400 text-sm">
            No loans yet.
          </div>
        </div>
      </div>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

const auth    = usePwaAuthStore()
const summary = ref({})
const recentLoans = ref([])
const loading = ref(false)

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'morning'
  if (h < 17) return 'afternoon'
  return 'evening'
})

const quickActions = [
  {
    label: 'My Loans',
    route: 'pwa.loans',
    bg: 'bg-blue-50',
    color: 'text-blue-600',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
  },
  {
    label: 'Payments',
    route: 'pwa.payments',
    bg: 'bg-purple-50',
    color: 'text-purple-600',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>',
  },
  {
    label: 'KYC Status',
    route: 'pwa.kyc.status',
    bg: 'bg-emerald-50',
    color: 'text-emerald-600',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
  },
  {
    label: 'Marketplace',
    route: 'pwa.marketplace.listings',
    bg: 'bg-orange-50',
    color: 'text-orange-600',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>',
  },
  {
    label: 'Apply Loan',
    route: 'pwa.loans.apply',
    bg: 'bg-emerald-50',
    color: 'text-emerald-700',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>',
  },
]

function loanStatusClass(status) {
  const map = {
    active:    'bg-emerald-100 text-emerald-700',
    disbursed: 'bg-blue-100 text-blue-700',
    completed: 'bg-gray-100 text-gray-600',
    overdue:   'bg-red-100 text-red-700',
    defaulted: 'bg-red-100 text-red-700',
    draft:     'bg-gray-100 text-gray-500',
  }
  return map[status] ?? 'bg-gray-100 text-gray-600'
}

onMounted(async () => {
  loading.value = true
  try {
    const [meRes, loansRes] = await Promise.all([
      axios.get('/api/v1/me'),
      axios.get('/api/v1/me/loans', { params: { per_page: 5 } }),
    ])
    const borrower = meRes.data.data
    summary.value = {
      outstanding_balance: borrower.outstanding_balance,
      active_loans: borrower.active_loans_count,
    }
    // Update store borrower data
    auth.setAuth(auth.token, borrower)
    recentLoans.value = loansRes.data.data ?? []
  } catch {
    // Token expired — redirect to login
    auth.clearAuth()
    router.visit(route('pwa.auth.login'))
  } finally {
    loading.value = false
  }
})
</script>
