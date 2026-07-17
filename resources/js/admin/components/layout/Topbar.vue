<template>
  <header class="bg-white border-b border-neutral-200 px-6 py-3 flex items-center justify-between shrink-0 z-10">
    <!-- Left: breadcrumb / page title -->
    <div class="flex items-center gap-3">
      <button
        @click="$emit('toggle-sidebar')"
        class="lg:hidden p-1.5 rounded-lg text-neutral-500 hover:text-neutral-700 hover:bg-neutral-100 transition"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

      <div v-if="$slots.title">
        <slot name="title" />
      </div>
    </div>

    <!-- Right: notifications + user menu -->
    <div class="flex items-center gap-2">
      <!-- Dark mode toggle -->
      <button
        @click="ui.toggleDarkMode()"
        class="p-2 rounded-lg text-neutral-500 hover:text-neutral-700 hover:bg-neutral-100 transition"
        :title="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
      >
        <svg v-if="ui.darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
      </button>

      <!-- Notifications bell -->
      <div class="relative">
        <button
          class="relative p-2 rounded-lg text-neutral-500 hover:text-neutral-700 hover:bg-neutral-100 transition"
          @click="toggleNotifications"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span
            v-if="unreadCount > 0"
            class="absolute top-1.5 right-1.5 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"
          >
            {{ unreadCount > 9 ? '9+' : unreadCount }}
          </span>
        </button>

        <Transition name="dropdown">
          <div
            v-if="showNotifications"
            v-click-outside="() => showNotifications = false"
            class="absolute right-0 top-full mt-1 w-80 bg-white rounded-xl shadow-lg border border-neutral-200 z-50 overflow-hidden"
          >
            <div class="flex items-center justify-between px-4 py-3 border-b border-neutral-100">
              <p class="text-sm font-semibold text-neutral-800">Notifications</p>
              <button
                v-if="notifications.some(n => !n.is_read)"
                @click="markAllRead"
                class="text-xs text-primary-600 hover:underline"
              >Mark all read</button>
            </div>
            <div v-if="loadingNotifs" class="px-4 py-6 text-center text-sm text-neutral-400">Loading…</div>
            <div v-else-if="!notifications.length" class="px-4 py-6 text-center text-sm text-neutral-400">
              No notifications yet.
            </div>
            <div v-else class="max-h-80 overflow-y-auto divide-y divide-neutral-50">
              <button
                v-for="n in notifications"
                :key="n.id"
                @click="readNotification(n)"
                class="w-full text-left px-4 py-3 hover:bg-neutral-50 transition flex items-start gap-3"
                :class="!n.is_read ? 'bg-primary-50/40' : ''"
              >
                <span class="mt-1.5 w-2 h-2 rounded-full shrink-0" :class="n.is_read ? 'bg-neutral-200' : 'bg-primary-500'"></span>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-neutral-800 truncate">{{ n.title }}</p>
                  <p class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ n.body }}</p>
                  <p class="text-[11px] text-neutral-400 mt-1">{{ formatTime(n.created_at) }}</p>
                </div>
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <!-- User dropdown -->
      <div class="relative">
        <button
          @click="showUserMenu = !showUserMenu"
          class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-neutral-100 transition"
        >
          <div class="w-7 h-7 rounded-full bg-primary-600 text-white text-xs font-bold flex items-center justify-center">
            {{ initials }}
          </div>
          <div class="hidden md:block text-left">
            <p class="text-sm font-medium text-neutral-800 leading-none">{{ auth?.user?.name }}</p>
            <p class="text-xs text-neutral-500 mt-0.5 capitalize">{{ auth?.user?.role?.replace('_', ' ') }}</p>
          </div>
          <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>

        <Transition name="dropdown">
          <div
            v-if="showUserMenu"
            v-click-outside="() => showUserMenu = false"
            class="absolute right-0 top-full mt-1 w-52 bg-white rounded-xl shadow-lg border border-neutral-200 py-1 z-50"
          >
            <div class="px-4 py-2.5 border-b border-neutral-100">
              <p class="text-sm font-medium text-neutral-800">{{ auth?.user?.name }}</p>
              <p class="text-xs text-neutral-500 truncate">{{ auth?.user?.email }}</p>
            </div>
            <Link :href="route('staff.profile')" class="flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 transition">
              Profile settings
            </Link>
            <button
              @click="logout"
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition"
            >
              Sign out
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import axios from 'axios'
import { useUiStore } from '@/admin/stores/ui.js'

defineProps({ sidebarCollapsed: Boolean })
defineEmits(['toggle-sidebar'])

const page = usePage()
const auth = computed(() => page.props.auth)
const ui   = useUiStore()
const showUserMenu      = ref(false)
const showNotifications = ref(false)
const notifications     = ref([])
const loadingNotifs     = ref(false)
const unreadCount       = computed(() => ui.unreadCount)

// Seed the badge from the server-rendered count on first load
onMounted(() => {
  const serverCount = auth.value?.user?.unread_notifications ?? 0
  if (serverCount > 0) ui.setUnreadCount(serverCount)
})

const initials = computed(() => {
  const name = auth.value?.user?.name || ''
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

async function toggleNotifications() {
  showNotifications.value = !showNotifications.value
  if (showNotifications.value && !notifications.value.length) {
    await fetchNotifications()
  }
}

async function fetchNotifications() {
  loadingNotifs.value = true
  try {
    const { data } = await axios.get(route('api.v1.notifications.index'))
    notifications.value = data.data.notifications ?? []
    ui.setUnreadCount(data.data.unread_count ?? 0)
  } finally {
    loadingNotifs.value = false
  }
}

async function readNotification(n) {
  if (!n.is_read) {
    await axios.put(route('api.v1.notifications.read', n.id))
    n.is_read = true
    ui.setUnreadCount(Math.max(0, unreadCount.value - 1))
  }
}

async function markAllRead() {
  await axios.put(route('api.v1.notifications.read-all'))
  notifications.value.forEach(n => { n.is_read = true })
  ui.clearUnread()
}

function formatTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const diff = Math.floor((Date.now() - d) / 1000)
  if (diff < 60)   return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function logout() {
  router.post(route('logout'))
}
</script>

<style scoped>
.dropdown-enter-active { transition: all 0.1s ease-out; }
.dropdown-leave-active { transition: all 0.075s ease-in; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-4px) scale(0.98); }
</style>
