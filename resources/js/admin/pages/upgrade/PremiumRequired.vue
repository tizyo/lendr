<template>
  <div class="min-h-screen bg-gradient-to-br from-neutral-50 to-emerald-50/20 flex items-center justify-center p-4">
    <div class="w-full max-w-lg text-center">

      <!-- Lock icon -->
      <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-amber-400 to-orange-500 rounded-3xl shadow-lg shadow-orange-200 mb-6">
        <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>

      <h1 class="text-2xl font-bold text-neutral-900 mb-2">Premium Feature</h1>
      <p class="text-neutral-500 text-sm mb-1">
        <span class="font-semibold text-emerald-700">{{ feature }}</span> is not available on your current plan.
      </p>
      <p class="text-neutral-400 text-xs mb-8">
        {{ tenantName }} is on the <span class="font-medium text-neutral-600">{{ currentPlan }}</span> plan.
        Upgrade to unlock this feature.
      </p>

      <!-- Plan comparison cards -->
      <div class="grid grid-cols-2 gap-4 mb-8 text-left">

        <!-- Current (Starter) -->
        <div class="bg-white rounded-2xl border border-neutral-200 p-5">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">{{ currentPlan }}</span>
            <span class="text-xs bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded-full font-medium">Current</span>
          </div>
          <p class="text-xl font-bold text-neutral-800 mb-4">Free</p>
          <ul class="space-y-2 text-xs text-neutral-500">
            <li class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Up to 100 borrowers
            </li>
            <li class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Staff web portal
            </li>
            <li class="flex items-center gap-2 opacity-40">
              <svg class="w-3.5 h-3.5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              Borrower PWA
            </li>
            <li class="flex items-center gap-2 opacity-40">
              <svg class="w-3.5 h-3.5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              Custom subdomain
            </li>
          </ul>
        </div>

        <!-- Growth (upgrade target) -->
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-2xl p-5 text-white relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-bl-full"></div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-emerald-200 uppercase tracking-wide">Growth</span>
            <span class="text-xs bg-white/20 text-white px-2 py-0.5 rounded-full font-medium">Recommended</span>
          </div>
          <p class="text-xl font-bold text-white mb-4">$49<span class="text-sm font-normal text-emerald-200">/mo</span></p>
          <ul class="space-y-2 text-xs text-emerald-100">
            <li class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Up to 1,000 borrowers
            </li>
            <li class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Custom subdomain
            </li>
            <li class="flex items-center gap-2 font-semibold text-white">
              <svg class="w-3.5 h-3.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Borrower PWA ✦
            </li>
            <li class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Mobile money
            </li>
          </ul>
        </div>
      </div>

      <!-- CTA -->
      <a href="mailto:sales@lendr.app?subject=Upgrade request — {{ tenantName }}&body=Hi, I would like to upgrade {{ tenantName }} from {{ currentPlan }} to Growth plan."
        class="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-semibold px-8 py-3 rounded-xl shadow-lg shadow-emerald-200 transition text-sm mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
        </svg>
        Request Upgrade
      </a>

      <div>
        <Link :href="route('dashboard')" class="text-sm text-neutral-400 hover:text-neutral-600 transition">
          ← Back to dashboard
        </Link>
      </div>

    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  feature:     { type: String, default: 'This feature' },
  currentPlan: { type: String, default: 'Starter' },
  tenantName:  { type: String, default: '' },
})
</script>
