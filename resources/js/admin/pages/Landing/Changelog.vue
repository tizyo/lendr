<template>
  <LandingLayout>

    <section class="bg-[#0A1628] text-white py-16 px-4 text-center">
      <div class="max-w-2xl mx-auto">
        <h1 class="text-4xl font-black mb-3">Changelog</h1>
        <p class="text-white/60">What's new in LENDR. We ship improvements every two weeks.</p>
      </div>
    </section>

    <section class="py-14 px-4">
      <div class="max-w-3xl mx-auto">
        <div v-for="release in releases" :key="release.version" class="mb-12 relative">
          <!-- Timeline dot -->
          <div class="flex items-center gap-4 mb-4">
            <span :class="['px-2.5 py-1 rounded-full text-xs font-bold', release.type === 'major' ? 'bg-blue-600 text-white' : release.type === 'minor' ? 'bg-green-100 text-green-800' : 'bg-neutral-100 text-neutral-600']">
              {{ release.version }}
            </span>
            <span class="text-sm text-neutral-400">{{ release.date }}</span>
            <span v-if="release.type === 'major'" class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded-full">Major Release</span>
          </div>

          <div class="bg-white rounded-2xl border border-neutral-200 p-6 shadow-sm">
            <h2 class="text-lg font-bold text-neutral-900 mb-4">{{ release.title }}</h2>
            <p v-if="release.summary" class="text-sm text-neutral-600 mb-4 leading-relaxed">{{ release.summary }}</p>

            <div v-for="section in release.sections" :key="section.label" class="mb-4">
              <p :class="['text-xs font-bold uppercase tracking-wider mb-2', section.label === 'New' ? 'text-blue-600' : section.label === 'Improved' ? 'text-green-600' : section.label === 'Fixed' ? 'text-orange-600' : 'text-neutral-400']">
                {{ section.label }}
              </p>
              <ul class="space-y-1.5">
                <li v-for="item in section.items" :key="item" class="text-sm text-neutral-600 flex items-start gap-2">
                  <span :class="['mt-1.5 w-1.5 h-1.5 rounded-full shrink-0', section.label === 'New' ? 'bg-blue-400' : section.label === 'Improved' ? 'bg-green-400' : section.label === 'Fixed' ? 'bg-orange-400' : 'bg-neutral-300']"></span>
                  {{ item }}
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const releases = [
  {
    version: 'v2.0.0',
    date: 'April 2025',
    type: 'major',
    title: 'LENDR v2 — Multi-Branch, Real-Time & Mobile Money',
    summary: 'The biggest release in LENDR history. v2 introduces multi-branch support, real-time WebSocket notifications, full mobile money collections via PawaPay, and a completely rebuilt borrower PWA.',
    sections: [
      {
        label: 'New',
        items: [
          'Multi-branch support with per-branch loan officer scoping.',
          'Real-time notifications via Laravel Reverb WebSocket.',
          'PawaPay mobile money collections integration (Airtel/MTN/Zamtel).',
          'Borrower self-service PWA — apply, view statements, and repay via mobile.',
          'Marketplace — list and manage loan products for external borrowers.',
          'Credit scoring engine for borrower risk assessment.',
          'Portfolio-at-Risk (PAR) report with aging buckets.',
          'Officer league table and campaign management.',
          'Investor portal for fund tracking.',
          'Full audit log with per-field change history.',
        ],
      },
      {
        label: 'Improved',
        items: [
          'Loan calculator supports reducing balance, flat rate, and amortised schedules.',
          'PDF receipts now include QR codes for verification.',
          'Dashboard KPIs refresh every 30 seconds via WebSocket.',
          'Bulk loan operations (approve, disburse, write-off) with CSV import.',
        ],
      },
    ],
  },
  {
    version: 'v1.8.2',
    date: 'February 2025',
    type: 'patch',
    title: 'Security & Performance Patch',
    sections: [
      {
        label: 'Fixed',
        items: [
          'Resolved a race condition in concurrent payment processing.',
          'Fixed PDF generation timeout for large repayment schedules.',
          'Corrected fee rounding for flat-rate loan products.',
        ],
      },
      {
        label: 'Improved',
        items: [
          'Webhook idempotency checks now use a distributed lock to prevent duplicate records under load.',
          'Login rate limiting increased to 10 attempts / 5 minutes.',
        ],
      },
    ],
  },
  {
    version: 'v1.8.0',
    date: 'January 2025',
    type: 'minor',
    title: 'Expense Tracking & Exchange Rates',
    sections: [
      {
        label: 'New',
        items: [
          'Expense management module — categorise and track operational costs.',
          'Exchange rate management for USD/ZMW conversions.',
          'Automated daily exchange rate fetching via cron job.',
          'Expense vs loan income profitability report.',
        ],
      },
    ],
  },
  {
    version: 'v1.7.0',
    date: 'December 2024',
    type: 'minor',
    title: 'Advanced Reports & Data Exports',
    sections: [
      {
        label: 'New',
        items: [
          '11 report types: loans, payments, expenses, borrowers, PAR, collections, portfolio trend, demographics, cohort, officer league, geographic.',
          'PDF, Excel (XLSX), and CSV export for all report types.',
          'Scheduled report delivery via email (Growth/Enterprise plans).',
        ],
      },
    ],
  },
  {
    version: 'v1.6.0',
    date: 'November 2024',
    type: 'minor',
    title: 'Flutterwave Mobile Money & SMS Gateways',
    sections: [
      {
        label: 'New',
        items: [
          'Flutterwave mobile money disbursement and collection webhooks.',
          'Twilio SMS gateway integration.',
          'Clickatell SMS gateway integration.',
          'Bulk SMS campaigns for repayment reminders.',
        ],
      },
    ],
  },
]
</script>
