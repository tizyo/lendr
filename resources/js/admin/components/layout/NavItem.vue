<template>
  <Link
    :href="routeExists ? route(item.route) : '#'"
    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition group w-full"
    :class="isActive
      ? 'bg-primary-600 text-white'
      : 'text-neutral-400 hover:text-white hover:bg-neutral-800'"
  >
    <component :is="iconComponent" class="w-5 h-5 shrink-0" />
    <Transition name="slide-fade">
      <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
    </Transition>
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  HomeIcon, UsersIcon, ShieldCheckIcon, CreditCardIcon,
  BanknotesIcon, BuildingLibraryIcon, ReceiptPercentIcon,
  ChartBarIcon, TagIcon, UserGroupIcon, Cog6ToothIcon,
  ClipboardDocumentListIcon, BuildingOffice2Icon, CurrencyDollarIcon,
  ShoppingBagIcon, RectangleStackIcon, LifebuoyIcon,
  PhoneIcon, MegaphoneIcon, ArrowUpTrayIcon, QueueListIcon,
  StarIcon, FireIcon, ArchiveBoxIcon, FunnelIcon, IdentificationIcon,
  ExclamationTriangleIcon, ClipboardDocumentCheckIcon, XCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  item: Object,
  collapsed: Boolean,
})

const page = usePage()

const iconMap = {
  'home': HomeIcon,
  'users': UsersIcon,
  'shield-check': ShieldCheckIcon,
  'credit-card': CreditCardIcon,
  'banknotes': BanknotesIcon,
  'building-library': BuildingLibraryIcon,
  'receipt-percent': ReceiptPercentIcon,
  'chart-bar': ChartBarIcon,
  'tag': TagIcon,
  'user-group': UserGroupIcon,
  'clipboard-document-list': ClipboardDocumentListIcon,
  'building-office-2': BuildingOffice2Icon,
  'currency-dollar':   CurrencyDollarIcon,
  'shopping-bag':      ShoppingBagIcon,
  'rectangle-stack':   RectangleStackIcon,
  'cog-6-tooth':  Cog6ToothIcon,
  'lifebuoy':     LifebuoyIcon,
  'phone':        PhoneIcon,
  'megaphone':    MegaphoneIcon,
  'arrow-up-tray': ArrowUpTrayIcon,
  'queue-list':   QueueListIcon,
  'star':         StarIcon,
  'fire':         FireIcon,
  'archive-box':  ArchiveBoxIcon,
  'funnel':       FunnelIcon,
  'identification': IdentificationIcon,
  'exclamation-triangle': ExclamationTriangleIcon,
  'clipboard-document-check': ClipboardDocumentCheckIcon,
  'x-circle':     XCircleIcon,
}

const iconComponent = computed(() => iconMap[props.item.icon] || HomeIcon)

const routeExists = computed(() => {
  try { route(props.item.route); return true } catch { return false }
})

const isActive = computed(() => {
  try {
    return route().current(props.item.route) || route().current(props.item.route + '.*')
  } catch {
    return false
  }
})
</script>

<style scoped>
.slide-fade-enter-active { transition: all 0.15s ease; }
.slide-fade-leave-active { transition: all 0.1s ease; }
.slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; transform: translateX(-4px); }
</style>
