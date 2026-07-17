<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Borrowers</h1>
          <p class="text-sm text-neutral-500 mt-0.5">
            {{ borrowers.total.toLocaleString() }} total borrowers
          </p>
        </div>
        <Link
          v-if="can('borrowers.create')"
          :href="route('borrowers.create')"
          class="btn-primary"
        >
          + Add Borrower
        </Link>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <div class="flex-1">
        <input
          v-model="search"
          type="search"
          placeholder="Search by name, phone, ID number…"
          class="input w-full"
          @input="debouncedSearch"
        />
      </div>
      <select v-model="status" @change="applyFilters" class="input w-full sm:w-44">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="blacklisted">Blacklisted</option>
        <option value="kyc_verified">KYC Verified</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Borrower</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden md:table-cell">Phone</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">City</th>
              <th class="text-center px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Loans</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="b in borrowers.data" :key="b.id" class="hover:bg-neutral-50 transition">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-1.5">
                  <div>
                    <div class="flex items-center gap-1">
                      <p class="font-medium text-neutral-900">{{ b.full_name }}</p>
                      <!-- Borrower verification tier badge -->
                      <span v-if="b.verification_tier" :title="tierLabel(b.verification_tier)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" :class="['w-3.5 h-3.5', tierColor(b.verification_tier)]">
                          <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.491 4.491 0 01-3.497-1.307 4.491 4.491 0 01-1.307-3.497A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                      </span>
                    </div>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ b.borrower_number }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5 text-neutral-700 hidden md:table-cell">{{ b.phone }}</td>
              <td class="px-5 py-3.5 text-neutral-700 hidden lg:table-cell">{{ b.city || '—' }}</td>
              <td class="px-5 py-3.5 text-center hidden lg:table-cell">
                <span class="text-neutral-900 font-medium">{{ b.active_loans_count }}</span>
                <span class="text-neutral-400"> / {{ b.loans_count }}</span>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex flex-wrap gap-1">
                  <span v-if="b.is_blacklisted" class="lendr-badge-danger">Blacklisted</span>
                  <span v-else-if="!b.is_active" class="lendr-badge-neutral">Inactive</span>
                  <span v-else class="lendr-badge-success">Active</span>
                  <span v-if="b.kyc_verified" class="lendr-badge-info">KYC ✓</span>
                </div>
              </td>
              <td class="px-5 py-3.5 text-right">
                <Link
                  :href="route('borrowers.show', b.id)"
                  class="text-primary-600 hover:text-primary-800 font-medium text-xs"
                >
                  View
                </Link>
              </td>
            </tr>
            <tr v-if="!borrowers.data.length">
              <td colspan="6" class="px-5 py-12 text-center text-neutral-400">
                No borrowers found. Try adjusting your search.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="borrowers.last_page > 1" class="px-5 py-3 border-t border-neutral-100 flex items-center justify-between text-sm">
        <p class="text-neutral-500">
          Showing {{ borrowers.from }}–{{ borrowers.to }} of {{ borrowers.total }}
        </p>
        <div class="flex gap-1">
          <Link
            v-for="link in borrowers.links"
            :key="link.label"
            :href="link.url || '#'"
            class="px-3 py-1 rounded text-sm"
            :class="link.active
              ? 'bg-primary-600 text-white'
              : link.url ? 'text-neutral-600 hover:bg-neutral-100' : 'text-neutral-300 cursor-default'"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  borrowers: Object,
  filters: Object,
})

const page = usePage()
const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')

function can(permission) {
  const user = page.props.auth?.user
  if (!user) return false
  // super_admin gets everything; fall back gracefully if permissions not yet loaded
  const role = user.role?.value ?? user.role
  if (role === 'super_admin') return true
  const perms = user.permissions
  if (!perms || perms.length === 0) return true // no restrictions configured → allow
  return perms.includes(permission)
}

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 400)
}

function applyFilters() {
  router.get(route('borrowers.index'), {
    search: search.value || undefined,
    status: status.value || undefined,
  }, { preserveState: true, replace: true })
}

function tierColor(tier) {
  return { blue: 'text-blue-500', yellow: 'text-amber-400', grey: 'text-gray-400' }[tier] ?? 'text-gray-300'
}

function tierLabel(tier) {
  return { blue: 'Blue Verified', yellow: 'Yellow Verified', grey: 'Grey Verified' }[tier] ?? ''
}
</script>
