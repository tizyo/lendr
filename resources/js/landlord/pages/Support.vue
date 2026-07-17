<template>
  <LandlordLayout title="Support Tickets">
    <div class="space-y-6">

      <!-- Stats row -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div v-for="s in statusSummary" :key="s.label" class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">{{ s.label }}</p>
          <p class="text-2xl font-bold" :class="s.color">{{ s.count }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-neutral-200 p-4 flex flex-wrap gap-3 items-center">
        <input v-model="filters.search" @input="load" type="text" placeholder="Search subject…" class="input w-56 text-sm" />
        <select v-model="filters.status" @change="load" class="input text-sm">
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        <select v-model="filters.type" @change="load" class="input text-sm">
          <option value="">All Types</option>
          <option value="support">Support</option>
          <option value="bug">Bug</option>
          <option value="feature">Feature Request</option>
        </select>
        <select v-model="filters.priority" @change="load" class="input text-sm">
          <option value="">All Priorities</option>
          <option value="critical">Critical</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
        <button @click="resetFilters" class="text-sm text-neutral-500 hover:text-neutral-800">Reset</button>
      </div>

      <!-- Ticket list / detail split -->
      <div class="flex gap-6 min-h-[500px]">

        <!-- List -->
        <div class="w-full lg:w-2/5 bg-white rounded-xl border border-neutral-200 divide-y divide-neutral-100 overflow-auto max-h-[70vh]">
          <div v-if="loading" class="flex justify-center py-12">
            <div class="w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
          </div>
          <div v-else-if="!tickets.length" class="py-12 text-center text-sm text-neutral-400">No tickets found.</div>
          <div
            v-else
            v-for="t in tickets"
            :key="t.id"
            @click="selectTicket(t.id)"
            class="px-4 py-3 cursor-pointer hover:bg-neutral-50 transition-colors"
            :class="{ 'bg-primary-50 border-l-2 border-primary-500': selected?.id === t.id }"
          >
            <div class="flex items-start gap-2">
              <span class="text-base mt-0.5 flex-shrink-0">{{ typeEmoji(t.type) }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-neutral-900 truncate">{{ t.subject }}</p>
                <p class="text-xs text-neutral-500 truncate">{{ t.tenant_name }} · {{ t.created_at }}</p>
              </div>
              <div class="flex flex-col items-end gap-1 flex-shrink-0">
                <span class="px-1.5 py-0.5 rounded text-xs font-medium" :class="statusBadge(t.status)">{{ statusLabel(t.status) }}</span>
                <span class="px-1.5 py-0.5 rounded text-xs" :class="priorityBadge(t.priority)">{{ t.priority }}</span>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 py-3 border-t border-neutral-100">
            <button
              v-for="p in pagination.last_page" :key="p"
              @click="page = p; load()"
              class="w-7 h-7 text-xs rounded"
              :class="p === pagination.current_page ? 'bg-primary-500 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'"
            >{{ p }}</button>
          </div>
        </div>

        <!-- Detail panel -->
        <div class="flex-1 bg-white rounded-xl border border-neutral-200 flex flex-col">
          <div v-if="!selected" class="flex-1 flex items-center justify-center text-neutral-400 text-sm">
            Select a ticket to view details.
          </div>
          <div v-else class="flex flex-col h-full">

            <!-- Ticket header -->
            <div class="p-5 border-b border-neutral-100">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <h2 class="font-bold text-neutral-900">{{ selected.subject }}</h2>
                  <p class="text-xs text-neutral-500 mt-1">
                    {{ selected.tenant_name }} ·
                    {{ selected.submitted_by ?? 'Unknown' }}
                    <span v-if="selected.submitted_by_email"> ({{ selected.submitted_by_email }})</span>
                    · {{ selected.created_at }}
                  </p>
                </div>
                <!-- Status / priority controls -->
                <div class="flex gap-2 flex-shrink-0">
                  <select v-model="editPriority" @change="updatePriority" class="input text-xs py-1">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                  </select>
                  <select v-model="editStatus" @change="updateStatus" class="input text-xs py-1">
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                  </select>
                </div>
              </div>

              <!-- Type badge -->
              <div class="mt-2 flex gap-2">
                <span class="px-2 py-0.5 rounded-full text-xs bg-neutral-100 text-neutral-600">{{ typeBadge(selected.type) }}</span>
              </div>
            </div>

            <!-- Scrollable thread -->
            <div class="flex-1 overflow-y-auto p-5 space-y-4" ref="threadEl">
              <!-- Original message -->
              <div class="bg-neutral-50 rounded-lg p-4 border border-neutral-100">
                <p class="text-xs font-semibold text-neutral-500 mb-2">Original Message</p>
                <p class="text-sm text-neutral-700 whitespace-pre-wrap leading-relaxed">{{ selected.message }}</p>
              </div>

              <!-- Replies -->
              <div
                v-for="r in selected.replies"
                :key="r.id"
                class="rounded-lg p-4 border"
                :class="r.author_type === 'landlord'
                  ? 'bg-primary-50 border-primary-200'
                  : 'bg-white border-neutral-200'"
              >
                <div class="flex justify-between items-center mb-1">
                  <span class="text-xs font-semibold" :class="r.author_type === 'landlord' ? 'text-primary-700' : 'text-neutral-700'">
                    {{ r.author_type === 'landlord' ? '🛡 LENDR Support' : '👤 ' + (r.author_name ?? 'Tenant') }}
                  </span>
                  <span class="text-xs text-neutral-400">{{ r.created_at }}</span>
                </div>
                <p class="text-sm text-neutral-700 whitespace-pre-wrap leading-relaxed">{{ r.message }}</p>
              </div>
            </div>

            <!-- Reply input -->
            <div v-if="selected.status !== 'closed'" class="p-4 border-t border-neutral-100 space-y-2">
              <textarea
                v-model="replyMessage"
                rows="3"
                class="input w-full text-sm"
                placeholder="Type your reply…"
              ></textarea>
              <div class="flex justify-end">
                <button @click="sendReply" :disabled="!replyMessage.trim() || sending" class="btn-primary text-sm px-4 py-1.5">
                  {{ sending ? 'Sending…' : 'Send Reply' }}
                </button>
              </div>
            </div>
            <div v-else class="p-4 border-t border-neutral-100 text-center text-xs text-neutral-400">
              Ticket is closed.
            </div>
          </div>
        </div>
      </div>

    </div>
  </LandlordLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import axios from 'axios'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'

const tickets    = ref([])
const selected   = ref(null)
const loading    = ref(false)
const sending    = ref(false)
const replyMessage = ref('')
const threadEl   = ref(null)
const page       = ref(1)
const pagination = ref({ current_page: 1, last_page: 1 })
const stats      = ref({ total: 0, open: 0, by_status: {}, by_type: {} })
const editStatus   = ref('')
const editPriority = ref('')

const filters = ref({ search: '', status: '', type: '', priority: '' })

// ─── Stats summary row ────────────────────────────────────────────────────────

const statusSummary = computed(() => [
  { label: 'Total',       count: stats.value.total,                       color: 'text-neutral-900' },
  { label: 'Open',        count: stats.value.by_status?.open ?? 0,        color: 'text-blue-700' },
  { label: 'In Progress', count: stats.value.by_status?.in_progress ?? 0, color: 'text-amber-600' },
  { label: 'Resolved',    count: stats.value.by_status?.resolved ?? 0,    color: 'text-emerald-700' },
  { label: 'Closed',      count: stats.value.by_status?.closed ?? 0,      color: 'text-neutral-500' },
])

// ─── Data loading ─────────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, ...filters.value }
    const { data } = await axios.get('/api/v1/landlord/support', { params })
    tickets.value  = data.data?.data ?? []
    pagination.value = { current_page: data.data?.current_page ?? 1, last_page: data.data?.last_page ?? 1 }
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  const { data } = await axios.get('/api/v1/landlord/support/stats')
  stats.value = data.data ?? {}
}

async function selectTicket(id) {
  const { data } = await axios.get(`/api/v1/landlord/support/${id}`)
  selected.value  = data.data
  editStatus.value   = selected.value.status
  editPriority.value = selected.value.priority
  await nextTick()
  if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight
}

function resetFilters() {
  filters.value = { search: '', status: '', type: '', priority: '' }
  page.value = 1
  load()
}

// ─── Actions ─────────────────────────────────────────────────────────────────

async function sendReply() {
  if (!replyMessage.value.trim() || !selected.value) return
  sending.value = true
  try {
    const { data } = await axios.post(`/api/v1/landlord/support/${selected.value.id}/reply`, {
      message: replyMessage.value,
    })
    selected.value   = data.data
    editStatus.value = selected.value.status
    replyMessage.value = ''
    await nextTick()
    if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight
    loadStats()
  } finally {
    sending.value = false
  }
}

async function updateStatus() {
  const { data } = await axios.patch(`/api/v1/landlord/support/${selected.value.id}/status`, {
    status: editStatus.value,
  })
  selected.value = { ...selected.value, ...data.data }
  // Refresh list & stats
  load()
  loadStats()
}

async function updatePriority() {
  const { data } = await axios.patch(`/api/v1/landlord/support/${selected.value.id}/priority`, {
    priority: editPriority.value,
  })
  selected.value = { ...selected.value, ...data.data }
  load()
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function typeEmoji(t) {
  return { bug: '🐛', feature: '💡', support: '🎧' }[t] ?? '🎧'
}

function typeBadge(t) {
  return { bug: 'Bug', feature: 'Feature Request', support: 'Support' }[t] ?? 'Support'
}

function statusLabel(s) {
  return { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', closed: 'Closed' }[s] ?? s
}

function statusBadge(s) {
  return {
    open:        'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    resolved:    'bg-emerald-100 text-emerald-700',
    closed:      'bg-neutral-100 text-neutral-500',
  }[s] ?? 'bg-neutral-100 text-neutral-500'
}

function priorityBadge(p) {
  return {
    low:      'bg-neutral-100 text-neutral-500',
    medium:   'bg-blue-100 text-blue-600',
    high:     'bg-orange-100 text-orange-700',
    critical: 'bg-red-100 text-red-700',
  }[p] ?? 'bg-neutral-100 text-neutral-500'
}

onMounted(() => {
  load()
  loadStats()
})
</script>
