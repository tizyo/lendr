<template>
  <div class="bg-white rounded-2xl p-5 shadow-[0_2px_20px_rgba(0,0,0,0.06)] flex flex-col gap-3 min-w-0">
    <div class="flex items-center justify-between gap-3">
      <p class="text-xs font-medium text-neutral-500 truncate">{{ label }}</p>
      <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" :style="{ background: tint, color: hex }">
        <component :is="icon" v-if="icon" class="w-5 h-5" stroke-width="1.5" />
      </span>
    </div>
    <p class="text-2xl font-semibold text-neutral-900 tabular-nums truncate" :title="String(value)">{{ value }}</p>
    <div class="flex items-center gap-2 text-xs min-h-4">
      <span
        v-if="delta !== null && delta !== undefined"
        class="inline-flex items-center gap-0.5 font-semibold px-1.5 py-0.5 rounded-md"
        :class="delta > 0 ? 'bg-emerald-50 text-emerald-700' : delta < 0 ? 'bg-red-50 text-red-700' : 'bg-neutral-100 text-neutral-600'"
      >
        {{ delta > 0 ? '▲' : delta < 0 ? '▼' : '•' }} {{ Math.abs(delta) }}{{ deltaSuffix }}
      </span>
      <span class="text-neutral-500 truncate">{{ sub }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label:       { type: String, required: true },
  value:       { type: [String, Number], required: true },
  sub:         { type: String, default: '' },
  icon:        { type: [Object, Function], default: null },
  /** Hex of the tile's accent (icon chip only — numbers stay in text ink). */
  hex:         { type: String, default: '#2a78d6' },
  delta:       { type: Number, default: null },
  deltaSuffix: { type: String, default: '' },
})

// 12% of the accent behind the icon.
const tint = computed(() => props.hex + '1f')
</script>
