<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Loans</h1>
          <p class="text-sm text-neutral-500 mt-0.5">
            {{ loans.total.toLocaleString() }} total loans
          </p>
        </div>
        <Link
          v-if="can('loans.create')"
          :href="route('loans.create')"
          class="btn-primary"
        >
          + New Loan
        </Link>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <div class="flex-1">
        <input
          v-model="filters.search"
          type="search"
          placeholder="Search by loan #, borrower name, phone…"
          class="input w-full"
          @input="debouncedSearch"
        />
      </div>
      <select v-model="filters.status" @change="applyFilters" class="input w-full sm:w-44">
        <option value="">All statuses</option>
        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <select v-model="filters.loan_type_id" @change="applyFilters" class="input w-full sm:w-48">
        <option value="">All types</option>
        <option v-for="t in loanTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Loan</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden md:table-cell">Borrower</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Type / Plan</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Principal</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Outstanding</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden xl:table-cell">Maturity</th>
              <th class="text-right px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="l in loans.data" :key="l.id" class="hover:bg-neutral-50 transition">
              <td class="px-5 py-3.5">
                <p class="font-medium text-neutral-900 font-mono text-xs">{{ l.loan_number }}</p>
                <p class="text-xs text-neutral-500 mt-0.5">{{ l.application_date }}</p>
              </td>
              <td class="px-5 py-3.5 hidden md:table-cell">
                <p class="font-medium text-neutral-800">{{ l.borrower_name }}</p>
                <p class="text-xs text-neutral-500">{{ l.borrower_number }}</p>
              </td>
              <td class="px-5 py-3.5 hidden lg:table-cell">
                <p class="text-neutral-800">{{ l.loan_type }}</p>
                <p class="text-xs text-neutral-500">{{ l.loan_plan }}</p>
              </td>
              <td class="px-5 py-3.5 text-right font-medium text-neutral-900">
                K {{ l.principal_amount }}
              </td>
              <td class="px-5 py-3.5 text-right hidden lg:table-cell">
                <span :class="parseFloat(l.outstanding_balance.replace(/,/g,'')) > 0 ? 'text-amber-700' : 'text-green-700'" class="font-medium">
                  K {{ l.outstanding_balance }}
                </span>
              </td>
              <td class="px-5 py-3.5">
                <LoanStatusBadge :status="l.status" :label="l.status_label" />
              </td>
              <td class="px-5 py-3.5 text-neutral-500 hidden xl:table-cell">{{ l.maturity_date || '—' }}</td>
              <td class="px-5 py-3.5 text-right">
                <Link :href="route('loans.show', l.id)" class="text-primary-600 hover:text-primary-800 font-medium text-xs">
                  View
                </Link>
              </td>
            </tr>
            <tr v-if="!loans.data.length">
              <td colspan="8" class="px-5 py-12 text-center text-neutral-400">
                No loans found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="loans.last_page > 1" class="px-5 py-3 border-t border-neutral-100 flex items-center justify-between text-sm">
        <p class="text-neutral-500">
          Showing {{ loans.from }}–{{ loans.to }} of {{ loans.total }}
        </p>
        <div class="flex gap-1">
          <Link
            v-for="link in loans.links"
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
import { ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import LoanStatusBadge from '@/admin/components/loans/LoanStatusBadge.vue'

const props = defineProps({
  loans: Object,
  filters: Object,
  loanTypes: Array,
  statuses: Array,
})

const page = usePage()

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
  loan_type_id: props.filters?.loan_type_id || '',
})

function can(permission) {
  const user = page.props.auth?.user
  if (!user) return false
  const role = user.role?.value ?? user.role
  if (role === 'super_admin') return true
  const perms = user.permissions
  if (!perms || perms.length === 0) return true
  return perms.includes(permission)
}

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 400)
}

function applyFilters() {
  router.get(route('loans.index'), {
    search: filters.value.search || undefined,
    status: filters.value.status || undefined,
    loan_type_id: filters.value.loan_type_id || undefined,
  }, { preserveState: true, replace: true })
}
</script>