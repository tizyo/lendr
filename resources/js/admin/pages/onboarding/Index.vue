<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">

      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center gap-3 mb-2">
          <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg">
            <span class="text-primary-700 font-black text-lg">L</span>
          </div>
          <span class="text-white font-black text-3xl tracking-tight">LENDR</span>
        </div>
        <p class="text-primary-200 text-sm">Create your MFI account</p>
      </div>

      <!-- Step indicator -->
      <div class="flex items-center justify-center gap-0 mb-8">
        <template v-for="(label, i) in stepLabels" :key="i">
          <div class="flex items-center gap-2">
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all"
              :class="step > i + 1 ? 'bg-green-400 text-white' :
                      step === i + 1 ? 'bg-white text-primary-700' :
                      'bg-primary-700 text-primary-300 border border-primary-500'"
            >
              <svg v-if="step > i + 1" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
              <span v-else>{{ i + 1 }}</span>
            </div>
            <span
              class="text-xs font-medium hidden sm:block"
              :class="step === i + 1 ? 'text-white' : 'text-primary-300'"
            >{{ label }}</span>
          </div>
          <div v-if="i < stepLabels.length - 1" class="w-10 h-px mx-2" :class="step > i + 1 ? 'bg-green-400' : 'bg-primary-600'"/>
        </template>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-2xl p-8">

        <!-- Step 1: Organisation -->
        <div v-if="step === 1">
          <h2 class="text-xl font-bold text-neutral-900 mb-1">Organisation details</h2>
          <p class="text-sm text-neutral-500 mb-6">Tell us about your microfinance institution</p>

          <div class="space-y-5">
            <!-- Org name -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Organisation name</label>
              <input
                v-model="form.org_name"
                type="text"
                placeholder="Zambia MFI Ltd."
                class="input w-full"
                :class="{ 'border-red-400 focus:ring-red-400': form.errors.org_name }"
              />
              <p v-if="form.errors.org_name" class="mt-1.5 text-xs text-red-600">{{ form.errors.org_name }}</p>
            </div>

            <!-- Workspace slug (always shown) -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Workspace name</label>
              <div class="flex items-stretch rounded-lg border overflow-hidden"
                   :class="form.errors.slug ? 'border-red-400' : 'border-neutral-300 focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-transparent'">
                <input
                  v-model="form.slug"
                  type="text"
                  placeholder="zambiamfi"
                  class="flex-1 px-3.5 py-2.5 text-sm outline-none"
                  @input="form.slug = form.slug.toLowerCase().replace(/[^a-z0-9-]/g, '')"
                />
              </div>
              <p v-if="form.errors.slug" class="mt-1.5 text-xs text-red-600">{{ form.errors.slug }}</p>
              <p v-else class="mt-1.5 text-xs text-neutral-400">
                <template v-if="selectedPlan?.subdomain">Used to identify your workspace</template>
                <template v-else>You'll log in at <strong>{{ sharedPortalHost }}</strong> using this as your workspace ID</template>
              </p>
            </div>

            <!-- Custom subdomain (Growth/Enterprise only) -->
            <div v-if="selectedPlan?.subdomain">
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Custom subdomain</label>
              <div class="flex items-stretch rounded-lg border overflow-hidden"
                   :class="form.errors.subdomain ? 'border-red-400' : 'border-neutral-300 focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-transparent'">
                <input
                  v-model="form.subdomain"
                  type="text"
                  placeholder="zambiamfi"
                  class="flex-1 px-3.5 py-2.5 text-sm outline-none"
                  @input="form.subdomain = form.subdomain.toLowerCase().replace(/[^a-z0-9-]/g, '')"
                />
                <span class="px-3 py-2.5 bg-neutral-50 border-l border-neutral-200 text-sm text-neutral-500 whitespace-nowrap">.{{ centralDomain }}</span>
              </div>
              <p v-if="form.errors.subdomain" class="mt-1.5 text-xs text-red-600">{{ form.errors.subdomain }}</p>
              <p v-else class="mt-1.5 text-xs text-neutral-400">Your staff will log in at this address</p>
            </div>

            <!-- Currency + Timezone -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1.5">Currency</label>
                <select v-model="form.currency" class="input w-full">
                  <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                </select>
                <p v-if="form.errors.currency" class="mt-1.5 text-xs text-red-600">{{ form.errors.currency }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1.5">Timezone</label>
                <select v-model="form.timezone" class="input w-full">
                  <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                </select>
                <p v-if="form.errors.timezone" class="mt-1.5 text-xs text-red-600">{{ form.errors.timezone }}</p>
              </div>
            </div>

            <!-- Plan selector -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-3">Plan</label>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <button
                  v-for="p in plans"
                  :key="p.key"
                  type="button"
                  class="text-left p-4 rounded-xl border-2 transition-all"
                  :class="form.plan === p.key
                    ? 'border-primary-500 bg-primary-50'
                    : 'border-neutral-200 hover:border-neutral-300'"
                  @click="form.plan = p.key"
                >
                  <div class="font-semibold text-neutral-800 text-sm">{{ p.label }}</div>
                  <div class="text-primary-600 font-bold text-base mt-0.5">{{ p.price }}</div>
                  <div class="text-xs text-neutral-500 mt-1.5 space-y-0.5">
                    <div v-for="f in p.features" :key="f" class="flex items-center gap-1.5">
                      <svg class="w-3 h-3 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                      </svg>
                      {{ f }}
                    </div>
                    <div v-for="f in (p.unavailable ?? [])" :key="'x-'+f" class="flex items-center gap-1.5 opacity-40">
                      <svg class="w-3 h-3 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                      <span class="line-through">{{ f }}</span>
                    </div>
                  </div>
                </button>
              </div>
              <p v-if="form.errors.plan" class="mt-1.5 text-xs text-red-600">{{ form.errors.plan }}</p>
            </div>
          </div>

          <div class="mt-8 flex justify-end">
            <button type="button" class="btn-primary px-8" @click="goNext">
              Continue
              <svg class="w-4 h-4 ml-1.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Step 2: Admin Account -->
        <div v-if="step === 2">
          <h2 class="text-xl font-bold text-neutral-900 mb-1">Admin account</h2>
          <p class="text-sm text-neutral-500 mb-6">This will be the first Super Admin for <strong>{{ form.org_name }}</strong></p>

          <div class="space-y-5">
            <!-- Full name -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Full name</label>
              <input
                v-model="form.admin_name"
                type="text"
                placeholder="Chanda Mutale"
                class="input w-full"
                :class="{ 'border-red-400 focus:ring-red-400': form.errors.admin_name }"
              />
              <p v-if="form.errors.admin_name" class="mt-1.5 text-xs text-red-600">{{ form.errors.admin_name }}</p>
            </div>

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Email address</label>
              <input
                v-model="form.admin_email"
                type="email"
                placeholder="admin@company.com"
                class="input w-full"
                :class="{ 'border-red-400 focus:ring-red-400': form.errors.admin_email }"
              />
              <p v-if="form.errors.admin_email" class="mt-1.5 text-xs text-red-600">{{ form.errors.admin_email }}</p>
            </div>

            <!-- Phone -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                Phone <span class="text-neutral-400 font-normal">(optional)</span>
              </label>
              <input
                v-model="form.admin_phone"
                type="tel"
                placeholder="+260 97 1234567"
                class="input w-full"
              />
            </div>

            <!-- Password -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Password</label>
              <div class="relative">
                <input
                  v-model="form.admin_password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Min. 8 characters"
                  class="input w-full pr-10"
                  :class="{ 'border-red-400 focus:ring-red-400': form.errors.admin_password }"
                />
                <button type="button" @click="showPassword = !showPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600">
                  <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                  </svg>
                </button>
              </div>
              <p v-if="form.errors.admin_password" class="mt-1.5 text-xs text-red-600">{{ form.errors.admin_password }}</p>
            </div>

            <!-- Confirm password -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1.5">Confirm password</label>
              <input
                v-model="form.admin_password_confirmation"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Repeat password"
                class="input w-full"
                :class="{ 'border-red-400 focus:ring-red-400': form.errors.admin_password_confirmation }"
              />
              <p v-if="form.errors.admin_password_confirmation" class="mt-1.5 text-xs text-red-600">{{ form.errors.admin_password_confirmation }}</p>
            </div>
          </div>

          <div class="mt-8 flex items-center justify-between">
            <button type="button" class="btn-secondary" @click="step = 1">
              <svg class="w-4 h-4 mr-1.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
              Back
            </button>
            <button
              type="button"
              class="btn-primary px-8"
              :disabled="form.processing"
              @click="submit"
            >
              <span v-if="form.processing" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Creating account…
              </span>
              <span v-else>Create account</span>
            </button>
          </div>

          <p class="mt-4 text-center text-xs text-neutral-400">
            By creating an account you agree to LENDR's
            <a href="#" class="underline hover:text-neutral-600">Terms of Service</a>
          </p>
        </div>

      </div>

      <p class="text-center text-primary-300 text-xs mt-6">
        Already have an account?
        <a :href="route('portal.login')" class="underline hover:text-white transition">Sign in</a>
        &nbsp;&middot;&nbsp;
        LENDR &copy; {{ year }} &middot; RISKCAPE Enterprises Limited
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  plans:            { type: Array,  default: () => [] },
  currencies:       { type: Array,  default: () => [] },
  timezones:        { type: Array,  default: () => [] },
  sharedPortalHost: { type: String, default: 'app.localhost' },
})

const step         = ref(1)
const showPassword = ref(false)
const year         = new Date().getFullYear()
const stepLabels   = ['Organisation', 'Admin account']

const centralDomain = window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost'
  ? 'localhost'
  : window.location.hostname.split('.').slice(1).join('.') || 'lendr.app'

const form = useForm({
  org_name:                    '',
  slug:                        '',
  subdomain:                   '',
  plan:                        'starter',
  currency:                    'ZMW',
  timezone:                    'Africa/Lusaka',
  admin_name:                  '',
  admin_email:                 '',
  admin_phone:                 '',
  admin_password:              '',
  admin_password_confirmation: '',
})

const selectedPlan = computed(() => props.plans.find(p => p.key === form.plan) ?? null)

const step1Fields = ['org_name', 'slug', 'subdomain', 'plan', 'currency', 'timezone']

function goNext() {
  step1Fields.forEach(f => form.clearErrors(f))

  if (! form.org_name.trim()) {
    form.setError('org_name', 'Organisation name is required.')
    return
  }
  if (! form.slug.trim()) {
    form.setError('slug', 'Workspace name is required.')
    return
  }
  if (form.slug.length < 3) {
    form.setError('slug', 'Workspace name must be at least 3 characters.')
    return
  }
  if (selectedPlan.value?.subdomain && ! form.subdomain.trim()) {
    form.setError('subdomain', 'Subdomain is required for this plan.')
    return
  }

  step.value = 2
}

function submit() {
  form.post(route('onboarding.store'), {
    onError: (errors) => {
      if (step1Fields.some(f => errors[f])) {
        step.value = 1
      }
    },
  })
}
</script>
