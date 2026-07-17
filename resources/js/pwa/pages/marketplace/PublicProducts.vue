<template>
  <PwaLayout title="Browse Loan Products">
    <!-- Search & Filter -->
    <div class="px-4 pt-4 pb-2 space-y-2">
      <input
        v-model="search"
        type="search"
        placeholder="Search products, lenders…"
        class="w-full px-4 py-2.5 rounded-xl border border-neutral-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary-400"
        @input="debouncedSearch"
      />
      <div class="flex gap-2 overflow-x-auto pb-1">
        <button
          v-for="amt in amountFilters"
          :key="amt.label"
          @click="setAmountFilter(amt)"
          class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium border transition"
          :class="activeAmountFilter === amt.label
            ? 'bg-primary-600 text-white border-primary-600'
            : 'bg-white text-neutral-600 border-neutral-200'"
        >
          {{ amt.label }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
    </div>

    <!-- Product Cards -->
    <div v-else class="px-4 py-2 space-y-3">
      <div
        v-for="product in products"
        :key="product.id"
        class="bg-white rounded-2xl shadow-sm border border-neutral-100 p-4"
      >
        <div class="flex items-start justify-between gap-2 mb-2">
          <div>
            <h3 class="font-semibold text-neutral-900 text-sm">{{ product.product_name }}</h3>
            <p class="text-xs text-neutral-500">{{ product.tenant_name }}<span v-if="product.tenant_city"> · {{ product.tenant_city }}</span></p>
          </div>
          <span class="flex-shrink-0 text-lg font-bold text-primary-600">{{ product.interest_rate }}%</span>
        </div>

        <p v-if="product.description" class="text-xs text-neutral-500 mb-3 line-clamp-2">{{ product.description }}</p>

        <div class="grid grid-cols-3 gap-2 mb-3 text-center">
          <div class="bg-neutral-50 rounded-lg p-2">
            <p class="text-xs text-neutral-400">Min Amount</p>
            <p class="text-xs font-semibold text-neutral-800">{{ formatAmount(product.min_amount) }}</p>
          </div>
          <div class="bg-neutral-50 rounded-lg p-2">
            <p class="text-xs text-neutral-400">Max Amount</p>
            <p class="text-xs font-semibold text-neutral-800">{{ formatAmount(product.max_amount) }}</p>
          </div>
          <div class="bg-neutral-50 rounded-lg p-2">
            <p class="text-xs text-neutral-400">Tenure</p>
            <p class="text-xs font-semibold text-neutral-800">{{ product.min_tenure }}–{{ product.max_tenure }} {{ product.tenure_type }}</p>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="flex gap-2 text-xs text-neutral-400">
            <span v-if="product.requires_collateral" class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full">Collateral</span>
            <span v-if="product.requires_guarantor" class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">Guarantor</span>
            <span class="capitalize">{{ product.repayment_schedule }}</span>
          </div>
          <button
            @click="expressInterest(product)"
            :disabled="applying === product.id"
            class="px-4 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-lg disabled:opacity-50 transition"
          >
            {{ applying === product.id ? 'Sending…' : 'Apply' }}
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="!products.length" class="text-center py-16">
        <p class="text-neutral-400 text-sm">No products found.</p>
        <button @click="clearSearch" class="mt-2 text-primary-600 text-sm">Clear filters</button>
      </div>

      <!-- Load more -->
      <button
        v-if="nextPageUrl"
        @click="loadMore"
        :disabled="loadingMore"
        class="w-full py-3 text-sm text-primary-600 font-medium"
      >
        {{ loadingMore ? 'Loading…' : 'Load more' }}
      </button>
    </div>

    <!-- Interest Confirmation Modal -->
    <div v-if="confirmedProduct" class="fixed inset-0 bg-black/40 flex items-end z-50" @click.self="confirmedProduct = null">
      <div class="bg-white w-full rounded-t-2xl p-6">
        <h3 class="font-bold text-neutral-900 mb-1">Interest Registered!</h3>
        <p class="text-sm text-neutral-500 mb-4">
          Your interest in <strong>{{ confirmedProduct.product_name }}</strong> by <strong>{{ confirmedProduct.tenant_name }}</strong> has been recorded.
          Contact them directly to proceed with your application.
        </p>
        <button @click="confirmedProduct = null" class="w-full py-3 bg-primary-600 text-white rounded-xl font-medium">
          Got it
        </button>
      </div>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'

const products        = ref([])
const loading         = ref(true)
const loadingMore     = ref(false)
const nextPageUrl     = ref(null)
const search          = ref('')
const applying        = ref(null)
const confirmedProduct = ref(null)
const activeAmountFilter = ref(null)

const amountFilters = [
  { label: 'All',     max: null },
  { label: 'Up to 5K',  max: 5000 },
  { label: 'Up to 20K', max: 20000 },
  { label: 'Up to 50K', max: 50000 },
]

async function fetchProducts(page = 1, append = false) {
  const params = {
    page,
    q: search.value || undefined,
  }
  const active = amountFilters.find(f => f.label === activeAmountFilter.value)
  if (active?.max) params.max_amount = active.max

  const { data } = await axios.get('/api/v1/me/public-products', { params })
  if (append) {
    products.value.push(...data.data)
  } else {
    products.value = data.data
  }
  nextPageUrl.value = data.links?.next ?? null
  loading.value = false
  loadingMore.value = false
}

onMounted(() => fetchProducts())

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loading.value = true
    fetchProducts()
  }, 400)
}

function setAmountFilter(filter) {
  activeAmountFilter.value = filter.label === activeAmountFilter.value ? null : filter.label
  loading.value = true
  fetchProducts()
}

async function loadMore() {
  if (!nextPageUrl.value || loadingMore.value) return
  loadingMore.value = true
  const url = new URL(nextPageUrl.value)
  const page = url.searchParams.get('page')
  await fetchProducts(page, true)
}

async function expressInterest(product) {
  if (applying.value) return
  applying.value = product.id
  try {
    await axios.post(`/api/v1/me/public-products/${product.id}/apply`)
    product.applications_count++
    confirmedProduct.value = product
  } finally {
    applying.value = null
  }
}

function clearSearch() {
  search.value = ''
  activeAmountFilter.value = null
  loading.value = true
  fetchProducts()
}

function formatAmount(val) {
  return Number(val).toLocaleString()
}
</script>
