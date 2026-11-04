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
 *
 * `can` is the permission the page's route requires (see routes/admin); items the
 * user can't open are hidden, and a group with nothing left disappears.
 */
export const navSections = [
  { name: 'Dashboard', icon: 'home', items: [{ name: 'Dashboard', route: 'dashboard', icon: 'home' }] },
  {
    name: 'Borrowers', icon: 'users', items: [
      { name: 'Borrowers', route: 'borrowers.index', can: 'borrowers.view' },
      { name: 'KYC Review', route: 'kyc.index', can: 'kyc.view' },
      { name: 'Import Borrowers', route: 'bulk.import-borrowers', can: 'borrowers.create' },
    ],
  },
  {
    name: 'Loans', icon: 'credit-card', items: [
      { name: 'Loans', route: 'loans.index', can: 'loans.view' },
      { name: 'Approvals', route: 'approvals.index', can: 'approvals.view' },
      { name: 'Loan Groups', route: 'loan-groups.index', can: 'loans.view' },
      { name: 'Bulk Loans', route: 'bulk.loans', can: 'loans.view' },
      { name: 'Write-offs', route: 'writeoffs.index', can: 'loans.view' },
    ],
  },
  {
    name: 'Payments', icon: 'banknotes', items: [
      { name: 'Payments', route: 'payments.index', can: 'payments.view' },
      { name: 'Bank Deposits', route: 'payments.bank-deposits', can: 'payments.view' },
      { name: 'Batch Payments', route: 'bulk.payments', can: 'payments.create' },
      { name: 'Reconciliation', route: 'reconciliation.index', can: 'accounting.view' },
    ],
  },
  {
    name: 'Collections', icon: 'phone', items: [
      { name: 'Collections', route: 'collections.index', can: 'collections.view' },
      { name: 'Collection Cases', route: 'collection-cases.index', can: 'collections.view' },
      { name: 'Penalties', route: 'penalties.index', can: 'collections.view' },
      { name: 'CRB', route: 'crb.index', can: 'crb.view' },
      { name: 'Risk Policies', route: 'risk-policies.index', can: 'risk.view' },
    ],
  },
  {
    name: 'Products', icon: 'tag', items: [
      { name: 'Loan Types', route: 'loan-types.index', can: 'loan_products.view' },
      { name: 'Savings', route: 'savings.index', can: 'savings.view' },
      { name: 'Insurance', route: 'insurance.index', can: 'insurance.view' },
    ],
  },
  {
    name: 'Funding', icon: 'building-library', items: [
      { name: 'Fund Management', route: 'funds.index', can: 'funds.view' },
      { name: 'Investors', route: 'investors.index', can: 'investors.view' },
      { name: 'Expenses', route: 'expenses.index', can: 'expenses.view' },
      { name: 'Expense Categories', route: 'expense-categories.index', can: 'expenses.view' },
      { name: 'Exchange Rates', route: 'exchange-rates.index', can: 'accounting.view' },
    ],
  },
  {
    name: 'Accounting', icon: 'scale', items: [
      { name: 'GL Ledger', route: 'gl-ledger.index', can: 'accounting.view' },
      { name: 'Financial Statements', route: 'financial-statements.index', can: 'accounting.view' },
      { name: 'Interest Accrual', route: 'interest-accrual.index', can: 'accounting.view' },
      { name: 'IFRS9 Provisioning', route: 'provisioning.index', can: 'accounting.view' },
    ],
  },
  {
    name: 'Reports', icon: 'chart-bar', items: [
      { name: 'Reports', route: 'reports.index', can: 'reports.view' },
      { name: 'Analytics', route: 'analytics.index', can: 'reports.view' },
    ],
  },
  {
    name: 'Marketplace', icon: 'shopping-bag', items: [
      { name: 'Marketplace', route: 'marketplace.index', can: 'marketplace.view' },
      { name: 'Repo Items', route: 'marketplace.repo-items', can: 'marketplace.view' },
      { name: 'Featured Items', route: 'featured-items.index', can: 'marketplace.view' },
      { name: 'Hot Deals', route: 'hot-deals.index', can: 'marketplace.view' },
    ],
  },
  {
    name: 'Sales', icon: 'briefcase', items: [
      { name: 'Leads (CRM)', route: 'leads.index', can: 'sales.view' },
      { name: 'Campaigns', route: 'campaigns.index', can: 'sales.view' },
      { name: 'Staff Targets', route: 'staff-targets.index', can: 'sales.view' },
      { name: 'Agents / DSA', route: 'agents.index', can: 'sales.view' },
      { name: 'Commissions', route: 'commissions.index', can: 'commissions.view' },
    ],
  },
  {
    name: 'Administration', icon: 'cog-6-tooth', items: [
      { name: 'Staff', route: 'staff.index', can: 'staff.view' },
      { name: 'Branches', route: 'branches.index', can: 'branches.view' },
      { name: 'Settings', route: 'settings.index', can: 'settings.view' },
      { name: 'Message Templates', route: 'notification-templates.index', can: 'settings.view' },
      { name: 'Billing', route: 'billing.index', can: 'billing.manage' },
      { name: 'API Clients', route: 'api-clients.index', can: 'integrations.manage' },
      { name: 'Audit Log', route: 'audit-log.index', can: 'audit_log.view' },
      { name: 'Support', route: 'support.index' },
    ],
  },
]

/** The sidebar as this user may see it: permitted items only, empty groups dropped. */
export function visibleNavSections(permissions = []) {
  const allowed = new Set(permissions)

  return navSections
    .map(section => ({ ...section, items: section.items.filter(item => !item.can || allowed.has(item.can)) }))
    .filter(section => section.items.length > 0)
}

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
