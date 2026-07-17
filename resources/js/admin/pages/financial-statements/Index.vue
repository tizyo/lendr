<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Financial Statements</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Balance sheet, income statement, and cash flow reports</p>
        </div>
        <div class="flex gap-2 items-center">
          <input v-model="asOf" type="date" class="lendr-input text-sm" />
          <button @click="fetchAll" :disabled="loading" class="lendr-btn-primary">{{ loading ? 'Loading…' : 'Generate' }}</button>
        </div>
      </div>
    </template>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-neutral-200 mb-4">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px', activeTab === tab.key ? 'border-primary-500 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700']">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-16 text-neutral-400">Generating statements…</div>

    <!-- Balance Sheet -->
    <div v-if="activeTab === 'balance-sheet' && !loading && balanceSheet">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="lendr-card p-5">
          <h3 class="font-bold text-neutral-700 mb-3">Assets</h3>
          <div v-for="item in balanceSheet.assets" :key="item.label" class="flex justify-between py-1.5 text-sm border-b border-neutral-50 last:border-0">
            <span class="text-neutral-600">{{ item.label }}</span>
            <span class="font-medium">{{ formatAmt(item.amount) }}</span>
          </div>
          <div class="flex justify-between pt-2 font-bold text-primary-600">
            <span>Total Assets</span>
            <span>{{ formatAmt(balanceSheet.total_assets) }}</span>
          </div>
        </div>
        <div class="lendr-card p-5">
          <h3 class="font-bold text-neutral-700 mb-3">Liabilities & Equity</h3>
          <div v-for="item in [...(balanceSheet.liabilities ?? []), ...(balanceSheet.equity ?? [])]" :key="item.label" class="flex justify-between py-1.5 text-sm border-b border-neutral-50 last:border-0">
            <span class="text-neutral-600">{{ item.label }}</span>
            <span class="font-medium">{{ formatAmt(item.amount) }}</span>
          </div>
          <div class="flex justify-between pt-2 font-bold text-primary-600">
            <span>Total L + E</span>
            <span>{{ formatAmt(balanceSheet.total_liabilities_equity) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Income Statement -->
    <div v-if="activeTab === 'income-statement' && !loading && incomeStatement">
      <div class="lendr-card p-5 max-w-2xl">
        <div class="space-y-1">
          <div v-for="item in incomeStatement.items" :key="item.label"
            :class="['flex justify-between py-1.5 text-sm', item.is_total ? 'font-bold border-t border-neutral-200 mt-2 pt-2 text-primary-600' : 'border-b border-neutral-50']">
            <span>{{ item.label }}</span>
            <span :class="item.amount < 0 ? 'text-red-600' : ''">{{ formatAmt(item.amount) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Cash Flow -->
    <div v-if="activeTab === 'cash-flow' && !loading && cashFlow">
      <div class="lendr-card p-5 max-w-2xl">
        <div class="space-y-1">
          <div v-for="item in cashFlow.items" :key="item.label"
            :class="['flex justify-between py-1.5 text-sm', item.is_total ? 'font-bold border-t border-neutral-200 mt-2 pt-2 text-primary-600' : 'border-b border-neutral-50']">
            <span>{{ item.label }}</span>
            <span :class="item.amount < 0 ? 'text-red-600' : ''">{{ formatAmt(item.amount) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- PAR -->
    <div v-if="activeTab === 'par' && !loading && par">
      <div class="lendr-card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Bucket</th>
              <th class="px-4 py-3 text-right">Loans</th>
              <th class="px-4 py-3 text-right">Outstanding</th>
              <th class="px-4 py-3 text-right">PAR %</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-for="row in par.buckets" :key="row.bucket" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-medium">{{ row.bucket }}</td>
              <td class="px-4 py-3 text-right">{{ row.count }}</td>
              <td class="px-4 py-3 text-right">{{ formatAmt(row.outstanding) }}</td>
              <td class="px-4 py-3 text-right font-semibold" :class="row.par_pct > 10 ? 'text-red-600' : 'text-neutral-700'">{{ row.par_pct }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const activeTab = ref('balance-sheet')
const tabs = [
  { key: 'balance-sheet', label: 'Balance Sheet' },
  { key: 'income-statement', label: 'Income Statement' },
  { key: 'cash-flow', label: 'Cash Flow' },
  { key: 'par', label: 'Portfolio at Risk' },
]

const asOf = ref(new Date().toISOString().slice(0, 10))
const loading = ref(false)
const balanceSheet = ref(null)
const incomeStatement = ref(null)
const cashFlow = ref(null)
const par = ref(null)

async function fetchAll() {
  loading.value = true
  try {
    const [bs, is, cf, p] = await Promise.all([
      axios.get('/api/v1/financial-statements/balance-sheet', { params: { as_of: asOf.value } }),
      axios.get('/api/v1/financial-statements/income-statement', { params: { as_of: asOf.value } }),
      axios.get('/api/v1/financial-statements/cash-flow', { params: { as_of: asOf.value } }),
      axios.get('/api/v1/financial-statements/par'),
    ])
    balanceSheet.value = bs.data.data
    incomeStatement.value = is.data.data
    cashFlow.value = cf.data.data
    par.value = p.data.data
  } finally { loading.value = false }
}

function formatAmt(n) { return 'K ' + Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
onMounted(() => fetchAll())
</script>
