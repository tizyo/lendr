<template>
  <AppLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">Audit Log</h1>
        <p class="text-sm text-neutral-500 mt-0.5">{{ logs.total.toLocaleString() }} total entries</p>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3 flex-wrap">
      <select v-model="filters.subject_type" class="input sm:w-44" @change="applyFilters">
        <option value="">All subjects</option>
        <option value="Loan">Loan</option>
        <option value="Borrower">Borrower</option>
        <option value="Payment">Payment</option>
        <option value="Expense">Expense</option>
        <option value="FundDeposit">Fund Deposit</option>
        <option value="User">User</option>
      </select>
      <input v-model="filters.date_from" type="date" class="input sm:w-36" @change="applyFilters" />
      <input v-model="filters.date_to"   type="date" class="input sm:w-36" @change="applyFilters" />
      <button v-if="hasFilters" @click="clearFilters" class="btn-secondary text-sm">Clear</button>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 border-b border-neutral-200">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Time</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Event</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Subject</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Performed By</th>
            <th class="px-4 py-3 text-left font-medium text-neutral-500">Changes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="!logs.data.length">
            <td colspan="5" class="px-4 py-10 text-center text-neutral-400">No log entries found.</td>
          </tr>
          <tr v-for="log in logs.data" :key="log.id" class="hover:bg-neutral-50 align-top">
            <td class="px-4 py-3 text-neutral-500 whitespace-nowrap text-xs">{{ formatTime(log.created_at) }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded text-xs font-medium" :class="eventClass(log.description)">
                {{ log.description }}
              </span>
            </td>
            <td class="px-4 py-3 text-neutral-700">
              <span v-if="log.subject_type">{{ log.subject_type }} <span class="text-neutral-400 text-xs">#{{ log.subject_id }}</span></span>
              <span v-else class="text-neutral-400">—</span>
            </td>
            <td class="px-4 py-3 text-neutral-700">{{ log.causer }}</td>
            <td class="px-4 py-3 max-w-xs">
              <details v-if="hasChanges(log.properties)" class="text-xs text-neutral-500">
                <summary class="cursor-pointer hover:text-neutral-800">View changes</summary>
                <pre class="mt-1 p-2 bg-neutral-50 rounded text-[11px] overflow-auto max-h-40 whitespace-pre-wrap">{{ formatProperties(log.properties) }}</pre>
              </details>
              <span v-else class="text-neutral-400 text-xs">—</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="logs.last_page > 1" class="px-4 py-3 border-t border-neutral-200 flex justify-between items-center text-sm text-neutral-600">
        <span>Page {{ logs.current_page }} of {{ logs.last_page }}</span>
        <div class="flex gap-2">
          <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="btn-secondary btn-sm">← Prev</Link>
          <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="btn-secondary btn-sm">Next →</Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  logs:    { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const filters = reactive({ ...props.filters })

const hasFilters = computed(() =>
  Object.values(filters).some(v => v !== '' && v != null)
)

function applyFilters() {
  router.get(route('audit-log.index'), filters, { preserveState: true, replace: true })
}

function clearFilters() {
  Object.assign(filters, { subject_type: '', date_from: '', date_to: '' })
  applyFilters()
}

function eventClass(desc) {
  if (!desc) return 'bg-neutral-100 text-neutral-600'
  if (desc.includes('created')) return 'bg-blue-100 text-blue-700'
  if (desc.includes('updated')) return 'bg-amber-100 text-amber-700'
  if (desc.includes('deleted')) return 'bg-red-100 text-red-700'
  if (desc.includes('approved')) return 'bg-green-100 text-green-700'
  if (desc.includes('rejected')) return 'bg-red-100 text-red-700'
  return 'bg-neutral-100 text-neutral-600'
}

function hasChanges(properties) {
  return properties && (properties.attributes || properties.old)
}

function formatProperties(properties) {
  if (!properties) return ''
  const lines = []
  if (properties.old) {
    Object.entries(properties.old).forEach(([k, v]) => {
      const newVal = properties.attributes?.[k]
      if (newVal !== v) lines.push(`${k}: ${v} → ${newVal}`)
    })
  }
  return lines.length ? lines.join('\n') : JSON.stringify(properties, null, 2)
}

function formatTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' })
}
</script>
