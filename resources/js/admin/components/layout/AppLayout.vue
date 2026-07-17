<template>
  <div class="flex h-screen overflow-hidden bg-neutral-50">
    <!-- Sidebar -->
    <Sidebar :collapsed="sidebarCollapsed" @toggle="sidebarCollapsed = !sidebarCollapsed" />

    <!-- Main content -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
      <!-- Topbar -->
      <Topbar
        :sidebar-collapsed="sidebarCollapsed"
        @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed"
      />

      <!-- Trial expiry countdown (≤7 days remaining) -->
      <div v-if="showTrialBanner"
        class="flex items-center justify-between gap-3 px-4 py-2.5 bg-gradient-to-r from-red-50 to-orange-50 border-b border-red-200 text-sm">
        <div class="flex items-center gap-2 min-w-0">
          <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span class="text-red-800 truncate">
            Your free trial ends in
            <strong>{{ trialDaysRemaining }} day{{ trialDaysRemaining === 1 ? '' : 's' }}</strong>.
            Upgrade now to keep full access.
          </span>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <a href="mailto:sales@lendr.app?subject=Upgrade request"
            class="text-xs font-semibold text-red-900 bg-red-200 hover:bg-red-300 px-3 py-1 rounded-lg transition whitespace-nowrap">
            Upgrade now
          </a>
          <button @click="trialNudgeDismissed = true" class="text-red-400 hover:text-red-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Starter plan upgrade nudge -->
      <div v-if="isStarterPlan && !nudgeDismissed"
        class="flex items-center justify-between gap-3 px-4 py-2.5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-200 text-sm">
        <div class="flex items-center gap-2 min-w-0">
          <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
          </svg>
          <span class="text-amber-800 truncate">
            You're on the <strong>Starter</strong> plan.
            Upgrade to <strong>Growth</strong> to unlock the Borrower PWA, custom subdomain &amp; more.
          </span>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <a href="mailto:sales@lendr.app?subject=Upgrade request"
            class="text-xs font-semibold text-amber-900 bg-amber-200 hover:bg-amber-300 px-3 py-1 rounded-lg transition whitespace-nowrap">
            Upgrade plan
          </a>
          <button @click="nudgeDismissed = true"
            class="text-amber-400 hover:text-amber-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-6">
        <!-- Flash notifications -->
        <FlashMessage />

        <!-- Page header slot -->
        <div v-if="$slots.header" class="mb-6">
          <slot name="header" />
        </div>

        <!-- Main slot -->
        <slot />
      </main>
    </div>

    <!-- Mobile sidebar overlay -->
    <Transition name="fade">
      <div
        v-if="mobileSidebarOpen"
        class="fixed inset-0 bg-black/50 z-40 lg:hidden"
        @click="mobileSidebarOpen = false"
      />
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import Sidebar from './Sidebar.vue'
import Topbar from './Topbar.vue'
import FlashMessage from '../ui/FlashMessage.vue'

const sidebarCollapsed  = ref(false)
const mobileSidebarOpen = ref(false)
const nudgeDismissed    = ref(false)

const tenant = computed(() => usePage().props.tenant)

const isStarterPlan = computed(() => {
  const plan = tenant.value?.plan
  return plan === 'starter' || plan === 'trial'
})

const trialDaysRemaining = computed(() => tenant.value?.trial_days_remaining ?? 0)
const isOnTrial          = computed(() => tenant.value?.status === 'trial')
const showTrialBanner    = computed(() => isOnTrial.value && trialDaysRemaining.value <= 7 && !trialNudgeDismissed.value)

const trialNudgeDismissed = ref(false)
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
