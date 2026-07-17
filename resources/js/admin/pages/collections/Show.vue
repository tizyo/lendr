<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link :href="route('collections.index')" class="text-neutral-400 hover:text-neutral-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
          </Link>
          <div>
            <h1 class="text-2xl font-bold text-neutral-900">Collection History</h1>
            <p class="text-sm text-neutral-500 mt-0.5">{{ loan.loan_number }} — {{ loan.borrower.name }}</p>
          </div>
        </div>
        <button @click="openModal()" class="btn-primary flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Log Activity
        </button>
      </div>
    </template>

    <div class="space-y-6">

      <!-- Loan Summary -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Borrower Card -->
        <div class="lendr-card p-5">
          <h2 class="text-sm font-semibold text-neutral-500 uppercase tracking-wide mb-4">Borrower</h2>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Name</span>
              <Link :href="route('borrowers.show', loan.borrower.id)" class="text-sm font-medium text-primary-600 hover:underline">
                {{ loan.borrower.name }}
              </Link>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Borrower #</span>
              <span class="text-sm font-medium text-neutral-800">{{ loan.borrower.borrower_number }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Phone</span>
              <a :href="`tel:${loan.borrower.phone}`" class="text-sm font-medium text-neutral-800 hover:text-primary-600">
                {{ loan.borrower.phone }}
              </a>
            </div>
            <div v-if="loan.borrower.email" class="flex justify-between">
              <span class="text-sm text-neutral-500">Email</span>
              <a :href="`mailto:${loan.borrower.email}`" class="text-sm font-medium text-neutral-800 hover:text-primary-600 truncate max-w-[200px]">
                {{ loan.borrower.email }}
              </a>
            </div>
            <div v-if="loan.borrower.city" class="flex justify-between">
              <span class="text-sm text-neutral-500">City</span>
              <span class="text-sm text-neutral-800">{{ loan.borrower.city }}</span>
            </div>
            <div v-if="loan.borrower.address" class="flex justify-between">
              <span class="text-sm text-neutral-500">Address</span>
              <span class="text-sm text-neutral-800 text-right max-w-[200px]">{{ loan.borrower.address }}</span>
            </div>
          </div>
        </div>

        <!-- Loan Card -->
        <div class="lendr-card p-5">
          <h2 class="text-sm font-semibold text-neutral-500 uppercase tracking-wide mb-4">Loan Details</h2>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Loan #</span>
              <Link :href="route('loans.show', loan.id)" class="text-sm font-medium text-primary-600 hover:underline">
                {{ loan.loan_number }}
              </Link>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Loan Type</span>
              <span class="text-sm text-neutral-800">{{ loan.loan_type }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Status</span>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 capitalize">
                {{ loan.status_label }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Outstanding Balance</span>
              <span class="text-sm font-semibold text-red-600">{{ loan.outstanding_balance }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Penalty Balance</span>
              <span class="text-sm font-medium text-orange-600">{{ loan.penalty_balance }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Days Overdue</span>
              <span :class="parBadgeClass(loan.max_days_overdue)" class="text-xs font-bold px-2 py-0.5 rounded-full">
                {{ loan.max_days_overdue }}d overdue
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Overdue Instalments</span>
              <span class="text-sm font-medium text-neutral-800">{{ loan.overdue_instalments }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-neutral-500">Overdue Amount</span>
              <span class="text-sm font-semibold text-red-600">{{ loan.overdue_outstanding }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Collection Log Timeline -->
      <div class="lendr-card p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-base font-semibold text-neutral-800">Activity Timeline</h2>
          <span class="text-sm text-neutral-500">{{ logList.length }} {{ logList.length === 1 ? 'entry' : 'entries' }}</span>
        </div>

        <!-- Empty state -->
        <div v-if="!logList.length" class="text-center py-12">
          <div class="w-12 h-12 rounded-full bg-neutral-100 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
          </div>
          <p class="text-sm font-medium text-neutral-600">No activity logged yet</p>
          <p class="text-xs text-neutral-400 mt-1">Click "Log Activity" to record the first contact attempt.</p>
        </div>

        <!-- Timeline -->
        <div v-else class="relative">
          <!-- Vertical line -->
          <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-neutral-100"></div>

          <div class="space-y-4">
            <div v-for="(log, i) in logList" :key="log.id" class="relative flex gap-4">
              <!-- Icon dot -->
              <div class="relative z-10 flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center border-2 border-white shadow-sm"
                   :class="dotClass(log.outcome_color)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="contactIcon(log.contact_method)"/>
                </svg>
              </div>

              <!-- Content -->
              <div class="flex-1 lendr-card p-4 mb-0 border border-neutral-100">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">{{ log.contact_label }}</span>
                    <span :class="outcomeBadge(log.outcome_color)" class="text-xs font-semibold px-2 py-0.5 rounded-full">
                      {{ log.outcome_label }}
                    </span>
                  </div>
                  <div class="text-right">
                    <p class="text-xs text-neutral-400">{{ log.created_at }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">by <span class="font-medium">{{ log.officer_name }}</span></p>
                  </div>
                </div>

                <!-- Amounts -->
                <div v-if="log.amount_promised || log.amount_collected" class="flex gap-4 mb-2">
                  <div v-if="log.amount_promised" class="text-xs text-neutral-600">
                    Promised: <span class="font-semibold text-amber-700">{{ log.amount_promised }}</span>
                  </div>
                  <div v-if="log.amount_collected" class="text-xs text-neutral-600">
                    Collected: <span class="font-semibold text-emerald-700">{{ log.amount_collected }}</span>
                  </div>
                </div>

                <!-- Notes -->
                <p v-if="log.notes" class="text-sm text-neutral-700 leading-relaxed">{{ log.notes }}</p>

                <!-- Follow-up -->
                <div v-if="log.follow_up_date" class="mt-2 flex items-center gap-1.5 text-xs text-neutral-500">
                  <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                  Follow up:
                  <span :class="isToday(log.follow_up_date) ? 'text-red-600 font-semibold' : 'text-neutral-700'">
                    {{ log.follow_up_date }}{{ isToday(log.follow_up_date) ? ' (Today!)' : '' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Log Activity Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="p-6 border-b border-neutral-100">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-neutral-900">Log Collection Activity</h3>
              <button @click="closeModal" class="text-neutral-400 hover:text-neutral-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <!-- Quick info strip -->
            <div class="mt-3 flex flex-wrap gap-3 text-xs">
              <span class="px-2 py-1 bg-neutral-100 rounded-lg font-medium text-neutral-700">{{ loan.borrower.name }}</span>
              <span class="px-2 py-1 bg-neutral-100 rounded-lg font-medium text-neutral-700">{{ loan.loan_number }}</span>
              <span class="px-2 py-1 bg-red-50 rounded-lg font-semibold text-red-700">{{ loan.max_days_overdue }}d overdue</span>
            </div>
          </div>

          <form @submit.prevent="submitLog" class="p-6 space-y-4">

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Contact Method <span class="text-red-500">*</span></label>
                <select v-model="form.contact_method" required class="lendr-input w-full">
                  <option value="">Select method</option>
                  <option value="call">Phone Call</option>
                  <option value="sms">SMS</option>
                  <option value="whatsapp">WhatsApp</option>
                  <option value="visit">Field Visit</option>
                  <option value="email">Email</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Outcome <span class="text-red-500">*</span></label>
                <select v-model="form.outcome" required class="lendr-input w-full">
                  <option value="">Select outcome</option>
                  <option value="reached">Reached</option>
                  <option value="no_answer">No Answer</option>
                  <option value="invalid_number">Invalid Number</option>
                  <option value="promised_payment">Promised Payment</option>
                  <option value="rescheduled">Rescheduled</option>
                  <option value="partial_payment">Partial Payment</option>
                  <option value="paid_up">Paid Up</option>
                  <option value="refused">Refused to Pay</option>
                </select>
              </div>
            </div>

            <!-- Conditional amounts -->
            <div v-if="['promised_payment','rescheduled'].includes(form.outcome)" class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-sm font-medium text-neutral-700 mb-1">Amount Promised</label>
                <input v-model="form.amount_promised" type="number" step="0.01" min="0.01"
                       class="lendr-input w-full" placeholder="0.00"/>
              </div>
            </div>
            <div v-if="['partial_payment','paid_up'].includes(form.outcome)" class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-sm font-medium text-neutral-700 mb-1">Amount Collected</label>
                <input v-model="form.amount_collected" type="number" step="0.01" min="0.01"
                       class="lendr-input w-full" placeholder="0.00"/>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Follow-up Date</label>
              <input v-model="form.follow_up_date" type="date" class="lendr-input w-full"/>
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Notes</label>
              <textarea v-model="form.notes" rows="3" class="lendr-input w-full resize-none"
                        placeholder="Add notes about the interaction…"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="closeModal" class="btn-secondary">Cancel</button>
              <button type="submit" :disabled="submitting" class="btn-primary disabled:opacity-50 flex items-center gap-2">
                <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ submitting ? 'Saving…' : 'Save Activity' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  loan: Object,
  logs: Array,
  officers: Array,
})

const logList   = ref([...props.logs])
const showModal = ref(false)
const submitting = ref(false)

const form = reactive({
  contact_method:   '',
  outcome:          '',
  notes:            '',
  follow_up_date:   '',
  amount_promised:  '',
  amount_collected: '',
})

function openModal() {
  Object.assign(form, {
    contact_method: '', outcome: '', notes: '',
    follow_up_date: '', amount_promised: '', amount_collected: '',
  })
  showModal.value = true
}
function closeModal() { showModal.value = false }

async function submitLog() {
  submitting.value = true
  try {
    const payload = { ...form }
    if (!['promised_payment','rescheduled'].includes(form.outcome)) delete payload.amount_promised
    if (!['partial_payment','paid_up'].includes(form.outcome))      delete payload.amount_collected
    if (!form.amount_promised)  delete payload.amount_promised
    if (!form.amount_collected) delete payload.amount_collected
    if (!form.follow_up_date)   delete payload.follow_up_date
    if (!form.notes)            delete payload.notes

    const { data } = await axios.post(route('collections.logs.store', props.loan.id), payload)
    logList.value.unshift(data.log)
    closeModal()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to save activity.')
  } finally {
    submitting.value = false
  }
}

// ── Helpers ────────────────────────────────────────────────────────────

function parBadgeClass(days) {
  if (days > 90)  return 'bg-red-100 text-red-700'
  if (days > 60)  return 'bg-orange-100 text-orange-700'
  if (days > 30)  return 'bg-amber-100 text-amber-700'
  return 'bg-yellow-100 text-yellow-700'
}

function dotClass(color) {
  const map = {
    emerald: 'bg-emerald-100 text-emerald-600',
    amber:   'bg-amber-100 text-amber-600',
    blue:    'bg-blue-100 text-blue-600',
    red:     'bg-red-100 text-red-600',
    neutral: 'bg-neutral-100 text-neutral-500',
  }
  return map[color] ?? map.neutral
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

function contactIcon(method) {
  const icons = {
    call:     'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
    sms:      'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
    whatsapp: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
    visit:    'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z',
    email:    'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  }
  return icons[method] ?? icons.call
}

function isToday(dateStr) {
  if (!dateStr) return false
  const today = new Date()
  const parts  = dateStr.split(' ')
  const months = { Jan:0, Feb:1, Mar:2, Apr:3, May:4, Jun:5, Jul:6, Aug:7, Sep:8, Oct:9, Nov:10, Dec:11 }
  if (parts.length === 3) {
    return parseInt(parts[0]) === today.getDate()
        && months[parts[1]] === today.getMonth()
        && parseInt(parts[2]) === today.getFullYear()
  }
  return false
}
</script>
