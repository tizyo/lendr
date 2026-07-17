<template>
  <AppLayout title="Hot Deals">
    <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-neutral-900">Hot Deals</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Promote your loan products on the public marketplace as time-limited hot deals.</p>
        </div>
        <button @click="openCreate" class="lendr-btn-primary flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
            <path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
          </svg>
          New Hot Deal
        </button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-neutral-100 text-center">
          <p class="text-3xl font-black text-orange-500">{{ activeDeals.length }}</p>
          <p class="text-xs text-neutral-500 mt-0.5">Active Deals</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-neutral-100 text-center">
          <p class="text-3xl font-black text-neutral-700">{{ totalViews }}</p>
          <p class="text-xs text-neutral-500 mt-0.5">Total Views</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-neutral-100 text-center">
          <p class="text-3xl font-black text-green-600">{{ totalLeads }}</p>
          <p class="text-xs text-neutral-500 mt-0.5">Leads Generated</p>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <svg class="animate-spin w-8 h-8 text-orange-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
      </div>

      <!-- Deals grid -->
      <div v-else-if="deals.length === 0" class="text-center py-16 text-neutral-400">
        <p class="text-4xl mb-3">🔥</p>
        <p class="font-medium">No Hot Deals yet</p>
        <p class="text-sm mt-1">Create a deal to attract borrowers from the public marketplace.</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="deal in deals"
          :key="deal.id"
          class="bg-white rounded-2xl shadow-sm border border-neutral-100 overflow-hidden"
        >
          <!-- Color header -->
          <div class="bg-gradient-to-r from-orange-500 to-red-500 px-4 py-3 flex items-center justify-between">
            <div class="flex-1 min-w-0">
              <p class="text-white font-black text-base truncate">{{ deal.title }}</p>
              <p v-if="deal.loan_product" class="text-orange-100 text-xs mt-0.5">{{ deal.loan_product }}</p>
            </div>
            <div class="flex items-center gap-2 ml-3">
              <span v-if="deal.badge_label" class="bg-white/20 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ deal.badge_label }}</span>
              <span :class="['w-2.5 h-2.5 rounded-full', deal.is_active ? 'bg-green-400' : 'bg-neutral-400']"></span>
            </div>
          </div>

          <div class="p-4">
            <!-- Key stats row -->
            <div class="flex gap-3 flex-wrap mb-3">
              <span v-if="deal.interest_rate" class="bg-green-50 text-green-700 text-xs font-semibold px-2.5 py-1 rounded-full">{{ deal.interest_rate }}% / month</span>
              <span v-if="deal.max_amount" class="bg-blue-50 text-blue-700 text-xs font-semibold px-2.5 py-1 rounded-full">Up to K{{ formatNum(deal.max_amount) }}</span>
              <span v-if="deal.tenure" class="bg-purple-50 text-purple-700 text-xs font-semibold px-2.5 py-1 rounded-full">{{ deal.tenure }}</span>
            </div>

            <p v-if="deal.description" class="text-xs text-neutral-500 mb-3 line-clamp-2">{{ deal.description }}</p>

            <!-- Analytics -->
            <div class="flex items-center gap-4 mb-3 text-xs text-neutral-400">
              <span>👁 {{ deal.views_count }} views</span>
              <span>✉ {{ deal.leads_count }} leads</span>
              <span v-if="deal.expires_at">🕐 Expires {{ deal.expires_at }}</span>
              <span v-else class="text-green-500">No expiry</span>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
              <button @click="openLeads(deal)" class="lendr-btn-ghost text-xs flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
                  <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" />
                  <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" />
                </svg>
                Leads ({{ deal.leads_count }})
              </button>
              <button @click="openEdit(deal)" class="lendr-btn-ghost text-xs">Edit</button>
              <button @click="toggleDeal(deal)" :class="['text-xs', deal.is_active ? 'lendr-btn-warning' : 'lendr-btn-success']">
                {{ deal.is_active ? 'Pause' : 'Activate' }}
              </button>
              <button @click="deleteDeal(deal)" class="lendr-btn-danger text-xs">Delete</button>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ─── Create/Edit Modal ──────────────────────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 z-50 bg-black/40 flex items-start justify-center px-4 py-8 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-lg font-black text-neutral-900">{{ editDeal ? 'Edit Hot Deal' : 'New Hot Deal' }}</h2>
          <button @click="showModal = false" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="lendr-label">Title *</label>
            <input v-model="form.title" class="lendr-input" placeholder="e.g. Get Loan in 24 Hours!" />
          </div>
          <div>
            <label class="lendr-label">Loan Product</label>
            <input v-model="form.loan_product" class="lendr-input" placeholder="e.g. Quick Cash Loan" />
          </div>
          <div>
            <label class="lendr-label">Badge Label</label>
            <input v-model="form.badge_label" class="lendr-input" placeholder="e.g. Limited Time" />
          </div>
          <div>
            <label class="lendr-label">Interest Rate (monthly %)</label>
            <input v-model.number="form.interest_rate" type="number" step="0.1" class="lendr-input" placeholder="e.g. 3.5" />
          </div>
          <div>
            <label class="lendr-label">Tenure</label>
            <input v-model="form.tenure" class="lendr-input" placeholder="e.g. 3 - 24 months" />
          </div>
          <div>
            <label class="lendr-label">Min Amount (K)</label>
            <input v-model.number="form.min_amount" type="number" class="lendr-input" placeholder="e.g. 500" />
          </div>
          <div>
            <label class="lendr-label">Max Amount (K)</label>
            <input v-model.number="form.max_amount" type="number" class="lendr-input" placeholder="e.g. 50000" />
          </div>
          <div>
            <label class="lendr-label">Contact Phone</label>
            <input v-model="form.contact_phone" class="lendr-input" placeholder="+260..." />
          </div>
          <div>
            <label class="lendr-label">Contact Email</label>
            <input v-model="form.contact_email" type="email" class="lendr-input" placeholder="loans@yourcompany.com" />
          </div>
          <div>
            <label class="lendr-label">Expires At (leave blank = no expiry)</label>
            <input v-model="form.expires_at" type="date" class="lendr-input" />
          </div>
          <div>
            <label class="lendr-label">Banner Image URL</label>
            <input v-model="form.image_url" class="lendr-input" placeholder="https://..." />
          </div>
          <div class="md:col-span-2">
            <label class="lendr-label">Description</label>
            <textarea v-model="form.description" rows="3" class="lendr-input resize-none" placeholder="What makes this deal special?" />
          </div>
          <div class="md:col-span-2">
            <label class="lendr-label">Eligibility Requirements</label>
            <textarea v-model="form.requirements" rows="3" class="lendr-input resize-none" placeholder="e.g. Must be employed, 18+ years old..." />
          </div>
        </div>

        <p v-if="formError" class="text-xs text-red-500 mt-3">{{ formError }}</p>

        <div class="flex gap-3 mt-5">
          <button @click="showModal = false" class="flex-1 lendr-btn-ghost">Cancel</button>
          <button @click="saveDeal" :disabled="saving" class="flex-1 lendr-btn-primary">
            {{ saving ? 'Saving…' : (editDeal ? 'Update Deal' : 'Create Deal') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ─── Leads Modal ────────────────────────────────────────────── -->
    <div v-if="leadsModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center px-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-6 max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-black text-neutral-900">Leads — {{ leadsForDeal?.title }}</h2>
          <button @click="leadsModal = false" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="overflow-y-auto flex-1">
          <div v-if="loadingLeads" class="text-center py-8 text-neutral-400">Loading…</div>
          <div v-else-if="leads.length === 0" class="text-center py-8 text-neutral-400">No leads yet.</div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b border-neutral-100 text-xs text-neutral-500">
                <th class="text-left pb-2">Name</th>
                <th class="text-left pb-2">Phone</th>
                <th class="text-left pb-2">Email</th>
                <th class="text-left pb-2">Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lead in leads" :key="lead.id" class="border-b border-neutral-50">
                <td class="py-2.5 font-medium text-neutral-800">{{ lead.full_name }}</td>
                <td class="py-2.5 text-neutral-600">{{ lead.phone }}</td>
                <td class="py-2.5 text-neutral-400 text-xs">{{ lead.email ?? '—' }}</td>
                <td class="py-2.5 text-neutral-400 text-xs">{{ lead.created_at?.slice(0, 10) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const deals   = ref([])
const loading = ref(true)

const activeDeals = computed(() => deals.value.filter(d => d.is_active))
const totalViews  = computed(() => deals.value.reduce((a, d) => a + d.views_count, 0))
const totalLeads  = computed(() => deals.value.reduce((a, d) => a + d.leads_count, 0))

// Create/Edit modal
const showModal = ref(false)
const editDeal  = ref(null)
const saving    = ref(false)
const formError = ref('')
const emptyForm = () => ({
  title: '', description: '', loan_product: '', interest_rate: null,
  min_amount: null, max_amount: null, tenure: '', requirements: '',
  contact_phone: '', contact_email: '', badge_label: '', image_url: '',
  expires_at: '', is_active: true,
})
const form = ref(emptyForm())

// Leads modal
const leadsModal   = ref(false)
const leadsForDeal = ref(null)
const leads        = ref([])
const loadingLeads = ref(false)

async function fetchDeals() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/hot-deals')
    deals.value = data.data ?? []
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editDeal.value  = null
  form.value      = emptyForm()
  formError.value = ''
  showModal.value = true
}

function openEdit(deal) {
  editDeal.value = deal
  form.value = { ...deal, expires_at: deal.expires_at ?? '' }
  formError.value = ''
  showModal.value = true
}

async function saveDeal() {
  if (!form.value.title) { formError.value = 'Title is required.'; return }
  saving.value = true
  formError.value = ''
  try {
    if (editDeal.value) {
      await axios.put(`/api/v1/hot-deals/${editDeal.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/hot-deals', form.value)
    }
    showModal.value = false
    await fetchDeals()
  } catch (e) {
    formError.value = e?.response?.data?.message ?? 'Failed to save.'
  } finally {
    saving.value = false
  }
}

async function toggleDeal(deal) {
  try {
    await axios.post(`/api/v1/hot-deals/${deal.id}/toggle`)
    await fetchDeals()
  } catch (e) {
    alert(e?.response?.data?.message ?? 'Failed to update status.')
  }
}

async function deleteDeal(deal) {
  if (!confirm('Delete this Hot Deal?')) return
  try {
    await axios.delete(`/api/v1/hot-deals/${deal.id}`)
    await fetchDeals()
  } catch (e) {
    alert(e?.response?.data?.message ?? 'Failed to delete.')
  }
}

async function openLeads(deal) {
  leadsForDeal.value = deal
  leadsModal.value   = true
  loadingLeads.value = true
  try {
    const { data } = await axios.get(`/api/v1/hot-deals/${deal.id}/leads`)
    leads.value = data.data ?? []
  } finally {
    loadingLeads.value = false
  }
}

function formatNum(n) {
  return Number(n).toLocaleString()
}

onMounted(fetchDeals)
</script>
