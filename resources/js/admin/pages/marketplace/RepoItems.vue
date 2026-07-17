<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Repo Marketplace</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Manage repossessed item listings</p>
        </div>
        <button @click="openCreate" class="lendr-btn-primary">
          + Post Item
        </button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4">
      <div class="flex flex-wrap gap-3">
        <input
          v-model="search"
          @input="debouncedFilter"
          placeholder="Search listings…"
          class="lendr-input flex-1 min-w-48"
        />
        <select v-model="statusFilter" @change="applyFilters" class="lendr-input w-40">
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="sold">Sold</option>
          <option value="inactive">Inactive</option>
        </select>
        <select v-model="categoryFilter" @change="applyFilters" class="lendr-input w-44">
          <option value="">All categories</option>
          <option value="furniture">Furniture</option>
          <option value="electronics">Electronics</option>
          <option value="vehicle">Vehicle</option>
          <option value="land">Land</option>
          <option value="equipment">Equipment</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600 uppercase text-xs tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Item</th>
            <th class="px-4 py-3 text-left">Category</th>
            <th class="px-4 py-3 text-right">Price</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-center">Views</th>
            <th class="px-4 py-3 text-center">Enquiries</th>
            <th class="px-4 py-3 text-left">Listed</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="items.data.length === 0">
            <td colspan="8" class="px-4 py-10 text-center text-neutral-400">No listings found.</td>
          </tr>
          <tr v-for="item in items.data" :key="item.id" class="hover:bg-neutral-50 transition">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <img v-if="item.primary_image" :src="item.primary_image" class="w-10 h-10 rounded-lg object-cover bg-neutral-100" />
                <div v-else class="w-10 h-10 rounded-lg bg-neutral-100 flex items-center justify-center text-neutral-400 text-xs">IMG</div>
                <div>
                  <p class="font-medium text-neutral-900">{{ item.title }}</p>
                  <p v-if="item.location" class="text-xs text-neutral-500">{{ item.location }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 capitalize text-neutral-600">{{ item.category }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ formatAmount(item.price) }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="statusClass(item)" class="lendr-badge text-xs capitalize">
                {{ item.is_sold ? 'Sold' : item.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center text-neutral-600">{{ item.views_count }}</td>
            <td class="px-4 py-3 text-center">
              <button @click="viewEnquiries(item)" class="text-primary-600 hover:underline font-medium">
                {{ item.enquiries_count }}
              </button>
            </td>
            <td class="px-4 py-3 text-neutral-500 text-xs">{{ item.created_at }}</td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center gap-2 justify-end">
                <button v-if="!item.is_sold && item.is_active" @click="markSold(item)" class="text-xs text-amber-600 hover:underline">Mark Sold</button>
                <button v-if="item.is_active && !item.is_sold" @click="deactivate(item)" class="text-xs text-red-500 hover:underline">Remove</button>
                <button @click="editItem(item)" class="text-xs text-neutral-500 hover:underline">Edit</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="items.last_page > 1" class="px-4 py-3 border-t border-neutral-100 flex gap-1">
        <Link
          v-for="link in items.links"
          :key="link.label"
          :href="link.url ?? '#'"
          :class="['px-3 py-1 rounded text-sm', link.active ? 'bg-primary-600 text-white' : 'text-neutral-600 hover:bg-neutral-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
          v-html="link.label"
          preserve-scroll
        />
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-neutral-100 flex items-center justify-between">
          <h2 class="font-bold text-lg">{{ editing ? 'Edit Listing' : 'Post Repo Item' }}</h2>
          <button @click="closeModal" class="text-neutral-400 hover:text-neutral-700 text-xl">&times;</button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="lendr-label">Title *</label>
            <input v-model="form.title" class="lendr-input w-full" placeholder="e.g. Samsung 55″ TV" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="lendr-label">Price (ZMW) *</label>
              <input v-model="form.price" type="number" min="0" class="lendr-input w-full" />
            </div>
            <div>
              <label class="lendr-label">Original Value</label>
              <input v-model="form.original_value" type="number" min="0" class="lendr-input w-full" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="lendr-label">Category *</label>
              <select v-model="form.category" class="lendr-input w-full">
                <option value="">Select…</option>
                <option value="furniture">Furniture</option>
                <option value="electronics">Electronics</option>
                <option value="vehicle">Vehicle</option>
                <option value="land">Land</option>
                <option value="equipment">Equipment</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="lendr-label">Condition *</label>
              <select v-model="form.condition" class="lendr-input w-full">
                <option value="">Select…</option>
                <option value="new">New</option>
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
              </select>
            </div>
          </div>
          <div>
            <label class="lendr-label">Location</label>
            <input v-model="form.location" class="lendr-input w-full" placeholder="e.g. Lusaka, Zambia" />
          </div>
          <div>
            <label class="lendr-label">Description</label>
            <textarea v-model="form.description" class="lendr-input w-full" rows="3" placeholder="Describe the item…"></textarea>
          </div>
          <div v-if="editing">
            <label class="lendr-label">Active</label>
            <input type="checkbox" v-model="form.is_active" class="w-4 h-4" />
            <span class="text-sm text-neutral-600 ml-2">Show on marketplace</span>
          </div>
          <p v-if="error" class="text-red-500 text-sm">{{ error }}</p>
        </div>
        <div class="px-6 pb-6 flex gap-3 justify-end">
          <button @click="closeModal" class="lendr-btn-ghost">Cancel</button>
          <button @click="saveItem" :disabled="saving" class="lendr-btn-primary">
            {{ saving ? 'Saving…' : (editing ? 'Update' : 'Post Item') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Enquiries Drawer -->
    <div v-if="enquiriesItem" class="fixed inset-0 bg-black/50 flex items-center justify-end z-50">
      <div class="bg-white w-full max-w-lg h-full overflow-y-auto flex flex-col">
        <div class="p-5 border-b flex items-center justify-between sticky top-0 bg-white">
          <div>
            <h2 class="font-bold text-lg">Enquiries</h2>
            <p class="text-sm text-neutral-500">{{ enquiriesItem.title }}</p>
          </div>
          <button @click="enquiriesItem = null" class="text-neutral-400 hover:text-neutral-700 text-xl">&times;</button>
        </div>
        <div class="p-5 flex-1 space-y-4">
          <div v-if="loadingEnquiries" class="text-center py-10 text-neutral-400">Loading…</div>
          <div v-else-if="enquiries.length === 0" class="text-center py-10 text-neutral-400">No enquiries yet.</div>
          <div v-for="enq in enquiries" :key="enq.id" class="border border-neutral-200 rounded-xl p-4 space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-medium text-sm">{{ enq.enquirer?.name ?? 'Anonymous' }}</p>
                <p class="text-xs text-neutral-500">{{ enq.enquirer?.phone }} · {{ formatDate(enq.created_at) }}</p>
              </div>
              <span :class="enqStatusClass(enq.status)" class="lendr-badge text-xs capitalize shrink-0">{{ enq.status }}</span>
            </div>
            <p class="text-sm text-neutral-700 bg-neutral-50 rounded-lg p-3">{{ enq.message }}</p>
            <div v-if="enq.reply" class="bg-primary-50 rounded-lg p-3">
              <p class="text-xs text-primary-600 font-medium mb-1">Your reply · {{ formatDate(enq.replied_at) }}</p>
              <p class="text-sm text-neutral-700">{{ enq.reply }}</p>
            </div>
            <div v-else>
              <textarea v-model="replyTexts[enq.id]" class="lendr-input w-full text-sm" rows="2" placeholder="Type a reply…"></textarea>
              <button @click="sendReply(enq)" :disabled="replying === enq.id" class="mt-2 lendr-btn-primary text-xs">
                {{ replying === enq.id ? 'Sending…' : 'Send Reply' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  items: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.status ?? '')
const categoryFilter = ref(props.filters?.category ?? '')

let filterTimeout = null
function debouncedFilter() {
  clearTimeout(filterTimeout)
  filterTimeout = setTimeout(() => applyFilters(), 400)
}
function applyFilters() {
  router.get(route('marketplace.repo-items'), {
    search: search.value || undefined,
    status: statusFilter.value || undefined,
    category: categoryFilter.value || undefined,
  }, { preserveState: true, replace: true })
}

// ── Create / Edit ──────────────────────────────────────────────────────────
const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const error = ref('')
const form = reactive({ title: '', price: '', original_value: '', category: '', condition: '', location: '', description: '', is_active: true })

function openCreate() {
  editing.value = null
  Object.assign(form, { title: '', price: '', original_value: '', category: '', condition: '', location: '', description: '', is_active: true })
  error.value = ''
  showModal.value = true
}
function editItem(item) {
  editing.value = item
  Object.assign(form, {
    title: item.title, price: item.price, original_value: item.original_value ?? '',
    category: item.category, condition: item.condition, location: item.location ?? '',
    description: '', is_active: item.is_active,
  })
  error.value = ''
  showModal.value = true
}
function closeModal() { showModal.value = false }

async function saveItem() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await axios.put(`/api/v1/repo-items/${editing.value.id}`, form)
    } else {
      await axios.post('/api/v1/repo-items', form)
    }
    router.reload({ only: ['items'] })
    closeModal()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to save.'
  } finally {
    saving.value = false
  }
}

async function markSold(item) {
  if (!confirm(`Mark "${item.title}" as sold?`)) return
  await axios.post(`/api/v1/repo-items/${item.id}/mark-sold`)
  router.reload({ only: ['items'] })
}

async function deactivate(item) {
  if (!confirm(`Remove "${item.title}" from marketplace?`)) return
  await axios.delete(`/api/v1/repo-items/${item.id}`)
  router.reload({ only: ['items'] })
}

// ── Enquiries ──────────────────────────────────────────────────────────────
const enquiriesItem = ref(null)
const enquiries = ref([])
const loadingEnquiries = ref(false)
const replyTexts = reactive({})
const replying = ref(null)

async function viewEnquiries(item) {
  enquiriesItem.value = item
  loadingEnquiries.value = true
  enquiries.value = []
  const { data } = await axios.get(`/api/v1/repo-items/${item.id}/enquiries`)
  enquiries.value = data.data ?? []
  loadingEnquiries.value = false
}

async function sendReply(enq) {
  if (!replyTexts[enq.id]) return
  replying.value = enq.id
  try {
    await axios.post(`/api/v1/repo-items/${enquiriesItem.value.id}/enquiries/${enq.id}/reply`, { reply: replyTexts[enq.id] })
    enq.reply = replyTexts[enq.id]
    enq.status = 'replied'
    delete replyTexts[enq.id]
  } finally {
    replying.value = null
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function formatAmount(n) { return 'K ' + Number(n).toLocaleString() }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '' }

function statusClass(item) {
  if (item.is_sold) return 'lendr-badge-warning'
  if (item.is_active) return 'lendr-badge-success'
  return 'lendr-badge-neutral'
}

function enqStatusClass(s) {
  return { new: 'lendr-badge-warning', read: 'lendr-badge-neutral', replied: 'lendr-badge-success' }[s] ?? 'lendr-badge-neutral'
}
</script>
