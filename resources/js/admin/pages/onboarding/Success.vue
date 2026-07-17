<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 flex items-center justify-center p-4">
    <div class="w-full max-w-lg">

      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center gap-3 mb-2">
          <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg">
            <span class="text-primary-700 font-black text-lg">L</span>
          </div>
          <span class="text-white font-black text-3xl tracking-tight">LENDR</span>
        </div>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">

        <!-- ── EMAIL VERIFICATION STATE ─────────────────────────────── -->
        <template v-if="needsVerification">
          <!-- Envelope icon -->
          <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
          </div>

          <h1 class="text-2xl font-bold text-neutral-900 mb-2">Check your email</h1>
          <p class="text-neutral-500 text-sm mb-6">
            We've sent a verification link to <strong class="text-neutral-800">{{ adminEmail }}</strong>.
            Click the link to activate your <strong>{{ orgName }}</strong> workspace.
          </p>

          <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-left mb-6">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
              <div class="text-sm text-amber-800 space-y-1">
                <p class="font-semibold">Important — do not skip this step</p>
                <ul class="text-xs text-amber-700 space-y-0.5 list-disc list-inside">
                  <li>Your account is locked until email is verified</li>
                  <li>The verification link expires in <strong>24 hours</strong></li>
                  <li>Check your spam/junk folder if you don't see it</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Login URL reminder (for after verification) -->
          <div class="bg-neutral-50 rounded-xl p-4 text-left mb-6">
            <p class="text-xs text-neutral-500 font-medium uppercase tracking-wide mb-1">After verification, log in at</p>
            <p class="text-sm font-semibold text-neutral-800 break-all">{{ loginUrl }}</p>
            <p v-if="isSharedPortal" class="text-xs text-neutral-400 mt-1">
              Use workspace ID: <span class="font-semibold text-neutral-600">{{ slug }}</span>
            </p>
          </div>

          <!-- Resend controls -->
          <div class="mt-2 text-center">
            <p v-if="resendSent" class="text-sm text-emerald-700 font-medium mb-2">
              ✓ New verification email sent — check your inbox.
            </p>
            <p class="text-xs text-neutral-400">
              Didn't receive it?
              <button type="button" @click="resend"
                :disabled="resending"
                class="text-emerald-700 font-medium underline hover:text-emerald-900 disabled:opacity-50">
                {{ resending ? 'Sending…' : 'Resend verification email' }}
              </button>
              or check your spam folder.
            </p>
          </div>
        </template>

        <!-- ── ALREADY VERIFIED / DIRECT SUCCESS STATE ────────────────── -->
        <template v-else>
          <!-- Success icon -->
          <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
          </div>

          <h1 class="text-2xl font-bold text-neutral-900 mb-2">Account created!</h1>
          <p class="text-neutral-500 text-sm mb-6">
            <strong>{{ orgName }}</strong> is ready. Your trial starts now.
          </p>

          <!-- Details card -->
          <div class="bg-neutral-50 rounded-xl p-5 text-left mb-6 space-y-3">

            <!-- Login URL -->
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                </svg>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-neutral-500 font-medium uppercase tracking-wide mb-0.5">Your login URL</p>
                <p class="text-sm font-semibold text-neutral-800 break-all">{{ loginUrl }}</p>
              </div>
            </div>

            <!-- Workspace (shared portal only) -->
            <div v-if="isSharedPortal" class="flex items-start gap-3">
              <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-neutral-500 font-medium uppercase tracking-wide mb-0.5">Workspace ID</p>
                <p class="text-sm font-semibold text-neutral-800">{{ slug }}</p>
                <p class="text-xs text-neutral-400 mt-0.5">Enter this when logging in at the shared portal</p>
              </div>
            </div>

            <!-- Admin email -->
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-neutral-500 font-medium uppercase tracking-wide mb-0.5">Admin email</p>
                <p class="text-sm font-semibold text-neutral-800">{{ adminEmail }}</p>
              </div>
            </div>

            <!-- Local dev note (custom subdomain only) -->
            <div v-if="!isSharedPortal" class="flex items-start gap-3">
              <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-yellow-700 font-medium uppercase tracking-wide mb-0.5">Local dev note</p>
                <p class="text-xs text-neutral-600 leading-relaxed">
                  Add <code class="bg-neutral-200 px-1 rounded text-xs">127.0.0.1 {{ subdomainHost }}</code>
                  to your <code class="bg-neutral-200 px-1 rounded text-xs">hosts</code> file to access locally.
                </p>
              </div>
            </div>
          </div>

          <a
            :href="loginUrl"
            class="inline-flex items-center justify-center gap-2 w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
          >
            Go to login
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
          </a>

          <p class="mt-4 text-xs text-neutral-400">
            Save your login URL — you'll need it every time you sign in.
          </p>
        </template>

      </div>

      <p class="text-center text-primary-300 text-xs mt-6">
        LENDR &copy; {{ year }} &middot; RISKCAPE Enterprises Limited
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  slug:              { type: String,  default: '' },
  plan:              { type: String,  default: 'starter' },
  loginUrl:          { type: String,  default: '' },
  orgName:           { type: String,  default: '' },
  adminEmail:        { type: String,  default: '' },
  needsVerification: { type: Boolean, default: false },
})

const year       = new Date().getFullYear()
const resending  = ref(false)
const resendSent = ref(false)

const isSharedPortal = computed(() => ['starter', 'trial'].includes(props.plan))

const subdomainHost = computed(() => {
  try {
    return new URL(props.loginUrl).hostname
  } catch {
    return ''
  }
})

async function resend() {
  if (resending.value || !props.adminEmail) return
  resending.value = true
  resendSent.value = false
  try {
    await axios.post(route('onboarding.resend-verification'), { email: props.adminEmail })
    resendSent.value = true
  } finally {
    resending.value = false
  }
}
</script>
