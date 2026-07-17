<template>
  <LandingLayout>

    <!-- Hero -->
    <section class="bg-[#0A1628] text-white py-20 px-4 text-center">
      <div class="max-w-3xl mx-auto">
        <span class="inline-block bg-blue-600/20 text-blue-400 text-xs font-bold px-3 py-1 rounded-full mb-4 uppercase tracking-wider">About LENDR</span>
        <h1 class="text-4xl sm:text-5xl font-black mb-5 leading-tight">Built for African Lenders.<br>Powered by Modern Tech.</h1>
        <p class="text-white/60 text-lg leading-relaxed">LENDR is a loan management platform developed by RISKCAPE Enterprises Limited to help Zambian and African lenders digitise, grow, and manage their loan portfolios with confidence.</p>
      </div>
    </section>

    <!-- Mission -->
    <section class="py-16 px-4 bg-white">
      <div class="max-w-5xl mx-auto grid md:grid-cols-2 gap-12 items-center">
        <div>
          <h2 class="text-3xl font-black text-neutral-900 mb-4">Our Mission</h2>
          <p class="text-neutral-600 leading-relaxed mb-4">We believe every lender — from a single-branch SACCO to a multi-branch MFI — deserves world-class lending software that is affordable, compliant, and easy to use.</p>
          <p class="text-neutral-600 leading-relaxed">LENDR eliminates paper-based processes, reduces loan officer fraud, and puts real-time analytics in the hands of management — all from a browser or mobile phone.</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-blue-50 rounded-2xl p-6 text-center">
            <div class="text-3xl font-black text-blue-600 mb-1">500+</div>
            <div class="text-sm text-neutral-600">Active Lenders</div>
          </div>
          <div class="bg-green-50 rounded-2xl p-6 text-center">
            <div class="text-3xl font-black text-green-600 mb-1">K2.4B+</div>
            <div class="text-sm text-neutral-600">Loans Managed</div>
          </div>
          <div class="bg-purple-50 rounded-2xl p-6 text-center">
            <div class="text-3xl font-black text-purple-600 mb-1">8</div>
            <div class="text-sm text-neutral-600">Provinces Covered</div>
          </div>
          <div class="bg-orange-50 rounded-2xl p-6 text-center">
            <div class="text-3xl font-black text-orange-600 mb-1">99.9%</div>
            <div class="text-sm text-neutral-600">Platform Uptime</div>
          </div>
        </div>
      </div>
    </section>

    <!-- For MFIs -->
    <section id="mfi" class="py-16 px-4 bg-neutral-50 scroll-mt-20">
      <div class="max-w-5xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
          <span class="text-3xl">🏦</span>
          <h2 class="text-2xl font-black text-neutral-900">For MFIs &amp; SACCOs</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-8 items-start">
          <div>
            <p class="text-neutral-600 leading-relaxed mb-4">Microfinance institutions and SACCOs face unique challenges: managing thousands of borrowers, ensuring loan officer accountability, and maintaining compliance with Bank of Zambia regulations.</p>
            <p class="text-neutral-600 leading-relaxed mb-6">LENDR is purpose-built for these demands — with multi-branch support, PAR tracking, collections management, and built-in audit trails.</p>
            <ul class="space-y-2">
              <li v-for="f in mfiFeatures" :key="f" class="flex items-start gap-2 text-sm text-neutral-700">
                <svg class="w-4 h-4 text-blue-600 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ f }}
              </li>
            </ul>
          </div>
          <div class="bg-white rounded-2xl border border-neutral-200 p-6 shadow-sm">
            <p class="font-semibold text-neutral-800 mb-3">Ideal plan: <span class="text-blue-600">Growth or Enterprise</span></p>
            <p class="text-sm text-neutral-600 leading-relaxed">MFIs typically need multi-branch support, advanced PAR reports, mobile money disbursements, and borrower PWA access — all included in Growth and Enterprise tiers.</p>
            <a href="/onboarding" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-500 transition">Start free trial</a>
          </div>
        </div>
      </div>
    </section>

    <!-- For Cooperatives -->
    <section id="coop" class="py-16 px-4 bg-white scroll-mt-20">
      <div class="max-w-5xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
          <span class="text-3xl">🤝</span>
          <h2 class="text-2xl font-black text-neutral-900">For Cooperatives</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-8 items-start">
          <div>
            <p class="text-neutral-600 leading-relaxed mb-4">Cooperatives manage member savings and loans simultaneously. LENDR's savings module, contribution tracking, and dividend calculations are designed with cooperatives in mind.</p>
            <p class="text-neutral-600 leading-relaxed mb-6">Members can apply for loans via the borrower PWA, check statements, and make mobile money repayments — reducing walk-in visits dramatically.</p>
            <ul class="space-y-2">
              <li v-for="f in coopFeatures" :key="f" class="flex items-start gap-2 text-sm text-neutral-700">
                <svg class="w-4 h-4 text-blue-600 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ f }}
              </li>
            </ul>
          </div>
          <div class="bg-white rounded-2xl border border-neutral-200 p-6 shadow-sm">
            <p class="font-semibold text-neutral-800 mb-3">Ideal plan: <span class="text-blue-600">Starter or Growth</span></p>
            <p class="text-sm text-neutral-600 leading-relaxed">Smaller cooperatives can get started on the Starter plan and scale to Growth as membership grows. All plans include unlimited loan products and savings tracking.</p>
            <a href="/onboarding" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-500 transition">Start free trial</a>
          </div>
        </div>
      </div>
    </section>

    <!-- For SME Lenders -->
    <section id="sme" class="py-16 px-4 bg-neutral-50 scroll-mt-20">
      <div class="max-w-5xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
          <span class="text-3xl">💼</span>
          <h2 class="text-2xl font-black text-neutral-900">For SME Lenders</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-8 items-start">
          <div>
            <p class="text-neutral-600 leading-relaxed mb-4">Small business lenders need speed. LENDR's fast loan origination, automated repayment schedules, and SMS/mobile money integrations let you process more loans with fewer staff.</p>
            <p class="text-neutral-600 leading-relaxed mb-6">The marketplace feature lets SME lenders list their loan products to a wider pool of pre-qualified borrowers — expanding reach without increasing overhead.</p>
            <ul class="space-y-2">
              <li v-for="f in smeFeatures" :key="f" class="flex items-start gap-2 text-sm text-neutral-700">
                <svg class="w-4 h-4 text-blue-600 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ f }}
              </li>
            </ul>
          </div>
          <div class="bg-white rounded-2xl border border-neutral-200 p-6 shadow-sm">
            <p class="font-semibold text-neutral-800 mb-3">Ideal plan: <span class="text-blue-600">Growth</span></p>
            <p class="text-sm text-neutral-600 leading-relaxed">SME lenders benefit most from Growth — API access for custom integrations, marketplace listing, mobile money disbursements, and up to 5,000 active borrowers.</p>
            <a href="/onboarding" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-500 transition">Start free trial</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Company -->
    <section class="py-16 px-4 bg-[#0A1628] text-white">
      <div class="max-w-4xl mx-auto text-center">
        <h2 class="text-3xl font-black mb-4">About RISKCAPE Enterprises</h2>
        <p class="text-white/60 leading-relaxed mb-6 max-w-2xl mx-auto">RISKCAPE Enterprises Limited is a Zambian technology company founded with the goal of building financial infrastructure for underserved markets. Registered in Lusaka, Zambia, we operate under the Zambia Data Protection Act 2021 and are committed to building products that are secure, compliant, and locally relevant.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/contact" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold transition">Contact Us</a>
          <a href="/careers" class="border border-white/20 hover:border-white/40 text-white px-6 py-3 rounded-xl font-medium transition">We're Hiring</a>
        </div>
      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const mfiFeatures = [
  'Multi-branch loan management',
  'PAR (Portfolio at Risk) tracking',
  'Collections management & field officer tools',
  'Mobile money disbursements (Airtel/MTN/Zamtel)',
  'Borrower KYC with document uploads',
  'Audit log & role-based access control',
  'BoZ-ready reporting formats',
]

const coopFeatures = [
  'Member savings & contribution tracking',
  'Loan-against-savings products',
  'Borrower self-service PWA (mobile app)',
  'Bulk SMS notifications to members',
  'Mobile money repayment collection',
  'Dividend calculation support',
  'Unlimited member (borrower) records',
]

const smeFeatures = [
  'Fast loan origination (< 5 minutes)',
  'Flexible repayment schedules (daily/weekly/monthly)',
  'Marketplace listing for broader borrower reach',
  'API access for custom integrations',
  'Automated SMS repayment reminders',
  'Mobile money disbursements',
  'Expense tracking & profitability reports',
]
</script>
