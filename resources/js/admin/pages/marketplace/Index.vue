<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Marketplace</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ listings.total.toLocaleString() }} listing{{ listings.total !== 1 ? 's' : '' }}</p>
        </div>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <input
        v-model="search"
        type="search"
        placeholder="Search by title or borrower…"
        class="input flex-1"
        @input="debouncedSearch"
      />
      <select v-model="statusFilter" @change="applyFilters" class="input w-full sm:w-40">
        <option value="">All statuses</option>
        <option value="draft">Draft</option>
        <option value="active">Active</option>
        <option value="funded">Funded</option>
        <option value="expired">Expired</option>
        <option value="withdrawn">Withdrawn</option>
      </select>
      <select v-model="purposeFilter" @change="applyFilters" class="input w-full sm:w-40">
        <option value="">All purposes</option>
        <option value="business">Business</option>
        <option value="education">Education</option>
        <option value="medical">Medical</option>
        <option value="personal">Personal</option>
        <option value="agriculture">Agriculture</option>
        <option value="other">Other</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Listing</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden md:table-cell">Borrower</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Amount</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Tenure</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Interests</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="listing in listings.data" :key="listing.id" class="hover:bg-neutral-50 transition">
              <td class="px-5 py-3.5">
                <p class="font-medium text-neutral-900 truncate max-w-xs">{{ listing.title }}</p>
                <p class="text-xs text-neutral-400 capitalize">{{ listing.purpose }} · {{ listing.created_at }}</p>
              </td>
              <td class="px-5 py-3.5 hidden md:table-cell">
                <p class="text-neutral-700 font-medium">{{ listing.borrower?.name || '—' }}</p>
                <p class="text-xs text-neutral-400 font-mono">{{ listing.borrower?.borrower_number }}</p>
              </td>
              <td class="px-5 py-3.5 hidden lg:table-cell">
                <p class="text-neutral-700 font-medium">ZMW {{ listing.amount_requested.toLocaleString() }}</p>
                <p class="text-xs text-neutral-400">{{ listing.interest_rate_offered }}% offered</p>
              </td>
              <td class="px-5 py-3.5 hidden lg:table-cell text-neutral-600">
                {{ listing.tenure_months }} mo
              </td>
              <td class="px-5 py-3.5">
                <Link
                  :href="route('marketplace.interests', listing.id)"
                  class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-800 font-medium text-xs"
                >
                  {{ listing.interests_count }}
                  <span class="hidden sm:inline">interest{{ listing.interests_count !== 1 ? 's' : '' }}</span>
                </Link>
              </td>
              <td class="px-5 py-3.5">
                <span :class="statusBadge(listing.status)">{{ listing.status }}</span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <div class="flex items-center justify-end gap-2">
                  <Link
                    :href="route('marketplace.interests', listing.id)"
                    class="text-xs text-neutral-500 hover:text-neutral-700 font-medium"
                  >
                    View
                  </Link>
                  <button
                    v-if="['active', 'draft'].includes(listing.status)"
                    @click="expireListing(listing)"
                    class="text-xs text-red-500 hover:text-red-700 font-medium"
                  >
                    Expire
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!listings.data.length">
              <td colspan="7" class="px-5 py-12 text-center text-neutral-400">No marketplace listings found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="listings.last_page > 1" class="px-5 py-3 border-t border-neutral-100 flex items-center justify-between text-sm">
        <p class="text-neutral-500">Showing {{ listings.from }}–{{ listings.to }} of {{ listings.total }}</p>
        <div class="flex gap-1">
          <Link
            v-for="link in listings.links"
            :key="link.label"
            :href="link.url || '#'"
            class="px-3 py-1 rounded text-sm"
            :class="link.active ? 'bg-primary-600 text-white' : link.url ? 'text-neutral-600 hover:bg-neutral-100' : 'text-neutral-300 cursor-default'"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  listings: Object,
  filters:  Object,
})

const search        = ref(props.filters?.search || '')
const statusFilter  = ref(props.filters?.status || '')
const purposeFilter = ref(props.filters?.purpose || '')

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 400)
}

function applyFilters() {
  router.get(route('marketplace.index'), {
    search:  search.value  || undefined,
    status:  statusFilter.value  || undefined,
    purpose: purposeFilter.value || undefined,
  }, { preserveState: true, replace: true })
}

function statusBadge(status) {
  return {
    active:    'lendr-badge-success',
    funded:    'lendr-badge-info',
    draft:     'lendr-badge-neutral',
    expired:   'lendr-badge-warning',
    withdrawn: 'lendr-badge-neutral',
  }[status] ?? 'lendr-badge-neutral'
}

function expireListing(listing) {
  if (confirm(`Mark listing "${listing.title}" as expired?`)) {
    router.post(route('marketplace.expire', listing.id), {}, { preserveScroll: true })
  }
}
</script>
