<template>
  <div class="min-h-screen bg-gradient-to-br from-neutral-50 to-red-50/20 flex items-center justify-center p-4">
    <div class="w-full max-w-md text-center">

      <!-- Icon -->
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl shadow-lg mb-6"
        :class="isCancelled ? 'bg-gradient-to-br from-neutral-500 to-neutral-600 shadow-neutral-200' : 'bg-gradient-to-br from-red-500 to-red-600 shadow-red-200'">
        <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path v-if="isCancelled" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
      </div>

      <h1 class="text-2xl font-bold text-neutral-900 mb-2">
        {{ isCancelled ? 'Account Cancelled' : 'Account Suspended' }}
      </h1>

      <p class="text-neutral-500 text-sm mb-1">
        <span class="font-semibold text-neutral-700">{{ tenantName }}</span>
        {{ isCancelled
          ? ' has been cancelled and is no longer accessible.'
          : ' has been suspended. Access is temporarily restricted.' }}
      </p>

      <p class="text-neutral-400 text-xs mb-8">
        {{ isCancelled
          ? 'If you believe this is a mistake, contact support.'
          : 'Please contact support to reinstate your account.' }}
      </p>

      <!-- Contact CTA -->
      <a href="mailto:support@lendr.app"
        class="inline-flex items-center gap-2 font-semibold px-6 py-3 rounded-xl text-sm transition shadow-sm mb-4"
        :class="isCancelled
          ? 'bg-neutral-800 hover:bg-neutral-700 text-white shadow-neutral-200'
          : 'bg-red-600 hover:bg-red-700 text-white shadow-red-200'">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        Contact Support
      </a>

      <div>
        <Link :href="logoutRoute" method="post" as="button"
          class="text-sm text-neutral-400 hover:text-neutral-600 transition">
          Sign out
        </Link>
      </div>

    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  status:     { type: String, default: 'suspended' },
  tenantName: { type: String, default: '' },
})

const isCancelled = computed(() => props.status === 'cancelled')

// Use the correct logout route (portal vs subdomain)
const page        = usePage()
const isPortal    = computed(() => !!page.props.tenant?.id && window.location.pathname.startsWith('/portal'))
const logoutRoute = computed(() => isPortal.value ? route('portal.logout') : route('logout'))
</script>
