<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Collections</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Overdue loan queue — track and log collection activities</p>
        </div>
        <Link :href="route('reports.par')" class="btn-secondary text-sm flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
          PAR Report
        </Link>
      </div>
    </template>

    <div class="space-y-5">

      <!-- KPI cards -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="lendr-card p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Overdue Accounts</p>
          <p class="text-2xl font-bold text-red-600">{{ stats.total_overdue }}</p>
        </div>
        <div class="lendr-card p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Logged Today</p>
          <p class="text-2xl font-bold text-neutral-900">{{ stats.logged_today }}</p>
        </div>
        <div class="lendr-card p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Follow-ups Today</p>
          <p class="text-2xl font-bold text-amber-600">{{ stats.follow_up_today }}</p>
        </div>
        <div class="lendr-card p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Promised (week)</p>
          <p class="text-lg font-bold text-neutral-800">K {{ fmt(stats.promised_this_week) }}</p>
        </div>
        <div class="lendr-card p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-1">Collected (week)</p>
          <p class="text-lg font-bold text-emerald-700">K {{ fmt(stats.collected_this_week) }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="lendr-card p-4 flex flex-wrap gap-3 items-center">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search loan, borrower, phone…"
          class="input text-sm flex-1 min-w-48"
          @keydown.enter="applyFilters"
        />
        <select v-model="filters.bucket" class="input text-sm">
          <option value="">All buckets</option>
          <option value="1_30">1–30 days</option>
          <option value="31_60">31–60 days</option>
          <option value="61_90">61–90 days</option>
          <option value="91plus">90+ days</option>
        </select>
        <select v-model="filters.officer_id" class="input text-sm">
          <option value="">All officers</option>
          <option v-for="o in officers" :key="o.id" :value="o.id">{{ o.name }}</option>
        </select>
        <button @click="applyFilters" class="btn-primary text-sm">Filter</button>
        <button @click="clearFilters" class="btn-secondary text-sm">Clear</button>
      </div>

      <!-- Queue table -->
      <div class="lendr-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Borrower</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase hidden md:table-cell">Loan</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Outstanding</th>
                <th class="text-center px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Days Overdue</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase hidden lg:table-cell">Last Contact</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase hidden lg:table-cell">Follow-up</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr
                v-for="loan in loans.data"
                :key="loan.id"
                class="hover:bg-neutral-50/60 transition"
                :class="loan.max_days_overdue > 90 ? 'bg-red-50/30' : ''"
              >
                <!-- Borrower -->
                <td class="px-5 py-3">
                  <p class="font-semibold text-neutral-900">{{ loan.borrower.name }}</p>
                  <p class="text-xs text-neutral-400 mt-0.5">{{ loan.borrower.phone }} · {{ loan.borrower.borrower_number }}</p>
                </td>

                <!-- Loan -->
                <td class="px-5 py-3 hidden md:table-cell">
                  <p class="font-mono text-xs text-neutral-600">{{ loan.loan_number }}</p>
                  <p class="text-xs text-neutral-400 mt-0.5">{{ loan.loan_type }}</p>
                </td>

                <!-- Outstanding -->
                <td class="px-5 py-3 text-right">
                  <p class="font-bold text-red-600">K {{ loan.outstanding_balance }}</p>
                  <p class="text-xs text-neutral-400 mt-0.5">{{ loan.overdue_instalments }} instalment{{ loan.overdue_instalments !== 1 ? 's' : '' }} overdue</p>
                </td>

                <!-- Days overdue bucket badge -->
                <td class="px-5 py-3 text-center">
                  <span
                    class="inline-block px-2.5 py-1 text-xs font-bold rounded-full"
                    :class="parBadge(loan.max_days_overdue)"
                  >{{ loan.max_days_overdue }}d</span>
                </td>

                <!-- Last contact -->
                <td class="px-5 py-3 hidden lg:table-cell">
                  <template v-if="loan.latest_log">
                    <span
                      class="inline-block px-2 py-0.5 text-xs font-semibold rounded-full mb-1"
                      :class="outcomeBadge(loan.latest_log.outcome_color)"
                    >{{ loan.latest_log.outcome_label }}</span>
                    <p class="text-xs text-neutral-400">{{ loan.latest_log.officer_name }} · {{ loan.latest_log.created_at }}</p>
                  </template>
                  <span v-else class="text-xs text-neutral-400 italic">Not contacted</span>
                </td>

                <!-- Follow-up date -->
                <td class="px-5 py-3 hidden lg:table-cell">
                  <span
                    v-if="loan.latest_log?.follow_up_date"
                    class="text-xs font-medium"
                    :class="isToday(loan.latest_log.follow_up_date) ? 'text-amber-600 font-bold' : 'text-neutral-600'"
                  >
                    {{ isToday(loan.latest_log.follow_up_date) ? '📌 Today' : loan.latest_log.follow_up_date }}
                  </span>
                  <span v-else class="text-xs text-neutral-400">—</span>
                </td>

                <!-- Actions -->
                <td class="px-5 py-3 text-right">
                  <div class="flex items-center gap-2 justify-end">
                    <button
                      @click="openLog(loan)"
                      class="text-xs bg-primary-50 text-primary-700 hover:bg-primary-100 font-semibold px-3 py-1.5 rounded-lg transition"
                    >
                      + Log
                    </button>
                    <Link
                      :href="route('collections.show', loan.id)"
                      class="text-xs text-neutral-500 hover:text-neutral-700 px-2 py-1.5"
                    >History</Link>
                  </div>
                </td>
              </tr>

              <tr v-if="!loans.data.length">
                <td colspan="7" class="px-5 py-12 text-center text-neutral-400">
                  <svg class="w-10 h-10 text-neutral-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  No overdue loans match the current filters.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="loans.last_page > 1" class="px-5 py-3 border-t border-neutral-100 flex items-center justify-between">
          <p class="text-xs text-neutral-500">{{ loans.from }}–{{ loans.to }} of {{ loans.total }}</p>
          <div class="flex gap-1">
            <Link
              v-for="link in loans.links"
              :key="link.label"
              :href="link.url ?? '#'"
              v-html="link.label"
              class="px-2.5 py-1 text-xs rounded"
              :class="link.active ? 'bg-primary-600 text-white' : 'text-neutral-500 hover:bg-neutral-100'"
              :aria-disabled="!link.url"
            />
          </div>
        </div>
      </div>

    </div>

    <!-- Log Activity Modal -->
    <div v-if="logModal.show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="closeLog">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">

        <!-- Modal header -->
        <div class="px-6 py-5 border-b border-neutral-100">
          <div class="flex items-start justify-between">
            <div>
              <p class="font-bold text-neutral-900">Log Collection Activity</p>
              <p class="text-sm text-neutral-500 mt-0.5">
                {{ logModal.loan?.borrower.name }} · {{ logModal.loan?.loan_number }}
              </p>
            </div>
            <button @click="closeLog" class="text-neutral-400 hover:text-neutral-600 mt-0.5">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Quick borrower info -->
        <div class="px-6 pt-4 pb-0">
          <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-red-50 rounded-lg p-3 text-center">
              <p class="text-xs text-neutral-500">Outstanding</p>
              <p class="font-bold text-red-600 text-sm">K {{ logModal.loan?.outstanding_balance }}</p>
            </div>
            <div class="bg-neutral-50 rounded-lg p-3 text-center">
              <p class="text-xs text-neutral-500">Days Overdue</p>
              <p class="font-bold text-neutral-800 text-sm">{{ logModal.loan?.max_days_overdue }}d</p>
            </div>
            <div class="bg-neutral-50 rounded-lg p-3 text-center">
              <p class="text-xs text-neutral-500">Phone</p>
              <p class="font-bold text-neutral-800 text-sm">{{ logModal.loan?.borrower.phone }}</p>
            </div>
          </div>
        </div>

        <!-- Form -->
        <div class="px-6 pb-6 space-y-4">

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Contact Method *</label>
              <select v-model="logForm.contact_method" class="input w-full">
                <option value="call">📞 Phone Call</option>
                <option value="sms">💬 SMS</option>
                <option value="visit">🚗 Field Visit</option>
                <option value="email">📧 Email</option>
                <option value="whatsapp">📱 WhatsApp</option>
              </select>
            </div>
            <div>
              <label class="label">Outcome *</label>
              <select v-model="logForm.outcome" class="input w-full">
                <option value="reached">Reached</option>
                <option value="no_answer">No Answer</option>
                <option value="promised_payment">Promised Payment</option>
                <option value="partial_payment">Partial Payment</option>
                <option value="paid_up">Paid Up</option>
                <option value="refused">Refused</option>
                <option value="invalid_number">Invalid Number</option>
                <option value="rescheduled">Rescheduled</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div v-if="['promised_payment', 'rescheduled'].includes(logForm.outcome)">
              <label class="label">Amount Promised</label>
              <input v-model="logForm.amount_promised" type="number" min="0.01" step="0.01" class="input w-full" placeholder="0.00" />
            </div>
            <div v-if="['partial_payment', 'paid_up'].includes(logForm.outcome)">
              <label class="label">Amount Collected</label>
              <input v-model="logForm.amount_collected" type="number" min="0.01" step="0.01" class="input w-full" placeholder="0.00" />
            </div>
            <div>
              <label class="label">Follow-up Date</label>
              <input v-model="logForm.follow_up_date" type="date" :min="tomorrow" class="input w-full" />
            </div>
          </div>

          <div>
            <label class="label">Notes</label>
            <textarea v-model="logForm.notes" rows="3" class="input resize-none" placeholder="Brief notes about the contact…"></textarea>
          </div>

          <p v-if="logError" class="text-xs text-red-600">{{ logError }}</p>

          <div class="flex gap-3 pt-1">
            <button @click="submitLog" :disabled="logSubmitting" class="btn-primary disabled:opacity-50 flex items-center gap-2">
              <svg v-if="logSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ logSubmitting ? 'Saving…' : 'Save Activity' }}
            </button>
            <button @click="closeLog" class="btn-secondary">Cancel</button>
          </div>

        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  loans:    Object,
  stats:    Object,
  officers: Array,
  filters:  Object,
})

// ─── Filters ─────────────────────────────────────────────────────────────────
const filters = reactive({
  search:     props.filters?.search     ?? '',
  bucket:     props.filters?.bucket     ?? '',
  officer_id: props.filters?.officer_id ?? '',
})

function applyFilters() {
  router.get(route('collections.index'), filters, { preserveState: true, replace: true })
}

function clearFilters() {
  filters.search = ''
  filters.bucket = ''
  filters.officer_id = ''
  applyFilters()
}

// ─── Log modal ────────────────────────────────────────────────────────────────
const logModal = reactive({ show: false, loan: null })
const logForm = reactive({
  contact_method:   'call',
  outcome:          'reached',
  notes:            '',
  follow_up_date:   '',
  amount_promised:  '',
  amount_collected: '',
})
const logSubmitting = ref(false)
const logError      = ref('')
const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10)

function openLog(loan) {
  logModal.loan = loan
  logModal.show = true
  logError.value = ''
  Object.assign(logForm, {
    contact_method: 'call', outcome: 'reached', notes: '',
    follow_up_date: '', amount_promised: '', amount_collected: '',
  })
}

function closeLog() {
  logModal.show = false
  logModal.loan = null
}

async function submitLog() {
  logError.value = ''
  logSubmitting.value = true
  try {
    const { data } = await axios.post(route('collections.logs.store', logModal.loan.id), {
      contact_method:   logForm.contact_method,
      outcome:          logForm.outcome,
      notes:            logForm.notes || null,
      follow_up_date:   logForm.follow_up_date || null,
      amount_promised:  logForm.amount_promised  || null,
      amount_collected: logForm.amount_collected || null,
    })

    // Update the row's latest_log in place
    const row = props.loans.data.find(l => l.id === logModal.loan.id)
    if (row) row.latest_log = data.log

    closeLog()
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) {
      logError.value = Object.values(errs).flat().join(' ')
    } else {
      logError.value = e.response?.data?.message ?? 'Failed to save.'
    }
  } finally {
    logSubmitting.value = false
  }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function fmt(n) {
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function parBadge(days) {
  if (days > 90)  return 'bg-red-200 text-red-900'
  if (days > 60)  return 'bg-red-100 text-red-700'
  if (days > 30)  return 'bg-orange-100 text-orange-700'
  return 'bg-amber-100 text-amber-700'
}

function outcomeBadge(color) {
  const map = {
    emerald: 'bg-emerald-100 text-emerald-700',
    amber:   'bg-amber-100 text-amber-700',
    blue:    'bg-blue-100 text-blue-700',
    red:     'bg-red-100 text-red-700',
    neutral: 'bg-neutral-100 text-neutral-600',
  }
  return map[color] ?? map.neutral
}

function isToday(dateStr) {
  if (!dateStr) return false
  const today = new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' })
  return dateStr === today
}
</script>
