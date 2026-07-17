<template>
  <AppLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">Broadcast Message</h1>
        <p class="text-sm text-neutral-500 mt-0.5">Send a custom SMS and/or email to your customers</p>
      </div>
    </template>

    <div class="max-w-2xl space-y-6">

      <!-- Success flash -->
      <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">
        {{ $page.props.flash.success }}
      </div>

      <!-- Audience card -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">1. Choose Audience</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <button
            v-for="opt in audienceOptions"
            :key="opt.value"
            @click="form.audience = opt.value"
            type="button"
            class="rounded-xl border-2 p-4 text-left transition"
            :class="form.audience === opt.value
              ? 'border-primary-500 bg-primary-50'
              : 'border-neutral-200 hover:border-neutral-300'"
          >
            <p class="text-sm font-semibold" :class="form.audience === opt.value ? 'text-primary-700' : 'text-neutral-800'">
              {{ opt.label }}
            </p>
            <p class="text-2xl font-bold mt-1" :class="form.audience === opt.value ? 'text-primary-600' : 'text-neutral-700'">
              {{ counts[opt.value] ?? 0 }}
            </p>
            <p class="text-xs text-neutral-500 mt-0.5">{{ opt.description }}</p>
          </button>
        </div>
        <p v-if="errors.audience" class="mt-2 text-xs text-red-600">{{ errors.audience }}</p>
      </div>

      <!-- Channel card -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">2. Delivery Channels</h2>
        <div class="flex flex-wrap gap-3">
          <label
            v-for="ch in channelOptions"
            :key="ch.value"
            class="flex items-center gap-2.5 cursor-pointer rounded-xl border-2 px-4 py-3 transition select-none"
            :class="form.channels.includes(ch.value)
              ? 'border-primary-500 bg-primary-50'
              : 'border-neutral-200 hover:border-neutral-300'"
          >
            <input
              type="checkbox"
              :value="ch.value"
              v-model="form.channels"
              class="rounded text-primary-600 focus:ring-primary-500"
            />
            <span class="text-sm font-medium" :class="form.channels.includes(ch.value) ? 'text-primary-700' : 'text-neutral-700'">
              {{ ch.label }}
            </span>
            <span class="text-xs text-neutral-400">{{ ch.hint }}</span>
          </label>
        </div>
        <p v-if="errors.channels" class="mt-2 text-xs text-red-600">{{ errors.channels }}</p>
      </div>

      <!-- Message card -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">3. Compose Message</h2>
        <div class="space-y-4">

          <!-- Subject — only if email selected -->
          <div v-if="form.channels.includes('email')">
            <label class="block text-xs font-medium text-neutral-500 mb-1">Email Subject <span class="text-red-500">*</span></label>
            <input v-model="form.subject" type="text" class="input" placeholder="e.g. Important Update from LENDR" maxlength="200" />
            <p v-if="errors.subject" class="mt-1 text-xs text-red-600">{{ errors.subject }}</p>
          </div>

          <!-- Message body -->
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">
              Message
              <span class="text-neutral-400 font-normal">
                ({{ form.message.length }}/1600
                <span v-if="form.channels.includes('sms')" class="text-amber-600">· SMS segments: {{ smsSegments }}</span>)
              </span>
            </label>
            <textarea
              v-model="form.message"
              rows="6"
              maxlength="1600"
              class="input resize-none"
              placeholder="Type your message here…"
            ></textarea>
            <p v-if="errors.message" class="mt-1 text-xs text-red-600">{{ errors.message }}</p>
            <p v-if="form.channels.includes('sms')" class="mt-1 text-xs text-neutral-400">
              Standard SMS: 160 chars per segment. Unicode (emojis/special chars): 70 chars per segment.
            </p>
          </div>
        </div>
      </div>

      <!-- Preview & Send -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-4">4. Review & Send</h2>

        <!-- Summary -->
        <div class="bg-neutral-50 border border-neutral-200 rounded-xl p-4 space-y-2 text-sm mb-5">
          <div class="flex justify-between">
            <span class="text-neutral-500">Recipients</span>
            <span class="font-semibold text-neutral-900">
              {{ counts[form.audience] ?? 0 }} customers
              <span class="font-normal text-neutral-500">({{ selectedAudienceLabel }})</span>
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500">Channels</span>
            <span class="font-semibold text-neutral-900">
              {{ form.channels.length ? form.channels.map(c => c.toUpperCase()).join(' + ') : '—' }}
            </span>
          </div>
          <div v-if="form.channels.includes('email') && form.subject" class="flex justify-between">
            <span class="text-neutral-500">Subject</span>
            <span class="font-medium text-neutral-800 max-w-xs text-right truncate">{{ form.subject }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500">Message length</span>
            <span class="font-medium text-neutral-800">{{ form.message.length }} chars</span>
          </div>
        </div>

        <!-- Warnings -->
        <div v-if="form.channels.length === 0" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
          Select at least one delivery channel.
        </div>
        <div v-if="!form.message.trim()" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
          Enter a message before sending.
        </div>
        <div v-if="(counts[form.audience] ?? 0) === 0" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
          No customers match the selected audience.
        </div>

        <div v-if="!showConfirm">
          <button
            @click="showConfirm = true"
            :disabled="!canSend"
            class="btn-primary disabled:opacity-50"
          >
            Review &amp; Send
          </button>
        </div>

        <!-- Confirmation step -->
        <div v-else class="bg-red-50 border border-red-200 rounded-xl p-4 space-y-3">
          <p class="text-sm font-semibold text-red-800">Confirm Broadcast</p>
          <p class="text-sm text-red-700">
            You are about to send a message to
            <strong>{{ counts[form.audience] ?? 0 }} customer(s)</strong>
            via <strong>{{ form.channels.map(c => c.toUpperCase()).join(' + ') }}</strong>.
            This action cannot be undone.
          </p>
          <div class="flex gap-3 pt-1">
            <button
              @click="sendBroadcast"
              :disabled="sending"
              class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-5 py-2 rounded-lg disabled:opacity-50 flex items-center gap-2"
            >
              <svg v-if="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ sending ? 'Queuing…' : 'Yes, Send Now' }}
            </button>
            <button @click="showConfirm = false" class="text-sm text-neutral-600 hover:text-neutral-800 px-4">
              Cancel
            </button>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  counts: { type: Object, default: () => ({}) },
})

const form = reactive({
  audience: 'all',
  channels: ['sms'],
  subject:  '',
  message:  '',
})

const errors      = reactive({ audience: '', channels: '', subject: '', message: '' })
const sending     = ref(false)
const showConfirm = ref(false)

// ─── Config ───────────────────────────────────────────────────────────────────
const audienceOptions = [
  { value: 'all',         label: 'All Customers',     description: 'Every active borrower' },
  { value: 'active_loan', label: 'Active Loans',       description: 'Borrowers with active/disbursed loans' },
  { value: 'overdue',     label: 'Overdue Loans',      description: 'Borrowers with unpaid overdue instalments' },
]

const channelOptions = [
  { value: 'sms',   label: 'SMS',   hint: 'text message' },
  { value: 'email', label: 'Email', hint: 'requires email address' },
]

// ─── Computed ─────────────────────────────────────────────────────────────────
const selectedAudienceLabel = computed(() =>
  audienceOptions.find(o => o.value === form.audience)?.label ?? ''
)

const smsSegments = computed(() => {
  const len = form.message.length
  if (len === 0) return 0
  const isUnicode = /[^\u0000-\u007F]/.test(form.message)
  const segSize = isUnicode ? 70 : 160
  return Math.ceil(len / segSize)
})

const canSend = computed(() =>
  form.channels.length > 0 &&
  form.message.trim().length > 0 &&
  (counts[form.audience] ?? 0) > 0 &&
  (! form.channels.includes('email') || form.subject.trim().length > 0)
)

// ─── Counts from props (refreshed on page visit) ──────────────────────────────
const counts = computed(() => props.counts ?? {})

// ─── Send ─────────────────────────────────────────────────────────────────────
import axios from 'axios'
import { router } from '@inertiajs/vue3'

async function sendBroadcast() {
  Object.assign(errors, { audience: '', channels: '', subject: '', message: '' })
  sending.value = true

  try {
    await axios.post(route('broadcast.send'), {
      audience: form.audience,
      channels: form.channels,
      subject:  form.subject || null,
      message:  form.message,
    })

    // Reload to pick up flash success and fresh counts
    showConfirm.value = false
    form.message = ''
    form.subject = ''
    router.reload({ only: ['counts'] })
  } catch (e) {
    const errs = e.response?.data?.errors ?? {}
    if (errs.audience) errors.audience = errs.audience[0]
    if (errs.channels) errors.channels = errs.channels[0]
    if (errs.subject)  errors.subject  = errs.subject[0]
    if (errs.message)  errors.message  = errs.message[0]
    showConfirm.value = false
  } finally {
    sending.value = false
  }
}
</script>
