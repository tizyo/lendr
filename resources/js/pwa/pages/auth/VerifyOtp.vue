<template>
  <div class="min-h-screen bg-white flex flex-col">
    <div class="flex-1 flex flex-col px-6 pt-12">
      <!-- Back -->
      <button @click="$inertia.visit(route('pwa.auth.login'))" class="self-start p-1.5 -ml-1.5 mb-6 text-gray-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
      </button>

      <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center mb-5">
        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-1 1-1 1-1-1-1-1z"/>
        </svg>
      </div>

      <h2 class="text-2xl font-bold text-gray-900 mb-1">Verify your number</h2>
      <p class="text-sm text-gray-500 mb-8">
        We sent a 6-digit code to <strong class="text-gray-700">{{ phone }}</strong>
      </p>

      <!-- OTP input grid -->
      <div class="flex gap-3 mb-6" @paste="onPaste">
        <input
          v-for="(_, i) in digits"
          :key="i"
          :ref="el => inputRefs[i] = el"
          v-model="digits[i]"
          type="text"
          inputmode="numeric"
          maxlength="1"
          class="flex-1 h-14 text-center text-2xl font-bold rounded-xl border-2 transition outline-none"
          :class="error
            ? 'border-red-400 text-red-700'
            : digits[i] ? 'border-emerald-500 text-emerald-700' : 'border-gray-200 text-gray-900'"
          @input="onInput(i, $event)"
          @keydown.backspace="onBackspace(i)"
          @keydown.left="inputRefs[i - 1]?.focus()"
          @keydown.right="inputRefs[i + 1]?.focus()"
        />
      </div>

      <p v-if="error" class="text-sm text-red-600 mb-4">{{ error }}</p>

      <button
        @click="verifyOtp"
        :disabled="otpValue.length < 6 || loading"
        class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-base transition disabled:opacity-50"
      >
        <span v-if="loading" class="flex items-center justify-center gap-2">
          <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          Verifying…
        </span>
        <span v-else>Verify Code</span>
      </button>

      <!-- Resend -->
      <div class="mt-6 text-center">
        <p v-if="resendCooldown > 0" class="text-sm text-gray-400">
          Resend code in <span class="font-medium text-gray-600">{{ resendCooldown }}s</span>
        </p>
        <button
          v-else
          @click="resend"
          :disabled="resending"
          class="text-sm font-medium text-emerald-600 hover:text-emerald-700 disabled:opacity-50"
        >
          {{ resending ? 'Sending…' : "Didn't receive it? Resend" }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

const props = defineProps({ phone: String })
const auth  = usePwaAuthStore()

const digits   = reactive(['', '', '', '', '', ''])
const inputRefs = ref([])
const loading  = ref(false)
const resending = ref(false)
const error    = ref('')
const resendCooldown = ref(30)

let cooldownTimer = null

onMounted(() => {
  inputRefs.value[0]?.focus()
  startCooldown()
})

onUnmounted(() => clearInterval(cooldownTimer))

function startCooldown() {
  resendCooldown.value = 30
  cooldownTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

const otpValue = computed(() => digits.join(''))

function onInput(index, event) {
  const val = event.target.value.replace(/\D/, '')
  digits[index] = val
  if (val && index < 5) inputRefs.value[index + 1]?.focus()
  error.value = ''
}

function onBackspace(index) {
  if (!digits[index] && index > 0) {
    digits[index - 1] = ''
    inputRefs.value[index - 1]?.focus()
  }
}

function onPaste(event) {
  const pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6)
  pasted.split('').forEach((ch, i) => { digits[i] = ch })
  inputRefs.value[Math.min(pasted.length, 5)]?.focus()
  event.preventDefault()
}

async function verifyOtp() {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await axios.post('/api/v1/borrower/auth/verify-otp', {
      phone: props.phone,
      otp:   otpValue.value,
    })
    auth.setAuth(data.data.token, data.data.borrower)

    // If no PIN set, go to set-pin page; otherwise go to dashboard
    if (data.data.requires_pin_setup) {
      router.visit(route('pwa.auth.set-pin'))
    } else {
      router.visit(route('pwa.dashboard'))
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid code. Please try again.'
    digits.splice(0, 6, '', '', '', '', '', '')
    inputRefs.value[0]?.focus()
  } finally {
    loading.value = false
  }
}

async function resend() {
  resending.value = true
  try {
    await axios.post('/api/v1/borrower/auth/request-otp', { phone: props.phone })
    startCooldown()
    digits.splice(0, 6, '', '', '', '', '', '')
    inputRefs.value[0]?.focus()
  } finally {
    resending.value = false
  }
}
</script>
