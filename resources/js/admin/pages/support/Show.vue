<template>
  <AppLayout :title="ticket.subject">
    <div class="max-w-3xl space-y-6">

      <!-- Back -->
      <Link :href="route('support.index')" class="inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-800">
        ← Back to Support
      </Link>

      <!-- Ticket header -->
      <div class="bg-white rounded-xl border border-neutral-200 p-6">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <h1 class="text-lg font-bold text-neutral-900">{{ ticket.subject }}</h1>
            <p class="text-sm text-neutral-500 mt-1">
              Submitted by {{ ticket.submitted_by ?? 'Unknown' }} · {{ ticket.created_at }}
            </p>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="priorityBadge(ticket.priority)">{{ ticket.priority }}</span>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(ticket.status)">{{ statusLabel(ticket.status) }}</span>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-neutral-100 text-neutral-600">{{ typeBadge(ticket.type) }}</span>
          </div>
        </div>

        <!-- Original message -->
        <div class="mt-4 pt-4 border-t border-neutral-100 text-sm text-neutral-700 whitespace-pre-wrap leading-relaxed">
          {{ ticket.message }}
        </div>
      </div>

      <!-- Thread -->
      <div v-if="ticket.replies?.length" class="space-y-3">
        <h2 class="text-xs font-semibold text-neutral-500 uppercase tracking-widest">Conversation</h2>
        <div
          v-for="reply in ticket.replies"
          :key="reply.id"
          class="bg-white rounded-xl border p-5"
          :class="reply.author_type === 'landlord' ? 'border-primary-200 bg-primary-50/30' : 'border-neutral-200'"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold" :class="reply.author_type === 'landlord' ? 'text-primary-700' : 'text-neutral-800'">
              {{ reply.author_type === 'landlord' ? '🛡 LENDR Support' : '👤 ' + (reply.author_name ?? 'You') }}
            </span>
            <span class="text-xs text-neutral-400">{{ reply.created_at }}</span>
          </div>
          <p class="text-sm text-neutral-700 whitespace-pre-wrap leading-relaxed">{{ reply.message }}</p>
        </div>
      </div>

      <!-- Reply box (only if not closed) -->
      <div v-if="ticket.status !== 'closed'" class="bg-white rounded-xl border border-neutral-200 p-6 space-y-3">
        <h2 class="text-sm font-semibold text-neutral-700">Send a Reply</h2>
        <textarea
          v-model="replyMessage"
          rows="4"
          class="input w-full"
          placeholder="Type your message…"
        ></textarea>
        <div class="flex justify-end">
          <button @click="sendReply" :disabled="!replyMessage.trim() || sending" class="btn-primary px-4 py-2 text-sm">
            {{ sending ? 'Sending…' : 'Send Reply' }}
          </button>
        </div>
      </div>

      <div v-else class="text-center py-4 text-sm text-neutral-400">
        This ticket is closed. <Link :href="route('support.index')" class="text-primary-600 hover:underline">Open a new ticket</Link> if you need further assistance.
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({ ticket: Object })

const replyMessage = ref('')
const sending      = ref(false)

function sendReply() {
  if (!replyMessage.value.trim()) return
  sending.value = true
  router.post(route('support.reply', props.ticket.id), { message: replyMessage.value }, {
    onSuccess: () => { replyMessage.value = '' },
    onFinish:  () => { sending.value = false },
  })
}

function statusLabel(s) {
  return { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', closed: 'Closed' }[s] ?? s
}

function typeBadge(t) {
  return { bug: 'Bug', feature: 'Feature Request', support: 'Support' }[t] ?? 'Support'
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
