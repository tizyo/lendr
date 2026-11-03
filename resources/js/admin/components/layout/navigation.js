import {
  HomeIcon, UsersIcon, ShieldCheckIcon, CreditCardIcon,
  BanknotesIcon, BuildingLibraryIcon, ReceiptPercentIcon,
  ChartBarIcon, TagIcon, UserGroupIcon, Cog6ToothIcon,
  ClipboardDocumentListIcon, BuildingOffice2Icon, CurrencyDollarIcon,
  ShoppingBagIcon, RectangleStackIcon, LifebuoyIcon,
  PhoneIcon, MegaphoneIcon, ArrowUpTrayIcon, QueueListIcon,
  StarIcon, FireIcon, ArchiveBoxIcon, FunnelIcon, IdentificationIcon,
  ExclamationTriangleIcon, ClipboardDocumentCheckIcon, XCircleIcon,
  BookOpenIcon, PresentationChartLineIcon, DocumentChartBarIcon, CalculatorIcon,
  ShieldExclamationIcon, ArrowsRightLeftIcon, TrophyIcon, CodeBracketIcon,
  WalletIcon, ScaleIcon, BriefcaseIcon,
} from '@heroicons/vue/24/outline'

/**
 * The staff sidebar. A section with one item is a plain link; anything else is a
 * group that expands and collapses. Order follows a loan's life: people → loans →
 * money in → arrears, then products, the books, and running the workspace.
 */
export const navSections = [
  { name: 'Dashboard', icon: 'home', items: [{ name: 'Dashboard', route: 'dashboard', icon: 'home' }] },
  {
    name: 'Borrowers', icon: 'users', items: [
      { name: 'Borrowers', route: 'borrowers.index' },
      { name: 'KYC Review', route: 'kyc.index' },
      { name: 'Import Borrowers', route: 'bulk.import-borrowers' },
    ],
  },
  {
    name: 'Loans', icon: 'credit-card', items: [
      { name: 'Loans', route: 'loans.index' },
      { name: 'Approvals', route: 'approvals.index' },
      { name: 'Loan Groups', route: 'loan-groups.index' },
      { name: 'Bulk Loans', route: 'bulk.loans' },
      { name: 'Write-offs', route: 'writeoffs.index' },
    ],
  },
  {
    name: 'Payments', icon: 'banknotes', items: [
      { name: 'Payments', route: 'payments.index' },
      { name: 'Bank Deposits', route: 'payments.bank-deposits' },
      { name: 'Batch Payments', route: 'bulk.payments' },
      { name: 'Reconciliation', route: 'reconciliation.index' },
    ],
  },
  {
    name: 'Collections', icon: 'phone', items: [
      { name: 'Collections', route: 'collections.index' },
      { name: 'Collection Cases', route: 'collection-cases.index' },
      { name: 'Penalties', route: 'penalties.index' },
      { name: 'CRB', route: 'crb.index' },
    ],
  },
  {
    name: 'Products', icon: 'tag', items: [
      { name: 'Loan Types', route: 'loan-types.index' },
      { name: 'Savings', route: 'savings.index' },
      { name: 'Insurance', route: 'insurance.index' },
    ],
  },
  {
    name: 'Funding', icon: 'building-library', items: [
      { name: 'Fund Management', route: 'funds.index' },
      { name: 'Investors', route: 'investors.index' },
      { name: 'Expenses', route: 'expenses.index' },
      { name: 'Expense Categories', route: 'expense-categories.index' },
      { name: 'Exchange Rates', route: 'exchange-rates.index' },
    ],
  },
  {
    name: 'Accounting', icon: 'scale', items: [
      { name: 'GL Ledger', route: 'gl-ledger.index' },
      { name: 'Financial Statements', route: 'financial-statements.index' },
      { name: 'Interest Accrual', route: 'interest-accrual.index' },
      { name: 'IFRS9 Provisioning', route: 'provisioning.index' },
    ],
  },
  {
    name: 'Reports', icon: 'chart-bar', items: [
      { name: 'Reports', route: 'reports.index' },
      { name: 'Analytics', route: 'analytics.index' },
    ],
  },
  {
    name: 'Marketplace', icon: 'shopping-bag', items: [
      { name: 'Marketplace', route: 'marketplace.index' },
      { name: 'Repo Items', route: 'marketplace.repo-items' },
      { name: 'Featured Items', route: 'featured-items.index' },
      { name: 'Hot Deals', route: 'hot-deals.index' },
    ],
  },
  {
    name: 'Sales', icon: 'briefcase', items: [
      { name: 'Leads (CRM)', route: 'leads.index' },
      { name: 'Campaigns', route: 'campaigns.index' },
      { name: 'Staff Targets', route: 'staff-targets.index' },
      { name: 'Commissions', route: 'commissions.index' },
    ],
  },
  {
    name: 'Administration', icon: 'cog-6-tooth', items: [
      { name: 'Staff', route: 'staff.index' },
      { name: 'Branches', route: 'branches.index' },
      { name: 'Settings', route: 'settings.index' },
      { name: 'Billing', route: 'billing.index' },
      { name: 'API Clients', route: 'api-clients.index' },
      { name: 'Audit Log', route: 'audit-log.index' },
      { name: 'Support', route: 'support.index' },
    ],
  },
]

const icons = {
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
  'currency-dollar': CurrencyDollarIcon,
  'shopping-bag': ShoppingBagIcon,
  'rectangle-stack': RectangleStackIcon,
  'cog-6-tooth': Cog6ToothIcon,
  'lifebuoy': LifebuoyIcon,
  'phone': PhoneIcon,
  'megaphone': MegaphoneIcon,
  'arrow-up-tray': ArrowUpTrayIcon,
  'queue-list': QueueListIcon,
  'star': StarIcon,
  'fire': FireIcon,
  'archive-box': ArchiveBoxIcon,
  'funnel': FunnelIcon,
  'identification': IdentificationIcon,
  'exclamation-triangle': ExclamationTriangleIcon,
  'clipboard-document-check': ClipboardDocumentCheckIcon,
  'x-circle': XCircleIcon,
  'book-open': BookOpenIcon,
  'presentation-chart-line': PresentationChartLineIcon,
  'document-chart-bar': DocumentChartBarIcon,
  'calculator': CalculatorIcon,
  'shield-exclamation': ShieldExclamationIcon,
  'arrows-right-left': ArrowsRightLeftIcon,
  'trophy': TrophyIcon,
  'code-bracket': CodeBracketIcon,
  'wallet': WalletIcon,
  'scale': ScaleIcon,
  'briefcase': BriefcaseIcon,
}

export function navIcon(name) {
  return icons[name] || HomeIcon
}

const allItems = navSections.flatMap(section => section.items)

/**
 * The one sidebar route the current page belongs to: an exact match, else the most
 * specific parent (loans.show → Loans; payments.bank-deposits stays Bank Deposits,
 * not Payments too).
 */
export function activeNavRoute() {
  try {
    const exact = allItems.find(item => route().current(item.route))
    if (exact) {
      return exact.route
    }

    const base = item => item.route.replace(/\.index$/, '')

    return allItems
      .filter(item => route().current(base(item) + '.*'))
      .sort((a, b) => base(b).length - base(a).length)[0]?.route ?? null
  } catch {
    return null
  }
}
