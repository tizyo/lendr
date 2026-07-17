<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Subscription & Billing</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Manage your LENDR subscription and payment history.</p>
        </div>
      </div>
    </template>

    <!-- Current plan card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
      <!-- Plan & status -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-neutral-200 p-6">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-xs font-semibold text-neutral-500 uppercase tracking-widest mb-1">Current Plan</p>
            <h2 class="text-2xl font-bold text-neutral-900 capitalize">{{ tenant?.plan ?? '—' }}</h2>
          </div>
          <span :class="statusBadge(tenant?.status)" class="text-xs font-semibold px-3 py-1 rounded-full capitalize">
            {{ tenant?.status ?? '—' }}
          </span>
        </div>

        <div v-if="subscription" class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-neutral-100">
          <div>
            <p class="text-xs text-neutral-400">Billing cycle</p>
            <p class="text-sm font-medium capitalize">{{ subscription.billing_cycle }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-400">Amount</p>
            <p class="text-sm font-medium">{{ subscription.currency }} {{ Number(subscription.amount).toLocaleString() }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-400">Started</p>
            <p class="text-sm font-medium">{{ subscription.starts_at ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-400">Renews</p>
            <p class="text-sm font-medium">{{ subscription.ends_at ?? '—' }}</p>
          </div>
        </div>

        <div v-else class="pt-4 border-t border-neutral-100">
          <p class="text-sm text-neutral-500">No active subscription found.</p>
        </div>
      </div>

      <!-- Upgrade CTA -->
      <div class="bg-gradient-to-br from-primary-600 to-primary-700 rounded-xl p-6 text-white flex flex-col justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest opacity-75 mb-2">Upgrade your plan</p>
          <p class="text-sm opacity-90">Unlock more features and higher limits for your team.</p>
        </div>
        <button
          @click="showUpgrade = true"
          class="mt-4 w-full bg-white text-primary-700 font-semibold text-sm py-2.5 rounded-lg hover:bg-primary-50 transition"
        >
          View Plans
        </button>
      </div>
    </div>

    <!-- Invoice history -->
    <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-neutral-100">
        <h3 class="text-sm font-semibold text-neutral-800">Payment History</h3>
      </div>

      <div v-if="invoices.length === 0" class="px-6 py-10 text-center text-sm text-neutral-400">
        No payments yet.
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-xs text-neutral-500 uppercase tracking-wider">
          <tr>
            <th class="px-6 py-3 text-left">Date</th>
            <th class="px-6 py-3 text-left">Plan</th>
            <th class="px-6 py-3 text-left">Cycle</th>
            <th class="px-6 py-3 text-right">Amount</th>
            <th class="px-6 py-3 text-left">Gateway</th>
            <th class="px-6 py-3 text-left">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-neutral-50 transition">
            <td class="px-6 py-3 text-neutral-600">{{ inv.paid_at ? formatDate(inv.paid_at) : formatDate(inv.created_at) }}</td>
            <td class="px-6 py-3 capitalize font-medium">{{ inv.plan }}</td>
            <td class="px-6 py-3 capitalize text-neutral-500">{{ inv.billing_cycle }}</td>
            <td class="px-6 py-3 text-right font-medium">{{ inv.currency }} {{ Number(inv.amount).toLocaleString() }}</td>
            <td class="px-6 py-3 capitalize text-neutral-500">{{ inv.gateway }}</td>
            <td class="px-6 py-3">
              <span :class="invoiceBadge(inv.status)" class="text-xs font-semibold px-2 py-0.5 rounded-full capitalize">
                {{ inv.status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Upgrade modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showUpgrade" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-neutral-100">
              <h2 class="text-lg font-bold text-neutral-900">Choose a Plan</h2>
              <button @click="showUpgrade = false" class="text-neutral-400 hover:text-neutral-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div
                v-for="plan in plans"
                :key="plan.plan"
                class="border-2 rounded-xl p-5 flex flex-col gap-4 transition cursor-pointer"
                :class="selectedPlan === plan.plan ? 'border-primary-500 bg-primary-50' : 'border-neutral-200 hover:border-neutral-300'"
                @click="selectedPlan = plan.plan"
              >
                <div>
                  <p class="font-bold text-neutral-900">{{ plan.label }}</p>
                  <p class="text-2xl font-black text-primary-600 mt-1">
                    <span v-if="plan.is_custom_price">Custom</span>
                    <span v-else-if="Number(plan.price_zmw) === 0">Free</span>
                    <span v-else>ZMW {{ Number(plan.price_zmw).toLocaleString() }}</span>
                    <span v-if="!plan.is_custom_price && Number(plan.price_zmw) > 0" class="text-sm font-normal text-neutral-500">/mo</span>
                  </p>
                </div>
                <div
                  v-if="selectedPlan === plan.plan"
                  class="mt-auto flex items-center gap-1 text-primary-600 text-sm font-semibold"
                >
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                  Selected
                </div>
              </div>
            </div>

            <div class="px-6 pb-6 flex items-center gap-3 justify-end border-t border-neutral-100 pt-4">
              <label class="text-sm text-neutral-600 flex items-center gap-2">
                <input v-model="annualBilling" type="checkbox" class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500"/>
                Annual billing (save ~17%)
              </label>
              <button @click="showUpgrade = false" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-900">Cancel</button>
              <form :action="route('billing.checkout')" method="POST" @submit.prevent="submitCheckout">
                <input type="hidden" name="_token" :value="csrfToken"/>
                <button
                  type="submit"
                  :disabled="!selectedPlan || checkoutLoading"
                  class="px-5 py-2 text-sm font-semibold bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ checkoutLoading ? 'Redirecting…' : 'Pay & Upgrade' }}
                </button>
              </form>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  subscription: Object,
  invoices:     { type: Array, default: () => [] },
  plans:        { type: Array, default: () => [] },
})

const page        = usePage()
const tenant      = computed(() => page.props.tenant)
const csrfToken   = computed(() => document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const showUpgrade    = ref(false)
const selectedPlan   = ref(null)
const annualBilling  = ref(false)
const checkoutLoading = ref(false)

function submitCheckout() {
  if (!selectedPlan.value || checkoutLoading.value) return
  checkoutLoading.value = true

  router.post(route('billing.checkout'), {
    plan:          selectedPlan.value,
    billing_cycle: annualBilling.value ? 'annual' : 'monthly',
  }, {
    onError:  () => { checkoutLoading.value = false },
    onFinish: () => { checkoutLoading.value = false },
  })
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statusBadge(status) {
  return {
    active:    'bg-emerald-100 text-emerald-800',
    trial:     'bg-amber-100 text-amber-800',
    suspended: 'bg-red-100 text-red-800',
    expired:   'bg-orange-100 text-orange-800',
    cancelled: 'bg-neutral-100 text-neutral-600',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}

function invoiceBadge(status) {
  return {
    paid:    'bg-emerald-100 text-emerald-800',
    pending: 'bg-amber-100 text-amber-800',
    failed:  'bg-red-100 text-red-800',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
