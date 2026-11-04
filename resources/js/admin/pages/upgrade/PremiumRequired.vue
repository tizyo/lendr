<template>
  <AppLayout>
    <template #header>
      <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-primary-600">Upgrade required</p>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white mt-1">{{ feature }}</h1>
        <p class="text-sm text-neutral-500 mt-1">
          {{ feature }} isn't included in your <strong class="font-semibold text-neutral-700 dark:text-neutral-300">{{ current?.label ?? 'current' }}</strong> plan.
          <template v-if="recommended">Upgrade to <strong class="font-semibold text-neutral-700 dark:text-neutral-300">{{ recommended.label }}</strong> to switch it on for {{ tenantName }}.</template>
        </p>
      </div>
    </template>

    <div class="max-w-4xl">
      <!-- Plan comparison -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Current plan -->
        <section v-if="current" class="lendr-card p-6 flex flex-col">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ current.label }}</h2>
            <span class="lendr-badge-neutral">Your plan</span>
          </div>
          <p class="mt-3 text-3xl font-bold text-neutral-900 dark:text-white">
            {{ price(current) }}<span v-if="perMonth(current)" class="text-sm font-medium text-neutral-500"> / month</span>
          </p>
          <p class="mt-1 text-sm text-neutral-500 min-h-[2.5rem]">{{ current.description }}</p>
          <PlanDetails :plan="current" :highlight="featureKey" class="mt-5" />
        </section>

        <!-- Recommended plan -->
        <section v-if="recommended" class="relative rounded-xl p-6 flex flex-col bg-white dark:bg-neutral-900 ring-2 ring-primary-600 shadow-lg shadow-primary-600/10">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-400">{{ recommended.label }}</h2>
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2.5 py-0.5 text-xs font-semibold text-white">
              <SparklesIcon class="w-3.5 h-3.5" /> Recommended
            </span>
          </div>
          <p class="mt-3 text-3xl font-bold text-neutral-900 dark:text-white">
            {{ price(recommended) }}<span v-if="perMonth(recommended)" class="text-sm font-medium text-neutral-500"> / month</span>
          </p>
          <p class="mt-1 text-sm text-neutral-500 min-h-[2.5rem]">{{ recommended.description }}</p>
          <PlanDetails :plan="recommended" :highlight="featureKey" class="mt-5" />

          <div class="mt-6 pt-5 border-t border-neutral-100 dark:border-neutral-800">
            <p v-if="!can('billing.manage')" class="text-sm text-neutral-500 text-center">
              Ask your workspace administrator to upgrade the plan.
            </p>
            <Link
              v-else-if="!recommended.is_custom_price"
              :href="route('billing.index', { plan: recommended.key })"
              class="btn-primary w-full justify-center py-2.5"
            >
              <ArrowTrendingUpIcon class="w-4 h-4" />
              Upgrade to {{ recommended.label }}
            </Link>
            <Link v-else :href="route('support.index')" class="btn-primary w-full justify-center py-2.5">
              <ChatBubbleLeftRightIcon class="w-4 h-4" />
              Talk to us about {{ recommended.label }}
            </Link>
          </div>
        </section>

        <!-- No plan includes it: point to support -->
        <section v-if="!recommended" class="lendr-card p-6 flex flex-col justify-center text-center">
          <ChatBubbleLeftRightIcon class="w-10 h-10 mx-auto text-primary-600" />
          <h2 class="mt-3 text-base font-semibold text-neutral-900 dark:text-white">Need {{ feature }}?</h2>
          <p class="mt-1 text-sm text-neutral-500">It isn't part of a standard plan yet. Tell us what you need and we'll help.</p>
          <Link :href="route('support.index')" class="btn-primary mx-auto mt-5">Contact support</Link>
        </section>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <Link v-if="can('billing.manage')" :href="route('billing.index')" class="font-medium text-primary-600 hover:text-primary-700">Compare all plans →</Link>
        <button type="button" class="text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300" @click="goBack">← Go back</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { h } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import { useCan } from '@/admin/composables/useCan.js'
import { CheckIcon, XMarkIcon } from '@heroicons/vue/20/solid'
import { SparklesIcon, ArrowTrendingUpIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  feature: { type: String, default: 'This feature' },
  featureKey: { type: String, default: '' },
  tenantName: { type: String, default: '' },
  current: { type: Object, default: null },
  recommended: { type: Object, default: null },
})

const can = useCan()

const kwacha = new Intl.NumberFormat('en-ZM', { maximumFractionDigits: 0 })
const price = (plan) => plan.is_custom_price ? 'Custom' : plan.price_zmw > 0 ? `K ${kwacha.format(plan.price_zmw)}` : 'Free'
const perMonth = (plan) => !plan.is_custom_price && plan.price_zmw > 0

const goBack = () => (window.history.length > 1 ? window.history.back() : window.location.assign(route('dashboard')))

// Limits, then features with a tick or cross; the feature being asked for stands out.
const PlanDetails = (p) => h('div', { class: 'space-y-5' }, [
  h('dl', { class: 'grid grid-cols-2 gap-3' }, p.plan.limits.map((l) =>
    h('div', { class: 'rounded-lg bg-neutral-50 dark:bg-neutral-800 px-3 py-2' }, [
      h('dt', { class: 'text-xs text-neutral-500' }, l.label),
      h('dd', { class: 'text-sm font-semibold text-neutral-900 dark:text-white' }, l.value),
    ]),
  )),
  h('ul', { class: 'space-y-2' }, p.plan.features.map((f) =>
    h('li', {
      class: ['flex items-center gap-2 text-sm', f.included ? 'text-neutral-700 dark:text-neutral-300' : 'text-neutral-400 dark:text-neutral-500',
        f.key === p.highlight ? 'font-semibold' : ''],
    }, [
      h(f.included ? CheckIcon : XMarkIcon, { class: ['w-4 h-4 shrink-0', f.included ? 'text-emerald-500' : 'text-neutral-300 dark:text-neutral-600'] }),
      f.label,
    ]),
  )),
])
PlanDetails.props = ['plan', 'highlight']
</script>
