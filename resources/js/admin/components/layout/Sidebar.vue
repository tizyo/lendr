<template>
  <aside
    class="flex flex-col bg-neutral-900 text-white transition-all duration-300 shrink-0 h-screen z-50"
    :class="collapsed ? 'w-16' : 'w-64'"
  >
    <!-- Logo -->
    <div class="px-4 py-5 border-b border-neutral-800" :class="collapsed ? 'flex justify-center' : ''">
      <!-- One logo: the wordmark, cropped to its mark when the sidebar is icon-only -->
      <div class="overflow-hidden" :class="collapsed ? 'w-7' : ''">
        <img src="/brand/lendr-logo-white.png" alt="LENDR" class="h-8 w-auto max-w-none" />
      </div>
      <Transition name="slide-fade">
        <p v-if="tenant && !collapsed" class="text-neutral-400 text-xs leading-none mt-2 truncate">{{ tenant.name }}</p>
      </Transition>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-0.5 sidebar-scroll">
      <template v-for="section in navSections" :key="section.name">
        <!-- A section of one is a plain link -->
        <NavItem v-if="section.items.length === 1" :item="section.items[0]" :collapsed="collapsed" />

        <div v-else>
          <button
            type="button"
            @click="toggleSection(section)"
            :aria-expanded="isOpen(section)"
            :title="collapsed ? section.name : undefined"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition w-full"
            :class="containsActive(section) ? 'text-white' : 'text-neutral-400 hover:text-white hover:bg-neutral-800'"
          >
            <component :is="navIcon(section.icon)" class="w-5 h-5 shrink-0" />
            <template v-if="!collapsed">
              <span class="truncate flex-1 text-left">{{ section.name }}</span>
              <ChevronRightIcon class="w-4 h-4 shrink-0 text-neutral-500 transition-transform duration-200" :class="isOpen(section) ? 'rotate-90' : ''" />
            </template>
          </button>

          <div v-if="isOpen(section) && !collapsed" class="mt-0.5 space-y-0.5">
            <NavItem v-for="item in section.items" :key="item.route" :item="item" nested />
          </div>
        </div>
      </template>
    </nav>

    <!-- Collapse toggle -->
    <div class="border-t border-neutral-800 p-3">
      <button
        @click="$emit('toggle')"
        class="w-full flex items-center justify-center p-2 rounded-lg text-neutral-500 hover:text-white hover:bg-neutral-800 transition"
      >
        <svg class="w-4 h-4 transition-transform duration-300" :class="collapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
        </svg>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import NavItem from './NavItem.vue'
import { navSections, navIcon, activeNavRoute } from './navigation.js'

const props = defineProps({ collapsed: Boolean })
const emit = defineEmits(['toggle'])

const page = usePage()
const tenant = computed(() => page.props.tenant)

function containsActive(section) {
  const active = activeNavRoute()
  return section.items.some(item => item.route === active)
}

// One group open at a time. The current page's group opens on arrival; otherwise
// the group last opened stays open across page visits.
const STORAGE_KEY = 'lendr.nav.open'
const openSection = ref(readOpenSection())

function readOpenSection() {
  const current = navSections.find(containsActive)?.name
  if (current) {
    return current
  }

  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    return navSections.some(s => s.name === saved) ? saved : null
  } catch {
    return null // storage unavailable
  }
}

function isOpen(section) {
  return openSection.value === section.name
}

function toggleSection(section) {
  const open = isOpen(section)

  // In the icon-only sidebar a group can't show its items, so widen it and open it.
  if (props.collapsed) {
    emit('toggle')
    if (open) {
      return
    }
  }

  // Opening a group closes whichever was open before.
  openSection.value = open ? null : section.name

  try {
    if (openSection.value) {
      localStorage.setItem(STORAGE_KEY, openSection.value)
    } else {
      localStorage.removeItem(STORAGE_KEY)
    }
  } catch { /* storage unavailable: still works on this page */ }
}
</script>

<style scoped>
.slide-fade-enter-active { transition: all 0.15s ease; }
.slide-fade-leave-active { transition: all 0.1s ease; }
.slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; transform: translateX(-4px); }

/* Seamless scrollbar that vanishes into the dark sidebar */
.sidebar-scroll {
  scrollbar-width: thin;
  scrollbar-color: transparent transparent;
  transition: scrollbar-color 0.3s;
}
.sidebar-scroll:hover {
  scrollbar-color: #2d2d2d transparent;
}
.sidebar-scroll::-webkit-scrollbar {
  width: 2px;
}
.sidebar-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
  background-color: transparent;
  border-radius: 9999px;
  transition: background-color 0.3s;
}
.sidebar-scroll:hover::-webkit-scrollbar-thumb {
  background-color: #2d2d2d;
}
.sidebar-scroll::-webkit-scrollbar-thumb:hover {
  background-color: #404040 !important;
}
</style>
