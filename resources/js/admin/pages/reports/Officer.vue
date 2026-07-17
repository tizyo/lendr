<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <div class="flex items-center gap-2 text-sm text-neutral-400 mb-1">
            <Link :href="route('reports.index')" class="hover:text-neutral-600">Reports</Link>
            <span>/</span>
            <span class="text-neutral-700 font-medium">Loan Officer Performance</span>
          </div>
          <h1 class="text-2xl font-bold text-neutral-900">Loan Officer Performance</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ dateFrom }} — {{ dateTo }}</p>
        </div>
        <!-- Filter form -->
        <form @submit.prevent="applyFilters" class="flex items-center gap-2">
          <input type="date" v-model="form.date_from" class="input text-sm" />
          <span class="text-neutral-400 text-sm">to</span>
          <input type="date" v-model="form.date_to" class="input text-sm" />
          <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>
      </div>
    </template>

    <div class="space-y-6">

      <!-- Summary row -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Officers</p>
          <p class="text-3xl font-bold text-neutral-900">{{ officers.length }}</p>
        </div>
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Total Loans</p>
          <p class="text-3xl font-bold text-neutral-900">{{ totalLoans }}</p>
        </div>
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Total Disbursed</p>
          <p class="text-2xl font-bold text-primary-700">K {{ fmt(totalDisbursed) }}</p>
        </div>
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Total Collected</p>
          <p class="text-2xl font-bold text-emerald-700">K {{ fmt(totalCollected) }}</p>
        </div>
      </div>

      <!-- Officer table -->
      <div class="lendr-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Officer</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Applications</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Disbursed</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Completed</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Defaulted</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Amount Out</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Collected</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Default %</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Collection %</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="o in officers" :key="o.officer_name" class="hover:bg-neutral-50/50">
                <td class="px-5 py-3 font-semibold text-neutral-800">{{ o.officer_name }}</td>
                <td class="px-5 py-3 text-right text-neutral-700">{{ o.total_loans }}</td>
                <td class="px-5 py-3 text-right text-neutral-700">{{ o.disbursed_loans }}</td>
                <td class="px-5 py-3 text-right text-emerald-600 font-medium">{{ o.completed_loans }}</td>
                <td class="px-5 py-3 text-right text-red-600 font-medium">{{ o.defaulted_loans }}</td>
                <td class="px-5 py-3 text-right text-neutral-700">K {{ fmt(o.total_disbursed) }}</td>
                <td class="px-5 py-3 text-right font-semibold text-primary-700">K {{ fmt(o.total_collected) }}</td>
                <td class="px-5 py-3 text-right">
                  <span class="inline-block px-2 py-0.5 text-xs font-bold rounded-full"
                    :class="o.default_rate > 10 ? 'bg-red-100 text-red-700' : o.default_rate > 5 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'"
                  >{{ o.default_rate }}%</span>
                </td>
                <td class="px-5 py-3 text-right">
                  <span class="inline-block px-2 py-0.5 text-xs font-bold rounded-full"
                    :class="o.collection_rate >= 90 ? 'bg-emerald-100 text-emerald-700' : o.collection_rate >= 70 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'"
                  >{{ o.collection_rate }}%</span>
                </td>
              </tr>
              <tr v-if="!officers.length">
                <td colspan="9" class="px-5 py-8 text-center text-neutral-400">No data for the selected period.</td>
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
  officers: Array,
  dateFrom: String,
  dateTo: String,
  filters: Object,
})

const form = ref({
  date_from: props.filters?.date_from ?? '',
  date_to:   props.filters?.date_to   ?? '',
})

function applyFilters() {
  router.get(route('reports.officer'), form.value, { preserveState: true })
}

const totalLoans     = computed(() => props.officers.reduce((s, o) => s + o.total_loans, 0))
const totalDisbursed = computed(() => props.officers.reduce((s, o) => s + o.total_disbursed, 0))
const totalCollected = computed(() => props.officers.reduce((s, o) => s + o.total_collected, 0))

function fmt(n) {
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
