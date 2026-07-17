<template>
  <LandlordLayout title="Featured Items">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-neutral-900">Featured Items &amp; Hot Deals</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Curate featured repo items and monitor hot deals across all tenants.</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 bg-neutral-100 rounded-xl p-1 w-fit">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="['px-4 py-2 rounded-lg text-sm font-semibold transition', activeTab === tab.key ? 'bg-white shadow text-neutral-900' : 'text-neutral-500 hover:text-neutral-700']"
        >{{ tab.label }}</button>
      </div>

      <!-- ── Tab: Featured Items ───────────────────────────────────── -->
      <div v-if="activeTab === 'featured'" class="space-y-4">

        <!-- Manual feature button -->
        <div class="flex justify-between items-center">
          <p class="text-sm font-semibold text-neutral-700">All Featured Slots</p>
          <button @click="openManualModal" class="lendr-btn-primary flex items-center gap-2 text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
              <path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
            </svg>
            Manually Feature Item
          </button>
        </div>

        <!-- Filters -->
        <div class="flex gap-3 flex-wrap">
          <select v-model="featuredFilter.type" @change="fetchFeatured" class="lendr-input-sm">
            <option value="">All Types</option>
            <option value="manual">Manual</option>
            <option value="paid">Paid</option>
          </select>
          <label class="flex items-center gap-2 text-sm text-neutral-600">
            <input type="checkbox" v-model="featuredFilter.active_only" @change="fetchFeatured" class="rounded" />
            Active only
          </label>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-neutral-100 overflow-hidden">
          <div v-if="loadingFeatured" class="py-12 text-center text-neutral-400">Loading…</div>
          <div v-else-if="featuredSlots.length === 0" class="py-12 text-center text-neutral-400">No featured slots found.</div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b border-neutral-100 bg-neutral-50 text-xs text-neutral-500">
                <th class="text-left px-4 py-3">Item</th>
                <th class="text-left px-4 py-3">Tenant</th>
                <th class="text-left px-4 py-3">Type</th>
                <th class="text-left px-4 py-3">Payment</th>
                <th class="text-left px-4 py-3">Status</th>
                <th class="text-left px-4 py-3">Expires</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="slot in featuredSlots" :key="slot.id" class="border-b border-neutral-50 last:border-0">
                <td class="px-4 py-3 font-medium text-neutral-800 truncate max-w-44">{{ slot.item_title }}</td>
                <td class="px-4 py-3 text-neutral-500 text-xs">{{ slot.tenant_id }}</td>
                <td class="px-4 py-3">
                  <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', slot.type === 'manual' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700']">
                    {{ slot.type === 'manual' ? 'Editor Pick' : 'Paid' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span v-if="slot.payment_status === 'pending'" class="text-amber-600 text-xs font-medium">
                    K{{ slot.amount_paid }} pending
                    <button @click="confirmPayment(slot)" class="ml-2 text-primary-600 underline">Confirm</button>
                  </span>
                  <span v-else-if="slot.payment_status === 'confirmed'" class="text-green-600 text-xs">K{{ slot.amount_paid ?? '—' }} ✓</span>
                  <span v-else class="text-neutral-400 text-xs">—</span>
                </td>
                <td class="px-4 py-3">
                  <span :class="['w-2 h-2 rounded-full inline-block mr-1.5', slot.is_active ? 'bg-green-400' : 'bg-neutral-300']"></span>
                  <span class="text-xs text-neutral-600">{{ slot.is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-neutral-400">
                  <span v-if="slot.expires_at">{{ slot.expires_at.slice(0, 10) }}</span>
                  <span v-else class="text-blue-500">Indefinite</span>
                </td>
                <td class="px-4 py-3">
                  <button @click="removeSlot(slot)" class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Tab: Hot Deals ───────────────────────────────────────── -->
      <div v-if="activeTab === 'hot-deals'" class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm border border-neutral-100 overflow-hidden">
          <div v-if="loadingDeals" class="py-12 text-center text-neutral-400">Loading…</div>
          <div v-else-if="hotDeals.length === 0" class="py-12 text-center text-neutral-400">No hot deals found.</div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b border-neutral-100 bg-neutral-50 text-xs text-neutral-500">
                <th class="text-left px-4 py-3">Deal</th>
                <th class="text-left px-4 py-3">Lender</th>
                <th class="text-left px-4 py-3">Status</th>
                <th class="text-left px-4 py-3">Views</th>
                <th class="text-left px-4 py-3">Leads</th>
                <th class="text-left px-4 py-3">Expires</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="deal in hotDeals" :key="deal.id" class="border-b border-neutral-50 last:border-0">
                <td class="px-4 py-3 font-medium text-neutral-800">
                  {{ deal.title }}
                  <span v-if="deal.badge_label" class="ml-2 bg-orange-100 text-orange-700 text-[10px] px-1.5 py-0.5 rounded-full">{{ deal.badge_label }}</span>
                </td>
                <td class="px-4 py-3 text-neutral-500 text-xs">{{ deal.tenant_name }}</td>
                <td class="px-4 py-3">
                  <span :class="['w-2 h-2 rounded-full inline-block mr-1.5', deal.is_active ? 'bg-green-400' : 'bg-neutral-300']"></span>
                  <span class="text-xs">{{ deal.is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="px-4 py-3 text-neutral-600">{{ deal.views_count }}</td>
                <td class="px-4 py-3 text-neutral-600">{{ deal.leads_count }}</td>
                <td class="px-4 py-3 text-xs text-neutral-400">{{ deal.expires_at ?? 'No expiry' }}</td>
                <td class="px-4 py-3">
                  <button @click="removeDeal(deal)" class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ─── Manual Feature Modal ───────────────────────────────────── -->
    <div v-if="showManualModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center px-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-lg font-black text-neutral-900">Manually Feature Item</h2>
          <button @click="showManualModal = false" class="text-neutral-400">✕</button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="lendr-label">Repo Item ID *</label>
            <input v-model.number="manualForm.repo_item_id" type="number" class="lendr-input" placeholder="Item ID from repo_items table" />
          </div>
          <div>
            <label class="lendr-label">Admin Note (optional)</label>
            <textarea v-model="manualForm.note" rows="2" class="lendr-input resize-none" placeholder="Why featuring this item…" />
          </div>
          <div>
            <label class="lendr-label">Expires At (leave blank = indefinite)</label>
            <input v-model="manualForm.expires_at" type="date" class="lendr-input" />
          </div>
          <p v-if="manualError" class="text-xs text-red-500">{{ manualError }}</p>
          <div class="flex gap-3">
            <button @click="showManualModal = false" class="flex-1 lendr-btn-ghost">Cancel</button>
            <button @click="submitManual" :disabled="submittingManual" class="flex-1 lendr-btn-primary">
              {{ submittingManual ? 'Featuring…' : 'Feature Item' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </LandlordLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'
import axios from 'axios'

const tabs = [
  { key: 'featured',  label: '⭐ Featured Items' },
  { key: 'hot-deals', label: '🔥 Hot Deals' },
]
const activeTab = ref('featured')

// Featured slots
const featuredSlots    = ref([])
const loadingFeatured  = ref(true)
const featuredFilter   = ref({ type: '', active_only: false })

// Hot deals
const hotDeals      = ref([])
const loadingDeals  = ref(true)

// Manual feature modal
const showManualModal   = ref(false)
const manualForm        = ref({ repo_item_id: '', note: '', expires_at: '' })
const manualError       = ref('')
const submittingManual  = ref(false)

async function fetchFeatured() {
  loadingFeatured.value = true
  try {
    const { data } = await axios.get('/api/v1/landlord/featured-items', {
      params: {
        type:        featuredFilter.value.type || undefined,
        active_only: featuredFilter.value.active_only ? 1 : undefined,
      },
    })
    featuredSlots.value = data.data ?? []
  } finally {
    loadingFeatured.value = false
  }
}

async function fetchHotDeals() {
  loadingDeals.value = true
  try {
    const { data } = await axios.get('/api/v1/landlord/hot-deals')
    hotDeals.value = data.data ?? []
  } finally {
    loadingDeals.value = false
  }
}

function openManualModal() {
  manualForm.value = { repo_item_id: '', note: '', expires_at: '' }
  manualError.value = ''
  showManualModal.value = true
}

async function submitManual() {
  if (!manualForm.value.repo_item_id) { manualError.value = 'Item ID is required.'; return }
  submittingManual.value = true
  manualError.value = ''
  try {
    await axios.post('/api/v1/landlord/featured-items', manualForm.value)
    showManualModal.value = false
    await fetchFeatured()
  } catch (e) {
    manualError.value = e?.response?.data?.message ?? 'Failed to feature item.'
  } finally {
    submittingManual.value = false
  }
}

async function removeSlot(slot) {
  if (!confirm('Remove this featured slot?')) return
  try {
    await axios.delete(`/api/v1/landlord/featured-items/${slot.id}`)
    await fetchFeatured()
  } catch (e) {
    alert(e?.response?.data?.message ?? 'Failed to remove.')
  }
}

async function confirmPayment(slot) {
  if (!confirm('Confirm this tenant payment and activate the slot?')) return
  try {
    await axios.post(`/api/v1/landlord/featured-items/${slot.id}/confirm-payment`)
    await fetchFeatured()
  } catch (e) {
    alert(e?.response?.data?.message ?? 'Failed to confirm payment.')
  }
}

async function removeDeal(deal) {
  if (!confirm('Remove this Hot Deal?')) return
  try {
    await axios.delete(`/api/v1/landlord/hot-deals/${deal.id}`)
    await fetchHotDeals()
  } catch (e) {
    alert(e?.response?.data?.message ?? 'Failed to remove.')
  }
}

onMounted(() => {
  fetchFeatured()
  fetchHotDeals()
})
</script>
