<template>
  <aside
    class="flex flex-col bg-neutral-900 text-white transition-all duration-300 shrink-0 h-screen z-50"
    :class="collapsed ? 'w-16' : 'w-64'"
  >
    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 py-5 border-b border-neutral-800">
      <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center shrink-0">
        <span class="font-black text-sm">L</span>
      </div>
      <Transition name="slide-fade">
        <div v-if="!collapsed">
          <span class="font-black text-lg tracking-tight">LENDR</span>
          <p v-if="tenant" class="text-neutral-400 text-xs leading-none mt-0.5 truncate">{{ tenant.name }}</p>
        </div>
      </Transition>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-0.5 sidebar-scroll">
      <template v-for="group in navGroups" :key="group.label">
        <!-- Group label -->
        <p
          v-if="!collapsed && group.label"
          class="px-3 py-2 text-xs font-semibold text-neutral-500 uppercase tracking-wider"
        >
          {{ group.label }}
        </p>

        <!-- Nav items -->
        <NavItem
          v-for="item in group.items"
          :key="item.name"
          :item="item"
          :collapsed="collapsed"
        />
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
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import NavItem from './NavItem.vue'

const props = defineProps({ collapsed: Boolean })
defineEmits(['toggle'])

const page = usePage()
const tenant = computed(() => page.props.tenant)

const navGroups = [
  {
    label: '',
    items: [
      { name: 'Dashboard', route: 'dashboard', icon: 'home' },
    ],
  },
  {
    label: 'Lending',
    items: [
      { name: 'Borrowers', route: 'borrowers.index', icon: 'users' },
      { name: 'KYC Review', route: 'kyc.index', icon: 'shield-check' },
      { name: 'Loans', route: 'loans.index', icon: 'credit-card' },
      { name: 'Payments', route: 'payments.index', icon: 'banknotes' },
      { name: 'Collections', route: 'collections.index', icon: 'phone' },
    ],
  },
  {
    label: 'Finance',
    items: [
      { name: 'Fund Management', route: 'funds.index', icon: 'building-library' },
      { name: 'Expenses', route: 'expenses.index', icon: 'receipt-percent' },
      { name: 'Expense Categories', route: 'expense-categories.index', icon: 'rectangle-stack' },
      { name: 'GL Ledger', route: 'gl-ledger.index', icon: 'book-open' },
      { name: 'Reports', route: 'reports.index', icon: 'chart-bar' },
      { name: 'Analytics', route: 'analytics.index', icon: 'presentation-chart-line' },
      { name: 'Financial Statements', route: 'financial-statements.index', icon: 'document-chart-bar' },
      { name: 'Interest Accrual', route: 'interest-accrual.index', icon: 'calculator' },
      { name: 'Penalties', route: 'penalties.index', icon: 'exclamation-triangle' },
      { name: 'IFRS9 Provisioning', route: 'provisioning.index', icon: 'shield-exclamation' },
      { name: 'Reconciliation', route: 'reconciliation.index', icon: 'arrows-right-left' },
      { name: 'Investors', route: 'investors.index', icon: 'banknotes' },
    ],
  },
  {
    label: 'Products',
    items: [
      { name: 'Loan Types', route: 'loan-types.index', icon: 'tag' },
      { name: 'Marketplace', route: 'marketplace.index', icon: 'shopping-bag' },
      { name: 'Repo Items', route: 'marketplace.repo-items', icon: 'archive-box' },
      { name: 'Featured Items', route: 'featured-items.index', icon: 'star' },
      { name: 'Hot Deals', route: 'hot-deals.index', icon: 'fire' },
      { name: 'Insurance', route: 'insurance.index', icon: 'shield-check' },
      { name: 'Savings', route: 'savings.index', icon: 'banknotes' },
      { name: 'Loan Groups', route: 'loan-groups.index', icon: 'user-group' },
    ],
  },
  {
    label: 'Operations',
    items: [
      { name: 'Approvals', route: 'approvals.index', icon: 'clipboard-document-check' },
      { name: 'Write-offs', route: 'writeoffs.index', icon: 'x-circle' },
      { name: 'Collection Cases', route: 'collection-cases.index', icon: 'exclamation-triangle' },
      { name: 'Campaigns', route: 'campaigns.index', icon: 'megaphone' },
      { name: 'Leads (CRM)', route: 'leads.index', icon: 'funnel' },
      { name: 'CRB', route: 'crb.index', icon: 'identification' },
    ],
  },
  {
    label: 'Bulk Operations',
    items: [
      { name: 'Import Borrowers', route: 'bulk.import-borrowers', icon: 'arrow-up-tray' },
      { name: 'Bulk Loans',       route: 'bulk.loans',            icon: 'queue-list' },
      { name: 'Batch Payments',   route: 'bulk.payments',         icon: 'banknotes' },
    ],
  },
  {
    label: 'Admin',
    items: [
      { name: 'Staff Targets', route: 'staff-targets.index', icon: 'trophy' },
      { name: 'Commissions', route: 'commissions.index', icon: 'currency-dollar' },
      { name: 'API Clients', route: 'api-clients.index', icon: 'code-bracket' },
      { name: 'Billing', route: 'billing.index', icon: 'credit-card' },
      { name: 'Staff', route: 'staff.index', icon: 'user-group' },
      { name: 'Branches', route: 'branches.index', icon: 'building-office-2' },
      { name: 'Exchange Rates', route: 'exchange-rates.index', icon: 'currency-dollar' },
      { name: 'Audit Log', route: 'audit-log.index', icon: 'clipboard-document-list' },
      { name: 'Settings', route: 'settings.index', icon: 'cog-6-tooth' },
      { name: 'Support', route: 'support.index', icon: 'lifebuoy' },
    ],
  },
]
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
