<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Dashboard</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ today }}</p>
        </div>
        <div class="flex gap-2">
          <a :href="route('reports.index')" class="btn-secondary">View Reports</a>
        </div>
      </div>
    </template>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <KpiCard
        label="Active Loans"
        :value="kpis.active_loans"
        icon="credit-card"
        color="blue"
        :trend="null"
      />
      <KpiCard
        label="Total Borrowers"
        :value="kpis.total_borrowers"
        icon="users"
        color="green"
      />
      <KpiCard
        label="Outstanding (ZMW)"
        :value="'K ' + kpis.total_outstanding"
        icon="banknotes"
        color="purple"
      />
      <KpiCard
        label="Overdue Loans"
        :value="kpis.overdue_loans"
        icon="exclamation-triangle"
        color="red"
        :alert="kpis.overdue_loans > 0"
      />
    </div>

    <!-- Secondary KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="lendr-card p-4">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wide">Disbursed This Month</p>
        <p class="text-2xl font-bold text-neutral-900 mt-1">K {{ kpis.disbursed_month }}</p>
      </div>
      <div class="lendr-card p-4">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wide">Collected This Month</p>
        <p class="text-2xl font-bold text-neutral-900 mt-1">K {{ kpis.collected_month }}</p>
      </div>
      <div class="lendr-card p-4">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wide">PAR 30</p>
        <p class="text-2xl font-bold mt-1" :class="kpis.par_30 > 5 ? 'text-red-600' : 'text-green-600'">
          {{ kpis.par_30 }}%
        </p>
      </div>
    </div>

    <!-- Charts + Tables row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Disbursements chart -->
      <div class="lg:col-span-2 lendr-card p-5">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Monthly Disbursements (12 months)</h2>
        <DisbursementChart :data="monthlyDisbursements" />
      </div>

      <!-- Loan status breakdown -->
      <div class="lendr-card p-5">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Portfolio Status</h2>
        <div class="space-y-3">
          <StatusBar label="Active" :count="kpis.active_loans" :total="kpis.active_loans + kpis.overdue_loans" color="bg-blue-500" />
          <StatusBar label="Overdue" :count="kpis.overdue_loans" :total="kpis.active_loans + kpis.overdue_loans" color="bg-red-500" />
        </div>
        <div class="mt-6 pt-4 border-t border-neutral-100 text-center">
          <p class="text-xs text-neutral-500">PAR 30 Rate</p>
          <p class="text-3xl font-bold mt-1" :class="kpis.par_30 > 5 ? 'text-red-600' : 'text-green-600'">
            {{ kpis.par_30 }}%
          </p>
        </div>
      </div>
    </div>

    <!-- Recent activity tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
      <!-- Recent Loans -->
      <div class="lendr-card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-100 flex justify-between items-center">
          <h2 class="text-sm font-semibold text-neutral-800">Recent Loans</h2>
          <Link :href="route('loans.index')" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
            View all
          </Link>
        </div>
        <div class="divide-y divide-neutral-50">
          <div v-for="loan in recentLoans" :key="loan.id" class="px-5 py-3 flex items-center justify-between hover:bg-neutral-50">
            <div>
              <p class="text-sm font-medium text-neutral-800">{{ loan.borrower }}</p>
              <p class="text-xs text-neutral-500">{{ loan.loan_number }} &middot; {{ loan.type }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-neutral-800">K {{ loan.amount }}</p>
              <span class="lendr-badge-{{ loan.status_color }} text-xs">{{ loan.status_label }}</span>
            </div>
          </div>
          <div v-if="!recentLoans.length" class="px-5 py-8 text-center text-neutral-400 text-sm">
            No loans yet
          </div>
        </div>
      </div>

      <!-- Recent Payments -->
      <div class="lendr-card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-100 flex justify-between items-center">
          <h2 class="text-sm font-semibold text-neutral-800">Recent Payments</h2>
          <Link :href="route('payments.index')" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
            View all
          </Link>
        </div>
        <div class="divide-y divide-neutral-50">
          <div v-for="payment in recentPayments" :key="payment.id" class="px-5 py-3 flex items-center justify-between hover:bg-neutral-50">
            <div>
              <p class="text-sm font-medium text-neutral-800">{{ payment.borrower }}</p>
              <p class="text-xs text-neutral-500">{{ payment.receipt_number }} &middot; {{ payment.method }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-green-700">K {{ payment.amount }}</p>
              <p class="text-xs text-neutral-400">{{ payment.date }}</p>
            </div>
          </div>
          <div v-if="!recentPayments.length" class="px-5 py-8 text-center text-neutral-400 text-sm">
            No payments yet
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import KpiCard from '@/admin/components/ui/KpiCard.vue'
import DisbursementChart from '@/admin/components/charts/DisbursementChart.vue'
import StatusBar from '@/admin/components/ui/StatusBar.vue'

const props = defineProps({
  kpis: Object,
  recentLoans: Array,
  recentPayments: Array,
  monthlyDisbursements: Array,
})

const today = new Intl.DateTimeFormat('en-ZM', {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}).format(new Date())
</script>
