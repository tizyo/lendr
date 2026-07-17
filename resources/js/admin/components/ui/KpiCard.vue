<template>
  <div class="lendr-card p-5" :class="alert ? 'ring-2 ring-red-400' : ''">
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wide truncate">{{ label }}</p>
        <p class="text-2xl font-bold text-neutral-900 mt-1 truncate">{{ value }}</p>
        <p v-if="trend !== null" class="text-xs mt-1" :class="trend >= 0 ? 'text-green-600' : 'text-red-600'">
          {{ trend >= 0 ? '+' : '' }}{{ trend }}% vs last month
        </p>
      </div>
      <div
        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 ml-3"
        :class="colorMap[color]?.bg"
      >
        <component :is="iconComponent" class="w-5 h-5" :class="colorMap[color]?.icon" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, markRaw } from 'vue'
import {
  CreditCardIcon, UsersIcon, BanknotesIcon,
  ExclamationTriangleIcon, ChartBarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  label: String,
  value: [String, Number],
  icon: { type: String, default: 'chart-bar' },
  color: { type: String, default: 'blue' },
  trend: { type: Number, default: null },
  alert: { type: Boolean, default: false },
})

const iconMap = {
  'credit-card': markRaw(CreditCardIcon),
  'users': markRaw(UsersIcon),
  'banknotes': markRaw(BanknotesIcon),
  'exclamation-triangle': markRaw(ExclamationTriangleIcon),
  'chart-bar': markRaw(ChartBarIcon),
}

const iconComponent = computed(() => iconMap[props.icon] || ChartBarIcon)

const colorMap = {
  blue:   { bg: 'bg-blue-50',   icon: 'text-blue-600' },
  green:  { bg: 'bg-green-50',  icon: 'text-green-600' },
  purple: { bg: 'bg-purple-50', icon: 'text-purple-600' },
  red:    { bg: 'bg-red-50',    icon: 'text-red-600' },
  yellow: { bg: 'bg-yellow-50', icon: 'text-yellow-600' },
}
</script>
