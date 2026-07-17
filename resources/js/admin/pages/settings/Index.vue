<template>
  <AppLayout>
    <template #header>
      <h1 class="text-2xl font-bold text-neutral-900">Settings</h1>
    </template>

    <!-- Tab bar -->
    <div class="flex gap-1 mb-6 border-b border-neutral-200 overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 -mb-px transition"
        :class="activeTab === tab.id
          ? 'border-primary-600 text-primary-700'
          : 'border-transparent text-neutral-500 hover:text-neutral-800'"
      >
        {{ tab.label }}
      </button>
    </div>

    <form @submit.prevent="save" class="space-y-6 max-w-2xl">

      <!-- ─── General ─── -->
      <template v-if="activeTab === 'general'">
        <div class="lendr-card p-6 space-y-4">
          <h2 class="text-base font-semibold text-neutral-800 mb-2">Company Information</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Company Name</label>
              <input v-model="form.company_name" type="text" class="input w-full" />
            </div>
            <div>
              <label class="label">Phone</label>
              <input v-model="form.company_phone" type="text" class="input w-full" />
            </div>
            <div>
              <label class="label">Email</label>
              <input v-model="form.company_email" type="email" class="input w-full" />
            </div>
            <div>
              <label class="label">Currency</label>
              <select v-model="form.currency" class="input w-full">
                <option value="ZMW">ZMW — Zambian Kwacha</option>
                <option value="USD">USD — US Dollar</option>
              </select>
            </div>
            <div>
              <label class="label">Timezone</label>
              <select v-model="form.timezone" class="input w-full">
                <option value="Africa/Lusaka">Africa/Lusaka (CAT)</option>
                <option value="UTC">UTC</option>
              </select>
            </div>
            <div>
              <label class="label">Date Format</label>
              <select v-model="form.date_format" class="input w-full">
                <option value="d/m/Y">DD/MM/YYYY</option>
                <option value="m/d/Y">MM/DD/YYYY</option>
                <option value="Y-m-d">YYYY-MM-DD</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label">Address</label>
            <textarea v-model="form.company_address" rows="2" class="input w-full" />
          </div>
        </div>
      </template>

      <!-- ─── Branding ─── -->
      <template v-if="activeTab === 'branding'">
        <div class="lendr-card p-6 space-y-5">
          <h2 class="text-base font-semibold text-neutral-800 mb-2">Branding</h2>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Primary Colour</label>
              <div class="flex items-center gap-2">
                <input type="color" v-model="form.primary_color" class="h-9 w-12 cursor-pointer rounded border border-neutral-200" />
                <input v-model="form.primary_color" type="text" class="input flex-1" />
              </div>
            </div>
            <div>
              <label class="label">Secondary Colour</label>
              <div class="flex items-center gap-2">
                <input type="color" v-model="form.secondary_color" class="h-9 w-12 cursor-pointer rounded border border-neutral-200" />
                <input v-model="form.secondary_color" type="text" class="input flex-1" />
              </div>
            </div>
          </div>
          <div>
            <label class="label">PWA App Name</label>
            <input v-model="form.pwa_app_name" type="text" class="input w-full" maxlength="30" />
            <p class="text-xs text-neutral-400 mt-1">Shown on borrower's home screen</p>
          </div>
          <div>
            <label class="label">PWA Theme Colour</label>
            <div class="flex items-center gap-2">
              <input type="color" v-model="form.pwa_theme_color" class="h-9 w-12 cursor-pointer rounded border border-neutral-200" />
              <input v-model="form.pwa_theme_color" type="text" class="input flex-1" />
            </div>
          </div>
          <!-- Live preview -->
          <div class="rounded-lg border border-neutral-200 p-4 mt-2">
            <p class="text-xs text-neutral-500 mb-2 font-medium uppercase tracking-wide">Preview</p>
            <div class="h-10 rounded flex items-center px-4 text-white text-sm font-semibold" :style="{ background: form.primary_color }">
              {{ form.company_name || 'LENDR' }} Admin
            </div>
          </div>
        </div>
      </template>

      <!-- ─── SMTP ─── -->
      <template v-if="activeTab === 'smtp'">
        <!-- Enterprise gate -->
        <div v-if="!isEnterprise" class="lendr-card p-6 text-center space-y-3">
          <div class="text-4xl">🔒</div>
          <h2 class="font-semibold text-neutral-800">Custom SMTP is an Enterprise feature</h2>
          <p class="text-sm text-neutral-500 max-w-sm mx-auto">
            Your workspace uses the LENDR platform email. Upgrade to Enterprise to configure your own SMTP server and send emails from your own domain.
          </p>
          <a :href="route('billing.index')" class="btn-primary inline-block text-sm px-4 py-2">Upgrade to Enterprise</a>
        </div>

        <div v-else class="lendr-card p-6 space-y-4">
          <h2 class="text-base font-semibold text-neutral-800 mb-2">Email SMTP</h2>
          <p class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
            Enterprise — your custom SMTP overrides the platform default.
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="label">SMTP Host</label>
              <input v-model="form.smtp_host" type="text" class="input w-full" placeholder="smtp.mailgun.org" />
            </div>
            <div>
              <label class="label">Port</label>
              <input v-model="form.smtp_port" type="number" class="input w-full" placeholder="587" />
            </div>
            <div>
              <label class="label">Encryption</label>
              <select v-model="form.smtp_encryption" class="input w-full">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="">None</option>
              </select>
            </div>
            <div>
              <label class="label">Username</label>
              <input v-model="form.smtp_username" type="text" class="input w-full" />
            </div>
            <div>
              <label class="label">Password</label>
              <div class="relative">
                <input v-model="form.smtp_password" :type="showSmtpPassword ? 'text' : 'password'" class="input w-full pr-10" />
                <button type="button" @click="showSmtpPassword = !showSmtpPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 text-xs">
                  {{ showSmtpPassword ? 'Hide' : 'Show' }}
                </button>
              </div>
            </div>
            <div>
              <label class="label">From Email</label>
              <input v-model="form.smtp_from_email" type="email" class="input w-full" />
            </div>
            <div>
              <label class="label">From Name</label>
              <input v-model="form.smtp_from_name" type="text" class="input w-full" />
            </div>
          </div>
          <div class="pt-2">
            <button type="button" @click="testEmail" class="btn-ghost text-sm" :disabled="testingEmail">
              {{ testingEmail ? 'Sending…' : '✉ Send Test Email' }}
            </button>
            <p v-if="testEmailResult" class="text-xs mt-1" :class="testEmailResult.ok ? 'text-green-600' : 'text-red-600'">
              {{ testEmailResult.message }}
            </p>
          </div>
        </div>
        </div>
      </template>

      <!-- ─── SMS ─── -->
      <template v-if="activeTab === 'sms'">
        <!-- Enterprise gate -->
        <div v-if="!isEnterprise" class="lendr-card p-6 text-center space-y-3">
          <div class="text-4xl">🔒</div>
          <h2 class="font-semibold text-neutral-800">Custom SMS Gateway is an Enterprise feature</h2>
          <p class="text-sm text-neutral-500 max-w-sm mx-auto">
            Your workspace uses the LENDR platform SMS provider. Upgrade to Enterprise to configure your own Africa's Talking or SMS.to account.
          </p>
          <a :href="route('billing.index')" class="btn-primary inline-block text-sm px-4 py-2">Upgrade to Enterprise</a>
        </div>

        <div v-else class="lendr-card p-6 space-y-4">
          <h2 class="text-base font-semibold text-neutral-800 mb-2">SMS Gateway</h2>
          <p class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
            Enterprise — your custom SMS credentials override the platform default.
          </p>
          <div>
            <label class="label">Gateway</label>
            <select v-model="form.sms_gateway" class="input w-full">
              <option value="africas_talking">Africa's Talking</option>
              <option value="sms_to">SMS.to</option>
            </select>
          </div>
          <div v-if="form.sms_gateway === 'africas_talking'">
            <label class="label">Username</label>
            <input v-model="form.sms_username" type="text" class="input w-full" placeholder="sandbox" />
          </div>
          <div>
            <label class="label">Sender Name / ID</label>
            <input v-model="form.sms_sender_name" type="text" class="input w-full" maxlength="11" />
            <p class="text-xs text-neutral-400 mt-1">Max 11 characters (alphanumeric sender ID)</p>
          </div>
          <div>
            <label class="label">API Key</label>
            <div class="relative">
              <input v-model="form.sms_api_key" :type="showSmsKey ? 'text' : 'password'" class="input w-full pr-10" />
              <button type="button" @click="showSmsKey = !showSmsKey"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 text-xs">
                {{ showSmsKey ? 'Hide' : 'Show' }}
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- ─── Security ─── -->
      <template v-if="activeTab === 'security'">
        <div class="lendr-card p-6 space-y-4">
          <h2 class="text-base font-semibold text-neutral-800 mb-2">Security</h2>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" v-model="form.require_2fa" class="w-4 h-4 rounded text-primary-600" />
            <span class="text-sm text-neutral-800">Require 2FA for all staff accounts</span>
          </label>
          <div>
            <label class="label">Session Timeout (minutes)</label>
            <input v-model="form.session_timeout_minutes" type="number" min="5" class="input w-32" />
          </div>
          <div>
            <label class="label">Password Expiry (days, 0 = never)</label>
            <input v-model="form.password_expiry_days" type="number" min="0" class="input w-32" />
          </div>
        </div>
      </template>

      <!-- ─── Notifications ─── -->
      <template v-if="activeTab === 'notifications'">
        <div class="lendr-card p-6 space-y-5">
          <div>
            <h2 class="text-base font-semibold text-neutral-800">Notification Preferences</h2>
            <p class="text-sm text-neutral-500 mt-0.5">Choose which events generate in-app, email, or SMS alerts for your account.</p>
          </div>

          <div v-if="prefLoading" class="text-sm text-neutral-400 py-6 text-center">Loading preferences…</div>

          <template v-else>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-neutral-200">
                    <th class="text-left py-2 pr-4 font-medium text-neutral-600 w-48">Event</th>
                    <th v-for="ch in prefChannels" :key="ch" class="text-center py-2 px-4 font-medium text-neutral-600 capitalize">{{ ch.replace('_', ' ') }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                  <tr v-for="event in prefEvents" :key="event">
                    <td class="py-2.5 pr-4 text-neutral-700 capitalize">{{ event.replace(/_/g, ' ') }}</td>
                    <td v-for="ch in prefChannels" :key="ch" class="text-center py-2.5 px-4">
                      <input
                        type="checkbox"
                        class="w-4 h-4 rounded text-primary-600"
                        :checked="prefMatrix[ch]?.[event] ?? true"
                        @change="togglePref(ch, event, $event.target.checked)"
                      />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex items-center gap-3">
              <button @click="savePreferences" :disabled="prefSaving" class="btn-primary text-sm px-4 py-2">
                {{ prefSaving ? 'Saving…' : 'Save Preferences' }}
              </button>
              <span v-if="prefMsg" class="text-sm" :class="prefErr ? 'text-red-500' : 'text-emerald-600'">{{ prefMsg }}</span>
            </div>
          </template>
        </div>
      </template>

      <!-- Save button (hidden on notifications tab — has its own save) -->
      <div v-if="activeTab !== 'notifications'" class="flex justify-end">
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save Settings' }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({ settings: Object })

const page = usePage()
const isEnterprise = computed(() => page.props.tenant?.plan === 'enterprise')

const tabs = [
  { id: 'general',       label: 'General'       },
  { id: 'branding',      label: 'Branding'      },
  { id: 'smtp',          label: 'Email SMTP'    },
  { id: 'sms',           label: 'SMS Gateway'   },
  { id: 'security',      label: 'Security'      },
  { id: 'notifications', label: 'Notifications' },
]

const activeTab = ref('general')
const saving    = ref(false)
const testingEmail  = ref(false)
const testEmailResult = ref(null)
const showSmtpPassword = ref(false)
const showSmsKey       = ref(false)

// Flatten settings prop into a single reactive form object
const s = props.settings || {}
const form = reactive({
  company_name:           s.company_name           || '',
  company_phone:          s.company_phone          || '',
  company_email:          s.company_email          || '',
  company_address:        s.company_address        || '',
  currency:               s.currency               || 'ZMW',
  timezone:               s.timezone               || 'Africa/Lusaka',
  date_format:            s.date_format            || 'd/m/Y',
  primary_color:          s.primary_color          || '#0D47A1',
  secondary_color:        s.secondary_color        || '#1565C0',
  pwa_app_name:           s.pwa_app_name           || 'LENDR',
  pwa_theme_color:        s.pwa_theme_color        || '#0D47A1',
  smtp_host:              s.smtp_host              || '',
  smtp_port:              s.smtp_port              || '587',
  smtp_username:          s.smtp_username          || '',
  smtp_password:          s.smtp_password          || '',
  smtp_from_email:        s.smtp_from_email        || '',
  smtp_from_name:         s.smtp_from_name         || '',
  smtp_encryption:        s.smtp_encryption        || 'tls',
  sms_gateway:            s.sms_gateway            || 'africas_talking',
  sms_username:           s.sms_username           || '',
  sms_sender_name:        s.sms_sender_name        || 'LENDR',
  sms_api_key:            s.sms_api_key            || '',
  require_2fa:            s.require_2fa === 'true' || s.require_2fa === true,
  session_timeout_minutes: s.session_timeout_minutes || '480',
  password_expiry_days:    s.password_expiry_days    || '0',
})

function save() {
  saving.value = true
  router.put(route('settings.update'), { settings: form }, {
    preserveScroll: true,
    onFinish: () => { saving.value = false },
  })
}

function testEmail() {
  testingEmail.value   = true
  testEmailResult.value = null
  router.post(route('settings.test-email'), {}, {
    preserveScroll: true,
    onSuccess: () => { testEmailResult.value = { ok: true, message: 'Test email sent successfully.' } },
    onError:   () => { testEmailResult.value = { ok: false, message: 'Failed to send. Check SMTP credentials.' } },
    onFinish:  () => { testingEmail.value = false },
  })
}

// ─── Notification Preferences ─────────────────────────────────────────────────

import axios from 'axios'
import { watch } from 'vue'

const prefLoading  = ref(false)
const prefSaving   = ref(false)
const prefMsg      = ref(null)
const prefErr      = ref(false)
const prefChannels = ref([])
const prefEvents   = ref([])
const prefMatrix   = ref({})

async function loadPreferences() {
  prefLoading.value = true
  try {
    const { data } = await axios.get(route('api.v1.notification-preferences.index'))
    prefChannels.value = data.data.channels
    prefEvents.value   = data.data.events
    prefMatrix.value   = data.data.matrix
  } finally {
    prefLoading.value = false
  }
}

function togglePref(channel, event, enabled) {
  if (!prefMatrix.value[channel]) prefMatrix.value[channel] = {}
  prefMatrix.value[channel][event] = enabled
}

async function savePreferences() {
  prefSaving.value = true
  prefMsg.value    = null
  try {
    const preferences = []
    for (const channel of prefChannels.value) {
      for (const event of prefEvents.value) {
        preferences.push({ channel, event, is_enabled: prefMatrix.value[channel]?.[event] ?? true })
      }
    }
    const { data } = await axios.put(route('api.v1.notification-preferences.update'), { preferences })
    prefMatrix.value = data.data.matrix
    prefMsg.value    = 'Preferences saved.'
    prefErr.value    = false
  } catch {
    prefMsg.value  = 'Failed to save preferences.'
    prefErr.value  = true
  } finally {
    prefSaving.value = false
  }
}

// Load preferences when tab is activated
watch(activeTab, (tab) => {
  if (tab === 'notifications' && !prefChannels.value.length) loadPreferences()
})
</script>
