<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <div class="flex items-center gap-2 text-sm text-neutral-400 mb-1">
            <Link :href="route('reports.index')" class="hover:text-neutral-600">Reports</Link>
            <span>/</span>
            <span class="text-neutral-700 font-medium">P&amp;L Summary</span>
          </div>
          <h1 class="text-2xl font-bold text-neutral-900">Profit &amp; Loss Summary</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ year }}{{ month ? ' — ' + monthLabel : '' }}</p>
        </div>
        <form @submit.prevent="applyFilters" class="flex items-center gap-2">
          <select v-model="form.year" class="input text-sm">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
          <select v-model="form.month" class="input text-sm">
            <option value="">Full Year</option>
            <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
          <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>
      </div>
    </template>

    <div class="space-y-6">

      <!-- Totals KPI -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Total Revenue</p>
          <p class="text-xl font-bold text-primary-700">K {{ fmt(totals.total_revenue) }}</p>
        </div>
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Total Expenses</p>
          <p class="text-xl font-bold text-red-600">K {{ fmt(totals.total_expenses) }}</p>
        </div>
        <div class="lendr-card p-5 text-center border-2" :class="totals.net_profit >= 0 ? 'border-emerald-200' : 'border-red-200'">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Net Profit</p>
          <p class="text-xl font-bold" :class="totals.net_profit >= 0 ? 'text-emerald-700' : 'text-red-600'">
            K {{ fmt(Math.abs(totals.net_profit)) }}
            <span class="text-base">{{ totals.net_profit >= 0 ? '▲' : '▼' }}</span>
          </p>
        </div>
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Total Disbursed</p>
          <p class="text-xl font-bold text-neutral-700">K {{ fmt(totals.disbursed) }}</p>
        </div>
      </div>

      <!-- Revenue breakdown cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="lendr-card p-4">
          <p class="text-xs text-neutral-500 mb-1">Interest Income</p>
          <p class="text-lg font-bold text-neutral-900">K {{ fmt(totals.interest_income) }}</p>
          <p class="text-xs text-neutral-400">{{ pct(totals.interest_income, totals.total_revenue) }}% of revenue</p>
        </div>
        <div class="lendr-card p-4">
          <p class="text-xs text-neutral-500 mb-1">Penalty Income</p>
          <p class="text-lg font-bold text-neutral-900">K {{ fmt(totals.penalty_income) }}</p>
          <p class="text-xs text-neutral-400">{{ pct(totals.penalty_income, totals.total_revenue) }}% of revenue</p>
        </div>
        <div class="lendr-card p-4">
          <p class="text-xs text-neutral-500 mb-1">Processing Fees</p>
          <p class="text-lg font-bold text-neutral-900">K {{ fmt(totals.processing_fees) }}</p>
          <p class="text-xs text-neutral-400">{{ pct(totals.processing_fees, totals.total_revenue) }}% of revenue</p>
        </div>
        <div class="lendr-card p-4">
          <p class="text-xs text-neutral-500 mb-1">Insurance Fees</p>
          <p class="text-lg font-bold text-neutral-900">K {{ fmt(totals.insurance_fees) }}</p>
          <p class="text-xs text-neutral-400">{{ pct(totals.insurance_fees, totals.total_revenue) }}% of revenue</p>
        </div>
      </div>

      <!-- Monthly table -->
      <div class="lendr-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Period</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Interest</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Penalty</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Fees</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Revenue</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Expenses</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Net Profit</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Disbursed</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="m in months" :key="m.month">
                <td class="px-5 py-3 font-medium text-neutral-700">{{ m.label }}</td>
                <td class="px-5 py-3 text-right text-neutral-600">K {{ fmt(m.interest_income) }}</td>
                <td class="px-5 py-3 text-right text-neutral-600">K {{ fmt(m.penalty_income) }}</td>
                <td class="px-5 py-3 text-right text-neutral-600">K {{ fmt(m.processing_fees + m.insurance_fees) }}</td>
                <td class="px-5 py-3 text-right font-semibold text-primary-700">K {{ fmt(m.total_revenue) }}</td>
                <td class="px-5 py-3 text-right text-red-600">K {{ fmt(m.total_expenses) }}</td>
                <td class="px-5 py-3 text-right font-bold" :class="m.net_profit >= 0 ? 'text-emerald-700' : 'text-red-700'">
                  {{ m.net_profit >= 0 ? '' : '-' }}K {{ fmt(Math.abs(m.net_profit)) }}
                </td>
                <td class="px-5 py-3 text-right text-neutral-500">K {{ fmt(m.disbursed) }}</td>
              </tr>
              <!-- Totals row -->
              <tr class="bg-neutral-50 font-bold border-t-2 border-neutral-200">
                <td class="px-5 py-3 text-neutral-700">Totals</td>
                <td class="px-5 py-3 text-right text-neutral-700">K {{ fmt(totals.interest_income) }}</td>
                <td class="px-5 py-3 text-right text-neutral-700">K {{ fmt(totals.penalty_income) }}</td>
                <td class="px-5 py-3 text-right text-neutral-700">K {{ fmt(totals.processing_fees + totals.insurance_fees) }}</td>
                <td class="px-5 py-3 text-right text-primary-700">K {{ fmt(totals.total_revenue) }}</td>
                <td class="px-5 py-3 text-right text-red-600">K {{ fmt(totals.total_expenses) }}</td>
                <td class="px-5 py-3 text-right" :class="totals.net_profit >= 0 ? 'text-emerald-700' : 'text-red-700'">
                  {{ totals.net_profit >= 0 ? '' : '-' }}K {{ fmt(Math.abs(totals.net_profit)) }}
                </td>
                <td class="px-5 py-3 text-right text-neutral-700">K {{ fmt(totals.disbursed) }}</td>
              </tr>
              <tr v-if="!months.length">
                <td colspan="8" class="px-5 py-8 text-center text-neutral-400">No data for the selected period.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  year: Number,
  month: [Number, String, null],
  months: Array,
  totals: Object,
  filters: Object,
})

const form = ref({
  year:  props.filters?.year  ?? new Date().getFullYear(),
  month: props.filters?.month ?? '',
})

const yearOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)
const monthOptions = [
  'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'
].map((label, i) => ({ value: i + 1, label }))

const monthLabel = computed(() => props.month ? monthOptions[props.month - 1]?.label : '')

function applyFilters() {
  router.get(route('reports.pnl'), form.value, { preserveState: true })
}

function fmt(n) {
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function pct(part, total) {
  return total > 0 ? (part / total * 100).toFixed(1) : '0.0'
}
</script>
