<template>
  <Link
    :href="routeExists ? route(item.route) : '#'"
    class="flex items-center gap-3 rounded-lg transition group w-full"
    :class="[
      nested ? 'pl-11 pr-3 py-1.5 text-[13px]' : 'px-3 py-2 text-sm',
      isActive ? 'bg-primary-600 text-white' : 'text-neutral-400 hover:text-white hover:bg-neutral-800',
    ]"
  >
    <component v-if="!nested" :is="navIcon(item.icon)" class="w-5 h-5 shrink-0" />
    <Transition name="slide-fade">
      <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
    </Transition>
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { navIcon, activeNavRoute } from './navigation.js'

const props = defineProps({
  item: Object,
  collapsed: Boolean,
  // Inside an expanded group: indented, no icon of its own.
  nested: Boolean,
})

const routeExists = computed(() => {
  try { route(props.item.route); return true } catch { return false }
})

const isActive = computed(() => activeNavRoute() === props.item.route)
</script>

<style scoped>
.slide-fade-enter-active { transition: all 0.15s ease; }
.slide-fade-leave-active { transition: all 0.1s ease; }
.slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; transform: translateX(-4px); }
</style>
