<template>
  <PwaLayout :title="item?.title ?? 'Item Detail'" :show-back="true" :show-nav="false">
    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin w-8 h-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
      </svg>
    </div>

    <template v-if="item && !loading">
      <!-- Image gallery -->
      <div class="aspect-video bg-neutral-100 relative">
        <img v-if="currentImage" :src="currentImage" class="w-full h-full object-cover" />
        <div v-else class="w-full h-full flex items-center justify-center text-neutral-300 text-6xl">📦</div>
        <!-- Sold badge -->
        <div v-if="item.is_sold" class="absolute inset-0 bg-black/50 flex items-center justify-center">
          <span class="bg-red-500 text-white font-bold text-2xl px-6 py-3 rounded-2xl rotate-12">SOLD</span>
        </div>
      </div>

      <!-- Thumbnail strip -->
      <div v-if="item.images?.length > 1" class="flex gap-2 px-4 py-3 overflow-x-auto">
        <button v-for="img in item.images" :key="img.id" @click="currentImage = img.image_url"
          :class="['shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition', currentImage === img.image_url ? 'border-primary-500' : 'border-transparent']">
          <img :src="img.image_url" class="w-full h-full object-cover" />
        </button>
      </div>

      <!-- Details -->
      <div class="px-4 py-4 space-y-4">
        <!-- Title + price -->
        <div>
          <div class="flex items-start justify-between gap-2">
            <h1 class="text-xl font-bold text-neutral-900">{{ item.title }}</h1>
            <button @click="toggleCart" :disabled="cartLoading" class="shrink-0">
              <span class="text-2xl">{{ inCart ? '❤️' : '🤍' }}</span>
            </button>
          </div>
          <p class="text-2xl font-bold text-primary-600 mt-1">K {{ formatNum(item.price) }}</p>
          <p v-if="item.original_value" class="text-sm text-neutral-400 line-through">Original: K {{ formatNum(item.original_value) }}</p>
        </div>

        <!-- Meta -->
        <div class="grid grid-cols-2 gap-3">
          <div class="bg-neutral-50 rounded-xl p-3">
            <p class="text-xs text-neutral-500">Category</p>
            <p class="font-medium capitalize">{{ item.category }}</p>
          </div>
          <div class="bg-neutral-50 rounded-xl p-3">
            <p class="text-xs text-neutral-500">Condition</p>
            <p class="font-medium capitalize">{{ item.condition }}</p>
          </div>
          <div v-if="item.location" class="bg-neutral-50 rounded-xl p-3">
            <p class="text-xs text-neutral-500">Location</p>
            <p class="font-medium">{{ item.location }}</p>
          </div>
          <div class="bg-neutral-50 rounded-xl p-3">
            <p class="text-xs text-neutral-500">Seller</p>
            <div class="flex items-center gap-1">
              <p class="font-medium">{{ item.tenant_name }}</p>
              <svg v-if="item.tenant_badge === 'gold'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" title="Gold Verified Lender" class="w-4 h-4 text-yellow-500 shrink-0">
                <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.491 4.491 0 01-3.497-1.307 4.491 4.491 0 01-1.307-3.497A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Description -->
        <div v-if="item.description">
          <h3 class="font-semibold text-neutral-900 mb-1.5">Description</h3>
          <p class="text-sm text-neutral-600 leading-relaxed">{{ item.description }}</p>
        </div>

        <!-- Stats -->
        <div class="flex gap-4 text-sm text-neutral-500">
          <span>👁 {{ item.views_count }} views</span>
          <span>💬 {{ item.enquiries_count }} enquiries</span>
        </div>
      </div>

      <!-- Enquire button (sticky) -->
      <div v-if="!item.is_sold" class="sticky bottom-0 bg-white border-t border-neutral-100 p-4">
        <button @click="startEnquiry" class="w-full py-3.5 rounded-2xl bg-primary-600 text-white font-bold text-base active:scale-95 transition">
          Enquire About This Item
        </button>
      </div>
    </template>

    <!-- Enquiry Modal -->
    <div v-if="showEnquiryModal" class="fixed inset-0 bg-black/50 flex items-end z-50">
      <div class="bg-white w-full rounded-t-3xl p-6 space-y-4">
        <h2 class="font-bold text-lg">Send Enquiry</h2>
        <p v-if="!ghostToken" class="text-sm text-neutral-600">You need to sign in to send an enquiry.</p>
        <template v-if="ghostToken">
          <textarea
            v-model="enquiryMessage"
            class="w-full border border-neutral-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            rows="4"
            placeholder="What would you like to know about this item?"
          ></textarea>
          <p v-if="enquiryError" class="text-red-500 text-sm">{{ enquiryError }}</p>
        </template>
        <div class="flex gap-3">
          <button @click="showEnquiryModal = false" class="flex-1 py-3 rounded-xl bg-neutral-100 text-sm font-medium">Cancel</button>
          <button v-if="ghostToken" @click="submitEnquiry" :disabled="submitting" class="flex-1 py-3 rounded-xl bg-primary-600 text-white font-medium text-sm">
            {{ submitting ? 'Sending…' : 'Send' }}
          </button>
          <button v-else @click="goToLogin" class="flex-1 py-3 rounded-xl bg-primary-600 text-white font-medium text-sm">
            Sign In
          </button>
        </div>
      </div>
    </div>

    <!-- Success toast -->
    <Transition name="fade">
      <div v-if="showSuccess" class="fixed top-5 inset-x-4 bg-green-600 text-white text-sm font-medium py-3 px-4 rounded-2xl text-center z-50 shadow-lg">
        Enquiry sent! The seller will respond soon.
      </div>
    </Transition>
  </PwaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import axios from 'axios'

const props = defineProps({ itemId: [Number, String] })

const item = ref(null)
const loading = ref(true)
const currentImage = ref(null)
const inCart = ref(false)
const cartLoading = ref(false)
const cartItemId = ref(null)

const showEnquiryModal = ref(false)
const enquiryMessage = ref('')
const enquiryError = ref('')
const submitting = ref(false)
const showSuccess = ref(false)

const ghostToken = computed(() => localStorage.getItem('ghost_token'))

async function fetchItem() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/v1/public/items/${props.itemId}`)
    item.value = data.data
    currentImage.value = item.value.images?.[0]?.image_url ?? null
    await checkCart()
  } finally {
    loading.value = false
  }
}

async function checkCart() {
  if (!ghostToken.value) return
  try {
    const { data } = await axios.get('/api/v1/public/cart', { headers: { Authorization: `Bearer ${ghostToken.value}` } })
    const cartEntry = (data.data ?? []).find(c => c.item?.id === item.value?.id)
    inCart.value = !!cartEntry
    cartItemId.value = cartEntry?.cart_id ?? null
  } catch {}
}

async function toggleCart() {
  if (!ghostToken.value) { goToLogin(); return }
  cartLoading.value = true
  try {
    if (inCart.value) {
      await axios.delete(`/api/v1/public/cart/${cartItemId.value}`, { headers: { Authorization: `Bearer ${ghostToken.value}` } })
      inCart.value = false
      cartItemId.value = null
    } else {
      const { data } = await axios.post('/api/v1/public/cart', { item_id: item.value.id }, { headers: { Authorization: `Bearer ${ghostToken.value}` } })
      inCart.value = true
      cartItemId.value = data.data.cart_id
    }
  } finally {
    cartLoading.value = false
  }
}

function startEnquiry() { showEnquiryModal.value = true; enquiryMessage.value = ''; enquiryError.value = '' }
function goToLogin() { router.visit('/app/repo/auth/login', { data: { redirect: `/app/repo/${props.itemId}` } }) }

async function submitEnquiry() {
  if (!enquiryMessage.value.trim()) { enquiryError.value = 'Please enter a message.'; return }
  submitting.value = true
  enquiryError.value = ''
  try {
    await axios.post(`/api/v1/public/items/${props.itemId}/enquire`, { message: enquiryMessage.value }, { headers: { Authorization: `Bearer ${ghostToken.value}` } })
    showEnquiryModal.value = false
    showSuccess.value = true
    setTimeout(() => showSuccess.value = false, 4000)
    item.value.enquiries_count++
  } catch (e) {
    enquiryError.value = e.response?.data?.message ?? 'Failed to send. Try again.'
  } finally {
    submitting.value = false
  }
}

function formatNum(n) { return Number(n).toLocaleString() }
onMounted(() => fetchItem())
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s, transform 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
