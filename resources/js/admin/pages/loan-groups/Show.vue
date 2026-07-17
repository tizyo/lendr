<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
          <Link :href="route('loan-groups.index')" class="text-neutral-400 hover:text-neutral-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
          </Link>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-2xl font-bold text-neutral-900">{{ group.name }}</h1>
              <span :class="statusClass(group.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ group.status }}</span>
            </div>
            <p class="text-sm text-neutral-500 mt-0.5">{{ group.group_number }}</p>
          </div>
        </div>
      </div>
    </template>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Active Members</p>
        <p class="text-2xl font-bold text-primary-600 mt-1">{{ group.active_members?.length ?? 0 }}</p>
        <p v-if="group.max_members" class="text-xs text-neutral-400">of {{ group.max_members }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Group Loans</p>
        <p class="text-2xl font-bold text-neutral-900 mt-1">{{ group.loans?.length ?? 0 }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Active Loans</p>
        <p class="text-2xl font-bold text-green-600 mt-1">{{ activeLoans.length }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide">Total Disbursed</p>
        <p class="text-lg font-bold text-neutral-900 mt-1">K {{ fmt(totalDisbursed) }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Group Info -->
      <div class="lendr-card p-5">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Group Details</h2>
        <div class="space-y-3 text-sm">
          <div>
            <p class="text-xs text-neutral-500">Loan Officer</p>
            <p class="font-medium">{{ group.officer?.name ?? '—' }}</p>
          </div>
          <div v-if="group.description">
            <p class="text-xs text-neutral-500">Description</p>
            <p class="text-neutral-600">{{ group.description }}</p>
          </div>
          <div v-if="group.meeting_schedule">
            <p class="text-xs text-neutral-500">Meeting Schedule</p>
            <p>{{ group.meeting_schedule }}</p>
          </div>
          <div v-if="group.meeting_location">
            <p class="text-xs text-neutral-500">Meeting Location</p>
            <p>{{ group.meeting_location }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Max Members</p>
            <p>{{ group.max_members ?? 'Unlimited' }}</p>
          </div>
        </div>
      </div>

      <!-- Members + Loans tabs -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Members -->
        <div class="lendr-card overflow-hidden">
          <div class="px-5 py-4 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-800">Active Members</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-neutral-100 bg-neutral-50">
                  <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Borrower</th>
                  <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Role</th>
                  <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Joined</th>
                  <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Phone</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-50">
                <tr v-if="!group.active_members?.length">
                  <td colspan="4" class="px-5 py-10 text-center text-neutral-400">No active members.</td>
                </tr>
                <tr v-for="m in group.active_members" :key="m.id" class="hover:bg-neutral-25">
                  <td class="px-5 py-3">
                    <Link :href="route('borrowers.show', m.borrower_id)" class="font-medium text-primary-600 hover:underline">
                      {{ m.borrower?.first_name }} {{ m.borrower?.last_name }}
                    </Link>
                    <p class="text-xs text-neutral-400 font-mono">{{ m.borrower?.borrower_number }}</p>
                  </td>
                  <td class="px-5 py-3">
                    <span :class="m.role === 'leader' ? 'bg-purple-100 text-purple-700' : 'bg-neutral-100 text-neutral-600'"
                      class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ m.role }}</span>
                  </td>
                  <td class="px-5 py-3 text-neutral-500 text-xs whitespace-nowrap">{{ m.joined_date }}</td>
                  <td class="px-5 py-3 text-neutral-500">{{ m.borrower?.phone ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Loans -->
        <div class="lendr-card overflow-hidden">
          <div class="px-5 py-4 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-800">Group Loans</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-neutral-100 bg-neutral-50">
                  <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Loan #</th>
                  <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Borrower</th>
                  <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Amount</th>
                  <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-50">
                <tr v-if="!group.loans?.length">
                  <td colspan="4" class="px-5 py-10 text-center text-neutral-400">No loans in this group.</td>
                </tr>
                <tr v-for="l in group.loans" :key="l.id" class="hover:bg-neutral-25">
                  <td class="px-5 py-3">
                    <Link :href="route('loans.show', l.id)" class="font-mono text-primary-600 hover:underline text-xs">{{ l.loan_number }}</Link>
                  </td>
                  <td class="px-5 py-3 text-neutral-700">
                    {{ l.borrower?.first_name }} {{ l.borrower?.last_name }}
                  </td>
                  <td class="px-5 py-3 text-right font-medium">K {{ fmt(l.amount) }}</td>
                  <td class="px-5 py-3">
                    <span :class="loanStatusClass(l.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ l.status }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({ group: Object })

const activeLoans = computed(() => props.group.loans?.filter(l => l.status === 'active') ?? [])
const totalDisbursed = computed(() => props.group.loans?.reduce((sum, l) => sum + parseFloat(l.amount ?? 0), 0) ?? 0)

function fmt(v) {
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0)
}

function statusClass(s) {
  return {
    active:   'bg-green-100 text-green-700',
    inactive: 'bg-neutral-100 text-neutral-500',
    closed:   'bg-red-100 text-red-700',
  }[s] ?? 'bg-neutral-100 text-neutral-600'
}

function loanStatusClass(s) {
  return {
    active:    'bg-green-100 text-green-700',
    disbursed: 'bg-blue-100 text-blue-700',
    completed: 'bg-neutral-100 text-neutral-600',
    defaulted: 'bg-red-100 text-red-700',
    pending:   'bg-yellow-100 text-yellow-700',
    approved:  'bg-indigo-100 text-indigo-700',
  }[s] ?? 'bg-neutral-100 text-neutral-600'
}
</script>
