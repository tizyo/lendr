<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Fund Management</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Capital pool, disbursements, and repayments</p>
        </div>
        <Link :href="route('funds.deposits.index')" class="btn-secondary">
          View All Deposits
        </Link>
      </div>
    </template>

    <!-- Balance Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
      <div class="lendr-card p-5 col-span-2 lg:col-span-1">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Available Balance</p>
        <p class="text-3xl font-bold text-emerald-600 mt-1">
          {{ balance.currency }} {{ fmt(balance.available_balance) }}
        </p>
      </div>
      <div class="lendr-card p-5">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Total Deposits</p>
        <p class="text-xl font-semibold text-neutral-800 mt-1">{{ fmt(balance.total_deposits) }}</p>
      </div>
      <div class="lendr-card p-5">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Total Disbursed</p>
        <p class="text-xl font-semibold text-amber-600 mt-1">{{ fmt(balance.total_disbursed) }}</p>
      </div>
      <div class="lendr-card p-5">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Total Repaid</p>
        <p class="text-xl font-semibold text-sky-600 mt-1">{{ fmt(balance.total_repaid) }}</p>
      </div>
      <div class="lendr-card p-5">
        <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Total Expenses</p>
        <p class="text-xl font-semibold text-red-500 mt-1">{{ fmt(balance.total_expenses) }}</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Recent Transactions -->
      <div class="lendr-card lg:col-span-2">
        <div class="p-4 border-b border-neutral-100">
          <h2 class="font-semibold text-neutral-800">Recent Transactions</h2>
        </div>
        <div class="divide-y divide-neutral-100">
          <div
            v-for="tx in recentTransactions"
            :key="tx.id"
            class="flex items-center justify-between px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <span
                class="w-2 h-2 rounded-full flex-shrink-0"
                :class="tx.is_credit ? 'bg-emerald-500' : 'bg-red-500'"
              />
              <div>
                <p class="text-sm font-medium text-neutral-800">{{ tx.description }}</p>
                <p class="text-xs text-neutral-400">{{ tx.transaction_ref }} · {{ tx.created_at }}</p>
              </div>
            </div>
            <div class="text-right">
              <p
                class="text-sm font-semibold"
                :class="tx.is_credit ? 'text-emerald-600' : 'text-red-600'"
              >
                {{ tx.is_credit ? '+' : '-' }}{{ fmt(tx.amount) }}
              </p>
              <p class="text-xs text-neutral-400">Bal: {{ fmt(tx.balance_after) }}</p>
            </div>
          </div>
          <div v-if="!recentTransactions.length" class="px-4 py-8 text-center text-neutral-400 text-sm">
            No transactions yet.
          </div>
        </div>
      </div>

      <!-- Pending Deposits -->
      <div class="lendr-card">
        <div class="p-4 border-b border-neutral-100 flex items-center justify-between">
          <h2 class="font-semibold text-neutral-800">Pending Deposits</h2>
          <span
            v-if="pendingDeposits.length"
            class="text-xs bg-amber-100 text-amber-700 font-medium px-2 py-0.5 rounded-full"
          >
            {{ pendingDeposits.length }}
          </span>
        </div>
        <div class="divide-y divide-neutral-100">
          <div
            v-for="dep in pendingDeposits"
            :key="dep.id"
            class="px-4 py-3"
          >
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-neutral-800">{{ dep.source }}</p>
              <p class="text-sm font-semibold text-neutral-700">{{ fmt(dep.amount) }}</p>
            </div>
            <p class="text-xs text-neutral-400 mt-0.5">{{ dep.reference }} · {{ dep.deposit_date }}</p>
          </div>
          <div v-if="!pendingDeposits.length" class="px-4 py-6 text-center text-neutral-400 text-sm">
            No pending deposits.
          </div>
        </div>
        <div class="p-3 border-t border-neutral-100">
          <Link :href="route('funds.deposits.index')" class="text-xs text-sky-600 hover:underline">
            Manage all deposits →
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

defineProps({
  balance: Object,
  recentTransactions: Array,
  pendingDeposits: Array,
})

const fmt = (n) => Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
</script>
