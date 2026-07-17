<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 flex items-center justify-center p-4">
    <div class="w-full max-w-md">

      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center gap-3 mb-2">
          <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg">
            <span class="text-primary-700 font-black text-lg">L</span>
          </div>
          <span class="text-white font-black text-3xl tracking-tight">LENDR</span>
        </div>
        <p class="text-primary-200 text-sm">Loan Management Platform</p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-2xl p-8">

        <!-- Sent state -->
        <template v-if="form.recentlySuccessful || status">
          <div class="text-center">
            <div class="w-14 h-14 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </div>
            <h2 class="text-lg font-bold text-neutral-900 mb-2">Check your inbox</h2>
            <p class="text-sm text-neutral-500 mb-6">{{ status }}</p>
            <a :href="loginHref" class="text-sm text-primary-600 hover:text-primary-800 font-medium">← Back to sign in</a>
          </div>
        </template>

        <!-- Form state -->
        <template v-else>
          <h1 class="text-xl font-bold text-neutral-900 mb-1">Forgot your password?</h1>
          <p class="text-neutral-500 text-sm mb-6">
            Enter your {{ isPortal ? 'workspace and ' : '' }}email address and we'll send you a reset link.
          </p>

          <form @submit.prevent="submit" class="space-y-4">

            <!-- Workspace (portal only) -->
            <div v-if="isPortal">
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Workspace</label>
              <input
                v-model="form.workspace"
                type="text"
                required
                autocomplete="organization"
                placeholder="your-workspace-id"
                class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
                :class="{ 'border-red-400 focus:ring-red-400': form.errors.workspace }"
              />
              <p v-if="form.errors.workspace" class="mt-1.5 text-xs text-red-600">{{ form.errors.workspace }}</p>
            </div>

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Email address</label>
              <input
                v-model="form.email"
                type="email"
                required
                autocomplete="email"
                placeholder="you@company.com"
                class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
                :class="{ 'border-red-400 focus:ring-red-400': form.errors.email }"
              />
              <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <button
              type="submit"
              :disabled="form.processing"
              class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2.5 rounded-lg text-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span v-if="form.processing" class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Sending…
              </span>
              <span v-else>Send reset link</span>
            </button>
          </form>

          <p class="mt-6 text-center text-sm text-neutral-500">
            Remember your password?
            <a :href="loginHref" class="text-primary-600 hover:text-primary-800 font-medium">Sign in</a>
          </p>
        </template>
      </div>

      <p class="text-center text-primary-300 text-xs mt-6">
        Don't have an account?
        <a :href="route('onboarding')" class="underline hover:text-white transition">Create one free</a>
        &nbsp;&middot;&nbsp;
        LENDR &copy; {{ year }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  isPortal: { type: Boolean, default: false },
  status:   { type: String,  default: null },
})

const year = new Date().getFullYear()

const form = useForm({
  workspace: '',
  email:     '',
})

const loginHref = computed(() =>
  props.isPortal ? route('portal.login') : route('login')
)

const status = computed(() => usePage().props.flash?.status ?? props.status)

function submit() {
  if (props.isPortal) {
    form.post(route('portal.password.email'), { preserveScroll: true })
  } else {
    form.post(route('password.email'), { preserveScroll: true })
  }
}
</script>
