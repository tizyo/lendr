<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2 text-sm text-neutral-400 mb-1">
            <Link :href="route('reports.index')" class="hover:text-neutral-600">Reports</Link>
            <span>/</span>
            <span class="text-neutral-700 font-medium">Portfolio At Risk</span>
          </div>
          <h1 class="text-2xl font-bold text-neutral-900">PAR Aging Report</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Overdue loan portfolio analysis as of {{ asOf }}</p>
        </div>
      </div>
    </template>

    <div class="space-y-6">

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div v-for="b in bucketSummary" :key="b.key" class="lendr-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide mb-1" :class="b.color">{{ b.label }}</p>
          <p class="text-2xl font-bold text-neutral-900">{{ b.count }}</p>
          <p class="text-sm text-neutral-500 mt-0.5">K {{ fmt(b.outstanding) }}</p>
          <div class="mt-2 flex items-center gap-1">
            <div class="h-1.5 rounded-full flex-1 bg-neutral-100 overflow-hidden">
              <div class="h-full rounded-full" :class="b.barColor" :style="{ width: b.parRate + '%' }"></div>
            </div>
            <span class="text-xs font-semibold" :class="b.color">{{ b.parRate }}%</span>
          </div>
          <p class="text-xs text-neutral-400 mt-0.5">of portfolio</p>
        </div>
      </div>

      <!-- Total portfolio -->
      <div class="lendr-card p-5 flex items-center justify-between">
        <div>
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold">Total Active Portfolio</p>
          <p class="text-2xl font-bold text-neutral-900 mt-0.5">K {{ fmt(totalPortfolio) }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold">Total PAR Amount</p>
          <p class="text-2xl font-bold text-red-600 mt-0.5">K {{ fmt(totalPar) }}</p>
          <p class="text-xs text-neutral-400">{{ totalParRate }}% of portfolio</p>
        </div>
      </div>

      <!-- Buckets detail -->
      <div v-for="b in bucketSummary" :key="'detail-' + b.key" class="lendr-card overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-sm font-semibold px-2.5 py-1 rounded-full" :class="b.badgeClass">{{ b.label }}</span>
            <span class="text-sm text-neutral-500">{{ b.count }} loans · K {{ fmt(b.outstanding) }}</span>
          </div>
          <span class="text-sm font-bold" :class="b.color">PAR: {{ b.parRate }}%</span>
        </div>

        <div v-if="!buckets[b.key]?.length" class="px-6 py-4 text-sm text-neutral-400">
          No loans in this bucket.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Loan No.</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Borrower</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Principal</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Outstanding</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Days Overdue</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="row in buckets[b.key]" :key="row.loan_number">
                <td class="px-5 py-3 font-mono text-xs text-neutral-700">{{ row.loan_number }}</td>
                <td class="px-5 py-3">
                  <p class="font-medium text-neutral-800">{{ row.borrower_name }}</p>
                  <p class="text-xs text-neutral-400">{{ row.borrower_number }}</p>
                </td>
                <td class="px-5 py-3 text-right text-neutral-600">K {{ fmt(row.principal) }}</td>
                <td class="px-5 py-3 text-right font-semibold text-red-600">K {{ fmt(row.outstanding) }}</td>
                <td class="px-5 py-3 text-right">
                  <span class="inline-block px-2 py-0.5 text-xs font-bold rounded-full" :class="b.badgeClass">
                    {{ row.days_overdue }}d
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  asOf: String,
  buckets: Object,
  summary: Object,
  totalPortfolio: Number,
})

const bucketSummary = computed(() => [
  {
    key: '1_30',
    label: '1–30 Days',
    color: 'text-amber-600',
    barColor: 'bg-amber-400',
    badgeClass: 'bg-amber-100 text-amber-700',
    count: props.summary['1_30']?.count ?? 0,
    outstanding: props.summary['1_30']?.outstanding ?? 0,
    parRate: props.summary['1_30']?.par_rate ?? 0,
  },
  {
    key: '31_60',
    label: '31–60 Days',
    color: 'text-orange-600',
    barColor: 'bg-orange-400',
    badgeClass: 'bg-orange-100 text-orange-700',
    count: props.summary['31_60']?.count ?? 0,
    outstanding: props.summary['31_60']?.outstanding ?? 0,
    parRate: props.summary['31_60']?.par_rate ?? 0,
  },
  {
    key: '61_90',
    label: '61–90 Days',
    color: 'text-red-600',
    barColor: 'bg-red-400',
    badgeClass: 'bg-red-100 text-red-700',
    count: props.summary['61_90']?.count ?? 0,
    outstanding: props.summary['61_90']?.outstanding ?? 0,
    parRate: props.summary['61_90']?.par_rate ?? 0,
  },
  {
    key: '91_plus',
    label: '90+ Days',
    color: 'text-red-800',
    barColor: 'bg-red-700',
    badgeClass: 'bg-red-200 text-red-900',
    count: props.summary['91_plus']?.count ?? 0,
    outstanding: props.summary['91_plus']?.outstanding ?? 0,
    parRate: props.summary['91_plus']?.par_rate ?? 0,
  },
])

const totalPar = computed(() =>
  bucketSummary.value.reduce((sum, b) => sum + b.outstanding, 0)
)

const totalParRate = computed(() =>
  props.totalPortfolio > 0 ? (totalPar.value / props.totalPortfolio * 100).toFixed(1) : '0.0'
)

function fmt(n) {
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
