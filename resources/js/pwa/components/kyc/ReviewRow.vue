<template>
  <div class="flex justify-between gap-3">
    <span class="text-xs text-gray-500 shrink-0">{{ label }}</span>
    <span class="text-xs text-gray-900 font-medium text-right">{{ value || '—' }}</span>
  </div>
</template>

<script setup>
defineProps(['label', 'value'])
</script>
