<template>
  <div>
    <!-- Stacked share bar: 2px surface gap between segments -->
    <div class="flex h-3 w-full gap-[2px] rounded-full overflow-hidden bg-neutral-100" role="img" :aria-label="ariaLabel">
      <div
        v-for="s in visible"
        :key="s.key"
        class="h-full first:rounded-l-full last:rounded-r-full transition-[width] duration-200"
        :style="{ width: pct(s.value) + '%', background: s.color }"
        :title="`${s.label}: ${s.value} (${pct(s.value)}%)`"
      ></div>
    </div>

    <!-- Legend: identity is always a label + number, never colour alone -->
    <ul class="mt-4 grid gap-2" :class="segments.length > 3 ? 'grid-cols-2' : 'grid-cols-1'">
      <li v-for="s in segments" :key="s.key" class="flex items-center gap-2 text-sm min-w-0">
        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: s.color }"></span>
        <span class="text-neutral-600 truncate">{{ s.label }}</span>
        <span class="ml-auto font-semibold text-neutral-900 tabular-nums">{{ s.value }}</span>
        <span class="w-10 text-right text-xs text-neutral-400 tabular-nums">{{ pct(s.value) }}%</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  /** [{ key, label, value, color }] in a fixed order (colour follows the entity) */
  segments: { type: Array, required: true },
})

const total = computed(() => props.segments.reduce((sum, s) => sum + (Number(s.value) || 0), 0))
const visible = computed(() => props.segments.filter(s => s.value > 0))
const pct = (v) => (total.value ? Math.round((v / total.value) * 100) : 0)
const ariaLabel = computed(() => props.segments.map(s => `${s.label} ${s.value}`).join(', '))
</script>
