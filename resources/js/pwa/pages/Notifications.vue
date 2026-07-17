<template>
  <PwaLayout title="Notifications" :show-back="true">
    <div class="px-4 py-6 space-y-3 max-w-lg mx-auto">

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <svg class="w-8 h-8 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
      </div>

      <!-- Empty -->
      <div v-else-if="!notifications.length" class="py-20 text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
        </div>
        <p class="text-gray-500 text-sm">No notifications yet.</p>
      </div>

      <template v-else>
        <!-- Mark all read -->
        <div v-if="unreadCount > 0" class="flex justify-between items-center mb-1">
          <p class="text-xs text-gray-500">{{ unreadCount }} unread</p>
          <button
            @click="markAllRead"
            class="text-xs text-emerald-600 font-medium"
          >Mark all as read</button>
        </div>

        <!-- Notification cards -->
        <div
          v-for="n in notifications"
          :key="n.id"
          @click="markRead(n)"
          class="bg-white rounded-xl border shadow-sm p-4 cursor-pointer transition"
          :class="n.is_read ? 'border-gray-100' : 'border-emerald-200 bg-emerald-50/30'"
        >
          <div class="flex items-start gap-3">
            <!-- Unread dot -->
            <div class="mt-1 shrink-0">
              <div
                class="w-2.5 h-2.5 rounded-full"
                :class="n.is_read ? 'bg-gray-200' : 'bg-emerald-500'"
              ></div>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-semibold text-gray-900">{{ n.title }}</p>
                <span class="text-xs text-gray-400 shrink-0">{{ n.created_at }}</span>
              </div>
              <p v-if="n.body" class="text-xs text-gray-600 mt-0.5 leading-relaxed">{{ n.body }}</p>
              <!-- Type badge -->
              <span class="mt-1.5 inline-block px-2 py-0.5 rounded-full text-[10px] font-medium" :class="typeBadge(n.type)">
                {{ typeLabel(n.type) }}
              </span>
            </div>
          </div>
        </div>
      </template>

    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

const auth = usePwaAuthStore()
const notifications = ref([])
const unreadCount   = ref(0)
const loading       = ref(false)

const typeLabel = (t) => ({
  approved:         'Approved',
  disbursed:        'Disbursed',
  payment_received: 'Payment',
  overdue:          'Overdue',
  defaulted:        'Defaulted',
  upcoming_payment: 'Reminder',
}[t] ?? t)

const typeBadge = (t) => ({
  approved:         'bg-emerald-100 text-emerald-700',
  disbursed:        'bg-blue-100 text-blue-700',
  payment_received: 'bg-emerald-100 text-emerald-700',
  overdue:          'bg-amber-100 text-amber-700',
  defaulted:        'bg-red-100 text-red-700',
  upcoming_payment: 'bg-purple-100 text-purple-700',
}[t] ?? 'bg-gray-100 text-gray-600')

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/me/notifications')
    notifications.value = data.data?.notifications ?? []
    unreadCount.value   = data.data?.unread_count  ?? 0
  } catch {
    auth.clearAuth()
    router.visit(route('pwa.auth.login'))
  } finally {
    loading.value = false
  }
})

async function markRead(n) {
  if (n.is_read) return
  try {
    await axios.put(`/api/v1/me/notifications/${n.id}/read`)
    n.is_read = true
    n.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch {
    // non-critical
  }
}

async function markAllRead() {
  try {
    await axios.put('/api/v1/me/notifications/read-all')
    notifications.value.forEach(n => { n.is_read = true })
    unreadCount.value = 0
  } catch {
    // non-critical
  }
}
</script>
