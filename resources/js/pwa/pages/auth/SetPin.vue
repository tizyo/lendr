<template>
  <div class="min-h-screen bg-white flex flex-col px-6 pt-12">
    <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center mb-5">
      <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
      </svg>
    </div>

    <h2 class="text-2xl font-bold text-gray-900 mb-1">{{ confirmed ? 'Confirm PIN' : 'Create PIN' }}</h2>
    <p class="text-sm text-gray-500 mb-8">
      {{ confirmed ? 'Re-enter your PIN to confirm' : 'Create a 4-digit PIN to secure your account' }}
    </p>

    <!-- PIN dots display -->
    <div class="flex gap-4 justify-center mb-10">
      <div
        v-for="i in 4"
        :key="i"
        class="w-4 h-4 rounded-full transition-all duration-200"
        :class="currentPin.length >= i
          ? (pinMismatch ? 'bg-red-500' : 'bg-emerald-500')
          : 'border-2 border-gray-300'"
      ></div>
    </div>

    <p v-if="pinMismatch" class="text-sm text-red-600 text-center mb-6">PINs do not match. Please try again.</p>

    <!-- Number pad -->
    <div class="grid grid-cols-3 gap-4 max-w-xs mx-auto w-full">
      <button
        v-for="key in keypad"
        :key="key"
        @click="handleKey(key)"
        class="aspect-square rounded-full text-xl font-medium flex items-center justify-center transition active:scale-95"
        :class="key === '' ? 'invisible' : key === '⌫' ? 'text-gray-500 hover:bg-gray-100' : 'bg-gray-100 hover:bg-gray-200 text-gray-900'"
      >
        {{ key }}
      </button>
    </div>

    <p class="text-xs text-gray-400 text-center mt-8">
      Your PIN is encrypted and stored securely.
    </p>

    <div v-if="loading" class="fixed inset-0 bg-white/70 flex items-center justify-center z-50">
      <svg class="w-8 h-8 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

const auth      = usePwaAuthStore()
const firstPin  = ref('')
const confirmed = ref(false)
const currentPin = ref('')
const pinMismatch = ref(false)
const loading   = ref(false)

const keypad = ['1','2','3','4','5','6','7','8','9','','0','⌫']

function handleKey(key) {
  if (key === '⌫') {
    currentPin.value = currentPin.value.slice(0, -1)
    pinMismatch.value = false
    return
  }
  if (key === '' || currentPin.value.length >= 4) return

  currentPin.value += key

  if (currentPin.value.length === 4) {
    if (!confirmed.value) {
      // First entry — move to confirmation
      firstPin.value  = currentPin.value
      confirmed.value = true
      currentPin.value = ''
    } else {
      // Second entry — check match
      if (currentPin.value === firstPin.value) {
        submitPin()
      } else {
        pinMismatch.value = true
        setTimeout(() => {
          currentPin.value  = ''
          confirmed.value   = false
          firstPin.value    = ''
          pinMismatch.value = false
        }, 800)
      }
    }
  }
}

async function submitPin() {
  loading.value = true
  try {
    await axios.post('/api/v1/borrower/auth/set-pin', { pin: firstPin.value })
    router.visit(route('pwa.kyc.onboarding'))
  } catch (e) {
    pinMismatch.value = true
    setTimeout(() => {
      currentPin.value  = ''
      confirmed.value   = false
      firstPin.value    = ''
      pinMismatch.value = false
    }, 800)
  } finally {
    loading.value = false
  }
}
</script>
