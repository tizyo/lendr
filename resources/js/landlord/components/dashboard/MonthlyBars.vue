<template>
  <div class="relative">
    <!-- Plot: one thin bar per month, rounded data-end, anchored to the baseline -->
    <div class="relative h-44" @mouseleave="hover = null">
      <!-- Recessive top gridline labelled with the scale's maximum -->
      <div class="absolute inset-x-0 top-0 border-t border-dashed border-neutral-200"></div>
      <span class="absolute -top-2 right-0 bg-white pl-1 text-[11px] text-neutral-400 tabular-nums">{{ format(max) }}</span>

      <div class="absolute inset-0 flex items-end gap-[2px] pt-3">
        <button
          v-for="(p, i) in points"
          :key="p.key"
          type="button"
          class="relative flex-1 h-full flex items-end justify-center focus:outline-none group"
          :aria-label="`${p.long}: ${format(p.value)}`"
          @mouseenter="hover = i"
          @focus="hover = i"
          @blur="hover = null"
        >
          <span
            class="w-full max-w-7 rounded-t-[4px] transition-opacity duration-150"
            :style="{ height: height(p.value), background: color, opacity: hover === null || hover === i ? 1 : 0.45 }"
          ></span>
        </button>
      </div>

      <!-- Hover tooltip -->
      <div
        v-if="hover !== null"
        class="absolute z-10 -translate-x-1/2 pointer-events-none bg-neutral-900 text-white text-xs rounded-lg px-2.5 py-1.5 shadow-lg whitespace-nowrap"
        :style="{ left: ((hover + 0.5) / points.length * 100) + '%', top: '-6px' }"
      >
        <p class="text-neutral-300">{{ points[hover].long }}</p>
        <p class="font-semibold tabular-nums">{{ format(points[hover].value) }}</p>
      </div>
    </div>

    <!-- Baseline + month labels -->
    <div class="border-t border-neutral-300"></div>
    <div class="flex gap-[2px] mt-1.5">
      <span v-for="(p, i) in points" :key="p.key" class="flex-1 text-center text-[11px] truncate" :class="hover === i ? 'text-neutral-900 font-medium' : 'text-neutral-400'">
        {{ p.short }}
      </span>
    </div>

    <!-- Accessible table view of the same numbers -->
    <details class="mt-3 text-xs">
      <summary class="cursor-pointer text-neutral-500 hover:text-neutral-700 select-none">View as table</summary>
      <table class="mt-2 w-full">
        <tbody class="divide-y divide-neutral-100">
          <tr v-for="p in points" :key="p.key">
            <td class="py-1 text-neutral-600">{{ p.long }}</td>
            <td class="py-1 text-right font-medium text-neutral-900 tabular-nums">{{ format(p.value) }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  /** [{ month: 'YYYY-MM', value: number }], oldest first */
  data:   { type: Array, required: true },
  color:  { type: String, default: '#2a78d6' },
  format: { type: Function, default: (v) => Number(v).toLocaleString() },
})

const hover = ref(null)

const points = computed(() => props.data.map(d => {
  const [y, m] = d.month.split('-')
  const date = new Date(+y, +m - 1, 1)
  return {
    key: d.month,
    value: Number(d.value) || 0,
    short: date.toLocaleString('en-GB', { month: 'short' }),
    long: date.toLocaleString('en-GB', { month: 'long', year: 'numeric' }),
  }
}))

const max = computed(() => Math.max(1, ...points.value.map(p => p.value)))

// A zero month keeps a 2px stub so the month still reads as "nothing", not "missing".
const height = (v) => (v > 0 ? Math.max(4, (v / max.value) * 100) + '%' : '2px')
</script>
