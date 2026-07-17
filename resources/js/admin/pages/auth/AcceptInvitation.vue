<template>
  <div class="min-h-screen bg-gradient-to-br from-neutral-50 to-emerald-50/30 flex items-center justify-center p-4">
    <div class="w-full max-w-md">

      <!-- Logo / Brand -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-600 rounded-2xl shadow-lg mb-4">
          <span class="text-white font-black text-xl">L</span>
        </div>
        <h1 class="text-2xl font-bold text-neutral-900">Activate your account</h1>
        <p class="text-sm text-neutral-500 mt-1">
          You've been invited to <span class="font-semibold text-emerald-700">{{ orgName }}</span>
        </p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-xl border border-neutral-100 overflow-hidden">

        <!-- User info strip -->
        <div class="bg-emerald-50 border-b border-emerald-100 px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-emerald-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
              {{ initials }}
            </div>
            <div>
              <p class="text-sm font-semibold text-neutral-800">{{ name }}</p>
              <p class="text-xs text-neutral-500">{{ email }}</p>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-5">
          <p class="text-sm text-neutral-600">
            Choose a strong password to secure your account. You'll use it every time you log in.
          </p>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">
              Password <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="lendr-input w-full pr-10"
                placeholder="At least 8 characters"
                required
                autocomplete="new-password"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="!showPassword" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
              </button>
            </div>
            <div v-if="form.password" class="mt-2 space-y-1">
              <div v-for="rule in passwordRules" :key="rule.label" class="flex items-center gap-1.5 text-xs">
                <svg class="w-3.5 h-3.5 shrink-0" :class="rule.met ? 'text-emerald-500' : 'text-neutral-300'" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span :class="rule.met ? 'text-emerald-700' : 'text-neutral-400'">{{ rule.label }}</span>
              </div>
            </div>
            <p v-if="errors.password" class="text-xs text-red-600 mt-1">{{ errors.password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1.5">
              Confirm Password <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.password_confirmation"
              :type="showPassword ? 'text' : 'password'"
              class="lendr-input w-full"
              placeholder="Re-enter your password"
              required
              autocomplete="new-password"
            />
            <p v-if="form.password_confirmation && form.password !== form.password_confirmation"
               class="text-xs text-red-600 mt-1">Passwords do not match.</p>
          </div>

          <button
            type="submit"
            :disabled="submitting || !canSubmit"
            class="w-full btn-primary disabled:opacity-50 flex items-center justify-center gap-2 py-3"
          >
            <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            {{ submitting ? 'Activating account…' : 'Activate Account' }}
          </button>
        </form>
      </div>

      <p class="text-center text-xs text-neutral-400 mt-6">
        Having trouble? Contact your system administrator.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  tenantId:  String,
  token:     String,
  name:      String,
  email:     String,
  orgName:   String,
  errors:    { type: Object, default: () => ({}) },
})

const form = ref({ password: '', password_confirmation: '' })
const showPassword = ref(false)
const submitting   = ref(false)

const initials = computed(() => {
  return (props.name || '').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

const passwordRules = computed(() => [
  { label: 'At least 8 characters',        met: form.value.password.length >= 8 },
  { label: 'Contains uppercase letter',     met: /[A-Z]/.test(form.value.password) },
  { label: 'Contains lowercase letter',     met: /[a-z]/.test(form.value.password) },
  { label: 'Contains a number',             met: /[0-9]/.test(form.value.password) },
])

const canSubmit = computed(() =>
  form.value.password.length >= 8 &&
  form.value.password === form.value.password_confirmation
)

function submit() {
  submitting.value = true
  router.post(
    route('invitation.accept', { tenant: props.tenantId, token: props.token }),
    { password: form.value.password, password_confirmation: form.value.password_confirmation },
    { onFinish: () => { submitting.value = false } }
  )
}
</script>
