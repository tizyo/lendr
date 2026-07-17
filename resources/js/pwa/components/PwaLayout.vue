<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Header -->
    <header v-if="showHeader" class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3 shrink-0">
      <button v-if="showBack" @click="goBack" class="p-1.5 -ml-1.5 rounded-lg text-gray-500 hover:bg-gray-100">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <h1 class="text-base font-semibold text-gray-900 truncate">{{ title }}</h1>
        <p v-if="subtitle" class="text-xs text-gray-500 truncate">{{ subtitle }}</p>
      </div>
      <slot name="header-right" />
      <!-- Notification bell -->
      <Link v-if="showNav" :href="route('pwa.notifications')" class="relative p-1.5 rounded-lg text-gray-500 hover:bg-gray-100">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span
          v-if="unreadCount > 0"
          class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 rounded-full text-[9px] text-white font-bold flex items-center justify-center leading-none"
        >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
      </Link>
    </header>

    <!-- Content -->
    <main class="flex-1 overflow-y-auto">
      <slot />
    </main>

    <!-- Bottom nav (only when authenticated) -->
    <nav v-if="showNav" class="bg-white border-t border-gray-200 flex shrink-0 safe-bottom">
      <Link
        v-for="item in navItems"
        :key="item.route"
        :href="route(item.route)"
        class="flex-1 flex flex-col items-center justify-center py-2 gap-0.5 text-xs transition"
        :class="isActive(item.route) ? 'text-emerald-600' : 'text-gray-400 hover:text-gray-600'"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="item.icon"></svg>
        <span>{{ item.label }}</span>
      </Link>
    </nav>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  title:      { type: String, default: 'LENDR' },
  subtitle:   { type: String, default: null },
  showBack:   { type: Boolean, default: false },
  showHeader: { type: Boolean, default: true },
  showNav:    { type: Boolean, default: true },
})

const page = usePage()
const unreadCount = ref(0)

// Fetch unread count on mount (silent — no redirect on fail)
onMounted(async () => {
  if (!props.showNav) return
  try {
    const { data } = await axios.get('/api/v1/me/notifications')
    unreadCount.value = data.data?.unread_count ?? 0
  } catch {
    // not authenticated or network error — bell stays empty
  }
})

function goBack() {
  window.history.back()
}

function isActive(routeName) {
  try {
    return page.url.startsWith(route(routeName).replace(window.location.origin, ''))
  } catch {
    return false
  }
}

const navItems = [
  {
    route: 'pwa.dashboard',
    label: 'Home',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
  },
  {
    route: 'pwa.loans',
    label: 'My Loans',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
  },
  {
    route: 'pwa.payments',
    label: 'Payments',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>',
  },
  {
    route: 'pwa.profile',
    label: 'Profile',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
  },
]
</script>

<style scoped>
.safe-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
