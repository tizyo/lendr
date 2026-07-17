<template>
  <PwaLayout title="My Enquiries">
    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin w-8 h-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
      </svg>
    </div>

    <div v-else-if="!ghostToken" class="px-4 py-16 text-center text-neutral-500 space-y-4">
      <p class="text-4xl">💬</p>
      <p class="font-medium">Sign in to view your enquiries</p>
      <button @click="goToLogin" class="px-6 py-3 rounded-2xl bg-primary-600 text-white font-medium text-sm">Sign In</button>
    </div>

    <div v-else-if="enquiries.length === 0" class="px-4 py-16 text-center text-neutral-400">
      <p class="text-4xl mb-3">💬</p>
      <p class="font-medium">No enquiries yet</p>
      <p class="text-sm mt-1">Browse items and send your first enquiry.</p>
    </div>

    <div v-else class="px-4 py-4 space-y-3">
      <div v-for="enq in enquiries" :key="enq.id" class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <!-- Item info -->
        <div v-if="enq.item" class="flex items-center gap-3 p-4 border-b border-neutral-100 cursor-pointer" @click="goToItem(enq.item.id)">
          <img v-if="enq.item.image_url" :src="enq.item.image_url" class="w-12 h-12 rounded-xl object-cover bg-neutral-100" />
          <div v-else class="w-12 h-12 rounded-xl bg-neutral-100 flex items-center justify-center text-xl">📦</div>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-sm text-neutral-900 truncate">{{ enq.item.title }}</p>
            <p class="text-primary-600 text-sm font-bold">K {{ formatNum(enq.item.price) }}</p>
          </div>
          <span :class="statusClass(enq.status)" class="text-xs px-2.5 py-1 rounded-full font-medium capitalize shrink-0">{{ enq.status }}</span>
        </div>

        <!-- Enquiry -->
        <div class="p-4 space-y-3">
          <div>
            <p class="text-xs text-neutral-500 mb-1">Your enquiry · {{ formatDate(enq.created_at) }}</p>
            <p class="text-sm text-neutral-700 bg-neutral-50 rounded-xl p-3">{{ enq.message }}</p>
          </div>

          <!-- Reply -->
          <div v-if="enq.reply" class="bg-primary-50 rounded-xl p-3">
            <p class="text-xs text-primary-600 font-medium mb-1">Seller replied · {{ formatDate(enq.replied_at) }}</p>
            <p class="text-sm text-neutral-700">{{ enq.reply }}</p>
          </div>
          <div v-else class="text-xs text-neutral-400 italic">Awaiting reply…</div>
        </div>
      </div>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import axios from 'axios'

const enquiries = ref([])
const loading = ref(true)
const ghostToken = computed(() => localStorage.getItem('ghost_token'))

async function fetchEnquiries() {
  if (!ghostToken.value) { loading.value = false; return }
  try {
    const { data } = await axios.get('/api/v1/public/my-enquiries', { headers: { Authorization: `Bearer ${ghostToken.value}` } })
    enquiries.value = data.data ?? []
  } finally {
    loading.value = false
  }
}

function goToItem(id) { router.visit(`/app/repo/${id}`) }
function goToLogin() { router.visit('/app/repo/auth/login') }
function formatNum(n) { return Number(n).toLocaleString() }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '' }

function statusClass(s) {
  return { new: 'bg-amber-100 text-amber-700', read: 'bg-neutral-100 text-neutral-600', replied: 'bg-green-100 text-green-700' }[s] ?? 'bg-neutral-100 text-neutral-600'
}

onMounted(() => fetchEnquiries())
</script>
