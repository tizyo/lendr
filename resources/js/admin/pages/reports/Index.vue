<template>
  <AppLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">Reports & Analytics</h1>
        <p class="text-sm text-neutral-500 mt-0.5">{{ year }} portfolio overview</p>
      </div>
    </template>

    <div class="space-y-6">

      <!-- KPI row 1 -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="card in kpiRow1" :key="card.label" class="lendr-card p-4">
          <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">{{ card.label }}</p>
          <p class="text-2xl font-bold" :class="card.color">{{ card.display }}</p>
        </div>
      </div>

      <!-- KPI row 2 -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="card in kpiRow2" :key="card.label" class="lendr-card p-4">
          <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">{{ card.label }}</p>
          <p class="text-2xl font-bold" :class="card.color">{{ card.display }}</p>
        </div>
      </div>

      <!-- Bar charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Monthly disbursements -->
        <div class="lendr-card p-5">
          <h3 class="text-sm font-semibold text-neutral-800 mb-4">Monthly Disbursements ({{ year }})</h3>
          <div class="flex items-end gap-1 h-36">
            <div v-for="m in months" :key="m.month" class="flex-1 flex flex-col items-center gap-1">
              <div class="w-full flex items-end justify-center" style="height:108px">
                <div
                  class="w-full rounded-t bg-emerald-500 transition-all duration-700"
                  :style="{ height: barH(m.disbursed_amount, disbursedMax) }"
                  :title="`K ${fmt(m.disbursed_amount)}`"
                />
              </div>
              <span class="text-[9px] text-neutral-400">{{ m.label }}</span>
            </div>
          </div>
        </div>

        <!-- Monthly collections -->
        <div class="lendr-card p-5">
          <h3 class="text-sm font-semibold text-neutral-800 mb-4">Monthly Collections ({{ year }})</h3>
          <div class="flex items-end gap-1 h-36">
            <div v-for="m in months" :key="m.month" class="flex-1 flex flex-col items-center gap-1">
              <div class="w-full flex items-end justify-center" style="height:108px">
                <div
                  class="w-full rounded-t bg-primary-500 transition-all duration-700"
                  :style="{ height: barH(m.collected_amount, collectedMax) }"
                  :title="`K ${fmt(m.collected_amount)}`"
                />
              </div>
              <span class="text-[9px] text-neutral-400">{{ m.label }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Breakdown row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Status distribution -->
        <div class="lendr-card p-5">
          <h3 class="text-sm font-semibold text-neutral-800 mb-4">Loan Status Distribution</h3>
          <div class="space-y-2.5">
            <div v-for="item in statusBreakdown" :key="item.status" class="flex items-center gap-3">
              <div class="w-24 shrink-0 text-right">
                <span class="text-xs font-medium capitalize px-2 py-0.5 rounded-full" :class="statusBadge(item.status)">
                  {{ item.status }}
                </span>
              </div>
              <div class="flex-1 h-2.5 bg-neutral-100 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-700"
                  :class="statusBarColor(item.status)"
                  :style="{ width: totalLoans > 0 ? Math.round(item.count / totalLoans * 100) + '%' : '0%' }"
                />
              </div>
              <span class="text-xs text-neutral-500 w-8 text-right">{{ item.count }}</span>
            </div>
            <p v-if="!statusBreakdown.length" class="text-sm text-neutral-400 italic text-center py-4">No loan data yet.</p>
          </div>
        </div>

        <!-- Loan type breakdown -->
        <div class="lendr-card p-5">
          <h3 class="text-sm font-semibold text-neutral-800 mb-4">Loans by Product</h3>
          <div class="space-y-2.5">
            <div v-for="item in loanTypeBreakdown" :key="item.name" class="flex items-center gap-3">
              <span class="w-28 shrink-0 text-xs text-neutral-700 truncate" :title="item.name">{{ item.name }}</span>
              <div class="flex-1 h-2.5 bg-neutral-100 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full bg-primary-500 transition-all duration-700"
                  :style="{ width: typeMax > 0 ? Math.round(item.count / typeMax * 100) + '%' : '0%' }"
                />
              </div>
              <span class="text-xs text-neutral-500 w-8 text-right">{{ item.count }}</span>
            </div>
            <p v-if="!loanTypeBreakdown.length" class="text-sm text-neutral-400 italic text-center py-4">No loan data yet.</p>
          </div>
        </div>
      </div>

      <!-- Recent payments -->
      <div class="lendr-card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-100">
          <h3 class="text-sm font-semibold text-neutral-800">Recent Payments</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-neutral-100 bg-neutral-50">
                <th class="th">Receipt</th>
                <th class="th">Loan</th>
                <th class="th">Borrower</th>
                <th class="th">Method</th>
                <th class="th text-right">Amount</th>
                <th class="th">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="p in recentPayments" :key="p.id" class="hover:bg-neutral-50 transition">
                <td class="td font-mono text-xs">{{ p.receipt_number }}</td>
                <td class="td text-neutral-600">{{ p.loan_number }}</td>
                <td class="td font-medium text-neutral-900">{{ p.borrower ?? '—' }}</td>
                <td class="td capitalize text-neutral-600">{{ p.payment_method }}</td>
                <td class="td text-right font-semibold text-emerald-700">K {{ fmt(p.amount) }}</td>
                <td class="td text-neutral-500">{{ p.payment_date }}</td>
              </tr>
              <tr v-if="!recentPayments.length">
                <td colspan="6" class="td text-center text-neutral-400 italic py-8">No payments recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Advanced Reports ──────────────────────────────────────── -->
      <div>
        <h2 class="text-base font-semibold text-neutral-800 mb-3">Advanced Reports</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Link
            :href="route('reports.par')"
            class="lendr-card p-5 hover:border-primary-300 hover:shadow-md transition group block"
          >
            <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <p class="font-semibold text-neutral-800 group-hover:text-primary-700">PAR Aging</p>
            <p class="text-xs text-neutral-500 mt-0.5">Portfolio at risk by overdue bucket</p>
          </Link>
          <Link
            :href="route('reports.officer')"
            class="lendr-card p-5 hover:border-primary-300 hover:shadow-md transition group block"
          >
            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
            <p class="font-semibold text-neutral-800 group-hover:text-primary-700">Loan Officer Performance</p>
            <p class="text-xs text-neutral-500 mt-0.5">Disbursements, collections & default rate per officer</p>
          </Link>
          <Link
            :href="route('reports.collections')"
            class="lendr-card p-5 hover:border-primary-300 hover:shadow-md transition group block"
          >
            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
            </div>
            <p class="font-semibold text-neutral-800 group-hover:text-primary-700">Collections Efficiency</p>
            <p class="text-xs text-neutral-500 mt-0.5">Monthly collections vs scheduled instalments</p>
          </Link>
          <Link
            :href="route('reports.pnl')"
            class="lendr-card p-5 hover:border-primary-300 hover:shadow-md transition group block"
          >
            <div class="w-10 h-10 bg-primary-50 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <p class="font-semibold text-neutral-800 group-hover:text-primary-700">P&amp;L Summary</p>
            <p class="text-xs text-neutral-500 mt-0.5">Revenue, expenses &amp; net profit by period</p>
          </Link>
        </div>
      </div>

      <!-- ── On-demand report generator ─────────────────────────────── -->
      <div class="lendr-card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-100">
          <h3 class="text-sm font-semibold text-neutral-800">Generate Report</h3>
          <p class="text-xs text-neutral-500 mt-0.5">Filter and export raw data to CSV</p>
        </div>
        <div class="p-5 space-y-4">

          <!-- Report type tabs -->
          <div class="flex gap-2 flex-wrap">
            <button
              v-for="r in reportTypes"
              :key="r.key"
              @click="selectReport(r.key)"
              class="px-3 py-1.5 rounded-lg text-xs font-medium transition"
              :class="activeReport === r.key
                ? 'bg-primary-600 text-white'
                : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200'"
            >
              {{ r.label }}
            </button>
          </div>

          <!-- Filters -->
          <div v-if="activeReport" class="flex flex-wrap gap-3 items-end">
            <template v-if="activeReport === 'loans'">
              <div>
                <label class="label">Status</label>
                <select v-model="filters.status" class="input w-36">
                  <option value="">All</option>
                  <option v-for="s in loanStatuses" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>
              <div>
                <label class="label">Loan Type</label>
                <select v-model="filters.loan_type_id" class="input w-40">
                  <option value="">All types</option>
                  <option v-for="t in loanTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
            </template>
            <template v-if="activeReport === 'expenses'">
              <div>
                <label class="label">Category</label>
                <select v-model="filters.category_id" class="input w-44">
                  <option value="">All categories</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
            </template>
            <div v-if="activeReport !== 'borrowers'">
              <label class="label">From</label>
              <input v-model="filters.date_from" type="date" class="input" />
            </div>
            <div v-if="activeReport !== 'borrowers'">
              <label class="label">To</label>
              <input v-model="filters.date_to" type="date" class="input" />
            </div>
            <div class="flex gap-2 items-end">
              <button @click="generate" :disabled="generating" class="btn-primary">
                {{ generating ? 'Loading…' : 'Generate' }}
              </button>
              <button v-if="reportRows.length" @click="exportCsv" class="btn-ghost">
                Export CSV
              </button>
            </div>
          </div>

          <!-- Summary cards -->
          <div v-if="reportSummary" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div v-for="(val, key) in reportSummary" :key="key" class="bg-neutral-50 rounded-lg p-3 border border-neutral-100">
              <p class="text-xs text-neutral-500 mb-0.5">{{ formatKey(key) }}</p>
              <p class="text-lg font-bold text-neutral-900">{{ typeof val === 'number' ? fmt(val) : val }}</p>
            </div>
          </div>

          <!-- Results table -->
          <div v-if="reportRows.length" class="overflow-x-auto rounded-lg border border-neutral-200">
            <table class="w-full text-sm min-w-max">
              <thead class="border-b border-neutral-200 bg-neutral-50">
                <tr>
                  <th
                    v-for="col in activeColumns"
                    :key="col.key"
                    class="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide whitespace-nowrap"
                  >{{ col.label }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-100">
                <tr v-for="(row, i) in reportRows" :key="i" class="hover:bg-neutral-50">
                  <td
                    v-for="col in activeColumns"
                    :key="col.key"
                    class="px-3 py-2.5 text-neutral-700 whitespace-nowrap text-xs"
                    :class="col.numeric ? 'text-right tabular-nums' : ''"
                  >{{ row[col.key] ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
            <div class="px-4 py-2 text-xs text-neutral-400 border-t border-neutral-100">
              {{ reportRows.length.toLocaleString() }} rows
            </div>
          </div>

          <p v-else-if="generated && !generating" class="text-sm text-neutral-400 italic text-center py-4">
            No data for the selected filters.
          </p>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  year:              { type: Number,  default: () => new Date().getFullYear() },
  stats:             { type: Object,  default: () => ({}) },
  months:            { type: Array,   default: () => [] },
  loanTypeBreakdown: { type: Array,   default: () => [] },
  statusBreakdown:   { type: Array,   default: () => [] },
  recentPayments:    { type: Array,   default: () => [] },
  loanTypes:         { type: Array,   default: () => [] },
  categories:        { type: Array,   default: () => [] },
})

// ── KPI helpers ───────────────────────────────────────────────────────────────
const fmtK = (n) => 'K ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmt  = (n) => Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtN = (n) => Number(n).toLocaleString()

const kpiRow1 = computed(() => [
  { label: 'Total Loans',  display: fmtN(props.stats.total_loans ?? 0),       color: 'text-blue-600' },
  { label: 'Disbursed',    display: fmtK(props.stats.total_disbursed ?? 0),    color: 'text-emerald-600' },
  { label: 'Collected',    display: fmtK(props.stats.total_collected ?? 0),    color: 'text-teal-600' },
  { label: 'Outstanding',  display: fmtK(props.stats.total_outstanding ?? 0),  color: 'text-amber-600' },
])
const kpiRow2 = computed(() => [
  { label: 'Total Borrowers',  display: fmtN(props.stats.total_borrowers ?? 0),  color: 'text-purple-600' },
  { label: 'Active Borrowers', display: fmtN(props.stats.active_borrowers ?? 0), color: 'text-blue-600' },
  { label: 'Overdue Loans',    display: fmtN(props.stats.overdue_loans ?? 0),    color: 'text-red-600' },
  { label: 'NPL Rate',         display: (props.stats.npl_rate ?? 0) + '%',       color: 'text-red-600' },
])

// ── Chart helpers ─────────────────────────────────────────────────────────────
const disbursedMax = computed(() => Math.max(...props.months.map(m => m.disbursed_amount), 1))
const collectedMax = computed(() => Math.max(...props.months.map(m => m.collected_amount), 1))
const barH = (val, max) => Math.max(2, Math.round(val / max * 108)) + 'px'

// ── Breakdown helpers ─────────────────────────────────────────────────────────
const totalLoans = computed(() => props.statusBreakdown.reduce((s, i) => s + i.count, 0))
const typeMax    = computed(() => Math.max(...props.loanTypeBreakdown.map(i => i.count), 1))

function statusBadge(status) {
  const map = {
    active:      'bg-emerald-100 text-emerald-700',
    overdue:     'bg-red-100 text-red-700',
    defaulted:   'bg-red-200 text-red-800',
    completed:   'bg-neutral-200 text-neutral-700',
    submitted:   'bg-amber-100 text-amber-700',
    approved:    'bg-blue-100 text-blue-700',
    disbursed:   'bg-teal-100 text-teal-700',
    written_off: 'bg-neutral-100 text-neutral-500',
    draft:       'bg-neutral-100 text-neutral-400',
  }
  return map[status] ?? 'bg-neutral-100 text-neutral-600'
}
function statusBarColor(status) {
  return {
    active:    'bg-emerald-500', overdue:   'bg-red-500',   defaulted: 'bg-red-700',
    completed: 'bg-neutral-400', submitted: 'bg-amber-500', approved:  'bg-blue-500',
    disbursed: 'bg-teal-500',
  }[status] ?? 'bg-neutral-300'
}

// ── Report generator ──────────────────────────────────────────────────────────
const activeReport  = ref(null)
const generating    = ref(false)
const generated     = ref(false)
const reportRows    = ref([])
const reportSummary = ref(null)

const loanStatuses = ['draft', 'submitted', 'approved', 'active', 'overdue', 'completed', 'defaulted', 'written_off']

const filters = reactive({
  status: '', loan_type_id: '', category_id: '', date_from: '', date_to: '',
})

const reportTypes = [
  { key: 'loans',     label: 'Loans' },
  { key: 'payments',  label: 'Payments' },
  { key: 'expenses',  label: 'Expenses' },
  { key: 'borrowers', label: 'Borrowers' },
]

const columnMap = {
  loans: [
    { key: 'loan_number',         label: 'Loan #' },
    { key: 'borrower_name',       label: 'Borrower' },
    { key: 'loan_type',           label: 'Type' },
    { key: 'principal_amount',    label: 'Principal',   numeric: true },
    { key: 'total_repayable',     label: 'Repayable',   numeric: true },
    { key: 'outstanding_balance', label: 'Outstanding', numeric: true },
    { key: 'status',              label: 'Status' },
    { key: 'application_date',    label: 'Applied' },
    { key: 'disbursement_date',   label: 'Disbursed' },
    { key: 'maturity_date',       label: 'Maturity' },
  ],
  payments: [
    { key: 'receipt_number',      label: 'Receipt #' },
    { key: 'borrower_name',       label: 'Borrower' },
    { key: 'loan_number',         label: 'Loan #' },
    { key: 'amount',              label: 'Amount',    numeric: true },
    { key: 'principal_allocated', label: 'Principal', numeric: true },
    { key: 'interest_allocated',  label: 'Interest',  numeric: true },
    { key: 'penalty_allocated',   label: 'Penalty',   numeric: true },
    { key: 'payment_method',      label: 'Method' },
    { key: 'payment_date',        label: 'Date' },
  ],
  expenses: [
    { key: 'expense_number', label: 'Expense #' },
    { key: 'title',          label: 'Title' },
    { key: 'category',       label: 'Category' },
    { key: 'amount',         label: 'Amount',  numeric: true },
    { key: 'vendor',         label: 'Vendor' },
    { key: 'status',         label: 'Status' },
    { key: 'expense_date',   label: 'Date' },
    { key: 'submitted_by',   label: 'Submitted By' },
  ],
  borrowers: [
    { key: 'borrower_number', label: 'Borrower #' },
    { key: 'first_name',      label: 'First Name' },
    { key: 'last_name',       label: 'Last Name' },
    { key: 'phone',           label: 'Phone' },
    { key: 'national_id',     label: 'National ID' },
    { key: 'is_active',       label: 'Active' },
    { key: 'total_loans',     label: 'Loans',        numeric: true },
    { key: 'active_loans',    label: 'Active Loans', numeric: true },
    { key: 'date_registered', label: 'Registered' },
  ],
}

const activeColumns = computed(() => activeReport.value ? columnMap[activeReport.value] ?? [] : [])

function selectReport(key) {
  activeReport.value = key
  reportRows.value   = []
  reportSummary.value = null
  generated.value    = false
  Object.assign(filters, { status: '', loan_type_id: '', category_id: '', date_from: '', date_to: '' })
}

async function generate() {
  generating.value = true
  generated.value  = true
  try {
    const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''))
    const res = await axios.get(`/api/v1/reports/${activeReport.value}`, { params })
    reportRows.value    = res.data.data.rows ?? []
    reportSummary.value = res.data.data.summary ?? null
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to generate report.')
    reportRows.value = []
  } finally {
    generating.value = false
  }
}

function exportCsv() {
  const cols   = activeColumns.value
  const header = cols.map(c => c.label).join(',')
  const body   = reportRows.value.map(r =>
    cols.map(c => JSON.stringify(r[c.key] ?? '')).join(',')
  ).join('\n')
  const blob = new Blob([header + '\n' + body], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = Object.assign(document.createElement('a'), {
    href: url,
    download: `${activeReport.value}-report-${new Date().toISOString().slice(0, 10)}.csv`,
  })
  a.click()
  URL.revokeObjectURL(url)
}

const formatKey = (key) => key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
</script>

<style scoped>
@reference "../../../../css/app.css";
.th { @apply px-5 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide; }
.td { @apply px-5 py-3.5 text-sm text-neutral-700; }
</style>
