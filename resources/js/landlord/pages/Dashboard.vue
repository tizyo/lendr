<template>
  <LandlordLayout title="Dashboard">
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else class="space-y-8">

      <!-- Revenue KPIs -->
      <div>
        <h2 class="text-xs font-semibold text-neutral-500 uppercase tracking-widest mb-3">Revenue</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
          <KpiCard label="MRR" :value="fmt(stats.revenue?.mrr)" sub="Monthly Recurring" color="emerald" />
          <KpiCard label="ARR" :value="fmt(stats.revenue?.arr)" sub="Annual Run Rate" color="emerald" />
          <KpiCard label="Total Revenue" :value="fmt(stats.revenue?.total_revenue)" sub="All-time paid" color="blue" />
          <KpiCard label="Active Subscriptions" :value="activeCount" sub="Paying tenants" color="blue" />
        </div>
      </div>

      <!-- Tenant KPIs -->
      <div>
        <h2 class="text-xs font-semibold text-neutral-500 uppercase tracking-widest mb-3">Tenants</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
          <KpiCard label="Total Tenants" :value="stats.tenants?.total ?? 0" sub="All workspaces" color="neutral" />
          <KpiCard label="New This Month" :value="stats.tenants?.new_this_month ?? 0" sub="Signed up this month" color="violet" />
          <KpiCard
            label="Trial Conversion"
            :value="(stats.tenants?.trial_conversion_rate ?? 0) + '%'"
            sub="90-day cohort"
            :color="conversionColor"
          />
          <KpiCard
            label="Monthly Churn"
            :value="(stats.tenants?.monthly_churn_rate ?? 0) + '%'"
            sub="Expired / cancelled"
            :color="churnColor"
          />
        </div>
      </div>

      <!-- Status + Plan distribution -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- By status -->
        <div class="bg-white rounded-xl border border-neutral-200 p-6">
          <h3 class="text-sm font-semibold text-neutral-700 mb-4">Tenants by Status</h3>
          <div class="space-y-3">
            <BarRow
              v-for="(count, status) in (stats.tenants?.by_status ?? {})"
              :key="status"
              :label="status"
              :count="count"
              :total="stats.tenants?.total ?? 1"
              :color="statusColor(status)"
            />
          </div>
        </div>

        <!-- By plan -->
        <div class="bg-white rounded-xl border border-neutral-200 p-6">
          <h3 class="text-sm font-semibold text-neutral-700 mb-4">Tenants by Plan</h3>
          <div class="space-y-3">
            <BarRow
              v-for="(count, plan) in (stats.tenants?.by_plan ?? {})"
              :key="plan"
              :label="plan"
              :count="count"
              :total="stats.tenants?.total ?? 1"
              color="bg-primary-500"
            />
          </div>
          <!-- Revenue split by plan -->
          <div v-if="Object.keys(stats.revenue?.by_plan ?? {}).length" class="mt-4 pt-4 border-t border-neutral-100">
            <p class="text-xs text-neutral-500 mb-2">MRR by plan</p>
            <div class="space-y-1">
              <div v-for="(amount, plan) in (stats.revenue?.by_plan ?? {})" :key="plan" class="flex justify-between text-sm">
                <span class="capitalize text-neutral-600">{{ plan }}</span>
                <span class="font-semibold text-neutral-800">{{ fmt(amount) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Revenue & Signup Trend -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Revenue trend -->
        <div class="bg-white rounded-xl border border-neutral-200 p-6">
          <h3 class="text-sm font-semibold text-neutral-700 mb-4">Revenue Trend (6 months)</h3>
          <div v-if="Object.keys(stats.revenue?.trend ?? {}).length">
            <MiniBarChart :data="stats.revenue?.trend ?? {}" color="emerald" :fmt="fmt" />
          </div>
          <p v-else class="text-sm text-neutral-400 italic">No paid invoices yet.</p>
        </div>

        <!-- Signup trend -->
        <div class="bg-white rounded-xl border border-neutral-200 p-6">
          <h3 class="text-sm font-semibold text-neutral-700 mb-4">New Signups (6 months)</h3>
          <div v-if="Object.keys(stats.growth?.signup_trend ?? {}).length">
            <MiniBarChart :data="stats.growth?.signup_trend ?? {}" color="violet" />
          </div>
          <p v-else class="text-sm text-neutral-400 italic">No signups recorded yet.</p>
        </div>
      </div>

      <!-- Recent invoices -->
      <div class="bg-white rounded-xl border border-neutral-200 p-6">
        <h3 class="text-sm font-semibold text-neutral-700 mb-4">Recent Payments</h3>
        <div v-if="stats.recent_invoices?.length">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-neutral-500 border-b border-neutral-100">
                <th class="pb-2 font-medium">Tenant</th>
                <th class="pb-2 font-medium">Plan</th>
                <th class="pb-2 font-medium">Cycle</th>
                <th class="pb-2 font-medium">Gateway</th>
                <th class="pb-2 font-medium text-right">Amount</th>
                <th class="pb-2 font-medium text-right">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="inv in stats.recent_invoices" :key="inv.id">
                <td class="py-2 font-medium text-neutral-800">{{ inv.tenant_name }}</td>
                <td class="py-2 capitalize text-neutral-600">{{ inv.plan }}</td>
                <td class="py-2 capitalize text-neutral-600">{{ inv.billing_cycle }}</td>
                <td class="py-2 capitalize text-neutral-500">{{ inv.gateway }}</td>
                <td class="py-2 text-right font-semibold text-emerald-700">{{ inv.currency }} {{ inv.amount.toLocaleString() }}</td>
                <td class="py-2 text-right text-neutral-500">{{ inv.paid_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="text-sm text-neutral-400 italic">No payments recorded yet.</p>
      </div>

    </div>
  </LandlordLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

const auth    = useLandlordAuth()
const stats   = ref({})
const loading = ref(true)

// ─── Derived ──────────────────────────────────────────────────────────────────

const activeCount = computed(() => stats.value.tenants?.by_status?.active ?? 0)

const conversionColor = computed(() => {
  const r = stats.value.tenants?.trial_conversion_rate ?? 0
  if (r >= 50) return 'emerald'
  if (r >= 25) return 'amber'
  return 'red'
})

const churnColor = computed(() => {
  const r = stats.value.tenants?.monthly_churn_rate ?? 0
  if (r === 0) return 'emerald'
  if (r <= 5)  return 'amber'
  return 'red'
})

// ─── Helpers ──────────────────────────────────────────────────────────────────

function fmt(value) {
  if (value == null) return '—'
  const n = parseFloat(value)
  if (isNaN(n)) return value
  return 'ZMW ' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusColor(status) {
  return {
    active:    'bg-emerald-500',
    trial:     'bg-amber-400',
    expired:   'bg-orange-400',
    suspended: 'bg-red-500',
    cancelled: 'bg-neutral-400',
  }[status] ?? 'bg-neutral-300'
}

// ─── Data fetch ───────────────────────────────────────────────────────────────

onMounted(async () => {
  if (!auth.isAuthenticated) {
    router.visit(route('landlord.login'))
    return
  }
  try {
    const { data } = await axios.get('/api/v1/landlord/stats')
    stats.value = data.data ?? {}
  } catch {
    auth.clearAuth()
    router.visit(route('landlord.login'))
  } finally {
    loading.value = false
  }
})
</script>

<!-- ─── Sub-components (defined inline to avoid extra files) ─────────────────── -->

<script>
// KpiCard — single metric tile
const KpiCard = {
  props: ['label', 'value', 'sub', 'color'],
  template: `
    <div class="bg-white rounded-xl border border-neutral-200 p-5">
      <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">{{ label }}</p>
      <p class="text-2xl font-bold" :class="textColor">{{ value }}</p>
      <p class="text-xs text-neutral-400 mt-1">{{ sub }}</p>
    </div>
  `,
  computed: {
    textColor() {
      return {
        emerald: 'text-emerald-700',
        blue:    'text-blue-700',
        violet:  'text-violet-700',
        amber:   'text-amber-600',
        red:     'text-red-600',
        neutral: 'text-neutral-900',
      }[this.color] ?? 'text-neutral-900'
    },
  },
}

// BarRow — horizontal bar for plan/status breakdown
const BarRow = {
  props: ['label', 'count', 'total', 'color'],
  template: `
    <div class="flex items-center gap-3">
      <span class="w-24 text-sm text-neutral-600 capitalize truncate">{{ label }}</span>
      <div class="flex-1 bg-neutral-100 rounded-full h-2">
        <div class="h-2 rounded-full transition-all" :class="color" :style="{ width: pct + '%' }"></div>
      </div>
      <span class="w-8 text-right text-sm font-semibold text-neutral-800">{{ count }}</span>
    </div>
  `,
  computed: {
    pct() { return this.total ? Math.round(this.count / this.total * 100) : 0 },
  },
}

// MiniBarChart — simple bar chart from { "YYYY-MM": value } object
const MiniBarChart = {
  props: ['data', 'color', 'fmt'],
  template: `
    <div>
      <div class="flex items-end gap-1 h-24">
        <div
          v-for="(value, month) in data"
          :key="month"
          class="flex-1 rounded-t transition-all"
          :class="barClass"
          :style="{ height: barHeight(value) + '%' }"
          :title="label(month) + ': ' + (fmtFn ? fmtFn(value) : value)"
        ></div>
      </div>
      <div class="flex gap-1 mt-1">
        <span
          v-for="(value, month) in data"
          :key="month"
          class="flex-1 text-center text-xs text-neutral-400 truncate"
        >{{ shortMonth(month) }}</span>
      </div>
    </div>
  `,
  computed: {
    barClass() {
      return {
        emerald: 'bg-emerald-400',
        violet:  'bg-violet-400',
        blue:    'bg-blue-400',
        amber:   'bg-amber-400',
      }[this.color] ?? 'bg-primary-400'
    },
    maxVal() {
      const vals = Object.values(this.data)
      return vals.length ? Math.max(...vals) : 1
    },
    fmtFn() { return this.fmt ?? null },
  },
  methods: {
    barHeight(value) {
      return this.maxVal ? Math.max(4, Math.round(value / this.maxVal * 100)) : 4
    },
    shortMonth(ym) {
      const [y, m] = ym.split('-')
      return new Date(+y, +m - 1).toLocaleString('default', { month: 'short' })
    },
    label(ym) {
      const [y, m] = ym.split('-')
      return new Date(+y, +m - 1).toLocaleString('default', { month: 'long', year: 'numeric' })
    },
  },
}

export default { components: { KpiCard, BarRow, MiniBarChart } }
</script>
