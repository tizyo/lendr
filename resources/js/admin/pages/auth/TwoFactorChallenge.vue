<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <div class="inline-flex items-center gap-3 mb-2">
          <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg">
            <span class="text-primary-700 font-black text-lg">L</span>
          </div>
          <span class="text-white font-black text-3xl tracking-tight">LENDR</span>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
        <div class="w-14 h-14 bg-primary-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
        </div>

        <h1 class="text-xl font-bold text-neutral-900 mb-1">Two-factor authentication</h1>
        <p class="text-neutral-500 text-sm mb-6">Enter the 6-digit code from your authenticator app</p>

        <form @submit.prevent="submit" class="space-y-4">
          <input
            v-model="form.code"
            type="text"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            autocomplete="one-time-code"
            required
            class="w-full text-center text-2xl font-mono tracking-widest px-4 py-3 border border-neutral-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            :class="{ 'border-red-400': form.errors.code }"
            placeholder="000000"
          />
          <p v-if="form.errors.code" class="text-xs text-red-600">{{ form.errors.code }}</p>

          <button
            type="submit"
            :disabled="form.processing || form.code.length !== 6"
            class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2.5 rounded-lg text-sm transition disabled:opacity-60 disabled:cursor-not-allowed"
          >
            Verify
          </button>
        </form>

        <Link :href="route('login')" class="mt-4 inline-block text-sm text-neutral-500 hover:text-neutral-700">
          Back to login
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({ code: '' })

function submit() {
  form.post(route('2fa.verify'))
}
</script>
