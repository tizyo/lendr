<template>
  <LandlordLayout title="Plan Configuration">

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-else>
      <!-- Alert -->
      <div v-if="alert.message" :class="alert.type === 'success' ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-red-50 border-red-300 text-red-800'"
        class="border rounded-xl px-4 py-3 text-sm mb-6 flex items-center justify-between">
        {{ alert.message }}
        <button @click="alert.message = ''" class="ml-4 opacity-60 hover:opacity-100">&times;</button>
      </div>

      <!-- Plan Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div
          v-for="plan in plans"
          :key="plan.plan"
          class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden"
        >
          <!-- Card Header -->
          <div :class="headerClass(plan.plan)" class="px-6 py-5">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-bold uppercase tracking-wider opacity-80">{{ plan.plan }}</span>
              <span v-if="editing === plan.plan" class="text-xs bg-white/20 px-2 py-0.5 rounded-full font-medium">Editing</span>
            </div>
            <div v-if="editing !== plan.plan">
              <p class="text-2xl font-bold">
                <template v-if="plan.is_custom_price">Custom</template>
                <template v-else>ZMW {{ plan.price_zmw.toFixed(2) }}</template>
                <span v-if="!plan.is_custom_price" class="text-sm font-normal opacity-70">/mo</span>
              </p>
              <p class="text-sm opacity-70 mt-0.5">{{ plan.label }}</p>
            </div>
            <div v-else class="space-y-2 mt-2">
              <input v-model="form.label" type="text" placeholder="Plan label"
                class="w-full bg-white/20 placeholder-white/50 text-white border border-white/30 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-white/50" />
              <div class="flex gap-2">
                <input v-model.number="form.price_zmw" type="number" min="0" step="0.01" placeholder="Price ZMW"
                  :disabled="form.is_custom_price"
                  class="flex-1 bg-white/20 placeholder-white/50 text-white border border-white/30 rounded-lg px-3 py-1.5 text-sm focus:outline-none disabled:opacity-40" />
                <label class="flex items-center gap-1.5 text-xs text-white/80 whitespace-nowrap cursor-pointer">
                  <input v-model="form.is_custom_price" type="checkbox" class="rounded" />
                  Custom
                </label>
              </div>
              <textarea v-model="form.description" rows="2" placeholder="Short description…"
                class="w-full bg-white/20 placeholder-white/50 text-white border border-white/30 rounded-lg px-3 py-1.5 text-sm focus:outline-none resize-none"></textarea>
            </div>
          </div>

          <!-- Features -->
          <div class="px-6 py-5 space-y-4">

            <!-- Numeric limits -->
            <div>
              <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-2">Limits</p>
              <div class="space-y-2">
                <div v-for="(key, label) in LIMIT_KEYS" :key="key" class="flex items-center justify-between">
                  <span class="text-sm text-neutral-600">{{ label }}</span>
                  <div v-if="editing === plan.plan">
                    <div class="flex items-center gap-1.5">
                      <input v-model.number="form.features[key]" type="number" min="-1"
                        class="w-20 border border-neutral-300 rounded-lg px-2 py-1 text-sm text-right focus:outline-none focus:ring-2 focus:ring-primary-500" />
                      <span class="text-xs text-neutral-400">−1=∞</span>
                    </div>
                  </div>
                  <span v-else class="text-sm font-semibold text-neutral-800">
                    {{ plan.features[key] === -1 ? '∞' : plan.features[key] }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Boolean features -->
            <div>
              <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-2">Features</p>
              <div class="space-y-1.5">
                <div v-for="(key, label) in BOOL_KEYS" :key="key" class="flex items-center justify-between">
                  <span class="text-sm text-neutral-600">{{ label }}</span>
                  <div v-if="editing === plan.plan">
                    <button @click="form.features[key] = !form.features[key]"
                      :class="form.features[key] ? 'bg-emerald-500' : 'bg-neutral-300'"
                      class="relative inline-flex h-5 w-9 items-center rounded-full transition">
                      <span :class="form.features[key] ? 'translate-x-4.5' : 'translate-x-0.5'"
                        class="inline-block h-4 w-4 bg-white rounded-full shadow transition-transform"></span>
                    </button>
                  </div>
                  <span v-else>
                    <svg v-if="plan.features[key]" class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <svg v-else class="w-4 h-4 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </span>
                </div>
              </div>
            </div>

          </div>

          <!-- Actions -->
          <div class="px-6 pb-5 flex gap-2">
            <template v-if="editing !== plan.plan">
              <button @click="startEdit(plan)"
                class="flex-1 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-sm font-semibold py-2 rounded-xl transition">
                Edit
              </button>
            </template>
            <template v-else>
              <button @click="saveEdit(plan.plan)" :disabled="saving"
                class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2 rounded-xl transition disabled:opacity-60">
                {{ saving ? 'Saving…' : 'Save' }}
              </button>
              <button @click="editing = null" :disabled="saving"
                class="px-4 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-sm font-semibold py-2 rounded-xl transition">
                Cancel
              </button>
            </template>
          </div>
        </div>
      </div>

      <!-- Feature key legend -->
      <div class="mt-8 bg-neutral-50 border border-neutral-200 rounded-xl p-5">
        <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-3">Feature Key Reference</p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs text-neutral-600 font-mono">
          <span v-for="(key, label) in { ...LIMIT_KEYS, ...BOOL_KEYS }" :key="key">
            <span class="text-neutral-400">{{ label }}:</span> {{ key }}
          </span>
        </div>
      </div>

    </template>
  </LandlordLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

const landlordAuth = useLandlordAuth()

const LIMIT_KEYS = {
  'Max Users':        'max_users',
  'Max Branches':     'max_branches',
  'Max Loan Products':'max_loan_products',
  'Max Borrowers':    'max_borrowers',
}

const BOOL_KEYS = {
  'Borrower PWA':          'pwa',
  'Custom Domain':         'custom_domain',
  'Bulk Operations':       'bulk_operations',
  'Advanced Reports':      'advanced_reports',
  'Collection Management': 'collection_management',
  'Marketplace':           'marketplace',
  'Mobile Money Disburse': 'disbursement_mobile_money',
  'Tenant Website':        'tenant_website',
  'API Access':            'api_access',
  'Exchange Rates':        'exchange_rates',
  'Audit Log':             'audit_log',
  'Two-Factor Auth':       'two_factor_auth',
}

const loading = ref(true)
const saving  = ref(false)
const editing = ref(null)
const plans   = ref([])
const form    = reactive({ label: '', description: '', price_zmw: 0, is_custom_price: false, features: {} })
const alert   = reactive({ message: '', type: 'success' })

function headerClass(plan) {
  return {
    starter:    'bg-gradient-to-br from-neutral-700 to-neutral-800 text-white',
    growth:     'bg-gradient-to-br from-emerald-600 to-emerald-700 text-white',
    enterprise: 'bg-gradient-to-br from-violet-600 to-violet-700 text-white',
  }[plan] ?? 'bg-neutral-800 text-white'
}

function api(path, options = {}) {
  return fetch(`/api/v1/landlord/${path}`, {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${landlordAuth.token}`,
      'Accept': 'application/json',
    },
    ...options,
  }).then(r => r.json())
}

async function load() {
  loading.value = true
  const res = await api('plan-configs')
  plans.value = res.data ?? []
  loading.value = false
}

function startEdit(plan) {
  editing.value = plan.plan
  Object.assign(form, {
    label:          plan.label,
    description:    plan.description ?? '',
    price_zmw:      plan.price_zmw,
    is_custom_price:plan.is_custom_price,
    features:       { ...plan.features },
  })
}

async function saveEdit(planSlug) {
  saving.value = true
  try {
    const res = await api(`plan-configs/${planSlug}`, {
      method: 'PUT',
      body: JSON.stringify({ ...form }),
    })

    if (res.success) {
      const idx = plans.value.findIndex(p => p.plan === planSlug)
      if (idx !== -1) plans.value[idx] = res.data
      editing.value = null
      alert.message = res.message ?? 'Saved.'
      alert.type = 'success'
    } else {
      alert.message = res.message ?? 'Save failed.'
      alert.type = 'error'
    }
  } catch {
    alert.message = 'Network error.'
    alert.type = 'error'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
