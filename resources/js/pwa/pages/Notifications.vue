<template>
  <PwaLayout title="Notifications" :show-back="true">
    <div class="px-4 py-6 space-y-3 max-w-lg mx-auto">

      <!-- Push notifications opt-in -->
      <div v-if="push.supported && push.enabledOnServer.value && !push.subscribed.value"
           class="rounded-2xl bg-gray-900 text-white p-4 flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold">Get payment reminders on your phone</p>

          <template v-if="push.needsInstall.value">
            <p class="text-xs text-white/70 mt-1 leading-relaxed">
              On iPhone, first tap <strong class="text-white">Share</strong> → <strong class="text-white">Add to Home Screen</strong>,
              then open the app from your home screen and turn notifications on here.
            </p>
          </template>
          <template v-else-if="push.permission.value === 'denied'">
            <p class="text-xs text-white/70 mt-1 leading-relaxed">
              Notifications are blocked for this app. Allow them in your browser or phone settings, then come back.
            </p>
          </template>
          <template v-else>
            <p class="text-xs text-white/70 mt-1">Due dates, payment receipts and loan updates — even when the app is closed.</p>
            <button @click="push.enable()" :disabled="push.busy.value"
                    class="mt-3 px-4 py-2 rounded-lg bg-white text-gray-900 text-sm font-semibold disabled:opacity-60">
              {{ push.busy.value ? 'Turning on…' : 'Turn on notifications' }}
            </button>
            <p v-if="push.error.value" class="text-xs text-red-300 mt-2">{{ push.error.value }}</p>
          </template>
        </div>
      </div>

      <div v-else-if="push.subscribed.value" class="flex items-center justify-between text-xs text-gray-500 px-1">
        <span>Push notifications are on for this phone.</span>
        <button @click="push.disable()" :disabled="push.busy.value" class="text-gray-400 underline">Turn off</button>
      </div>

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
import { usePushNotifications } from '@/pwa/composables/usePushNotifications.js'

const auth = usePwaAuthStore()
const push = usePushNotifications()
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
  kyc_verified:     'Verified',
  kyc_rejected:     'Action needed',
  kyc_expired:      'Expired',
}[t] ?? t)

const typeBadge = (t) => ({
  approved:         'bg-emerald-100 text-emerald-700',
  disbursed:        'bg-blue-100 text-blue-700',
  payment_received: 'bg-emerald-100 text-emerald-700',
  overdue:          'bg-amber-100 text-amber-700',
  defaulted:        'bg-red-100 text-red-700',
  upcoming_payment: 'bg-purple-100 text-purple-700',
  kyc_verified:     'bg-emerald-100 text-emerald-700',
  kyc_rejected:     'bg-amber-100 text-amber-700',
  kyc_expired:      'bg-amber-100 text-amber-700',
}[t] ?? 'bg-gray-100 text-gray-600')

onMounted(async () => {
  push.refresh()
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/me/notifications')
    notifications.value = data.data?.notifications ?? []
    unreadCount.value   = data.data?.unread_count  ?? 0
  } catch (error) {
    auth.handleAuthError(error) // only a 401 signs out; offline keeps cached data
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
