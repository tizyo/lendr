<template>
  <AppLayout title="Featured Items">
    <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-neutral-900">Featured Items</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Promote your repo items on the public marketplace for K50/day. Max 10 active slots.</p>
        </div>
        <button @click="openNewSlot" class="lendr-btn-primary flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
            <path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
          </svg>
          Feature an Item
        </button>
      </div>

      <!-- Stats bar -->
      <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-neutral-100 text-center">
          <p class="text-3xl font-black text-yellow-500">{{ activeSlots.length }}</p>
          <p class="text-xs text-neutral-500 mt-0.5">Active Slots</p>
          <p class="text-xs text-neutral-400">of {{ MAX_SLOTS }} max</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-neutral-100 text-center">
          <p class="text-3xl font-black text-neutral-700">{{ pendingSlots.length }}</p>
          <p class="text-xs text-neutral-500 mt-0.5">Pending Payment</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-neutral-100 text-center">
          <p class="text-3xl font-black text-primary-600">K{{ totalSpent }}</p>
          <p class="text-xs text-neutral-500 mt-0.5">Total Invested</p>
        </div>
      </div>

      <!-- Rate info banner -->
      <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4 flex items-start gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-yellow-500 shrink-0 mt-0.5">
          <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
        </svg>
        <div>
          <p class="text-sm font-bold text-yellow-800">Featured Item Rate: K50 per day</p>
          <p class="text-xs text-yellow-700 mt-0.5">Your item appears in the "Featured Picks" section on the public repo marketplace. Featured slots are automatically removed after the chosen period ends. No refunds for early removal.</p>
        </div>
      </div>

      <!-- Active slots -->
      <div v-if="activeSlots.length">
        <h2 class="text-sm font-bold text-neutral-700 mb-3 uppercase tracking-wide">Active Featured Slots</h2>
        <div class="space-y-3">
          <div v-for="slot in activeSlots" :key="slot.id" class="bg-white rounded-2xl shadow-sm border border-yellow-100 p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-yellow-500">
                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-neutral-800 truncate">{{ slot.item_title }}</p>
              <div class="flex items-center gap-3 mt-1 flex-wrap">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', slot.type === 'manual' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700']">
                  {{ slot.type === 'manual' ? 'Editor Pick' : 'Paid' }}
                </span>
                <span v-if="slot.days_remaining >= 0" class="text-xs text-amber-600 font-medium">⏳ {{ slot.days_remaining }} day(s) left</span>
                <span v-else class="text-xs text-blue-600">Indefinite</span>
                <span v-if="slot.expires_at" class="text-xs text-neutral-400">Expires {{ slot.expires_at?.slice(0, 10) }}</span>
              </div>
            </div>
            <button @click="removeSlot(slot)" class="text-red-400 hover:text-red-600 p-2 rounded-lg hover:bg-red-50 transition">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                <path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 013.878.512.75.75 0 11-.256 1.478l-.209-.035-1.005 13.07a3 3 0 01-2.991 2.77H8.084a3 3 0 01-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 01-.256-1.478A48.567 48.567 0 017.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 013.369 0c1.603.051 2.815 1.387 2.815 2.951zm-6.136-1.452a51.196 51.196 0 013.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 00-6 0v-.113c0-.794.609-1.428 1.364-1.452zm-.355 5.945a.75.75 0 10-1.5.058l.347 9a.75.75 0 101.499-.058l-.346-9zm5.48.058a.75.75 0 10-1.498-.058l-.347 9a.75.75 0 001.5.058l.345-9z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Pending slots -->
      <div v-if="pendingSlots.length">
        <h2 class="text-sm font-bold text-neutral-700 mb-3 uppercase tracking-wide">Pending Payment</h2>
        <div class="space-y-3">
          <div v-for="slot in pendingSlots" :key="slot.id" class="bg-white rounded-2xl shadow-sm border border-amber-100 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-neutral-800">{{ slot.item_title }}</p>
                <p class="text-xs text-neutral-500 mt-0.5">{{ slot.days_paid }} day(s) · K{{ slot.amount_paid }} due</p>
                <p class="text-xs font-mono bg-neutral-100 text-neutral-600 px-2 py-1 rounded mt-1">Ref: {{ slot.payment_reference }}</p>
              </div>
              <div class="flex gap-2">
                <button @click="confirmSlot(slot)" class="lendr-btn-success text-xs">Mark Paid</button>
                <button @click="removeSlot(slot)" class="lendr-btn-danger text-xs">Cancel</button>
              </div>
            </div>
            <!-- Payment instructions -->
            <div class="mt-3 bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-800">
              <p class="font-semibold">Payment Instructions:</p>
              <p class="mt-0.5">Pay <strong>K{{ slot.amount_paid }}</strong> via Mobile Money or Bank Transfer using reference <strong>{{ slot.payment_reference }}</strong>. Click "Mark Paid" after payment is confirmed.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="!loading && slots.length === 0" class="text-center py-16 text-neutral-400">
        <p class="text-4xl mb-3">⭐</p>
        <p class="font-medium">No featured slots yet</p>
        <p class="text-sm mt-1">Feature your items to get more visibility on the marketplace.</p>
      </div>

      <!-- All slots table -->
      <div v-if="expiredSlots.length">
        <h2 class="text-sm font-bold text-neutral-700 mb-3 uppercase tracking-wide">Expired / Inactive</h2>
        <div class="bg-white rounded-2xl shadow-sm border border-neutral-100 overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-neutral-100 bg-neutral-50">
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500">Item</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500">Days</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500">Paid</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500">Expired</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="slot in expiredSlots" :key="slot.id" class="border-b border-neutral-50 last:border-0">
                <td class="px-4 py-3 text-neutral-700 truncate max-w-48">{{ slot.item_title }}</td>
                <td class="px-4 py-3 text-neutral-500">{{ slot.days_paid ?? '—' }}</td>
                <td class="px-4 py-3 text-neutral-500">{{ slot.amount_paid ? 'K' + slot.amount_paid : '—' }}</td>
                <td class="px-4 py-3 text-neutral-400 text-xs">{{ slot.expires_at?.slice(0, 10) ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ─── New Slot Modal ──────────────────────────────────────── -->
    <div v-if="showNewModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center px-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-lg font-black text-neutral-900">Feature a Repo Item</h2>
          <button @click="showNewModal = false" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>

        <div class="space-y-4">
          <!-- Item selector -->
          <div>
            <label class="lendr-label">Select Item *</label>
            <select v-model="newForm.repo_item_id" class="lendr-input">
              <option value="">— Choose an item —</option>
              <option v-for="item in myItems" :key="item.id" :value="item.id">{{ item.title }} (K{{ formatNum(item.price) }})</option>
            </select>
          </div>

          <!-- Days -->
          <div>
            <label class="lendr-label">Duration (days) *</label>
            <input v-model.number="newForm.days" type="number" min="1" max="90" class="lendr-input" placeholder="e.g. 7" />
          </div>

          <!-- Quote -->
          <div v-if="newForm.days > 0" class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-sm">
            <div class="flex justify-between items-center">
              <span class="text-yellow-800">Total Cost:</span>
              <span class="font-black text-yellow-700 text-lg">K{{ costForDays(newForm.days) }}</span>
            </div>
            <p class="text-xs text-yellow-600 mt-1">K50 × {{ newForm.days }} day(s)</p>
          </div>

          <p v-if="newError" class="text-xs text-red-500">{{ newError }}</p>

          <div class="flex gap-3 pt-2">
            <button @click="showNewModal = false" class="flex-1 lendr-btn-ghost">Cancel</button>
            <button @click="submitNewSlot" :disabled="submitting" class="flex-1 lendr-btn-primary">
              {{ submitting ? 'Processing…' : 'Initiate Featuring' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const MAX_SLOTS  = 10
const slots      = ref([])
const loading    = ref(true)
const myItems    = ref([])

const showNewModal = ref(false)
const newForm      = ref({ repo_item_id: '', days: 7 })
const newError     = ref('')
const submitting   = ref(false)

const activeSlots  = computed(() => slots.value.filter(s => s.is_active && !s.is_expired))
const pendingSlots = computed(() => slots.value.filter(s => s.payment_status === 'pending'))
const expiredSlots = computed(() => slots.value.filter(s => s.is_expired || (!s.is_active && s.payment_status === 'confirmed')))
const totalSpent   = computed(() => {
  const t = slots.value.filter(s => s.payment_status === 'confirmed').reduce((a, b) => a + (b.amount_paid || 0), 0)
  return Number(t).toLocaleString()
})

async function fetchSlots() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/featured-items')
    slots.value = data.data ?? []
  } finally {
    loading.value = false
  }
}

async function fetchMyItems() {
  try {
    const { data } = await axios.get('/api/v1/repo-items', { params: { per_page: 100 } })
    myItems.value = (data.data ?? []).filter(i => i.is_active && !i.is_sold)
  } catch { myItems.value = [] }
}

function openNewSlot() {
  newForm.value = { repo_item_id: '', days: 7 }
  newError.value = ''
  showNewModal.value = true
}

async function submitNewSlot() {
  if (!newForm.value.repo_item_id) { newError.value = 'Please select an item.'; return }
  if (!newForm.value.days || newForm.value.days < 1) { newError.value = 'Choose at least 1 day.'; return }
  submitting.value = true
  newError.value = ''
  try {
    await axios.post('/api/v1/featured-items', newForm.value)
    showNewModal.value = false
    await fetchSlots()
  } catch (e) {
    newError.value = e?.response?.data?.message ?? 'Failed to initiate featuring.'
  } finally {
    submitting.value = false
  }
}

async function confirmSlot(slot) {
  try {
    await axios.post(`/api/v1/featured-items/${slot.id}/confirm`)
    await fetchSlots()
  } catch (e) {
    alert(e?.response?.data?.message ?? 'Failed to confirm.')
  }
}

async function removeSlot(slot) {
  if (!confirm('Remove this featured slot? No refund will be issued.')) return
  try {
    await axios.delete(`/api/v1/featured-items/${slot.id}`)
    await fetchSlots()
  } catch (e) {
    alert(e?.response?.data?.message ?? 'Failed to remove.')
  }
}

function costForDays(days) {
  return Number(days * 50).toLocaleString()
}

function formatNum(n) {
  return Number(n).toLocaleString()
}

onMounted(() => {
  fetchSlots()
  fetchMyItems()
})
</script>
