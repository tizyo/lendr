<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <div class="flex items-center gap-2 text-sm text-neutral-400 mb-1">
            <Link :href="route('reports.index')" class="hover:text-neutral-600">Reports</Link>
            <span>/</span>
            <span class="text-neutral-700 font-medium">Collections Efficiency</span>
          </div>
          <h1 class="text-2xl font-bold text-neutral-900">Collections Efficiency</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ year }}</p>
        </div>
        <form @submit.prevent="applyFilters" class="flex items-center gap-2">
          <select v-model="form.year" class="input text-sm">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
          <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>
      </div>
    </template>

    <div class="space-y-6">

      <!-- KPI cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Total Due</p>
          <p class="text-xl font-bold text-neutral-900">K {{ fmt(totalDue) }}</p>
        </div>
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Total Collected</p>
          <p class="text-xl font-bold text-emerald-700">K {{ fmt(totalCollected) }}</p>
        </div>
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Efficiency Rate</p>
          <p class="text-xl font-bold" :class="overallRate >= 90 ? 'text-emerald-700' : overallRate >= 70 ? 'text-amber-600' : 'text-red-600'">
            {{ overallRate }}%
          </p>
        </div>
        <div class="lendr-card p-5 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Uncollected</p>
          <p class="text-xl font-bold text-red-600">K {{ fmt(totalDue - totalCollected) }}</p>
        </div>
      </div>

      <!-- Monthly chart (bar-like) -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">Monthly Collections vs Expected</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-neutral-100">
                <th class="text-left pb-3 text-xs font-semibold text-neutral-500 uppercase">Month</th>
                <th class="text-right pb-3 text-xs font-semibold text-neutral-500 uppercase">Instalments Due</th>
                <th class="text-right pb-3 text-xs font-semibold text-neutral-500 uppercase">Amount Due</th>
                <th class="text-right pb-3 text-xs font-semibold text-neutral-500 uppercase">Payments</th>
                <th class="text-right pb-3 text-xs font-semibold text-neutral-500 uppercase">Collected</th>
                <th class="pb-3 pl-4" style="width:160px;">Efficiency</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="m in months" :key="m.month">
                <td class="py-3 text-neutral-700 font-medium">{{ m.label }}</td>
                <td class="py-3 text-right text-neutral-500">{{ m.instalments_due }}</td>
                <td class="py-3 text-right text-neutral-700">K {{ fmt(m.due) }}</td>
                <td class="py-3 text-right text-neutral-500">{{ m.payments_count }}</td>
                <td class="py-3 text-right font-semibold text-emerald-700">K {{ fmt(m.collected) }}</td>
                <td class="py-3 pl-4">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-neutral-100 rounded-full h-2 overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="m.efficiency_rate >= 90 ? 'bg-emerald-500' : m.efficiency_rate >= 70 ? 'bg-amber-400' : 'bg-red-400'"
                        :style="{ width: Math.min(m.efficiency_rate, 100) + '%' }"
                      ></div>
                    </div>
                    <span class="text-xs font-bold w-10 text-right"
                      :class="m.efficiency_rate >= 90 ? 'text-emerald-700' : m.efficiency_rate >= 70 ? 'text-amber-600' : 'text-red-600'">
                      {{ m.efficiency_rate }}%
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Top collectors -->
      <div class="lendr-card overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-100">
          <h2 class="text-base font-semibold text-neutral-800">Top Collectors — {{ year }}</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">#</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Staff Member</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Payments</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Total Collected</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="(c, i) in topCollectors" :key="c.name">
                <td class="px-5 py-3 font-bold text-neutral-400">{{ i + 1 }}</td>
                <td class="px-5 py-3 font-semibold text-neutral-800">{{ c.name }}</td>
                <td class="px-5 py-3 text-right text-neutral-600">{{ c.payment_count }}</td>
                <td class="px-5 py-3 text-right font-bold text-primary-700">K {{ fmt(c.total_collected) }}</td>
              </tr>
              <tr v-if="!topCollectors.length">
                <td colspan="4" class="px-5 py-8 text-center text-neutral-400">No collection data for this year.</td>
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
  months: Array,
  topCollectors: Array,
  filters: Object,
})

const form = ref({ year: props.filters?.year ?? new Date().getFullYear() })
const yearOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

function applyFilters() {
  router.get(route('reports.collections'), form.value, { preserveState: true })
}

const totalDue       = computed(() => props.months.reduce((s, m) => s + m.due, 0))
const totalCollected = computed(() => props.months.reduce((s, m) => s + m.collected, 0))
const overallRate    = computed(() =>
  totalDue.value > 0 ? (totalCollected.value / totalDue.value * 100).toFixed(1) : '0.0'
)

function fmt(n) {
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
