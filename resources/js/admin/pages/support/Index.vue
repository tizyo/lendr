<template>
  <AppLayout title="Support">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Support</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Submit tickets, report bugs, or request features.</p>
        </div>
        <button @click="showForm = true" class="btn-primary px-4 py-2 text-sm">+ New Ticket</button>
      </div>

      <!-- New Ticket Form -->
      <div v-if="showForm" class="bg-white rounded-xl border border-neutral-200 p-6 space-y-4">
        <h2 class="font-semibold text-neutral-800">New Ticket</h2>

        <div>
          <label class="label">Subject</label>
          <input v-model="form.subject" type="text" class="input w-full" placeholder="Brief description of your issue" />
          <p v-if="errors.subject" class="text-xs text-red-500 mt-1">{{ errors.subject }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Type</label>
            <select v-model="form.type" class="input w-full">
              <option value="support">Support</option>
              <option value="bug">Bug Report</option>
              <option value="feature">Feature Request</option>
            </select>
          </div>
          <div>
            <label class="label">Priority</label>
            <select v-model="form.priority" class="input w-full">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>
        </div>

        <div>
          <label class="label">Message</label>
          <textarea v-model="form.message" rows="5" class="input w-full" placeholder="Describe your issue in detail…"></textarea>
          <p v-if="errors.message" class="text-xs text-red-500 mt-1">{{ errors.message }}</p>
        </div>

        <div class="flex gap-3 justify-end">
          <button @click="showForm = false; resetForm()" class="btn-secondary px-4 py-2 text-sm">Cancel</button>
          <button @click="submit" :disabled="submitting" class="btn-primary px-4 py-2 text-sm">
            {{ submitting ? 'Submitting…' : 'Submit Ticket' }}
          </button>
        </div>
      </div>

      <!-- Ticket List -->
      <div class="bg-white rounded-xl border border-neutral-200 divide-y divide-neutral-100">
        <div v-if="!tickets.length" class="py-12 text-center text-neutral-400 text-sm">
          No tickets yet. Submit your first ticket above.
        </div>
        <Link
          v-for="ticket in tickets"
          :key="ticket.id"
          :href="route('support.show', ticket.id)"
          class="flex items-center gap-4 px-6 py-4 hover:bg-neutral-50 transition-colors"
        >
          <!-- Type icon -->
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" :class="typeIcon(ticket.type).bg">
            <span class="text-base">{{ typeIcon(ticket.type).emoji }}</span>
          </div>

          <div class="flex-1 min-w-0">
            <p class="font-medium text-neutral-900 truncate">{{ ticket.subject }}</p>
            <p class="text-xs text-neutral-500 mt-0.5">
              {{ ticket.type === 'bug' ? 'Bug' : ticket.type === 'feature' ? 'Feature Request' : 'Support' }}
              · {{ ticket.replies_count }} {{ ticket.replies_count === 1 ? 'reply' : 'replies' }}
              · {{ ticket.created_at }}
            </p>
          </div>

          <div class="flex items-center gap-2 flex-shrink-0">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="priorityBadge(ticket.priority)">
              {{ ticket.priority }}
            </span>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(ticket.status)">
              {{ statusLabel(ticket.status) }}
            </span>
          </div>
        </Link>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({ tickets: Array })

const showForm  = ref(false)
const submitting = ref(false)
const errors    = ref({})

const form = ref({ subject: '', message: '', type: 'support', priority: 'medium' })

function resetForm() {
  form.value = { subject: '', message: '', type: 'support', priority: 'medium' }
  errors.value = {}
}

async function submit() {
  errors.value  = {}
  submitting.value = true
  const inertiaForm = useForm(form.value)
  inertiaForm.post(route('support.store'), {
    onError:  (e) => { errors.value = e },
    onFinish: () => { submitting.value = false },
  })
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function typeIcon(type) {
  return {
    bug:     { emoji: '🐛', bg: 'bg-red-50' },
    feature: { emoji: '💡', bg: 'bg-violet-50' },
    support: { emoji: '🎧', bg: 'bg-blue-50' },
  }[type] ?? { emoji: '🎧', bg: 'bg-blue-50' }
}

function statusLabel(s) {
  return { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', closed: 'Closed' }[s] ?? s
}

function statusBadge(s) {
  return {
    open:        'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    resolved:    'bg-emerald-100 text-emerald-700',
    closed:      'bg-neutral-100 text-neutral-500',
  }[s] ?? 'bg-neutral-100 text-neutral-500'
}

function priorityBadge(p) {
  return {
    low:      'bg-neutral-100 text-neutral-500',
    medium:   'bg-blue-100 text-blue-600',
    high:     'bg-orange-100 text-orange-700',
    critical: 'bg-red-100 text-red-700',
  }[p] ?? 'bg-neutral-100 text-neutral-500'
}
</script>
