<template>
  <LandingLayout>

    <!-- Hero -->
    <section class="bg-[#0A1628] text-white py-20 px-4 text-center relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-blue-900/30 to-purple-900/20 pointer-events-none"></div>
      <div class="max-w-3xl mx-auto relative">
        <span class="inline-flex items-center gap-1.5 bg-blue-500/20 text-blue-300 text-xs font-bold px-3 py-1 rounded-full mb-4 uppercase tracking-wider">
          <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
          Now Live
        </span>
        <h1 class="text-4xl sm:text-5xl font-black mb-5 leading-tight">The LENDR Marketplace</h1>
        <p class="text-white/60 text-lg leading-relaxed mb-8">Connect your loan products to thousands of pre-qualified borrowers. List products, manage applications, and grow your portfolio — all within LENDR.</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <a href="/onboarding" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold transition shadow-lg shadow-blue-900/40">List your products</a>
          <a href="/app/repo" class="border border-white/20 hover:border-white/40 text-white px-6 py-3 rounded-xl font-medium transition">Browse as borrower</a>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section class="py-16 px-4 bg-white">
      <div class="max-w-5xl mx-auto">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-black text-neutral-900 mb-3">How the Marketplace Works</h2>
          <p class="text-neutral-500 max-w-xl mx-auto">A two-sided marketplace connecting licensed lenders with creditworthy borrowers across Zambia.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-8">
          <div class="bg-blue-50 rounded-2xl p-8">
            <div class="text-3xl mb-4">🏦</div>
            <h3 class="text-lg font-bold text-neutral-900 mb-3">For Lenders</h3>
            <ol class="space-y-3">
              <li v-for="(step, i) in lenderSteps" :key="i" class="flex items-start gap-3 text-sm text-neutral-700">
                <span class="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">{{ i + 1 }}</span>
                {{ step }}
              </li>
            </ol>
          </div>
          <div class="bg-green-50 rounded-2xl p-8">
            <div class="text-3xl mb-4">👤</div>
            <h3 class="text-lg font-bold text-neutral-900 mb-3">For Borrowers</h3>
            <ol class="space-y-3">
              <li v-for="(step, i) in borrowerSteps" :key="i" class="flex items-start gap-3 text-sm text-neutral-700">
                <span class="w-5 h-5 bg-green-600 text-white rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">{{ i + 1 }}</span>
                {{ step }}
              </li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats -->
    <section class="py-14 px-4 bg-neutral-50">
      <div class="max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
        <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl p-6 shadow-sm border border-neutral-100">
          <div class="text-2xl font-black text-blue-600 mb-1">{{ stat.value }}</div>
          <div class="text-xs text-neutral-500">{{ stat.label }}</div>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="py-16 px-4 bg-white">
      <div class="max-w-5xl mx-auto">
        <h2 class="text-3xl font-black text-neutral-900 text-center mb-10">Marketplace Features</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="feature in features" :key="feature.title" class="rounded-2xl border border-neutral-100 p-6 hover:shadow-md transition">
            <div class="text-2xl mb-3">{{ feature.icon }}</div>
            <h3 class="font-bold text-neutral-900 mb-2">{{ feature.title }}</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">{{ feature.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-16 px-4 bg-[#0A1628] text-white text-center">
      <div class="max-w-2xl mx-auto">
        <h2 class="text-3xl font-black mb-4">Ready to grow your portfolio?</h2>
        <p class="text-white/60 mb-8">Marketplace access is included in the Growth and Enterprise plans. Start a free trial — no credit card required.</p>
        <a href="/onboarding" class="inline-block bg-blue-600 hover:bg-blue-500 text-white px-8 py-3 rounded-xl font-bold transition shadow-lg shadow-blue-900/40">Create free account</a>
      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const lenderSteps = [
  'Enable Marketplace from your LENDR dashboard (Growth/Enterprise plan).',
  'List your loan products with rates, terms, and eligibility criteria.',
  'Receive applications from pre-verified borrowers in your region.',
  'Approve, disburse, and manage loans — all within LENDR.',
]

const borrowerSteps = [
  'Browse loan products from licensed lenders near you.',
  'Compare rates, terms, and repayment schedules side by side.',
  'Apply in minutes using the LENDR borrower PWA.',
  'Receive mobile money disbursement upon approval.',
]

const stats = [
  { value: '120+', label: 'Active lenders listed' },
  { value: '8,400+', label: 'Loan applications processed' },
  { value: 'K14M+', label: 'Loans disbursed via marketplace' },
  { value: '48h', label: 'Average time to approval' },
]

const features = [
  { icon: '📋', title: 'Product Listings', description: 'List multiple loan products with custom eligibility rules, interest rates, and repayment terms.' },
  { icon: '🔍', title: 'Borrower Discovery', description: 'Reach creditworthy borrowers who are actively searching for loans in your target market.' },
  { icon: '📊', title: 'Credit Scoring', description: 'Integrated credit scoring engine assesses borrower risk before applications reach your inbox.' },
  { icon: '📱', title: 'Mobile-First Applications', description: 'Borrowers apply via the LENDR PWA — a mobile-optimised experience with document upload.' },
  { icon: '💸', title: 'Mobile Money Disbursement', description: 'Disburse approved loans directly to borrowers via Airtel Money, MTN MoMo, or Zamtel Kwacha.' },
  { icon: '📈', title: 'Portfolio Analytics', description: 'Track marketplace-sourced loan performance separately from your direct portfolio.' },
]
</script>
