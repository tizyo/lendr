<template>
  <div class="flex justify-between gap-3 text-sm">
    <dt class="text-neutral-500 shrink-0">{{ label }}</dt>
    <dd class="text-neutral-800 font-medium text-right truncate">{{ value || '—' }}</dd>
  </div>
</template>

<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
