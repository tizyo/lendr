<template>
  <div class="min-h-screen bg-gradient-to-br from-emerald-600 to-teal-700 flex flex-col">
    <!-- Logo area -->
    <div class="flex-1 flex flex-col items-center justify-center px-6 pt-12 pb-4">
      <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-4">
        <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h1 class="text-3xl font-bold text-white tracking-tight">LENDR</h1>
      <p class="text-emerald-100 text-sm mt-1">Your financial partner</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-t-3xl px-6 pt-8 pb-10 shadow-2xl">
      <h2 class="text-xl font-bold text-gray-900 mb-1">Sign In</h2>
      <p class="text-sm text-gray-500 mb-6">Enter your mobile number to receive a one-time code</p>

      <form @submit.prevent="requestOtp">
        <div class="mb-5">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Mobile Number</label>
          <div class="flex rounded-xl border border-gray-300 overflow-hidden focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition">
            <span class="flex items-center px-3 bg-gray-50 border-r border-gray-300 text-sm text-gray-600 font-medium whitespace-nowrap">🇿🇲 +260</span>
            <input
              v-model="phoneLocal"
              type="tel"
              inputmode="numeric"
              placeholder="971 234 567"
              class="flex-1 px-3 py-3.5 text-base outline-none bg-white"
              :class="error ? 'text-red-700' : ''"
              maxlength="9"
              autocomplete="tel-local"
              @input="error = ''"
            />
          </div>
          <p v-if="error" class="mt-1.5 text-sm text-red-600">{{ error }}</p>
        </div>

        <button
          type="submit"
          :disabled="loading || phoneLocal.length < 9"
          class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-base transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span v-if="loading" class="flex items-center justify-center gap-2">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Sending code…
          </span>
          <span v-else>Send Code</span>
        </button>
      </form>

      <p class="text-xs text-gray-400 text-center mt-6">
        By continuing you agree to our Terms of Service and Privacy Policy.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const phoneLocal = ref('')
const loading    = ref(false)
const error      = ref('')

async function requestOtp() {
  const phone = `+260${phoneLocal.value.replace(/\D/g, '')}`
  loading.value = true
  error.value   = ''

  try {
    await axios.post('/api/v1/borrower/auth/request-otp', { phone })
    // Pass phone to OTP page via Inertia visit
    router.visit(route('pwa.auth.otp'), {
      method: 'get',
      data: { phone },
    })
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to send code. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>
