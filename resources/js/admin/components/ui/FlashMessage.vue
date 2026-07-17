<template>
  <TransitionGroup name="flash" tag="div" class="space-y-2 mb-4">
    <div
      v-for="msg in messages"
      :key="msg.id"
      class="flex items-start gap-3 px-4 py-3 rounded-lg border text-sm font-medium"
      :class="typeClasses[msg.type]"
    >
      <component :is="iconMap[msg.type]" class="w-5 h-5 shrink-0 mt-0.5" />
      <p class="flex-1">{{ msg.text }}</p>
      <button @click="dismiss(msg.id)" class="opacity-60 hover:opacity-100 transition shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </TransitionGroup>
</template>

<script setup>
import { ref, watch, markRaw } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  CheckCircleIcon, ExclamationCircleIcon,
  ExclamationTriangleIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const messages = ref([])
let nextId = 0

const typeClasses = {
  success: 'bg-green-50 text-green-800 border-green-200',
  error:   'bg-red-50 text-red-800 border-red-200',
  warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
  info:    'bg-blue-50 text-blue-800 border-blue-200',
}

const iconMap = {
  success: markRaw(CheckCircleIcon),
  error:   markRaw(ExclamationCircleIcon),
  warning: markRaw(ExclamationTriangleIcon),
  info:    markRaw(InformationCircleIcon),
}

function addMessage(type, text) {
  if (!text) return
  const id = nextId++
  messages.value.push({ id, type, text })
  setTimeout(() => dismiss(id), 5000)
}

function dismiss(id) {
  messages.value = messages.value.filter(m => m.id !== id)
}

watch(() => page.props.flash, (flash) => {
  if (!flash) return
  if (flash.success) addMessage('success', flash.success)
  if (flash.error)   addMessage('error', flash.error)
  if (flash.warning) addMessage('warning', flash.warning)
  if (flash.info)    addMessage('info', flash.info)
}, { immediate: true, deep: true })
</script>

<style scoped>
.flash-enter-active { transition: all 0.25s ease-out; }
.flash-leave-active { transition: all 0.2s ease-in; }
.flash-enter-from { opacity: 0; transform: translateY(-8px); }
.flash-leave-to   { opacity: 0; transform: translateX(8px); }
</style>
