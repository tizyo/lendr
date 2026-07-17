<template>
  <div class="h-48 flex items-end gap-1.5">
    <div
      v-for="(bar, i) in data"
      :key="i"
      class="flex-1 flex flex-col items-center gap-1 group"
    >
      <div class="relative w-full">
        <div
          class="w-full bg-primary-500 hover:bg-primary-600 rounded-t transition cursor-default"
          :style="{ height: barHeight(bar.total) + 'px' }"
        >
          <!-- Tooltip -->
          <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 opacity-0 group-hover:opacity-100 transition pointer-events-none z-10">
            <div class="bg-neutral-900 text-white text-xs rounded-lg px-2.5 py-1.5 whitespace-nowrap shadow-lg">
              <p class="font-semibold">K {{ bar.total.toLocaleString() }}</p>
              <p class="text-neutral-400">{{ bar.count }} loans</p>
            </div>
          </div>
        </div>
      </div>
      <span class="text-[10px] text-neutral-400 rotate-45 origin-left hidden sm:block">{{ bar.label.split(' ')[0] }}</span>
    </div>
    <div v-if="!data?.length" class="w-full flex items-center justify-center text-sm text-neutral-400">
      No disbursement data
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ data: Array })

const maxValue = computed(() => Math.max(...(props.data?.map(d => d.total) || [1]), 1))

function barHeight(value) {
  const max = 160 // px max height
  return Math.max(4, (value / maxValue.value) * max)
}
</script>
