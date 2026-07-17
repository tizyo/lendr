<template>
  <div class="min-h-screen bg-gradient-to-br from-neutral-50 to-amber-50/30 flex items-center justify-center p-4">
    <div class="w-full max-w-md text-center">

      <!-- Icon -->
      <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-amber-400 to-orange-500 rounded-3xl shadow-lg shadow-orange-200 mb-6">
        <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>

      <h1 class="text-2xl font-bold text-neutral-900 mb-2">Free Trial Ended</h1>
      <p class="text-neutral-500 text-sm mb-1">
        The free trial for <span class="font-semibold text-neutral-700">{{ tenantName }}</span> expired on
        <span class="font-medium text-neutral-600">{{ trialEndedAt }}</span>.
      </p>
      <p class="text-neutral-400 text-xs mb-8">
        Your data is safe and preserved. Upgrade to restore full access immediately.
      </p>

      <!-- What's preserved -->
      <div class="bg-white border border-neutral-200 rounded-2xl p-5 mb-6 text-left">
        <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-3">What's preserved</p>
        <ul class="space-y-2 text-sm text-neutral-600">
          <li class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            All borrowers and loan records
          </li>
          <li class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            Payment history and audit log
          </li>
          <li class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            Settings, staff accounts and documents
          </li>
        </ul>
      </div>

      <a href="mailto:sales@lendr.app?subject=Upgrade request — {{ tenantName }}&body=Hi, I would like to upgrade {{ tenantName }} to a paid plan and restore access."
        class="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-semibold px-8 py-3 rounded-xl shadow-lg shadow-emerald-200 transition text-sm mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
        </svg>
        Upgrade &amp; Restore Access
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
import { Link } from '@inertiajs/vue3'

defineProps({
  tenantName:   { type: String, default: '' },
  trialEndedAt: { type: String, default: '' },
})

const logoutRoute = computed(() =>
  window.location.pathname.startsWith('/portal') ? route('portal.logout') : route('logout')
)
</script>
