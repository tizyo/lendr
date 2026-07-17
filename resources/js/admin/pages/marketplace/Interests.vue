<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link :href="route('marketplace.index')" class="text-neutral-400 hover:text-neutral-700 text-sm">
          ← Marketplace
        </Link>
        <span class="text-neutral-300">/</span>
        <h1 class="text-xl font-bold text-neutral-900 truncate">{{ listing.title }}</h1>
      </div>
    </template>

    <!-- Listing summary -->
    <div class="lendr-card p-5 mb-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div>
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Borrower</p>
        <p class="font-medium text-neutral-800 mt-0.5">{{ listing.borrower?.name || '—' }}</p>
        <p class="text-xs text-neutral-400 font-mono">{{ listing.borrower?.borrower_number }}</p>
      </div>
      <div>
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Amount Requested</p>
        <p class="font-medium text-neutral-800 mt-0.5">ZMW {{ listing.amount_requested.toLocaleString() }}</p>
      </div>
      <div>
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Loan Reference</p>
        <p class="font-medium text-neutral-800 mt-0.5">{{ listing.loan_number || '—' }}</p>
      </div>
      <div>
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Status</p>
        <span :class="statusBadge(listing.status)" class="mt-0.5 inline-block">{{ listing.status }}</span>
      </div>
    </div>

    <!-- Interests table -->
    <div class="lendr-card overflow-hidden">
      <div class="px-5 py-3 border-b border-neutral-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-neutral-700">
          {{ listing.interests.length }} Interest{{ listing.interests.length !== 1 ? 's' : '' }}
        </h2>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Lender</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Offer</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden md:table-cell">Message</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Expressed</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="interest in listing.interests" :key="interest.id" class="hover:bg-neutral-50 transition">
              <td class="px-5 py-3.5">
                <p class="font-medium text-neutral-800">{{ interest.user || '—' }}</p>
              </td>
              <td class="px-5 py-3.5">
                <p class="font-medium text-neutral-800">ZMW {{ interest.amount_offered.toLocaleString() }}</p>
                <p class="text-xs text-neutral-400">{{ interest.interest_rate }}% p.a.</p>
              </td>
              <td class="px-5 py-3.5 text-neutral-600 hidden md:table-cell max-w-xs">
                <p class="truncate">{{ interest.message || '—' }}</p>
              </td>
              <td class="px-5 py-3.5">
                <span :class="interestBadge(interest.status)">{{ interest.status }}</span>
              </td>
              <td class="px-5 py-3.5 text-neutral-500 hidden lg:table-cell text-xs">
                {{ interest.created_at }}
              </td>
            </tr>
            <tr v-if="!listing.interests.length">
              <td colspan="5" class="px-5 py-10 text-center text-neutral-400">No interests expressed yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

defineProps({
  listing: Object,
})

function statusBadge(status) {
  return {
    active:    'lendr-badge-success',
    funded:    'lendr-badge-info',
    draft:     'lendr-badge-neutral',
    expired:   'lendr-badge-warning',
    withdrawn: 'lendr-badge-neutral',
  }[status] ?? 'lendr-badge-neutral'
}

function interestBadge(status) {
  return {
    pending:  'lendr-badge-neutral',
    accepted: 'lendr-badge-success',
    declined: 'lendr-badge-warning',
  }[status] ?? 'lendr-badge-neutral'
}
</script>
