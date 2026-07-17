<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Loan Insurance</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Manage insurance products, policies, and claims</p>
        </div>
        <button @click="openAddProduct" class="lendr-btn-primary">+ New Product</button>
      </div>
    </template>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-neutral-200 mb-4">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px', activeTab === tab.key ? 'border-primary-500 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700']">
        {{ tab.label }}
      </button>
    </div>

    <!-- Products -->
    <div v-if="activeTab === 'products'">
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingProducts" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Product</th>
              <th class="px-4 py-3 text-left">Code</th>
              <th class="px-4 py-3 text-left">Type</th>
              <th class="px-4 py-3 text-right">Premium Rate</th>
              <th class="px-4 py-3 text-center">Active</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="products.length === 0"><td colspan="6" class="px-4 py-10 text-center text-neutral-400">No insurance products configured.</td></tr>
            <tr v-for="p in products" :key="p.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3">
                <p class="font-medium">{{ p.name }}</p>
                <p class="text-xs text-neutral-500">{{ p.description }}</p>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ p.code }}</td>
              <td class="px-4 py-3 capitalize text-neutral-600">{{ p.coverage_type?.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-right">{{ p.premium_rate }}% ({{ p.premium_type }})</td>
              <td class="px-4 py-3 text-center">
                <span :class="p.is_active ? 'lendr-badge-success' : 'lendr-badge-neutral'" class="lendr-badge text-xs">{{ p.is_active ? 'Active' : 'Off' }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="editProduct(p)" class="text-primary-600 text-xs hover:underline mr-2">Edit</button>
                <button @click="deleteProduct(p)" class="text-red-400 text-xs hover:text-red-600">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Policies -->
    <div v-if="activeTab === 'policies'">
      <div class="flex gap-2 mb-4">
        <input v-model="policySearch" @input="debouncedPolicySearch" class="lendr-input w-64 text-sm" placeholder="Search loan…" />
        <select v-model="policyStatus" @change="fetchPolicies" class="lendr-input text-sm w-32">
          <option value="">All</option>
          <option value="active">Active</option>
          <option value="claimed">Claimed</option>
          <option value="expired">Expired</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingPolicies" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Policy #</th>
              <th class="px-4 py-3 text-left">Loan</th>
              <th class="px-4 py-3 text-left">Product</th>
              <th class="px-4 py-3 text-right">Premium</th>
              <th class="px-4 py-3 text-right">Sum Insured</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="policies.length === 0"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">No policies found.</td></tr>
            <tr v-for="pol in policies" :key="pol.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-mono text-xs">{{ pol.policy_number }}</td>
              <td class="px-4 py-3 font-mono text-xs">{{ pol.loan_id ? `#${pol.loan_id}` : '—' }}</td>
              <td class="px-4 py-3 text-neutral-700">{{ pol.product?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-right">{{ fmt(pol.premium_amount) }}</td>
              <td class="px-4 py-3 text-right">{{ fmt(pol.sum_insured) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="statusClass(pol.status)" class="lendr-badge text-xs capitalize">{{ pol.status }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="viewClaims(pol)" class="text-primary-600 text-xs hover:underline">Claims</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Claims -->
    <div v-if="activeTab === 'claims'">
      <div class="lendr-card overflow-hidden">
        <div v-if="loadingClaims" class="p-10 text-center text-neutral-400">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Claim #</th>
              <th class="px-4 py-3 text-left">Policy</th>
              <th class="px-4 py-3 text-left">Type</th>
              <th class="px-4 py-3 text-right">Amount</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100">
            <tr v-if="claims.length === 0"><td colspan="6" class="px-4 py-10 text-center text-neutral-400">No claims found.</td></tr>
            <tr v-for="c in claims" :key="c.id" class="hover:bg-neutral-50">
              <td class="px-4 py-3 font-mono text-xs">{{ c.claim_number }}</td>
              <td class="px-4 py-3 font-mono text-xs">Policy #{{ c.loan_insurance_id }}</td>
              <td class="px-4 py-3 capitalize text-neutral-600">{{ c.claim_type?.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-right font-medium">{{ fmt(c.claim_amount) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="statusClass(c.status)" class="lendr-badge text-xs capitalize">{{ c.status }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button v-if="c.status === 'pending'" @click="openReview(c, 'approved')" class="text-green-600 text-xs hover:underline mr-2">Approve</button>
                <button v-if="c.status === 'pending'" @click="openReview(c, 'rejected')" class="text-red-500 text-xs hover:underline">Reject</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Product Modal -->
    <div v-if="showProductModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="font-bold text-lg">{{ editingProduct ? 'Edit' : 'Add' }} Insurance Product</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2"><label class="lendr-label">Name *</label><input v-model="productForm.name" class="lendr-input w-full" /></div>
          <div>
            <label class="lendr-label">Code *</label>
            <input v-model="productForm.code" class="lendr-input w-full" placeholder="e.g. CREDIT_LIFE_01" :disabled="!!editingProduct" />
          </div>
          <div>
            <label class="lendr-label">Premium Type *</label>
            <select v-model="productForm.premium_type" class="lendr-input w-full">
              <option value="percentage">Percentage</option>
              <option value="flat">Flat</option>
            </select>
          </div>
          <div>
            <label class="lendr-label">Premium Rate *</label>
            <input v-model.number="productForm.premium_rate" type="number" step="0.01" class="lendr-input w-full" />
          </div>
          <div>
            <label class="lendr-label">Coverage Type *</label>
            <select v-model="productForm.coverage_type" class="lendr-input w-full">
              <option value="credit_life">Credit Life</option>
              <option value="disability">Disability</option>
              <option value="property">Property</option>
              <option value="comprehensive">Comprehensive</option>
            </select>
          </div>
          <div><label class="lendr-label">Max Term (months)</label><input v-model.number="productForm.max_term_months" type="number" min="1" class="lendr-input w-full" /></div>
          <div class="flex items-end pb-1">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="productForm.is_active" type="checkbox" class="rounded" />
              <span class="text-sm text-neutral-700">Active</span>
            </label>
          </div>
          <div class="col-span-2"><label class="lendr-label">Description</label><textarea v-model="productForm.description" class="lendr-input w-full" rows="2"></textarea></div>
          <div class="col-span-2"><label class="lendr-label">Notes</label><textarea v-model="productForm.notes" class="lendr-input w-full" rows="2"></textarea></div>
        </div>
        <p v-if="productError" class="text-red-500 text-sm">{{ productError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showProductModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="saveProduct" :disabled="savingProduct" class="lendr-btn-primary">{{ savingProduct ? 'Saving…' : 'Save' }}</button>
        </div>
      </div>
    </div>

    <!-- Claim Review Modal -->
    <div v-if="reviewModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="font-bold text-lg capitalize">{{ reviewAction }} Claim</h2>
        <div v-if="reviewAction === 'approved'" class="space-y-3">
          <div>
            <label class="lendr-label">Approved Amount</label>
            <input v-model.number="reviewForm.approved_amount" type="number" step="0.01" class="lendr-input w-full" />
          </div>
        </div>
        <div v-if="reviewAction === 'rejected'" class="space-y-3">
          <div>
            <label class="lendr-label">Rejection Reason *</label>
            <textarea v-model="reviewForm.rejection_reason" class="lendr-input w-full" rows="3"></textarea>
          </div>
        </div>
        <div class="flex gap-3 justify-end">
          <button @click="reviewModal = null" class="lendr-btn-ghost">Cancel</button>
          <button @click="submitReview" :disabled="reviewing"
            :class="reviewAction === 'approved' ? 'lendr-btn-primary' : 'bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium'">
            {{ reviewing ? 'Processing…' : reviewAction === 'approved' ? 'Approve' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const activeTab = ref('products')
const tabs = [
  { key: 'products', label: 'Products' },
  { key: 'policies', label: 'Policies' },
  { key: 'claims', label: 'Claims' },
]

const products = ref([])
const policies = ref([])
const claims = ref([])
const loadingProducts = ref(false)
const loadingPolicies = ref(false)
const loadingClaims = ref(false)

const policySearch = ref('')
const policyStatus = ref('')

const showProductModal = ref(false)
const editingProduct = ref(null)
const savingProduct = ref(false)
const productError = ref('')
const productForm = ref({
  name: '', code: '', description: '', premium_type: 'percentage', premium_rate: 2,
  coverage_type: 'credit_life', max_term_months: '', is_active: true, notes: '',
})

const reviewModal = ref(null)
const reviewAction = ref('')
const reviewForm = ref({ approved_amount: null, rejection_reason: '' })
const reviewing = ref(false)

async function fetchProducts() {
  loadingProducts.value = true
  try {
    const { data } = await axios.get('/api/v1/insurance/products')
    products.value = data.data ?? []
  } finally { loadingProducts.value = false }
}

async function fetchPolicies() {
  loadingPolicies.value = true
  try {
    const { data } = await axios.get('/api/v1/insurance/policies', {
      params: { search: policySearch.value || undefined, status: policyStatus.value || undefined }
    })
    policies.value = data.data?.data ?? []
  } catch { policies.value = [] } finally { loadingPolicies.value = false }
}

async function fetchClaims() {
  loadingClaims.value = true
  try {
    const { data } = await axios.get('/api/v1/insurance/claims')
    claims.value = data.data?.data ?? []
  } catch { claims.value = [] } finally { loadingClaims.value = false }
}

async function viewClaims(pol) {
  activeTab.value = 'claims'
  loadingClaims.value = true
  try {
    const { data } = await axios.get(`/api/v1/insurance/policies/${pol.id}/claims`)
    claims.value = data.data ?? []
  } finally { loadingClaims.value = false }
}

function openAddProduct() {
  editingProduct.value = null
  productForm.value = {
    name: '', code: '', description: '', premium_type: 'percentage', premium_rate: 2,
    coverage_type: 'credit_life', max_term_months: '', is_active: true, notes: '',
  }
  productError.value = ''
  showProductModal.value = true
}

function editProduct(p) {
  editingProduct.value = p
  productForm.value = {
    name: p.name, code: p.code, description: p.description ?? '',
    premium_type: p.premium_type, premium_rate: p.premium_rate,
    coverage_type: p.coverage_type, max_term_months: p.max_term_months ?? '',
    is_active: p.is_active, notes: p.notes ?? '',
  }
  productError.value = ''
  showProductModal.value = true
}

async function saveProduct() {
  productError.value = ''
  savingProduct.value = true
  const payload = { ...productForm.value, max_term_months: productForm.value.max_term_months || null }
  try {
    if (editingProduct.value) {
      await axios.put(`/api/v1/insurance/products/${editingProduct.value.id}`, payload)
    } else {
      await axios.post('/api/v1/insurance/products', payload)
    }
    await fetchProducts()
    showProductModal.value = false
  } catch (e) { productError.value = e.response?.data?.message ?? 'Failed.' } finally { savingProduct.value = false }
}

async function deleteProduct(p) {
  if (!confirm('Remove this product?')) return
  try {
    await axios.delete(`/api/v1/insurance/products/${p.id}`)
    await fetchProducts()
  } catch {}
}

function openReview(c, action) {
  reviewModal.value = c
  reviewAction.value = action
  reviewForm.value = { approved_amount: action === 'approved' ? c.claim_amount : null, rejection_reason: '' }
}

async function submitReview() {
  reviewing.value = true
  try {
    const payload = { status: reviewAction.value }
    if (reviewAction.value === 'approved' && reviewForm.value.approved_amount) {
      payload.approved_amount = reviewForm.value.approved_amount
    }
    if (reviewAction.value === 'rejected' && reviewForm.value.rejection_reason) {
      payload.rejection_reason = reviewForm.value.rejection_reason
    }
    await axios.put(`/api/v1/insurance/claims/${reviewModal.value.id}/review`, payload)
    await fetchClaims()
    reviewModal.value = null
  } finally { reviewing.value = false }
}

let pSearchTimeout = null
function debouncedPolicySearch() { clearTimeout(pSearchTimeout); pSearchTimeout = setTimeout(fetchPolicies, 400) }

function statusClass(s) {
  const map = {
    active: 'lendr-badge-success', claimed: 'lendr-badge-warning',
    expired: 'lendr-badge-neutral', cancelled: 'lendr-badge-neutral',
    pending: 'lendr-badge-warning', approved: 'lendr-badge-success',
    paid: 'lendr-badge-success', rejected: 'lendr-badge-danger',
    under_review: 'lendr-badge-warning',
  }
  return map[s] ?? 'lendr-badge-neutral'
}
function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString() }

onMounted(() => { fetchProducts(); fetchPolicies(); fetchClaims() })
</script>
