<template>
  <div class="min-h-screen bg-neutral-50 flex">
    <!-- Sidebar -->
    <aside class="w-60 shrink-0 bg-neutral-900 text-white flex flex-col">
      <div class="px-6 py-5 border-b border-neutral-800">
        <p class="text-xs text-neutral-400 uppercase tracking-widest mb-0.5">Platform</p>
        <h1 class="text-lg font-bold text-white">LENDR Admin</h1>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-1">
        <Link
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition"
          :class="isActive(item.href)
            ? 'bg-white/10 text-white'
            : 'text-neutral-400 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="item.icon"></svg>
          {{ item.label }}
        </Link>
      </nav>

      <div class="px-4 py-4 border-t border-neutral-800">
        <button @click="logout" class="w-full text-left text-xs text-neutral-400 hover:text-red-400 transition px-3 py-2">
          Sign out
        </button>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="bg-white border-b border-neutral-200 px-8 py-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-neutral-900">{{ title }}</h2>
        <div class="text-sm text-neutral-500">{{ adminEmail }}</div>
      </header>
      <main class="flex-1 p-8 overflow-y-auto">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

defineProps({
  title: { type: String, default: 'Dashboard' },
})

const page        = usePage()
const landlordAuth = useLandlordAuth()
const adminEmail  = computed(() => landlordAuth.email)

const navItems = [
  {
    href: route('landlord.dashboard'),
    label: 'Dashboard',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>',
  },
  {
    href: route('landlord.tenants'),
    label: 'Tenants',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
  },
  {
    href: route('landlord.plan-configs'),
    label: 'Plan Config',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>',
  },
  {
    href: route('landlord.billing-settings'),
    label: 'Billing Settings',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>',
  },
  {
    href: route('landlord.support'),
    label: 'Support Tickets',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>',
  },
  {
    href: route('landlord.platform-settings'),
    label: 'Platform Settings',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
  },
  {
    href: route('landlord.featured-items'),
    label: 'Featured & Hot Deals',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
  },
]

function isActive(href) {
  return page.url === href.replace(window.location.origin, '')
}

async function logout() {
  await landlordAuth.logout()
  window.location.href = route('landlord.login')
}
</script>
