<template>
  <div>
    <div class="flex justify-between text-xs mb-1">
      <span class="font-medium text-neutral-700">{{ label }}</span>
      <span class="text-neutral-500">{{ count }} ({{ pct }}%)</span>
    </div>
    <div class="w-full bg-neutral-100 rounded-full h-2">
      <div class="h-2 rounded-full transition-all" :class="color" :style="{ width: pct + '%' }" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: String,
  count: Number,
  total: Number,
  color: { type: String, default: 'bg-blue-500' },
})

const pct = computed(() =>
  props.total > 0 ? Math.round((props.count / props.total) * 100) : 0
)
</script>
