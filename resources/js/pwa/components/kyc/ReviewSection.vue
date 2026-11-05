<template>
  <div class="bg-gray-50 rounded-xl overflow-hidden">
    <div class="px-4 py-2.5 bg-gray-100 border-b border-gray-200">
      <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ title }}</h4>
    </div>
    <div class="px-4 py-3 space-y-2">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps(['title'])
</script>
