<template>
  <AppLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">My Profile</h1>
        <p class="text-sm text-neutral-500 mt-0.5">Update your personal information and password</p>
      </div>
    </template>

    <div class="max-w-2xl space-y-6">

      <!-- Personal info -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">Personal Information</h2>

        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">Full Name</label>
              <input v-model="profileForm.name" type="text" class="input" />
              <p v-if="profileErrors.name" class="mt-1 text-xs text-red-600">{{ profileErrors.name }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">Phone</label>
              <input v-model="profileForm.phone" type="tel" class="input" placeholder="e.g. +260971234567" />
              <p v-if="profileErrors.phone" class="mt-1 text-xs text-red-600">{{ profileErrors.phone }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">Email</label>
              <input :value="staff.email" type="email" class="input bg-neutral-50 cursor-not-allowed" disabled />
            </div>
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">Username</label>
              <input :value="staff.username" type="text" class="input bg-neutral-50 cursor-not-allowed" disabled />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">Role</label>
              <input :value="staff.role" type="text" class="input bg-neutral-50 cursor-not-allowed capitalize" disabled />
            </div>
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">Department</label>
              <input :value="staff.department ?? '—'" type="text" class="input bg-neutral-50 cursor-not-allowed" disabled />
            </div>
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">Branch</label>
              <input :value="staff.branch ?? '—'" type="text" class="input bg-neutral-50 cursor-not-allowed" disabled />
            </div>
          </div>
        </div>

        <div v-if="profileSuccess" class="mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">
          {{ profileSuccess }}
        </div>

        <div class="mt-5 flex justify-end">
          <button
            @click="saveProfile"
            :disabled="profileSaving"
            class="btn-primary"
          >
            {{ profileSaving ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <!-- Two-Factor Authentication -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-1">Two-Factor Authentication</h2>
        <p class="text-sm text-neutral-500 mb-4">Add an extra layer of security to your account using an authenticator app.</p>

        <!-- Enabled state -->
        <div v-if="twoFactorEnabled && tfa.step === 'idle'">
          <div class="flex items-center gap-2 mb-4">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold">
              <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              2FA Enabled
            </span>
          </div>
          <button @click="disable2fa" :disabled="tfa.loading" class="text-sm text-red-600 hover:underline font-medium">
            {{ tfa.loading ? 'Disabling…' : 'Disable 2FA' }}
          </button>
        </div>

        <!-- Not enabled + idle -->
        <div v-if="!twoFactorEnabled && tfa.step === 'idle'">
          <p class="text-sm text-neutral-500 mb-4">2FA is not enabled on your account.</p>
          <button @click="setup2fa" :disabled="tfa.loading" class="btn-primary text-sm">
            {{ tfa.loading ? 'Setting up…' : 'Enable 2FA' }}
          </button>
        </div>

        <!-- Setup: scan QR -->
        <div v-if="tfa.step === 'setup'" class="space-y-4">
          <p class="text-sm text-neutral-600">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):</p>
          <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-4 inline-block">
            <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(tfa.qrUrl)" alt="QR Code" class="w-44 h-44" />
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Or enter this key manually:</p>
            <code class="block bg-neutral-100 border border-neutral-200 rounded px-3 py-2 text-sm font-mono tracking-widest text-neutral-800">{{ tfa.secret }}</code>
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Enter the 6-digit code from your app to confirm</label>
            <input
              v-model="tfa.code"
              type="text"
              inputmode="numeric"
              maxlength="6"
              placeholder="000000"
              class="input w-40 text-center tracking-widest text-lg font-mono"
            />
            <p v-if="tfa.error" class="mt-1 text-xs text-red-600">{{ tfa.error }}</p>
          </div>
          <div class="flex gap-3">
            <button @click="verify2fa" :disabled="tfa.loading" class="btn-primary text-sm">
              {{ tfa.loading ? 'Verifying…' : 'Confirm & Enable' }}
            </button>
            <button @click="cancelSetup" class="text-sm text-neutral-500 hover:text-neutral-700">Cancel</button>
          </div>
        </div>

        <div v-if="tfa.success" class="mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">
          {{ tfa.success }}
        </div>
      </div>

      <!-- Change password -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">Change Password</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Current Password</label>
            <input v-model="pwForm.current_password" type="password" class="input" autocomplete="current-password" />
            <p v-if="pwErrors.current_password" class="mt-1 text-xs text-red-600">{{ pwErrors.current_password }}</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">New Password</label>
              <input v-model="pwForm.password" type="password" class="input" autocomplete="new-password" />
              <p v-if="pwErrors.password" class="mt-1 text-xs text-red-600">{{ pwErrors.password }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-neutral-500 mb-1">Confirm New Password</label>
              <input v-model="pwForm.password_confirmation" type="password" class="input" autocomplete="new-password" />
            </div>
          </div>
        </div>

        <div v-if="pwSuccess" class="mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">
          {{ pwSuccess }}
        </div>
        <div v-if="pwError" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          {{ pwError }}
        </div>

        <div class="mt-5 flex justify-end">
          <button
            @click="changePassword"
            :disabled="pwSaving"
            class="btn-primary"
          >
            {{ pwSaving ? 'Updating…' : 'Update Password' }}
          </button>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  staff: { type: Object, required: true },
})

// ── 2FA ───────────────────────────────────────────────────────────────────────
const twoFactorEnabled = ref(props.staff.two_factor_enabled ?? false)
const tfa = reactive({ step: 'idle', loading: false, error: '', success: '', qrUrl: '', secret: '', code: '' })

async function setup2fa() {
  tfa.loading = true
  tfa.error   = ''
  tfa.success = ''
  try {
    const { data } = await axios.post(route('api.v1.auth.2fa.setup'))
    tfa.qrUrl   = data.data.qr_code_url
    tfa.secret  = data.data.secret
    tfa.step    = 'setup'
  } catch (e) {
    tfa.error = e.response?.data?.message ?? 'Failed to start 2FA setup.'
  } finally {
    tfa.loading = false
  }
}

async function verify2fa() {
  if (tfa.code.length !== 6) { tfa.error = 'Enter a 6-digit code.'; return }
  tfa.loading = true
  tfa.error   = ''
  try {
    await axios.post(route('api.v1.auth.2fa.verify'), { code: tfa.code })
    twoFactorEnabled.value = true
    tfa.step    = 'idle'
    tfa.success = '2FA has been enabled on your account.'
    tfa.code    = ''
  } catch (e) {
    tfa.error = e.response?.data?.message ?? 'Invalid code.'
  } finally {
    tfa.loading = false
  }
}

async function disable2fa() {
  tfa.loading = true
  tfa.success = ''
  try {
    await axios.delete(route('api.v1.auth.2fa.disable'))
    twoFactorEnabled.value = false
    tfa.success = '2FA has been disabled.'
  } catch (e) {
    tfa.error = e.response?.data?.message ?? 'Failed to disable 2FA.'
  } finally {
    tfa.loading = false
  }
}

function cancelSetup() {
  tfa.step  = 'idle'
  tfa.code  = ''
  tfa.error = ''
}

// ── Profile form ──────────────────────────────────────────────────────────────
const profileForm    = reactive({ name: props.staff.name, phone: props.staff.phone ?? '' })
const profileErrors  = reactive({ name: '', phone: '' })
const profileSaving  = ref(false)
const profileSuccess = ref('')

async function saveProfile() {
  Object.assign(profileErrors, { name: '', phone: '' })
  profileSuccess.value = ''
  profileSaving.value  = true
  try {
    await axios.put(route('staff.profile.update'), profileForm)
    profileSuccess.value = 'Profile updated successfully.'
  } catch (e) {
    const errs = e.response?.data?.errors ?? {}
    if (errs.name)  profileErrors.name  = errs.name[0]
    if (errs.phone) profileErrors.phone = errs.phone[0]
  } finally {
    profileSaving.value = false
  }
}

// ── Password form ─────────────────────────────────────────────────────────────
const pwForm    = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwErrors  = reactive({ current_password: '', password: '' })
const pwSaving  = ref(false)
const pwSuccess = ref('')
const pwError   = ref('')

async function changePassword() {
  Object.assign(pwErrors, { current_password: '', password: '' })
  pwSuccess.value = ''
  pwError.value   = ''
  pwSaving.value  = true
  try {
    await axios.put(route('staff.profile.password'), pwForm)
    pwSuccess.value = 'Password changed successfully.'
    Object.assign(pwForm, { current_password: '', password: '', password_confirmation: '' })
  } catch (e) {
    const errs = e.response?.data?.errors ?? {}
    if (errs.current_password) pwErrors.current_password = errs.current_password[0]
    if (errs.password)         pwErrors.password         = errs.password[0]
    if (!errs.current_password && !errs.password) {
      pwError.value = e.response?.data?.message ?? 'Failed to update password.'
    }
  } finally {
    pwSaving.value = false
  }
}
</script>
