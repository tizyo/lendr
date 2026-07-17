<template>
  <PwaLayout title="My Cart">
    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin w-8 h-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
      </svg>
    </div>

    <div v-else-if="!ghostToken" class="px-4 py-16 text-center text-neutral-500 space-y-4">
      <p class="text-4xl">🛒</p>
      <p class="font-medium">Sign in to view your cart</p>
      <button @click="goToLogin" class="px-6 py-3 rounded-2xl bg-primary-600 text-white font-medium text-sm">Sign In</button>
    </div>

    <div v-else-if="cartItems.length === 0" class="px-4 py-16 text-center text-neutral-400">
      <p class="text-4xl mb-3">🛒</p>
      <p class="font-medium">Your cart is empty</p>
      <p class="text-sm mt-1">Browse the marketplace to add items.</p>
      <button @click="$inertia.visit('/app/repo')" class="mt-4 px-6 py-3 rounded-2xl bg-primary-600 text-white font-medium text-sm">Browse Items</button>
    </div>

    <div v-else class="px-4 py-4 space-y-3">
      <div
        v-for="entry in cartItems"
        :key="entry.cart_id"
        class="bg-white rounded-2xl shadow-sm p-4 flex gap-3"
      >
        <div class="w-20 h-20 rounded-xl bg-neutral-100 overflow-hidden shrink-0">
          <img v-if="entry.item?.image_url" :src="entry.item.image_url" class="w-full h-full object-cover" />
          <div v-else class="w-full h-full flex items-center justify-center text-2xl">📦</div>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-neutral-900 truncate">{{ entry.item?.title }}</p>
          <p class="text-primary-600 font-bold text-sm mt-0.5">K {{ formatNum(entry.item?.price) }}</p>
          <p class="text-xs text-neutral-500 capitalize mt-0.5">{{ entry.item?.condition }} · {{ entry.item?.category }}</p>
          <div v-if="entry.item?.is_sold" class="mt-1">
            <span class="bg-red-100 text-red-600 text-xs px-2 py-0.5 rounded-full font-medium">SOLD</span>
          </div>
        </div>
        <div class="flex flex-col items-end justify-between">
          <button @click="removeFromCart(entry)" class="text-neutral-300 hover:text-red-400 text-xl">&times;</button>
          <button v-if="!entry.item?.is_sold" @click="goToItem(entry.item?.id)" class="text-primary-600 text-xs font-medium">View</button>
        </div>
      </div>

      <!-- Enquire all button -->
      <div class="pt-2">
        <button @click="enquireAll" :disabled="enquiringAll" class="w-full py-3.5 rounded-2xl bg-primary-600 text-white font-bold">
          {{ enquiringAll ? 'Sending enquiries…' : 'Enquire About All Items' }}
        </button>
        <p class="text-xs text-neutral-400 text-center mt-2">Sends a single enquiry to each seller</p>
      </div>
    </div>

    <!-- Toast -->
    <Transition name="fade">
      <div v-if="toast" class="fixed top-5 inset-x-4 bg-green-600 text-white text-sm font-medium py-3 px-4 rounded-2xl text-center z-50 shadow-lg">{{ toast }}</div>
    </Transition>
  </PwaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import axios from 'axios'

const cartItems = ref([])
const loading = ref(true)
const enquiringAll = ref(false)
const toast = ref('')
const ghostToken = computed(() => localStorage.getItem('ghost_token'))

function authHeaders() { return { Authorization: `Bearer ${ghostToken.value}` } }

async function fetchCart() {
  if (!ghostToken.value) { loading.value = false; return }
  try {
    const { data } = await axios.get('/api/v1/public/cart', { headers: authHeaders() })
    cartItems.value = data.data ?? []
  } finally {
    loading.value = false
  }
}

async function removeFromCart(entry) {
  await axios.delete(`/api/v1/public/cart/${entry.cart_id}`, { headers: authHeaders() })
  cartItems.value = cartItems.value.filter(c => c.cart_id !== entry.cart_id)
}

async function enquireAll() {
  enquiringAll.value = true
  const available = cartItems.value.filter(c => !c.item?.is_sold)
  for (const entry of available) {
    try {
      await axios.post(`/api/v1/public/items/${entry.item.id}/enquire`, { message: 'Hi, I am interested in this item. Is it still available?' }, { headers: authHeaders() })
    } catch {}
  }
  enquiringAll.value = false
  showToast('Enquiries sent to all sellers!')
}

function goToItem(id) { router.visit(`/app/repo/${id}`) }
function goToLogin() { router.visit('/app/repo/auth/login') }
function showToast(msg) { toast.value = msg; setTimeout(() => toast.value = '', 4000) }
function formatNum(n) { return Number(n).toLocaleString() }
onMounted(() => fetchCart())
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s, transform 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
