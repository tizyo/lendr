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

      <!-- Flash success (e.g. after password reset) -->
      <div v-if="flash.success" class="mb-4 p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800 text-center">
        {{ flash.success }}
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <h1 class="text-xl font-bold text-neutral-900 mb-1">Welcome back</h1>
        <p class="text-neutral-500 text-sm mb-6">
          {{ isPortal ? 'Sign in to your workspace' : 'Sign in to your staff account' }}
        </p>

        <form @submit.prevent="submit" class="space-y-4">

          <!-- Workspace (portal only) -->
          <div v-if="isPortal">
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">Workspace</label>
            <input
              v-model="form.workspace"
              type="text"
              autocomplete="organization"
              required
              class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
              :class="{ 'border-red-400 focus:ring-red-400': errors.workspace }"
              placeholder="your-workspace-id"
            />
            <p v-if="errors.workspace" class="mt-1.5 text-xs text-red-600">
              {{ errors.workspace }}
              <!-- Unverified workspace: show resend link -->
              <template v-if="workspaceUnverified">
                &mdash;
                <button type="button" @click="resendVerification"
                  class="underline font-medium hover:text-red-800">
                  Resend verification email
                </button>
              </template>
            </p>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">Email address</label>
            <input
              v-model="form.email"
              type="email"
              autocomplete="email"
              required
              class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
              :class="{ 'border-red-400 focus:ring-red-400': errors.email }"
              placeholder="you@company.com"
            />
            <p v-if="errors.email" class="mt-1.5 text-xs text-red-600">
              {{ errors.email }}
              <!-- Unverified subdomain workspace: show resend link -->
              <template v-if="emailUnverified">
                &mdash;
                <button type="button" @click="resendVerification"
                  class="underline font-medium hover:text-red-800">
                  Resend verification email
                </button>
              </template>
            </p>
          </div>

          <!-- Password -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-sm font-medium text-neutral-700">Password</label>
              <a :href="forgotPasswordHref" class="text-xs text-primary-600 hover:text-primary-800 font-medium">
                Forgot password?
              </a>
            </div>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition pr-10"
                :class="{ 'border-red-400 focus:ring-red-400': errors.password }"
                placeholder="••••••••"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 transition"
              >
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
              </button>
            </div>
            <p v-if="errors.password" class="mt-1.5 text-xs text-red-600">{{ errors.password }}</p>
          </div>

          <!-- Remember me -->
          <div class="flex items-center">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.remember" type="checkbox" class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500" />
              <span class="text-sm text-neutral-600">Remember me</span>
            </label>
          </div>

          <!-- Resend sent feedback -->
          <div v-if="resendSent" class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800">
            Verification email resent — please check your inbox.
          </div>

          <!-- Submit -->
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
              Signing in…
            </span>
            <span v-else>Sign in</span>
          </button>
        </form>

        <!-- Sign up link (portal only — subdomain users are invited by their admin) -->
        <p v-if="isPortal" class="mt-5 text-center text-sm text-neutral-500">
          New to LENDR?
          <a :href="route('onboarding')" class="text-primary-600 hover:text-primary-800 font-medium">Create a free account</a>
        </p>
      </div>

      <p class="text-center text-primary-300 text-xs mt-6">
        LENDR &copy; {{ year }} &middot; RISKCAPE Enterprises Limited
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  isPortal: { type: Boolean, default: false },
})

const showPassword = ref(false)
const resendSent   = ref(false)
const year         = new Date().getFullYear()

const form = useForm({
  workspace: '',
  email:     '',
  password:  '',
  remember:  false,
})

const errors = computed(() => form.errors)

const flash = computed(() => usePage().props.flash ?? {})

const forgotPasswordHref = computed(() =>
  props.isPortal ? route('portal.password.request') : route('password.request')
)

const UNVERIFIED_MSG = 'not yet active'

// Show resend link when workspace error mentions "not yet active"
const workspaceUnverified = computed(() =>
  props.isPortal && (errors.value.workspace ?? '').toLowerCase().includes(UNVERIFIED_MSG)
)

// Show resend link when email error on subdomain login mentions "not yet active"
const emailUnverified = computed(() =>
  !props.isPortal && (errors.value.email ?? '').toLowerCase().includes(UNVERIFIED_MSG)
)

async function resendVerification() {
  resendSent.value = false
  // Determine which email to use for resend
  const email = props.isPortal ? form.email : form.email
  if (!email) return

  try {
    await axios.post(route('onboarding.resend-verification'), { email })
    resendSent.value = true
  } catch {
    // Fail silently — error is not user-actionable
  }
}

function submit() {
  if (props.isPortal) {
    form.post(route('portal.login.post'), {
      onFinish: () => form.reset('password'),
    })
  } else {
    form.post(route('login.post'), {
      onFinish: () => form.reset('password'),
    })
  }
}
</script>
