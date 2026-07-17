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
        <h1 class="text-xl font-bold text-neutral-900 mb-1">Set a new password</h1>
        <p class="text-neutral-500 text-sm mb-6">
          Choose a strong password for <strong>{{ email }}</strong>
        </p>

        <!-- General error -->
        <div v-if="form.errors.email" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          {{ form.errors.email }}
          <a :href="forgotHref" class="ml-1 underline font-medium">Request a new link</a>
        </div>

        <form @submit.prevent="submit" class="space-y-4">

          <!-- Password -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">
              New password <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="new-password"
                placeholder="At least 8 characters"
                class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition pr-10"
                :class="{ 'border-red-400 focus:ring-red-400': form.errors.password }"
              />
              <button type="button" @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="!showPassword" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
              </button>
            </div>
            <!-- Strength hints -->
            <div v-if="form.password" class="mt-2 space-y-1">
              <div v-for="rule in passwordRules" :key="rule.label" class="flex items-center gap-1.5 text-xs">
                <svg class="w-3.5 h-3.5 shrink-0" :class="rule.met ? 'text-emerald-500' : 'text-neutral-300'" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span :class="rule.met ? 'text-emerald-700' : 'text-neutral-400'">{{ rule.label }}</span>
              </div>
            </div>
            <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>

          <!-- Confirm password -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">
              Confirm password <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.password_confirmation"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="new-password"
              placeholder="Re-enter your password"
              class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
              :class="{ 'border-red-400': form.password_confirmation && form.password !== form.password_confirmation }"
            />
            <p v-if="form.password_confirmation && form.password !== form.password_confirmation"
               class="mt-1.5 text-xs text-red-600">Passwords do not match.</p>
          </div>

          <button
            type="submit"
            :disabled="form.processing || !canSubmit"
            class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2.5 rounded-lg text-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <span v-if="form.processing" class="flex items-center justify-center gap-2">
              <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              Resetting…
            </span>
            <span v-else>Reset password</span>
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-neutral-500">
          <a :href="forgotHref" class="text-primary-600 hover:text-primary-800 font-medium">Request a new link</a>
          &nbsp;&middot;&nbsp;
          <a :href="loginHref" class="text-primary-600 hover:text-primary-800 font-medium">Back to sign in</a>
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
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  token:     { type: String,  default: '' },
  email:     { type: String,  default: '' },
  workspace: { type: String,  default: '' },
  isPortal:  { type: Boolean, default: false },
})

const showPassword = ref(false)
const year = new Date().getFullYear()

const form = useForm({
  token:                 props.token,
  email:                 props.email,
  workspace:             props.workspace,
  password:              '',
  password_confirmation: '',
})

const loginHref  = computed(() => props.isPortal ? route('portal.login') : route('login'))
const forgotHref = computed(() => props.isPortal ? route('portal.password.request') : route('password.request'))

const passwordRules = computed(() => [
  { label: 'At least 8 characters',    met: form.password.length >= 8 },
  { label: 'Contains uppercase letter', met: /[A-Z]/.test(form.password) },
  { label: 'Contains lowercase letter', met: /[a-z]/.test(form.password) },
  { label: 'Contains a number',         met: /[0-9]/.test(form.password) },
])

const canSubmit = computed(() =>
  form.password.length >= 8 &&
  form.password === form.password_confirmation
)

function submit() {
  if (props.isPortal) {
    form.post(route('portal.password.update'), { preserveScroll: true })
  } else {
    form.post(route('password.update'), { preserveScroll: true })
  }
}
</script>
