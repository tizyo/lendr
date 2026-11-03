<template>
  <Head :title="title" />

  <div class="min-h-screen flex items-center justify-center px-4 py-12 bg-neutral-50 dark:bg-neutral-950" :style="{ '--brand': brand.color, '--brand-on': brand.on_color, '--brand-tint': brand.tint }">
    <!-- Soft brand glow -->
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
      <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[42rem] h-[42rem] rounded-full blur-3xl opacity-[0.12]" style="background: var(--brand)"></div>
    </div>

    <main class="relative w-full max-w-lg text-center">
      <img v-if="brand.is_tenant" :src="brand.logo" :alt="brand.name" class="h-10 w-auto mx-auto mb-10 object-contain" />
      <template v-else>
        <img src="/brand/lendr-logo.png" alt="LENDR" class="h-10 w-auto mx-auto mb-10 dark:hidden" />
        <img src="/brand/lendr-logo-white.png" alt="LENDR" class="h-10 w-auto mx-auto mb-10 hidden dark:block" />
      </template>

      <div class="bg-white dark:bg-neutral-900 rounded-3xl shadow-xl shadow-black/5 ring-1 ring-black/5 dark:ring-white/10 px-8 py-10 sm:px-12">
        <div class="mx-auto mb-6 w-16 h-16 rounded-2xl flex items-center justify-center" style="background: var(--brand-tint); color: var(--brand)">
          <component :is="icon" class="w-8 h-8" />
        </div>

        <p class="text-sm font-semibold tracking-widest uppercase" style="color: var(--brand)">Error {{ status }}</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">{{ title }}</h1>
        <p class="mt-3 text-base leading-relaxed text-neutral-600 dark:text-neutral-400">{{ message }}</p>

        <div class="mt-8 flex flex-col-reverse sm:flex-row gap-3 justify-center">
          <button
            v-if="canGoBack"
            type="button"
            @click="goBack"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-neutral-700 dark:text-neutral-200 bg-white dark:bg-neutral-800 ring-1 ring-neutral-300 dark:ring-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition"
          >
            <ArrowLeftIcon class="w-4 h-4" />
            Go back
          </button>
          <button
            v-if="status === 500 || status === 503 || status === 429"
            type="button"
            @click="reload"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-neutral-700 dark:text-neutral-200 bg-white dark:bg-neutral-800 ring-1 ring-neutral-300 dark:ring-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition"
          >
            <ArrowPathIcon class="w-4 h-4" />
            Try again
          </button>
          <a
            :href="homeUrl"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition hover:opacity-90"
            style="background: var(--brand); color: var(--brand-on)"
          >
            <HomeIcon class="w-4 h-4" />
            Take me home
          </a>
        </div>
      </div>

      <p class="mt-8 text-xs text-neutral-500 dark:text-neutral-500">{{ brand.name }}<template v-if="brand.is_tenant"> · Powered by LENDR</template></p>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import {
  ArrowLeftIcon, ArrowPathIcon, HomeIcon,
  MagnifyingGlassIcon, LockClosedIcon, ExclamationTriangleIcon,
  WrenchScrewdriverIcon, ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  status: { type: Number, required: true },
  title: { type: String, required: true },
  message: { type: String, required: true },
  homeUrl: { type: String, default: '/' },
  brand: {
    type: Object,
    default: () => ({ name: 'LENDR', logo: '/brand/lendr-logo.png', is_tenant: false, color: '#2563EB', on_color: '#FFFFFF', tint: '#EAF0FD' }),
  },
})

const icon = computed(() => ({
  403: LockClosedIcon,
  404: MagnifyingGlassIcon,
  429: ClockIcon,
  503: WrenchScrewdriverIcon,
}[props.status] ?? ExclamationTriangleIcon))

const canGoBack = typeof window !== 'undefined' && window.history.length > 1

const goBack = () => window.history.back()
const reload = () => window.location.reload()
</script>
