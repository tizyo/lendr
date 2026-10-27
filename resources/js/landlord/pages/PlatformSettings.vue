<template>
  <LandlordLayout title="Platform Settings">
    <div class="space-y-8 max-w-3xl">

      <!-- ─── Platform SMS ─────────────────────────────────────────────────── -->
      <section>
        <h2 class="text-xs font-semibold text-neutral-500 uppercase tracking-widest mb-4">Platform SMS</h2>
        <p class="text-sm text-neutral-500 mb-4">
          Configure the SMS provider used by all tenants on Starter &amp; Growth plans.
          Enterprise tenants may override with their own credentials.
          Only one provider can be active at a time.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div
            v-for="provider in smsProviders"
            :key="provider.provider"
            class="bg-white rounded-xl border p-5 space-y-4"
            :class="provider.is_active ? 'border-emerald-400 ring-1 ring-emerald-300' : 'border-neutral-200'"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-lg">{{ provider.provider === 'africas_talking' ? '📡' : '💬' }}</span>
                <h3 class="font-semibold text-neutral-800">{{ provider.label }}</h3>
              </div>
              <span
                v-if="provider.is_active"
                class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700 font-medium"
              >Active</span>
            </div>

            <!-- AfricasTalking fields -->
            <template v-if="provider.provider === 'africas_talking'">
              <div>
                <label class="label">Username</label>
                <input v-model="provider._form.username" type="text" class="input w-full text-sm" placeholder="sandbox" />
              </div>
              <div>
                <label class="label">API Key</label>
                <input v-model="provider._form.api_key" type="password" class="input w-full text-sm" placeholder="Enter to update" />
              </div>
              <div>
                <label class="label">Sender ID</label>
                <input v-model="provider._form.sender_id" type="text" class="input w-full text-sm" maxlength="11" />
              </div>
              <label class="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
                <input type="checkbox" v-model="provider._form.sandbox" class="rounded" />
                Sandbox mode
              </label>
            </template>

            <!-- SMS.to fields -->
            <template v-else>
              <div>
                <label class="label">API Key</label>
                <input v-model="provider._form.api_key" type="password" class="input w-full text-sm" placeholder="Enter to update" />
              </div>
              <div>
                <label class="label">Sender ID</label>
                <input v-model="provider._form.sender_id" type="text" class="input w-full text-sm" maxlength="11" />
              </div>
            </template>

            <div class="flex gap-2 pt-1">
              <button
                @click="saveSms(provider)"
                :disabled="provider._saving"
                class="btn-secondary text-xs px-3 py-1.5"
              >{{ provider._saving ? 'Saving…' : 'Save' }}</button>
              <button
                v-if="!provider.is_active"
                @click="activateSms(provider)"
                :disabled="provider._activating || !provider.is_configured"
                class="btn-primary text-xs px-3 py-1.5"
                :title="!provider.is_configured ? 'Save API key first' : ''"
              >{{ provider._activating ? 'Activating…' : 'Activate' }}</button>
              <button
                v-else
                @click="deactivateSms(provider)"
                :disabled="provider._activating"
                class="text-xs px-3 py-1.5 rounded-lg border border-neutral-200 text-neutral-500 hover:bg-neutral-50"
              >Deactivate</button>
            </div>

            <p v-if="provider._msg" class="text-xs" :class="provider._err ? 'text-red-500' : 'text-emerald-600'">
              {{ provider._msg }}
            </p>
          </div>
        </div>
      </section>

      <!-- ─── Platform Branding ───────────────────────────────────────────────── -->
      <section>
        <h2 class="text-xs font-semibold text-neutral-500 uppercase tracking-widest mb-4">Platform Branding</h2>
        <p class="text-sm text-neutral-500 mb-4">
          Company details, logo, and favicon used on all PDF invoices, receipts, and tenant-facing emails.
        </p>

        <div class="bg-white rounded-xl border border-neutral-200 p-6 space-y-6">

          <!-- Logo + Favicon uploads -->
          <div class="grid grid-cols-2 gap-6">
            <!-- Logo -->
            <div>
              <p class="label mb-2">Company Logo</p>
              <div class="flex flex-col items-start gap-3">
                <img
                  v-if="branding.logo_url"
                  :src="branding.logo_url"
                  alt="Logo"
                  class="h-16 w-auto object-contain border border-neutral-200 rounded-lg p-1 bg-white"
                />
                <div v-else class="h-16 w-40 border border-dashed border-neutral-300 rounded-lg flex items-center justify-center text-neutral-400 text-xs">No logo</div>
                <div class="flex gap-2 items-center">
                  <label class="btn-secondary text-xs px-3 py-1.5 cursor-pointer">
                    <input type="file" accept="image/*" class="hidden" @change="uploadLogo" />
                    {{ brandingLogoUploading ? 'Uploading…' : 'Upload' }}
                  </label>
                  <button
                    v-if="branding.logo_url"
                    @click="deleteLogo"
                    :disabled="brandingLogoDeleting"
                    class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-500 hover:bg-red-50"
                  >{{ brandingLogoDeleting ? 'Removing…' : 'Remove' }}</button>
                </div>
              </div>
            </div>

            <!-- Favicon -->
            <div>
              <p class="label mb-2">Favicon</p>
              <div class="flex flex-col items-start gap-3">
                <img
                  v-if="branding.favicon_url"
                  :src="branding.favicon_url"
                  alt="Favicon"
                  class="h-16 w-16 object-contain border border-neutral-200 rounded-lg p-1 bg-white"
                />
                <div v-else class="h-16 w-16 border border-dashed border-neutral-300 rounded-lg flex items-center justify-center text-neutral-400 text-xs">None</div>
                <div class="flex gap-2 items-center">
                  <label class="btn-secondary text-xs px-3 py-1.5 cursor-pointer">
                    <input type="file" accept="image/x-icon,image/png,image/svg+xml" class="hidden" @change="uploadFavicon" />
                    {{ brandingFaviconUploading ? 'Uploading…' : 'Upload' }}
                  </label>
                  <button
                    v-if="branding.favicon_url"
                    @click="deleteFavicon"
                    :disabled="brandingFaviconDeleting"
                    class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-500 hover:bg-red-50"
                  >{{ brandingFaviconDeleting ? 'Removing…' : 'Remove' }}</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Company Details -->
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="label">Company Name</label>
              <input v-model="branding.company_name" type="text" class="input w-full" placeholder="LENDR Financial" />
            </div>
            <div class="col-span-2">
              <label class="label">Tagline</label>
              <input v-model="branding.tagline" type="text" class="input w-full" placeholder="Smart Lending Solutions" />
            </div>
            <div class="col-span-2">
              <label class="label">Address</label>
              <input v-model="branding.address" type="text" class="input w-full" placeholder="123 Main St, City" />
            </div>
            <div>
              <label class="label">Phone</label>
              <input v-model="branding.phone" type="text" class="input w-full" placeholder="+260 97 000 0000" />
            </div>
            <div>
              <label class="label">Email</label>
              <input v-model="branding.email" type="email" class="input w-full" placeholder="info@company.com" />
            </div>
            <div>
              <label class="label">Website</label>
              <input v-model="branding.website" type="text" class="input w-full" placeholder="https://company.com" />
            </div>
            <div>
              <label class="label">Brand Color</label>
              <div class="flex gap-2 items-center">
                <input v-model="branding.primary_color" type="color" class="h-9 w-12 rounded border border-neutral-200 cursor-pointer p-0.5" />
                <input v-model="branding.primary_color" type="text" class="input flex-1" placeholder="#059669" maxlength="7" />
              </div>
            </div>
          </div>

          <!-- Footers -->
          <div class="space-y-4">
            <div>
              <label class="label">Invoice / PDF Footer</label>
              <textarea v-model="branding.invoice_footer" class="input w-full text-sm" rows="2"
                placeholder="Licensed by the Bank of Zambia. Reg. No. 12345."></textarea>
              <p class="text-xs text-neutral-400 mt-1">Appears at the bottom of all PDF documents (receipts, agreements, schedules).</p>
            </div>
            <div>
              <label class="label">Email Footer</label>
              <textarea v-model="branding.email_footer" class="input w-full text-sm" rows="2"
                placeholder="© 2025 LENDR Financial. All rights reserved."></textarea>
              <p class="text-xs text-neutral-400 mt-1">Appears at the bottom of all branded tenant emails.</p>
            </div>
          </div>

          <div class="flex items-center gap-3 pt-1">
            <button @click="saveBranding" :disabled="brandingSaving" class="btn-primary text-sm px-4 py-2">
              {{ brandingSaving ? 'Saving…' : 'Save Branding' }}
            </button>
          </div>

          <p v-if="brandingMsg" class="text-sm" :class="brandingErr ? 'text-red-500' : 'text-emerald-600'">
            {{ brandingMsg }}
          </p>
        </div>
      </section>

      <!-- ─── Platform Email ────────────────────────────────────────────────── -->
      <section>
        <h2 class="text-xs font-semibold text-neutral-500 uppercase tracking-widest mb-4">Platform Email</h2>
        <p class="text-sm text-neutral-500 mb-4">
          Delivers every email the platform sends — system emails (trial warnings, verification) and tenant emails
          (staff invites, loan notices, statements). Enterprise tenants may override with their own SMTP.
        </p>

        <div class="bg-white rounded-xl border border-neutral-200 p-6 space-y-5">
          <div>
            <label class="label">Provider</label>
            <div class="inline-flex rounded-lg border border-neutral-200 p-0.5 bg-neutral-50">
              <button
                v-for="opt in emailDrivers"
                :key="opt.value"
                type="button"
                @click="email.driver = opt.value"
                class="px-4 py-1.5 text-sm rounded-md transition-colors"
                :class="email.driver === opt.value ? 'bg-white shadow-sm text-neutral-900 font-medium' : 'text-neutral-500 hover:text-neutral-700'"
              >{{ opt.label }}</button>
            </div>
            <p class="text-xs text-neutral-400 mt-1.5">
              {{ email.driver === 'resend'
                ? 'Sends over HTTPS — use this on DigitalOcean, which blocks outbound SMTP.'
                : 'Classic SMTP relay. Will not work on hosts that block SMTP ports (e.g. DigitalOcean).' }}
            </p>
          </div>

          <!-- Resend -->
          <div v-if="email.driver === 'resend'">
            <label class="label">Resend API Key</label>
            <div class="flex gap-2">
              <input
                v-model="email.api_key"
                :type="showApiKey ? 'text' : 'password'"
                class="input w-full font-mono text-sm"
                autocomplete="off"
                :placeholder="email.has_api_key ? '•••••••• saved — enter a new key to replace' : 're_…'"
              />
              <button type="button" @click="showApiKey = !showApiKey" class="btn-secondary text-xs px-3">
                {{ showApiKey ? 'Hide' : 'Show' }}
              </button>
            </div>
            <p class="text-xs text-neutral-400 mt-1">
              Create one at resend.com → API Keys. Stored encrypted and never shown again after saving.
            </p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">From Address</label>
              <input v-model="email.from_address" type="email" class="input w-full" placeholder="noreply@lendr.app" />
              <p v-if="email.driver === 'resend'" class="text-xs text-neutral-400 mt-1">Must be on a domain verified in Resend.</p>
            </div>
            <div>
              <label class="label">System Sender Name</label>
              <input v-model="email.from_name" type="text" class="input w-full" placeholder="LENDR" />
            </div>
            <p class="col-span-2 text-xs text-neutral-500 -mt-1">
              System emails are sent as <span class="font-medium text-neutral-700">{{ email.from_name || 'LENDR' }}</span>.
              Tenant emails use the tenant's workspace slug as the sender name, e.g.
              <span class="font-medium text-neutral-700">vozara &lt;{{ email.from_address || 'noreply@lendr.app' }}&gt;</span>.
            </p>
          </div>

          <!-- SMTP -->
          <div v-if="email.driver === 'smtp'" class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="label">SMTP Host</label>
              <input v-model="email.host" type="text" class="input w-full" placeholder="smtp.mailgun.org" />
            </div>
            <div>
              <label class="label">Port</label>
              <input v-model="email.port" type="number" class="input w-full" placeholder="587" />
            </div>
            <div>
              <label class="label">Encryption</label>
              <select v-model="email.encryption" class="input w-full">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="">None</option>
              </select>
            </div>
            <div>
              <label class="label">Username</label>
              <input v-model="email.username" type="text" class="input w-full" />
            </div>
            <div>
              <label class="label">Password</label>
              <input
                v-model="email.password"
                :type="showPassword ? 'text' : 'password'"
                class="input w-full"
                placeholder="Enter to update"
              />
            </div>
          </div>

          <div class="flex items-center gap-3 pt-1 flex-wrap">
            <button @click="saveEmail" :disabled="emailSaving" class="btn-primary text-sm px-4 py-2">
              {{ emailSaving ? 'Saving…' : 'Save Email Config' }}
            </button>
            <div class="flex gap-2 items-center">
              <input v-model="testTo" type="email" class="input text-sm w-52" placeholder="test@example.com" />
              <button @click="testEmail" :disabled="emailTesting || !testTo" class="btn-secondary text-sm px-3 py-2">
                {{ emailTesting ? 'Sending…' : '✉ Send Test' }}
              </button>
            </div>
          </div>

          <p v-if="emailMsg" class="text-sm" :class="emailErr ? 'text-red-500' : 'text-emerald-600'">
            {{ emailMsg }}
          </p>

          <div v-if="email.is_active" class="flex items-center gap-2 text-xs text-emerald-600 font-medium">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            Active — all mail is sent via {{ email.active_driver === 'resend' ? 'Resend' : 'SMTP' }}
          </div>
          <div v-else class="flex items-center gap-2 text-xs text-amber-600 font-medium">
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            Not configured — mail falls back to the server's .env mailer
          </div>
        </div>
      </section>

    </div>
  </LandlordLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'

// ─── Branding state ───────────────────────────────────────────────────────────
const branding = ref({
  company_name: '', tagline: '', address: '', phone: '', email: '', website: '',
  primary_color: '#059669', invoice_footer: '', email_footer: '',
  logo_url: null, favicon_url: null,
})
const brandingSaving         = ref(false)
const brandingMsg            = ref('')
const brandingErr            = ref(false)
const brandingLogoUploading  = ref(false)
const brandingLogoDeleting   = ref(false)
const brandingFaviconUploading = ref(false)
const brandingFaviconDeleting  = ref(false)

async function loadBranding() {
  const { data } = await axios.get('/api/v1/landlord/platform-settings/branding')
  const d = data.data ?? {}
  branding.value = {
    company_name:    d.company_name    ?? '',
    tagline:         d.tagline         ?? '',
    address:         d.address         ?? '',
    phone:           d.phone           ?? '',
    email:           d.email           ?? '',
    website:         d.website         ?? '',
    primary_color:   d.primary_color   ?? '#059669',
    invoice_footer:  d.invoice_footer  ?? '',
    email_footer:    d.email_footer    ?? '',
    logo_url:        d.logo_url        ?? null,
    favicon_url:     d.favicon_url     ?? null,
  }
}

async function saveBranding() {
  brandingSaving.value = true
  brandingMsg.value    = ''
  try {
    await axios.put('/api/v1/landlord/platform-settings/branding', {
      company_name:   branding.value.company_name,
      tagline:        branding.value.tagline,
      address:        branding.value.address,
      phone:          branding.value.phone,
      email:          branding.value.email,
      website:        branding.value.website,
      primary_color:  branding.value.primary_color,
      invoice_footer: branding.value.invoice_footer,
      email_footer:   branding.value.email_footer,
    })
    brandingMsg.value = 'Branding saved.'
    brandingErr.value = false
  } catch (e) {
    brandingMsg.value = e.response?.data?.message ?? 'Save failed.'
    brandingErr.value = true
  } finally {
    brandingSaving.value = false
  }
}

async function uploadLogo(e) {
  const file = e.target.files[0]
  if (!file) return
  brandingLogoUploading.value = true
  brandingMsg.value = ''
  const form = new FormData()
  form.append('logo', file)
  try {
    const { data } = await axios.post('/api/v1/landlord/platform-settings/branding/logo', form)
    branding.value.logo_url = data.data?.logo_url ?? null
    brandingMsg.value = 'Logo uploaded.'
    brandingErr.value = false
  } catch (e) {
    brandingMsg.value = e.response?.data?.message ?? 'Upload failed.'
    brandingErr.value = true
  } finally {
    brandingLogoUploading.value = false
    e.target.value = ''
  }
}

async function deleteLogo() {
  brandingLogoDeleting.value = true
  try {
    await axios.delete('/api/v1/landlord/platform-settings/branding/logo')
    branding.value.logo_url = null
    brandingMsg.value = 'Logo removed.'
    brandingErr.value = false
  } catch {
    brandingMsg.value = 'Remove failed.'
    brandingErr.value = true
  } finally {
    brandingLogoDeleting.value = false
  }
}

async function uploadFavicon(e) {
  const file = e.target.files[0]
  if (!file) return
  brandingFaviconUploading.value = true
  brandingMsg.value = ''
  const form = new FormData()
  form.append('favicon', file)
  try {
    const { data } = await axios.post('/api/v1/landlord/platform-settings/branding/favicon', form)
    branding.value.favicon_url = data.data?.favicon_url ?? null
    brandingMsg.value = 'Favicon uploaded.'
    brandingErr.value = false
  } catch (e) {
    brandingMsg.value = e.response?.data?.message ?? 'Upload failed.'
    brandingErr.value = true
  } finally {
    brandingFaviconUploading.value = false
    e.target.value = ''
  }
}

async function deleteFavicon() {
  brandingFaviconDeleting.value = true
  try {
    await axios.delete('/api/v1/landlord/platform-settings/branding/favicon')
    branding.value.favicon_url = null
    brandingMsg.value = 'Favicon removed.'
    brandingErr.value = false
  } catch {
    brandingMsg.value = 'Remove failed.'
    brandingErr.value = true
  } finally {
    brandingFaviconDeleting.value = false
  }
}

// ─── SMS state ────────────────────────────────────────────────────────────────
const smsProviders = ref([])

async function loadSms() {
  const { data } = await axios.get('/api/v1/landlord/platform-settings/sms')
  smsProviders.value = (data.data ?? []).map(p => ({
    ...p,
    _form: {
      api_key:   '',
      username:  p.username  ?? '',
      sender_id: p.sender_id ?? 'LENDR',
      sandbox:   p.sandbox   ?? false,
    },
    _saving:    false,
    _activating: false,
    _msg: '',
    _err: false,
  }))
}

async function saveSms(provider) {
  provider._saving = true
  provider._msg    = ''
  try {
    await axios.put(`/api/v1/landlord/platform-settings/sms/${provider.provider}`, provider._form)
    provider.is_configured = true
    provider._msg = 'Saved.'
    provider._err = false
    provider._form.api_key = '' // clear after save
  } catch (e) {
    provider._msg = e.response?.data?.message ?? 'Save failed.'
    provider._err = true
  } finally {
    provider._saving = false
  }
}

async function activateSms(provider) {
  provider._activating = true
  provider._msg        = ''
  try {
    await axios.post(`/api/v1/landlord/platform-settings/sms/${provider.provider}/activate`)
    smsProviders.value.forEach(p => { p.is_active = p.provider === provider.provider })
    provider._msg = `${provider.label} is now active.`
    provider._err = false
  } catch (e) {
    provider._msg = e.response?.data?.message ?? 'Activation failed.'
    provider._err = true
  } finally {
    provider._activating = false
  }
}

async function deactivateSms(provider) {
  provider._activating = true
  try {
    await axios.post(`/api/v1/landlord/platform-settings/sms/${provider.provider}/deactivate`)
    provider.is_active = false
    provider._msg = 'Deactivated.'
    provider._err = false
  } catch {
    provider._msg = 'Failed.'
    provider._err = true
  } finally {
    provider._activating = false
  }
}

// ─── Email state ──────────────────────────────────────────────────────────────
const emailDrivers = [
  { value: 'resend', label: 'Resend (recommended)' },
  { value: 'smtp',   label: 'SMTP' },
]
const email      = ref({ driver: 'resend', api_key: '', has_api_key: false, active_driver: null, host: '', port: 587, encryption: 'tls', username: '', password: '', from_address: '', from_name: '', is_active: false })
const emailSaving = ref(false)
const emailTesting = ref(false)
const emailMsg   = ref('')
const emailErr   = ref(false)
const showPassword = ref(false)
const showApiKey = ref(false)
const testTo     = ref('')

async function loadEmail() {
  const { data } = await axios.get('/api/v1/landlord/platform-settings/email')
  const d = data.data ?? {}
  email.value = {
    driver:        d.driver       ?? 'resend',
    api_key:       '',
    has_api_key:   d.has_api_key  ?? false,
    active_driver: d.is_active ? d.driver : null,
    host:         d.host         ?? '',
    port:         d.port         ?? 587,
    encryption:   d.encryption   ?? 'tls',
    username:     d.username     ?? '',
    password:     '',
    from_address: d.from_address ?? '',
    from_name:    d.from_name    ?? 'LENDR',
    is_active:    d.is_active    ?? false,
  }
}

async function saveEmail() {
  emailSaving.value = true
  emailMsg.value    = ''
  try {
    const { has_api_key, active_driver, is_active, ...payload } = email.value
    if (! payload.password) delete payload.password
    if (! payload.api_key) delete payload.api_key
    const { data } = await axios.put('/api/v1/landlord/platform-settings/email', payload)
    if (payload.api_key) email.value.has_api_key = true
    email.value.is_active     = data.data?.is_active ?? false
    email.value.active_driver = email.value.is_active ? email.value.driver : null
    email.value.password      = ''
    email.value.api_key       = ''
    showApiKey.value          = false
    emailMsg.value = email.value.is_active ? 'Email config saved.' : 'Saved, but not active yet — complete the provider details.'
    emailErr.value = ! email.value.is_active
  } catch (e) {
    emailMsg.value = e.response?.data?.message ?? 'Save failed.'
    emailErr.value = true
  } finally {
    emailSaving.value = false
  }
}

async function testEmail() {
  emailTesting.value = true
  emailMsg.value     = ''
  try {
    const { data } = await axios.post('/api/v1/landlord/platform-settings/email/test', { to: testTo.value })
    emailMsg.value = data.message ?? 'Test sent.'
    emailErr.value = false
  } catch (e) {
    emailMsg.value = e.response?.data?.message ?? 'Send failed.'
    emailErr.value = true
  } finally {
    emailTesting.value = false
  }
}

onMounted(() => {
  loadBranding()
  loadSms()
  loadEmail()
})
</script>
