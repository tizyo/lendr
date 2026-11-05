<template>
  <LandlordLayout title="Dashboard">
    <!-- Skeleton while loading -->
    <div v-if="loading" class="space-y-6 animate-pulse">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 8" :key="i" class="h-32 bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.06)]"></div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div v-for="i in 2" :key="i" class="h-72 bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.06)]"></div>
      </div>
    </div>

    <div v-else-if="loadError" class="p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ loadError }}</div>

    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="text-sm text-neutral-500">{{ greeting }}</p>
          <h2 class="text-xl font-semibold text-neutral-900">Platform overview</h2>
        </div>
        <div class="flex items-center gap-3 text-xs text-neutral-500">
          <span v-if="stats.usage?.as_of" :title="formatDateTime(stats.usage.as_of)">Workspace figures as of {{ timeAgo(stats.usage.as_of) }}</span>
          <button @click="load(true)" :disabled="refreshing" class="inline-flex items-center gap-1.5 border border-neutral-300 text-neutral-700 hover:bg-neutral-50 font-medium px-3 py-1.5 rounded-lg disabled:opacity-50">
            <ArrowPathIcon class="w-4 h-4" :class="refreshing ? 'animate-spin' : ''" stroke-width="1.5" />
            {{ refreshing ? 'Refreshing…' : 'Refresh' }}
          </button>
        </div>
      </div>

      <!-- Headline numbers -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatTile label="Monthly recurring revenue" :value="money(stats.revenue?.mrr)" :sub="`ARR ${money(stats.revenue?.arr)}`" :icon="BanknotesIcon" hex="#1baf7a" />
        <StatTile label="Workspaces" :value="num(o.workspaces)" :sub="`${num(byStatus.active)} active · ${num(byStatus.trial)} on trial`" :icon="BuildingOffice2Icon" hex="#2a78d6" />
        <StatTile label="Portfolio under management" :value="primaryOutstanding" :sub="`${num(totals.active_loans)} live loans · ${num(totals.borrowers)} borrowers`" :icon="ChartBarIcon" hex="#6250d6" />
        <StatTile label="Active this week" :value="`${num(o.active_7d)} / ${num(o.workspaces)}`" sub="workspaces used in the last 7 days" :icon="BoltIcon" hex="#eb6834" />
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatTile label="New sign-ups this month" :value="num(o.new_this_month)" sub="vs last month" :delta="o.new_this_month - o.new_last_month" :icon="UserPlusIcon" hex="#2a78d6" />
        <StatTile label="Revenue this month" :value="money(o.revenue_this_month)" :sub="`last month ${money(o.revenue_last_month)}`" :icon="ArrowTrendingUpIcon" hex="#1baf7a" />
        <StatTile label="Trial conversion" :value="(stats.tenants?.trial_conversion_rate ?? 0) + '%'" sub="of sign-ups in the last 90 days" :icon="CheckBadgeIcon" hex="#6250d6" />
        <StatTile label="Email delivery (7 days)" :value="deliveryRate" :sub="`${num(email.total)} sent · ${num(email.failed)} failed`" :icon="EnvelopeIcon" :hex="email.failed ? '#d03b3b' : '#0ca30c'" />
      </div>

      <!-- Twelve-month trends: two single-series charts, never one dual-axis chart -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <section class="bg-white rounded-2xl p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)]">
          <div class="flex items-baseline justify-between mb-5">
            <h3 class="text-sm font-semibold text-neutral-900">New sign-ups</h3>
            <span class="text-xs text-neutral-500">{{ num(sum(stats.monthly, 'signups')) }} in 12 months</span>
          </div>
          <MonthlyBars :data="(stats.monthly ?? []).map(m => ({ month: m.month, value: m.signups }))" color="#2a78d6" :format="num" />
        </section>
        <section class="bg-white rounded-2xl p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)]">
          <div class="flex items-baseline justify-between mb-5">
            <h3 class="text-sm font-semibold text-neutral-900">Subscription revenue</h3>
            <span class="text-xs text-neutral-500">{{ money(sum(stats.monthly, 'revenue')) }} in 12 months</span>
          </div>
          <MonthlyBars :data="(stats.monthly ?? []).map(m => ({ month: m.month, value: m.revenue }))" color="#1baf7a" :format="money" />
        </section>
      </div>

      <!-- Shares -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section class="bg-white rounded-2xl p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)]">
          <h3 class="text-sm font-semibold text-neutral-900 mb-4">Workspaces by status</h3>
          <SegmentBar :segments="statusSegments" />
        </section>
        <section class="bg-white rounded-2xl p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)]">
          <h3 class="text-sm font-semibold text-neutral-900 mb-4">Workspaces by plan</h3>
          <SegmentBar :segments="planSegments" />
          <div v-if="Object.keys(stats.revenue?.by_plan ?? {}).length" class="mt-4 pt-4 border-t border-neutral-100 space-y-1">
            <p class="text-xs text-neutral-500 mb-1">Monthly revenue by plan</p>
            <div v-for="(amount, plan) in stats.revenue.by_plan" :key="plan" class="flex justify-between text-sm">
              <span class="capitalize text-neutral-600">{{ plan }}</span>
              <span class="font-semibold text-neutral-900 tabular-nums">{{ money(amount) }}</span>
            </div>
          </div>
        </section>
        <section class="bg-white rounded-2xl p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)]">
          <h3 class="text-sm font-semibold text-neutral-900 mb-4">Email to workspaces · 7 days</h3>
          <SegmentBar v-if="email.total" :segments="emailSegments" />
          <p v-else class="text-sm text-neutral-400">No emails sent in the last 7 days.</p>
          <div class="mt-4 pt-4 border-t border-neutral-100 grid grid-cols-2 gap-3 text-sm">
            <div>
              <p class="text-xs text-neutral-500">Open support tickets</p>
              <Link :href="route('landlord.support')" class="font-semibold text-neutral-900 hover:underline tabular-nums">{{ num(o.support_open) }}</Link>
            </div>
            <div>
              <p class="text-xs text-neutral-500">Unpaid invoices</p>
              <p class="font-semibold text-neutral-900 tabular-nums">{{ num(o.pending_invoices?.count) }} <span class="text-xs font-normal text-neutral-500">· {{ money(o.pending_invoices?.amount) }}</span></p>
            </div>
          </div>
        </section>
      </div>

      <!-- Needs attention + recent sign-ups -->
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <section class="lg:col-span-3 bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.06)] overflow-hidden">
          <div class="px-6 py-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-neutral-900">Needs attention</h3>
            <span class="text-xs text-neutral-500">{{ attention.length }} item{{ attention.length === 1 ? '' : 's' }}</span>
          </div>
          <div v-if="attention.length === 0" class="px-6 pb-8 pt-2 flex items-center gap-3 text-sm text-neutral-500">
            <CheckCircleIcon class="w-5 h-5 text-[#0ca30c]" stroke-width="1.5" /> Nothing needs you right now.
          </div>
          <ul v-else class="divide-y divide-neutral-100 border-t border-neutral-100">
            <li v-for="(a, i) in attention" :key="i" class="px-6 py-3 flex items-start gap-3 hover:bg-neutral-50">
              <component :is="severityIcon(a.severity)" class="w-5 h-5 mt-0.5 shrink-0" :style="{ color: severityColor(a.severity) }" stroke-width="1.5" />
              <div class="min-w-0 flex-1">
                <p class="text-sm text-neutral-900">
                  <Link v-if="a.tenant_id && a.tenant_name" :href="route('landlord.tenant-detail', a.tenant_id)" class="font-medium hover:underline">{{ a.tenant_name }}</Link>
                  <span v-else class="font-medium">Platform</span>
                  <span class="ml-2 inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide" :style="{ background: severityColor(a.severity) + '1f', color: severityInk(a.severity) }">{{ severityLabel(a.severity) }}</span>
                </p>
                <p class="text-sm text-neutral-600">{{ a.message }}</p>
              </div>
              <span class="text-xs text-neutral-400 whitespace-nowrap" :title="formatDateTime(a.at)">{{ timeAgo(a.at) }}</span>
            </li>
          </ul>
        </section>

        <section class="lg:col-span-2 bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.06)] overflow-hidden">
          <div class="px-6 py-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-neutral-900">Recent sign-ups</h3>
            <Link :href="route('landlord.tenants')" class="text-xs text-primary-600 hover:underline">All workspaces</Link>
          </div>
          <ul class="divide-y divide-neutral-100 border-t border-neutral-100">
            <li v-for="t in stats.recent_signups ?? []" :key="t.id" class="px-6 py-3 flex items-center gap-3 hover:bg-neutral-50">
              <span class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-semibold shrink-0" :style="{ background: planColor(t.plan) + '1f', color: planColor(t.plan) }">{{ (t.name || '?').charAt(0) }}</span>
              <div class="min-w-0 flex-1">
                <Link :href="route('landlord.tenant-detail', t.id)" class="block text-sm font-medium text-neutral-900 truncate hover:underline">{{ t.name }}</Link>
                <p class="text-xs text-neutral-500 truncate">
                  <span class="font-mono">{{ t.slug }}</span> · <span class="capitalize">{{ t.plan }}</span>
                  <span v-if="!t.verified" class="text-[#c2410c]"> · email not verified</span>
                </p>
              </div>
              <span class="text-xs text-neutral-400 whitespace-nowrap">{{ timeAgo(t.created_at) }}</span>
            </li>
          </ul>
        </section>
      </div>

      <!-- Workspaces at a glance -->
      <section class="bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.06)] overflow-hidden">
        <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold text-neutral-900">Workspaces at a glance</h3>
          <p class="text-xs text-neutral-500">Largest portfolios first<span v-if="totals.unreachable"> · {{ totals.unreachable }} database{{ totals.unreachable === 1 ? '' : 's' }} could not be read</span></p>
        </div>
        <div class="overflow-x-auto border-t border-neutral-100">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-neutral-500">
                <th class="px-6 py-3 font-medium">Workspace</th>
                <th class="px-3 py-3 font-medium">Status</th>
                <th class="px-3 py-3 font-medium text-right">Staff</th>
                <th class="px-3 py-3 font-medium text-right">Borrowers</th>
                <th class="px-3 py-3 font-medium text-right">Live loans</th>
                <th class="px-3 py-3 font-medium text-right min-w-44">Outstanding</th>
                <th class="px-6 py-3 font-medium text-right">Last active</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-for="w in shownWorkspaces" :key="w.id" class="hover:bg-neutral-50">
                <td class="px-6 py-3">
                  <Link :href="route('landlord.tenant-detail', w.id)" class="font-medium text-neutral-900 hover:underline">{{ w.name }}</Link>
                  <p class="text-xs text-neutral-400"><span class="font-mono">{{ w.slug }}</span> · <span class="capitalize">{{ w.plan }}</span></p>
                </td>
                <td class="px-3 py-3">
                  <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-700 capitalize">
                    <span class="w-2 h-2 rounded-full" :style="{ background: statusColor(w.status) }"></span>{{ w.status }}
                  </span>
                </td>
                <td class="px-3 py-3 text-right tabular-nums text-neutral-700">{{ w.reachable ? num(w.staff) : '—' }}</td>
                <td class="px-3 py-3 text-right tabular-nums text-neutral-700">{{ w.reachable ? num(w.borrowers) : '—' }}</td>
                <td class="px-3 py-3 text-right tabular-nums text-neutral-700">{{ w.reachable ? num(w.active_loans) : '—' }}</td>
                <td class="px-3 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <div class="w-16 h-1.5 bg-neutral-100 rounded-full overflow-hidden hidden sm:block">
                      <div class="h-full rounded-full" :style="{ width: share(w.outstanding) + '%', background: '#6250d6' }"></div>
                    </div>
                    <span class="tabular-nums font-medium text-neutral-900">{{ money(w.outstanding, w.currency) }}</span>
                  </div>
                </td>
                <td class="px-6 py-3 text-right text-xs whitespace-nowrap" :title="formatDateTime(w.last_active)">
                  <span v-if="w.last_active" :class="activeRecently(w.last_active) ? 'text-[#0a7d0a] font-medium' : 'text-neutral-500'">{{ timeAgo(w.last_active) }}</span>
                  <span v-else class="text-neutral-400">—</span>
                </td>
              </tr>
              <tr v-if="!workspaces.length">
                <td colspan="7" class="px-6 py-10 text-center text-neutral-400">No workspaces yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="workspaces.length > 10" class="px-6 py-3 border-t border-neutral-100 text-center">
          <button @click="showAll = !showAll" class="text-sm text-primary-600 hover:underline font-medium">
            {{ showAll ? 'Show top 10' : `Show all ${workspaces.length}` }}
          </button>
        </div>
      </section>

      <!-- Recent payments -->
      <section class="bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.06)] overflow-hidden">
        <div class="px-6 py-4">
          <h3 class="text-sm font-semibold text-neutral-900">Recent payments</h3>
        </div>
        <div v-if="stats.recent_invoices?.length" class="overflow-x-auto border-t border-neutral-100">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-neutral-500">
                <th class="px-6 py-3 font-medium">Workspace</th>
                <th class="px-3 py-3 font-medium">Plan</th>
                <th class="px-3 py-3 font-medium">Cycle</th>
                <th class="px-3 py-3 font-medium">Gateway</th>
                <th class="px-3 py-3 font-medium text-right">Amount</th>
                <th class="px-6 py-3 font-medium text-right">Paid</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-for="inv in stats.recent_invoices" :key="inv.id" class="hover:bg-neutral-50">
                <td class="px-6 py-3 font-medium text-neutral-900">{{ inv.tenant_name }}</td>
                <td class="px-3 py-3 capitalize text-neutral-600">{{ inv.plan }}</td>
                <td class="px-3 py-3 capitalize text-neutral-600">{{ inv.billing_cycle }}</td>
                <td class="px-3 py-3 capitalize text-neutral-500">{{ inv.gateway }}</td>
                <td class="px-3 py-3 text-right font-semibold text-neutral-900 tabular-nums">{{ money(inv.amount, inv.currency) }}</td>
                <td class="px-6 py-3 text-right text-neutral-500">{{ inv.paid_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="px-6 pb-6 text-sm text-neutral-400">No payments recorded yet.</p>
      </section>
    </div>
  </LandlordLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import {
  ArrowPathIcon, ArrowTrendingUpIcon, BanknotesIcon, BoltIcon, BuildingOffice2Icon, ChartBarIcon,
  CheckBadgeIcon, CheckCircleIcon, ClockIcon, EnvelopeIcon, ExclamationTriangleIcon, UserPlusIcon, XCircleIcon,
} from '@heroicons/vue/24/outline'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'
import StatTile from '@/landlord/components/dashboard/StatTile.vue'
import MonthlyBars from '@/landlord/components/dashboard/MonthlyBars.vue'
import SegmentBar from '@/landlord/components/dashboard/SegmentBar.vue'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

const auth       = useLandlordAuth()
const stats      = ref({})
const loading    = ref(true)
const refreshing = ref(false)
const loadError  = ref('')
const showAll    = ref(false)

// ─── Palette (validated: dataviz categorical slots 1–3 + the fixed status set) ──
// Plans are identities → categorical, in a fixed order. Workspace states are
// states → the status colours. Every segment also carries a label and count.
const PLAN_COLORS   = { starter: '#2a78d6', growth: '#eb6834', enterprise: '#1baf7a' }
const STATUS_COLORS = { active: '#0ca30c', trial: '#2a78d6', expired: '#ec835a', suspended: '#d03b3b', cancelled: '#a3a29c' }
const SEVERITY      = {
  critical: { color: '#d03b3b', ink: '#b42323', label: 'Critical', icon: XCircleIcon },
  serious:  { color: '#ec835a', ink: '#b4532a', label: 'Action needed', icon: ExclamationTriangleIcon },
  warning:  { color: '#fab219', ink: '#8a5a00', label: 'Soon', icon: ClockIcon },
}

// ─── Derived ──────────────────────────────────────────────────────────────────
const o          = computed(() => stats.value.overview ?? {})
const totals     = computed(() => stats.value.usage?.totals ?? {})
const workspaces = computed(() => stats.value.usage?.workspaces ?? [])
const attention  = computed(() => stats.value.attention ?? [])
const email      = computed(() => stats.value.email ?? { total: 0, delivered: 0, failed: 0, pending: 0 })
const byStatus   = computed(() => stats.value.tenants?.by_status ?? {})

const shownWorkspaces = computed(() => (showAll.value ? workspaces.value : workspaces.value.slice(0, 10)))
const maxOutstanding  = computed(() => Math.max(1, ...workspaces.value.map(w => w.outstanding)))
const share = (v) => Math.round((v / maxOutstanding.value) * 100)

const primaryOutstanding = computed(() => {
  const entries = Object.entries(totals.value.outstanding ?? {})
  if (!entries.length) return money(0)
  entries.sort((a, b) => b[1] - a[1])
  return money(entries[0][1], entries[0][0]) + (entries.length > 1 ? ` +${entries.length - 1}` : '')
})

const deliveryRate = computed(() => {
  const settled = email.value.delivered + email.value.failed
  return settled ? Math.round((email.value.delivered / settled) * 100) + '%' : '—'
})

const statusSegments = computed(() => ['active', 'trial', 'expired', 'suspended', 'cancelled']
  .map(key => ({ key, label: key[0].toUpperCase() + key.slice(1), value: byStatus.value[key] ?? 0, color: STATUS_COLORS[key] }))
  .filter(s => s.value > 0 || ['active', 'trial'].includes(s.key)))

const planSegments = computed(() => ['starter', 'growth', 'enterprise']
  .map(key => ({ key, label: key[0].toUpperCase() + key.slice(1), value: stats.value.tenants?.by_plan?.[key] ?? 0, color: PLAN_COLORS[key] })))

const emailSegments = computed(() => [
  { key: 'delivered', label: 'Delivered', value: email.value.delivered, color: '#0ca30c' },
  { key: 'pending', label: 'Sent, not yet confirmed', value: email.value.pending, color: '#86b6ef' },
  { key: 'failed', label: 'Failed or bounced', value: email.value.failed, color: '#d03b3b' },
])

const greeting = computed(() => {
  const h = new Date().getHours()
  const part = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
  return `${part} · ${new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })}`
})

// ─── Helpers ──────────────────────────────────────────────────────────────────
const num = (v) => Number(v ?? 0).toLocaleString('en-US')

function money(value, currency = 'ZMW') {
  const n = Number(value ?? 0)
  if (Math.abs(n) >= 1_000_000) return `${currency} ${(n / 1_000_000).toFixed(2)}M`
  if (Math.abs(n) >= 100_000) return `${currency} ${(n / 1_000).toFixed(0)}K`
  return `${currency} ${n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`
}

const sum = (rows, key) => (rows ?? []).reduce((s, r) => s + (Number(r[key]) || 0), 0)
const planColor = (plan) => PLAN_COLORS[plan] ?? '#52514e'
const statusColor = (status) => STATUS_COLORS[status] ?? '#a3a29c'
const severityColor = (s) => SEVERITY[s]?.color ?? '#a3a29c'
const severityInk = (s) => SEVERITY[s]?.ink ?? '#52514e'
const severityLabel = (s) => SEVERITY[s]?.label ?? s
const severityIcon = (s) => SEVERITY[s]?.icon ?? ExclamationTriangleIcon
const activeRecently = (iso) => Date.now() - new Date(iso).getTime() < 7 * 86400000

function timeAgo(iso) {
  if (!iso) return '—'
  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000)
  const future = seconds < 0
  const s = Math.abs(seconds)
  if (s < 60) return 'just now'
  for (const [unit, size] of [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]]) {
    const n = Math.floor(s / size)
    if (n >= 1) return future ? `in ${n} ${unit}${n === 1 ? '' : 's'}` : `${n} ${unit}${n === 1 ? '' : 's'} ago`
  }
  return 'just now'
}

function formatDateTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ─── Data ─────────────────────────────────────────────────────────────────────
async function load(fresh = false) {
  if (fresh) refreshing.value = true
  try {
    const { data } = await axios.get('/api/v1/landlord/stats', { params: fresh ? { fresh: 1 } : {} })
    stats.value = data.data ?? {}
    loadError.value = ''
  } catch (e) {
    // Only a rejected token means signing in again; anything else must be shown.
    if (e.response?.status === 401) {
      auth.clearAuth()
      router.visit(route('landlord.login'))
      return
    }
    loadError.value = `Couldn't load the dashboard (${e.response?.status ?? 'network error'}): ${e.response?.data?.message ?? e.message}`
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

onMounted(() => {
  if (!auth.isAuthenticated) {
    router.visit(route('landlord.login'))
    return
  }
  load()
})
</script>
