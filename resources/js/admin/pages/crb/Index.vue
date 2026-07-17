<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">CRB — Credit Reference Bureau</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Cross-tenant credit checks and borrower reports</p>
        </div>
      </div>
    </template>

    <!-- Search / Lookup -->
    <div class="lendr-card p-5 mb-4">
      <h2 class="font-semibold text-neutral-800 mb-3">Identity Lookup</h2>
      <div class="flex flex-col sm:flex-row gap-3">
        <input v-model="lookupNrc" class="lendr-input flex-1 text-sm" placeholder="National ID / NRC…" />
        <input v-model="lookupTpin" class="lendr-input flex-1 text-sm" placeholder="TPIN Number…" />
        <input v-model="lookupCompany" class="lendr-input flex-1 text-sm" placeholder="Company Reg Number…" />
        <button @click="doLookup" :disabled="looking" class="lendr-btn-primary shrink-0">{{ looking ? 'Searching…' : 'Check CRB' }}</button>
      </div>
      <p v-if="lookupError" class="text-red-500 text-sm mt-2">{{ lookupError }}</p>
    </div>

    <!-- Lookup Result -->
    <div v-if="lookupResult" class="lendr-card p-5 mb-4">
      <div class="flex justify-between items-start mb-4">
        <div>
          <p class="text-sm text-neutral-500">Type: <span class="capitalize font-medium">{{ lookupResult.identity_type ?? '—' }}</span></p>
          <p class="text-sm text-neutral-500">Hash: <span class="font-mono text-xs">{{ lookupResult.identity_hash?.slice(0, 20) }}…</span></p>
          <p class="text-sm text-neutral-500">Band: <span class="capitalize font-medium">{{ lookupResult.score_band ?? '—' }}</span></p>
        </div>
        <div class="text-right">
          <p class="text-3xl font-black" :class="scoreColor(lookupResult.credit_score)">{{ lookupResult.credit_score ?? 'N/A' }}</p>
          <p class="text-xs text-neutral-400">CRB Score</p>
        </div>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <div class="lendr-card p-3 border">
          <p class="text-xs text-neutral-500">Active Loans</p>
          <p class="text-xl font-bold mt-0.5">{{ lookupResult.active_loan_count ?? 0 }}</p>
        </div>
        <div class="lendr-card p-3 border">
          <p class="text-xs text-neutral-500">Total Borrowed</p>
          <p class="text-xl font-bold mt-0.5">{{ fmt(lookupResult.total_amount_borrowed) }}</p>
        </div>
        <div class="lendr-card p-3 border">
          <p class="text-xs text-neutral-500">Loans Completed</p>
          <p class="text-xl font-bold mt-0.5 text-green-600">{{ lookupResult.total_loans_completed ?? 0 }}</p>
        </div>
        <div class="lendr-card p-3 border">
          <p class="text-xs text-neutral-500">Defaults</p>
          <p class="text-xl font-bold mt-0.5 text-red-600">{{ lookupResult.total_loans_defaulted ?? 0 }}</p>
        </div>
      </div>
      <!-- Recent Score Events -->
      <div v-if="lookupResult.recent_events?.length">
        <h3 class="font-semibold text-sm mb-2 text-neutral-700">Recent Score Events</h3>
        <table class="w-full text-xs">
          <thead class="text-neutral-500 uppercase tracking-wide border-b">
            <tr><th class="py-2 text-left">Event</th><th class="py-2 text-right">Change</th><th class="py-2 text-right">Score After</th><th class="py-2 text-left">Date</th></tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="e in lookupResult.recent_events" :key="e.event_type + e.created_at" class="hover:bg-neutral-50">
              <td class="py-1.5 capitalize text-neutral-700">{{ e.event_type?.replace(/_/g, ' ') }}</td>
              <td class="py-1.5 text-right font-semibold" :class="(e.points_change ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'">{{ e.points_change >= 0 ? '+' : '' }}{{ e.points_change }}</td>
              <td class="py-1.5 text-right text-neutral-500">{{ e.score_after }}</td>
              <td class="py-1.5 text-neutral-400">{{ e.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Inquiries -->
    <div class="lendr-card overflow-hidden">
      <div class="p-4 border-b">
        <span class="font-medium text-sm text-neutral-700">Recent CRB Inquiries</span>
      </div>
      <div v-if="loadingInquiries" class="p-10 text-center text-neutral-400">Loading…</div>
      <div v-else-if="inquiries.length === 0" class="p-10 text-center text-neutral-400">No recent inquiries.</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Identity Hash</th>
            <th class="px-4 py-3 text-right">Score at Inquiry</th>
            <th class="px-4 py-3 text-left">Purpose</th>
            <th class="px-4 py-3 text-center">Risk Level</th>
            <th class="px-4 py-3 text-left">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-for="inq in inquiries" :key="inq.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3 font-mono text-xs text-neutral-500">{{ inq.identity_hash?.slice(0, 16) }}…</td>
            <td class="px-4 py-3 text-right font-bold" :class="scoreColor(inq.score_at_inquiry)">{{ inq.score_at_inquiry ?? 'N/A' }}</td>
            <td class="px-4 py-3 text-neutral-600 capitalize text-xs">{{ inq.purpose?.replace(/_/g, ' ') ?? '—' }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="inq.risk_level === 'excellent' || inq.risk_level === 'good' ? 'lendr-badge-success' : inq.risk_level === 'very_poor' ? 'lendr-badge-danger' : 'lendr-badge-neutral'" class="lendr-badge text-xs capitalize">{{ inq.risk_level?.replace('_', ' ') ?? 'N/A' }}</span>
            </td>
            <td class="px-4 py-3 text-neutral-400 text-xs">{{ inq.created_at ? new Date(inq.created_at).toLocaleDateString() : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const lookupNrc = ref('')
const lookupTpin = ref('')
const lookupCompany = ref('')
const looking = ref(false)
const lookupError = ref('')
const lookupResult = ref(null)
const inquiries = ref([])
const loadingInquiries = ref(false)

async function doLookup() {
  lookupError.value = ''
  lookupResult.value = null
  const type = lookupNrc.value ? 'nrc' : lookupTpin.value ? 'tpin' : lookupCompany.value ? 'company_reg' : null
  const value = lookupNrc.value || lookupTpin.value || lookupCompany.value
  if (!type || !value) {
    lookupError.value = 'Enter at least one identifier.'
    return
  }
  looking.value = true
  try {
    const { data } = await axios.post('/api/v1/crb/check', { type, value })
    const result = data.data ?? {}
    // Always fetch full report (has score events, totals etc.)
    if (result.identity_hash) {
      const rep = await axios.get(`/api/v1/crb/report/${result.identity_hash}`)
      lookupResult.value = { ...result, ...(rep.data.data ?? {}) }
    } else {
      lookupResult.value = result
    }
  } catch (e) { lookupError.value = e.response?.data?.message ?? 'Lookup failed.' } finally { looking.value = false }
}

async function fetchInquiries() {
  loadingInquiries.value = true
  try {
    const { data } = await axios.get('/api/v1/crb/inquiries')
    inquiries.value = data.data ?? []
  } catch { inquiries.value = [] } finally { loadingInquiries.value = false }
}

function scoreColor(score) {
  if (!score) return 'text-neutral-400'
  if (score >= 700) return 'text-green-600'
  if (score >= 550) return 'text-amber-600'
  return 'text-red-600'
}
function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString() }

onMounted(() => fetchInquiries())
</script>
