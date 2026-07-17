<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Public Loan Products</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ products.total.toLocaleString() }} product{{ products.total !== 1 ? 's' : '' }} published to the marketplace</p>
        </div>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <input
        v-model="search"
        type="search"
        placeholder="Search by product or lender name…"
        class="input flex-1"
        @input="debouncedSearch"
      />
      <select v-model="statusFilter" @change="applyFilters" class="input w-full sm:w-40">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Product</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden md:table-cell">Lender</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Amount Range</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Rate</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Applications</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="product in products.data" :key="product.id" class="hover:bg-neutral-50 transition">
              <td class="px-5 py-3.5">
                <p class="font-medium text-neutral-900 truncate max-w-xs">{{ product.product_name }}</p>
                <p class="text-xs text-neutral-400 capitalize">{{ product.repayment_schedule }} · published {{ product.created_at }}</p>
              </td>
              <td class="px-5 py-3.5 hidden md:table-cell">
                <p class="text-neutral-700 font-medium">{{ product.tenant_name }}</p>
                <p class="text-xs text-neutral-400">{{ product.tenant_city || '—' }}</p>
              </td>
              <td class="px-5 py-3.5 hidden lg:table-cell">
                <p class="text-neutral-700">{{ formatAmount(product.min_amount) }} – {{ formatAmount(product.max_amount) }}</p>
                <p class="text-xs text-neutral-400 capitalize">{{ product.interest_type }}</p>
              </td>
              <td class="px-5 py-3.5 hidden lg:table-cell">
                <span class="font-medium text-neutral-800">{{ product.interest_rate }}%</span>
              </td>
              <td class="px-5 py-3.5">
                <span class="font-medium text-neutral-800">{{ product.applications_count.toLocaleString() }}</span>
              </td>
              <td class="px-5 py-3.5">
                <span :class="product.is_active ? 'lendr-badge-success' : 'lendr-badge-neutral'">
                  {{ product.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <button
                  v-if="product.is_active"
                  @click="unpublish(product)"
                  class="text-xs text-red-500 hover:text-red-700 font-medium"
                >
                  Unpublish
                </button>
                <span v-else class="text-xs text-neutral-400">—</span>
              </td>
            </tr>
            <tr v-if="!products.data.length">
              <td colspan="7" class="px-5 py-12 text-center text-neutral-400">No public products found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="products.last_page > 1" class="px-5 py-3 border-t border-neutral-100 flex items-center justify-between text-sm">
        <p class="text-neutral-500">Showing {{ products.from }}–{{ products.to }} of {{ products.total }}</p>
        <div class="flex gap-1">
          <Link
            v-for="link in products.links"
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
  products: Object,
  filters:  Object,
})

const search       = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 400)
}

function applyFilters() {
  router.get(route('marketplace.products'), {
    search: search.value  || undefined,
    status: statusFilter.value || undefined,
  }, { preserveState: true, replace: true })
}

function formatAmount(val) {
  return Number(val).toLocaleString()
}

function unpublish(product) {
  if (confirm(`Remove "${product.product_name}" from the marketplace?`)) {
    router.post(route('marketplace.products.unpublish', product.id), {}, { preserveScroll: true })
  }
}
</script>
