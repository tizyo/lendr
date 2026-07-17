<template>
  <div class="max-w-5xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-6">Notification Templates</h1>

    <div v-if="loading" class="text-gray-500 text-sm">Loading templates…</div>

    <div v-else>
      <!-- Placeholders reference -->
      <details class="mb-6 border rounded p-4 bg-gray-50">
        <summary class="cursor-pointer font-medium text-sm text-gray-700">Available Placeholders</summary>
        <div class="mt-3 grid grid-cols-2 gap-2">
          <div v-for="(desc, key) in placeholders" :key="key" class="text-sm">
            <code class="bg-white border rounded px-1 py-0.5 text-indigo-700 text-xs">{{ key }}</code>
            <span class="text-gray-600 ml-2">{{ desc }}</span>
          </div>
        </div>
      </details>

      <!-- Templates table -->
      <div class="space-y-4">
        <div v-for="row in templates" :key="row.event" class="border rounded-lg overflow-hidden">
          <div class="bg-gray-100 px-4 py-2 flex items-center justify-between">
            <span class="font-semibold text-sm">{{ row.label }}</span>
            <span class="text-xs text-gray-500 font-mono">{{ row.event }}</span>
          </div>
          <div class="grid grid-cols-2 divide-x">
            <!-- SMS -->
            <div class="p-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">SMS</span>
                <div class="flex gap-2">
                  <button
                    v-if="row.sms"
                    @click="openEdit(row.event, 'sms', row.sms)"
                    class="text-xs text-indigo-600 hover:underline"
                  >Edit</button>
                  <button
                    v-if="row.sms"
                    @click="deleteTemplate(row.event, 'sms')"
                    class="text-xs text-red-500 hover:underline"
                  >Delete</button>
                  <button
                    v-if="!row.sms"
                    @click="openEdit(row.event, 'sms', null)"
                    class="text-xs text-green-600 hover:underline"
                  >+ Add</button>
                </div>
              </div>
              <div v-if="row.sms" class="text-sm text-gray-700 whitespace-pre-wrap">{{ row.sms.body }}</div>
              <div v-else class="text-sm text-gray-400 italic">No custom template — using system default</div>
            </div>
            <!-- Email -->
            <div class="p-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Email</span>
                <div class="flex gap-2">
                  <button
                    v-if="row.email"
                    @click="openEdit(row.event, 'email', row.email)"
                    class="text-xs text-indigo-600 hover:underline"
                  >Edit</button>
                  <button
                    v-if="row.email"
                    @click="deleteTemplate(row.event, 'email')"
                    class="text-xs text-red-500 hover:underline"
                  >Delete</button>
                  <button
                    v-if="!row.email"
                    @click="openEdit(row.event, 'email', null)"
                    class="text-xs text-green-600 hover:underline"
                  >+ Add</button>
                </div>
              </div>
              <div v-if="row.email">
                <div class="text-xs text-gray-500 mb-1">Subject: <span class="text-gray-700">{{ row.email.subject }}</span></div>
                <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ row.email.body }}</div>
              </div>
              <div v-else class="text-sm text-gray-400 italic">No custom template — using system default</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="modal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 space-y-4">
        <h2 class="text-lg font-semibold">
          {{ modal.existing ? 'Edit' : 'Add' }} Template —
          <span class="font-mono text-sm text-indigo-700">{{ modal.event }}/{{ modal.channel }}</span>
        </h2>

        <div>
          <label class="block text-sm font-medium mb-1">Name (optional)</label>
          <input v-model="modal.form.name" type="text" class="w-full border rounded px-3 py-2 text-sm" placeholder="e.g. Loan Approved SMS" />
        </div>

        <div v-if="modal.channel === 'email'">
          <label class="block text-sm font-medium mb-1">Subject</label>
          <input v-model="modal.form.subject" type="text" class="w-full border rounded px-3 py-2 text-sm" placeholder="e.g. Your loan has been approved" />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Body <span class="text-red-500">*</span></label>
          <textarea
            v-model="modal.form.body"
            rows="6"
            class="w-full border rounded px-3 py-2 text-sm font-mono"
            placeholder="Use {{borrower_name}}, {{loan_number}}, etc."
          ></textarea>
        </div>

        <div class="flex items-center gap-2">
          <input v-model="modal.form.is_active" type="checkbox" id="is_active" class="rounded" />
          <label for="is_active" class="text-sm">Active</label>
        </div>

        <!-- Preview -->
        <div v-if="preview" class="border rounded p-3 bg-gray-50 text-sm">
          <div v-if="preview.subject" class="font-medium mb-1">Subject: {{ preview.subject }}</div>
          <div class="whitespace-pre-wrap text-gray-700">{{ preview.body }}</div>
        </div>

        <div v-if="modal.error" class="text-sm text-red-600">{{ modal.error }}</div>

        <div class="flex justify-between gap-3 pt-2">
          <button @click="fetchPreview" class="text-sm text-indigo-600 hover:underline">Preview with sample data</button>
          <div class="flex gap-3">
            <button @click="closeModal" class="px-4 py-2 text-sm border rounded hover:bg-gray-50">Cancel</button>
            <button @click="saveTemplate" :disabled="modal.saving" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
              {{ modal.saving ? 'Saving…' : 'Save Template' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading   = ref(true)
const templates = ref([])
const placeholders = ref({})
const preview   = ref(null)

const modal = ref({
  open: false,
  event: '',
  channel: '',
  existing: false,
  saving: false,
  error: '',
  form: { name: '', subject: '', body: '', is_active: true },
})

async function load() {
  loading.value = true
  const { data } = await axios.get('/api/v1/notification-templates')
  templates.value  = data.data.templates
  placeholders.value = data.data.placeholders
  loading.value = false
}

function openEdit(event, channel, existing) {
  preview.value = null
  modal.value = {
    open: true,
    event,
    channel,
    existing: !!existing,
    saving: false,
    error: '',
    form: {
      name:      existing?.name      ?? '',
      subject:   existing?.subject   ?? '',
      body:      existing?.body      ?? '',
      is_active: existing?.is_active ?? true,
    },
  }
}

function closeModal() {
  modal.value.open = false
  preview.value = null
}

async function fetchPreview() {
  if (!modal.value.form.body) return
  try {
    const { data } = await axios.post(
      `/api/v1/notification-templates/${modal.value.event}/${modal.value.channel}/preview`,
      { body: modal.value.form.body, subject: modal.value.form.subject || undefined }
    )
    preview.value = data.data
  } catch {
    // ignore
  }
}

async function saveTemplate() {
  modal.value.saving = true
  modal.value.error  = ''
  try {
    await axios.put(
      `/api/v1/notification-templates/${modal.value.event}/${modal.value.channel}`,
      modal.value.form
    )
    closeModal()
    await load()
  } catch (e) {
    modal.value.error = e.response?.data?.message ?? 'Failed to save.'
    modal.value.saving = false
  }
}

async function deleteTemplate(event, channel) {
  if (!confirm(`Delete ${channel} template for "${event}"?`)) return
  await axios.delete(`/api/v1/notification-templates/${event}/${channel}`)
  await load()
}

onMounted(load)
</script>
