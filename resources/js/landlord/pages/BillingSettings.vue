<template>
  <LandlordLayout title="Billing Settings">
    <div class="max-w-4xl">
      <div class="mb-6">
        <h2 class="text-lg font-bold text-neutral-900">Payment Gateway Configuration</h2>
        <p class="text-sm text-neutral-500 mt-1">
          Configure credentials for each gateway. Only one gateway can be active at a time.
          Credentials are encrypted at rest.
        </p>
      </div>

      <!-- Error/Success -->
      <div v-if="flash.success" class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-lg">
        {{ flash.success }}
      </div>
      <div v-if="flash.error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg">
        {{ flash.error }}
      </div>

      <div class="space-y-4">
        <div
          v-for="gw in gateways"
          :key="gw.gateway"
          class="bg-white border rounded-xl overflow-hidden"
          :class="gw.is_active ? 'border-primary-400 ring-1 ring-primary-300' : 'border-neutral-200'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 bg-neutral-50 border-b border-neutral-100">
            <div class="flex items-center gap-3">
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold text-white"
                :class="gatewayColor(gw.gateway)"
              >
                {{ gw.gateway.slice(0, 2).toUpperCase() }}
              </div>
              <div>
                <p class="font-semibold text-neutral-900 capitalize">{{ gw.gateway }}</p>
                <p class="text-xs text-neutral-500">
                  {{ gw.configured ? 'Credentials saved' : 'Not configured' }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="gw.is_active" class="text-xs font-semibold px-2.5 py-1 bg-primary-100 text-primary-700 rounded-full">
                Active
              </span>
              <button
                v-if="!gw.is_active && gw.configured"
                @click="activate(gw.gateway)"
                :disabled="loading[gw.gateway]"
                class="text-xs font-semibold px-3 py-1.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition disabled:opacity-50"
              >
                Set Active
              </button>
              <button
                v-if="gw.is_active"
                @click="deactivate(gw.gateway)"
                :disabled="loading[gw.gateway]"
                class="text-xs font-semibold px-3 py-1.5 bg-neutral-200 text-neutral-700 rounded-lg hover:bg-neutral-300 transition disabled:opacity-50"
              >
                Deactivate
              </button>
              <button
                @click="toggleExpand(gw.gateway)"
                class="text-xs text-neutral-500 hover:text-neutral-800 px-2 py-1 transition"
              >
                {{ expanded[gw.gateway] ? 'Hide' : 'Edit' }}
              </button>
            </div>
          </div>

          <!-- Credential form (expandable) -->
          <Transition name="slide">
            <div v-if="expanded[gw.gateway]" class="px-6 py-5">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-neutral-600 mb-1">Public Key / API Key</label>
                  <input
                    v-model="forms[gw.gateway].public_key"
                    type="password"
                    :placeholder="gw.has_public_key ? '••••••••••••' : 'Enter public key'"
                    class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-neutral-600 mb-1">Secret Key</label>
                  <input
                    v-model="forms[gw.gateway].secret_key"
                    type="password"
                    :placeholder="gw.has_secret_key ? '••••••••••••' : 'Enter secret key'"
                    class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                  />
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-neutral-600 mb-1">Webhook Secret / Verification Hash</label>
                  <input
                    v-model="forms[gw.gateway].webhook_secret"
                    type="password"
                    :placeholder="gw.has_webhook_secret ? '••••••••••••' : 'Enter webhook secret'"
                    class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                  />
                  <p class="text-xs text-neutral-400 mt-1">
                    For Flutterwave: the "verif-hash" value set in your dashboard webhook settings.
                  </p>
                </div>
              </div>
              <div class="flex items-center justify-between mt-4 pt-4 border-t border-neutral-100">
                <p v-if="stubGateways.includes(gw.gateway)" class="text-xs text-amber-600">
                  ⚠ This gateway is a stub — credentials can be saved but full API integration is pending.
                </p>
                <div class="ml-auto flex gap-2">
                  <button
                    @click="toggleExpand(gw.gateway)"
                    class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-900"
                  >
                    Cancel
                  </button>
                  <button
                    @click="save(gw.gateway)"
                    :disabled="loading[gw.gateway]"
                    class="px-4 py-2 text-sm font-semibold bg-neutral-900 text-white rounded-lg hover:bg-neutral-700 transition disabled:opacity-50"
                  >
                    {{ loading[gw.gateway] ? 'Saving…' : 'Save Credentials' }}
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>

      <!-- Webhook URL reference -->
      <div class="mt-8 p-5 bg-neutral-50 border border-neutral-200 rounded-xl">
        <p class="text-sm font-semibold text-neutral-700 mb-2">Webhook Endpoints</p>
        <p class="text-xs text-neutral-500 mb-3">
          Configure these URLs in your payment gateway dashboard to receive subscription payment notifications.
        </p>
        <div v-for="gw in ['flutterwave', 'pawapay', 'lipila', 'stripe']" :key="gw" class="flex items-center gap-3 mb-2">
          <span class="text-xs font-medium text-neutral-500 w-24 capitalize">{{ gw }}</span>
          <code class="text-xs bg-white border border-neutral-200 rounded px-2 py-1 text-neutral-700 flex-1">
            {{ baseUrl }}/api/v1/webhooks/subscription/{{ gw }}
          </code>
        </div>
      </div>
    </div>
  </LandlordLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

const auth    = useLandlordAuth()
const baseUrl = window.location.origin
const flash   = reactive({ success: '', error: '' })

const stubGateways = ['pawapay', 'lipila', 'stripe']

const gateways = ref([])
const loading  = reactive({})
const expanded = reactive({})
const forms    = reactive({})

// ─── Init ─────────────────────────────────────────────────────────────────

async function loadGateways() {
  try {
    const res = await fetch('/api/v1/landlord/billing-settings', {
      headers: { Authorization: `Bearer ${auth.token}`, Accept: 'application/json' },
    })
    const json = await res.json()
    gateways.value = json.data ?? []
    for (const gw of gateways.value) {
      expanded[gw.gateway] = false
      loading[gw.gateway]  = false
      forms[gw.gateway]    = { public_key: '', secret_key: '', webhook_secret: '' }
    }
  } catch (e) {
    flash.error = 'Failed to load gateway settings.'
  }
}

loadGateways()

// ─── Actions ──────────────────────────────────────────────────────────────

function toggleExpand(gateway) {
  expanded[gateway] = !expanded[gateway]
}

async function save(gateway) {
  loading[gateway] = true
  flash.success = ''
  flash.error   = ''
  try {
    const body = {}
    const form = forms[gateway]
    if (form.public_key)     body.public_key     = form.public_key
    if (form.secret_key)     body.secret_key     = form.secret_key
    if (form.webhook_secret) body.webhook_secret = form.webhook_secret

    const res = await fetch(`/api/v1/landlord/billing-settings/${gateway}`, {
      method: 'PUT',
      headers: {
        Authorization:  `Bearer ${auth.token}`,
        Accept:         'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Error saving credentials.')

    flash.success = json.message ?? 'Credentials saved.'
    forms[gateway] = { public_key: '', secret_key: '', webhook_secret: '' }
    expanded[gateway] = false
    await loadGateways()
  } catch (e) {
    flash.error = e.message
  } finally {
    loading[gateway] = false
  }
}

async function activate(gateway) {
  loading[gateway] = true
  flash.success = ''
  flash.error   = ''
  try {
    const res = await fetch(`/api/v1/landlord/billing-settings/${gateway}/activate`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}`, Accept: 'application/json' },
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Error.')
    flash.success = json.message
    await loadGateways()
  } catch (e) {
    flash.error = e.message
  } finally {
    loading[gateway] = false
  }
}

async function deactivate(gateway) {
  loading[gateway] = true
  try {
    await fetch(`/api/v1/landlord/billing-settings/${gateway}/deactivate`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}`, Accept: 'application/json' },
    })
    await loadGateways()
  } finally {
    loading[gateway] = false
  }
}

function gatewayColor(gateway) {
  return {
    flutterwave: 'bg-orange-500',
    pawapay:     'bg-purple-500',
    lipila:      'bg-teal-500',
    stripe:      'bg-indigo-500',
  }[gateway] ?? 'bg-neutral-500'
}
</script>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
