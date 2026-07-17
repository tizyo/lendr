<template>
  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="colorClass">
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: String,
  label: String,
})

const colorClass = computed(() => ({
  'bg-neutral-100 text-neutral-600':   props.status === 'draft',
  'bg-blue-100 text-blue-700':         props.status === 'submitted',
  'bg-amber-100 text-amber-700':       props.status === 'approved' || props.status === 'frozen',
  'bg-indigo-100 text-indigo-700':     props.status === 'disbursed',
  'bg-green-100 text-green-700':       props.status === 'active' || props.status === 'completed',
  'bg-red-100 text-red-700':           props.status === 'denied' || props.status === 'defaulted' || props.status === 'written_off',
  'bg-purple-100 text-purple-700':     props.status === 're_loaned',
}))
</script>
