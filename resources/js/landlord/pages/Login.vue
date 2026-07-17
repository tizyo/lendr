<template>
  <div class="min-h-screen bg-neutral-900 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-white">LENDR</h1>
        <p class="text-neutral-400 text-sm mt-1">Platform Administration</p>
      </div>

      <div class="bg-white rounded-xl p-8 shadow-xl">
        <h2 class="text-lg font-semibold text-neutral-900 mb-5">Sign In</h2>

        <!-- Step 1: Credentials -->
        <form v-if="step === 'credentials'" @submit.prevent="submitCredentials" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Email</label>
            <input v-model="form.email" type="email" required class="input w-full" placeholder="admin@lendr.app" autocomplete="email" />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Password</label>
            <input v-model="form.password" type="password" required class="input w-full" autocomplete="current-password" />
          </div>
          <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ error }}</div>
          <button type="submit" :disabled="submitting" class="w-full btn-primary py-2.5">
            {{ submitting ? 'Signing in…' : 'Continue' }}
          </button>
        </form>

        <!-- Step 2a: 2FA Setup (QR code) -->
        <div v-else-if="step === 'setup'" class="space-y-4">
          <p class="text-sm text-neutral-600">Scan this QR code with your authenticator app to enable 2FA.</p>
          <div class="flex justify-center py-2">
            <img :src="qrCodeUrl" alt="QR Code" class="w-48 h-48 border border-neutral-200 rounded-lg" />
          </div>
          <p class="text-xs text-neutral-500 text-center">Or enter this key manually: <code class="font-mono bg-neutral-100 px-1 rounded">{{ secretKey }}</code></p>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Verification Code</label>
            <input v-model="totpCode" type="text" inputmode="numeric" maxlength="6" class="input w-full text-center text-lg font-mono tracking-widest" placeholder="000000" />
          </div>
          <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ error }}</div>
          <button @click="verifySetup" :disabled="submitting || totpCode.length !== 6" class="w-full btn-primary py-2.5">
            {{ submitting ? 'Verifying…' : 'Activate 2FA & Sign In' }}
          </button>
        </div>

        <!-- Step 2b: 2FA Challenge -->
        <div v-else-if="step === 'challenge'" class="space-y-4">
          <p class="text-sm text-neutral-600">Enter the 6-digit code from your authenticator app.</p>
          <input
            v-model="totpCode"
            type="text"
            inputmode="numeric"
            maxlength="6"
            class="input w-full text-center text-2xl font-mono tracking-widest"
            placeholder="000000"
            autofocus
          />
          <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ error }}</div>
          <button @click="submitChallenge" :disabled="submitting || totpCode.length !== 6" class="w-full btn-primary py-2.5">
            {{ submitting ? 'Verifying…' : 'Verify & Sign In' }}
          </button>
          <button @click="step = 'credentials'; error = ''" class="w-full text-sm text-neutral-500 hover:text-neutral-700">← Back</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

const auth      = useLandlordAuth()
const step      = ref('credentials')
const submitting = ref(false)
const error     = ref('')
const totpCode  = ref('')
const qrCodeUrl = ref('')
const secretKey = ref('')
let   authToken = ''   // pre-auth token

const form = ref({ email: '', password: '' })

async function submitCredentials() {
  error.value     = ''
  submitting.value = true
  try {
    const { data } = await axios.post('/api/v1/landlord/auth/login', form.value)
    const res = data.data ?? data

    if (res.requires_2fa_setup) {
      // Need to set up 2FA first
      authToken   = res.setup_token
      // Call setup endpoint to get QR code
      axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`
      const { data: setupData } = await axios.post('/api/v1/landlord/auth/2fa/setup')
      qrCodeUrl.value = setupData.data?.qr_code_url ?? ''
      secretKey.value = setupData.data?.secret ?? ''
      step.value      = 'setup'
    } else if (res.requires_2fa) {
      authToken  = res.pre_auth_token
      step.value = 'challenge'
    } else if (res.token) {
      auth.setToken(res.token, form.value.email)
      router.visit(route('landlord.dashboard'))
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Login failed.'
  } finally {
    submitting.value = false
  }
}

async function verifySetup() {
  error.value      = ''
  submitting.value  = true
  try {
    const { data } = await axios.post('/api/v1/landlord/auth/2fa/verify', { code: totpCode.value })
    const token = data.data?.token ?? data.token
    auth.setToken(token, form.value.email)
    router.visit(route('landlord.dashboard'))
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Invalid code.'
  } finally {
    submitting.value = false
  }
}

async function submitChallenge() {
  error.value      = ''
  submitting.value  = true
  axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`
  try {
    const { data } = await axios.post('/api/v1/landlord/auth/2fa/challenge', { code: totpCode.value })
    const token = data.data?.token ?? data.token
    auth.setToken(token, form.value.email)
    router.visit(route('landlord.dashboard'))
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Invalid code.'
  } finally {
    submitting.value = false
  }
}
</script>
