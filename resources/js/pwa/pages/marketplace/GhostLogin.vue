<template>
  <div class="min-h-screen bg-neutral-50 flex flex-col">
    <!-- Header -->
    <div class="bg-white px-5 pt-12 pb-6 shadow-sm">
      <button @click="goBack" class="text-neutral-500 text-sm mb-4">← Back</button>
      <h1 class="text-2xl font-black text-neutral-900">Sign in to LENDR Market</h1>
      <p class="text-neutral-500 text-sm mt-1">Browse for free. Sign in to enquire or save items.</p>
    </div>

    <div class="flex-1 px-5 py-6 space-y-4">
      <!-- Step 1: Phone/Name -->
      <template v-if="step === 'register'">
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">Full Name</label>
            <input v-model="form.name" class="w-full px-4 py-3 rounded-xl border border-neutral-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Your name" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">Phone Number</label>
            <input v-model="form.phone" type="tel" class="w-full px-4 py-3 rounded-xl border border-neutral-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="+260971234567" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">Email (optional)</label>
            <input v-model="form.email" type="email" class="w-full px-4 py-3 rounded-xl border border-neutral-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="you@example.com" />
          </div>
        </div>
        <p v-if="error" class="text-red-500 text-sm">{{ error }}</p>
        <button @click="requestOtp" :disabled="loading" class="w-full py-3.5 rounded-2xl bg-primary-600 text-white font-bold text-base">
          {{ loading ? 'Sending OTP…' : 'Send OTP' }}
        </button>
      </template>

      <!-- Step 2: OTP verification -->
      <template v-if="step === 'verify'">
        <div class="text-center space-y-2 py-4">
          <p class="text-4xl">📱</p>
          <p class="font-semibold text-neutral-900">Enter the code sent to</p>
          <p class="text-primary-600 font-bold">{{ form.phone }}</p>
        </div>
        <input
          v-model="otp"
          maxlength="6"
          class="w-full text-center text-3xl font-bold tracking-widest px-4 py-4 rounded-xl border-2 border-neutral-200 focus:outline-none focus:border-primary-500"
          placeholder="• • • • • •"
        />
        <p v-if="error" class="text-red-500 text-sm text-center">{{ error }}</p>
        <button @click="verifyOtp" :disabled="loading || otp.length < 6" class="w-full py-3.5 rounded-2xl bg-primary-600 text-white font-bold text-base disabled:opacity-50">
          {{ loading ? 'Verifying…' : 'Verify & Sign In' }}
        </button>
        <button @click="step = 'register'" class="w-full text-sm text-neutral-500 text-center">← Change number</button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const step = ref('register')
const loading = ref(false)
const error = ref('')
const otp = ref('')

const form = reactive({ name: '', phone: '', email: '' })

function goBack() {
  const redirect = new URLSearchParams(window.location.search).get('redirect')
  router.visit(redirect ?? '/app/repo')
}

async function requestOtp() {
  error.value = ''
  if (!form.name.trim() || !form.phone.trim()) { error.value = 'Name and phone are required.'; return }
  loading.value = true
  try {
    // Register (idempotent) then request OTP
    await axios.post('/api/v1/public/auth/register', { name: form.name, phone: form.phone, email: form.email || undefined })
    await axios.post('/api/v1/public/auth/request-otp', { phone: form.phone })
    step.value = 'verify'
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed. Please try again.'
  } finally {
    loading.value = false
  }
}

async function verifyOtp() {
  error.value = ''
  loading.value = true
  try {
    const { data } = await axios.post('/api/v1/public/auth/verify-otp', { phone: form.phone, otp: otp.value })
    localStorage.setItem('ghost_token', data.data.token)
    localStorage.setItem('ghost_user', JSON.stringify(data.data.ghost_user))
    const redirect = new URLSearchParams(window.location.search).get('redirect')
    router.visit(redirect ?? '/app/repo')
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Invalid or expired code.'
  } finally {
    loading.value = false
  }
}
</script>
