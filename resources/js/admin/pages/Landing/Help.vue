<template>
  <LandingLayout>

    <!-- Header -->
    <section class="bg-[#0A1628] text-white py-16 px-4">
      <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-4xl font-black mb-4">Help Center</h1>
        <p class="text-white/60 text-lg mb-8">Find answers to common questions, guides, and troubleshooting tips.</p>
        <div class="relative max-w-lg mx-auto">
          <input v-model="search" type="text" placeholder="Search help articles…"
            class="w-full bg-white/10 border border-white/20 text-white placeholder-white/40 rounded-xl px-4 py-3 pl-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          <svg class="absolute left-3 top-3.5 w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
      </div>
    </section>

    <!-- Categories -->
    <section class="py-12 px-4 bg-white border-b border-neutral-100">
      <div class="max-w-5xl mx-auto grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
        <button v-for="cat in categories" :key="cat.label"
          @click="activeCategory = cat.label"
          :class="['rounded-xl p-4 text-center border transition text-sm font-medium', activeCategory === cat.label ? 'bg-blue-600 text-white border-blue-600' : 'bg-neutral-50 text-neutral-700 border-neutral-200 hover:border-blue-300']">
          <div class="text-xl mb-1">{{ cat.icon }}</div>
          {{ cat.label }}
        </button>
      </div>
    </section>

    <!-- Articles -->
    <section class="py-14 px-4 bg-neutral-50">
      <div class="max-w-5xl mx-auto">
        <div v-for="cat in filteredArticles" :key="cat.category" class="mb-10">
          <h2 class="text-lg font-bold text-neutral-900 mb-4">{{ cat.category }}</h2>
          <div class="grid sm:grid-cols-2 gap-3">
            <a v-for="article in cat.articles" :key="article.title"
              href="#"
              class="bg-white rounded-xl border border-neutral-200 p-4 hover:shadow-sm hover:border-blue-300 transition flex items-start gap-3">
              <svg class="w-4 h-4 text-blue-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              <div>
                <p class="text-sm font-medium text-neutral-800">{{ article.title }}</p>
                <p class="text-xs text-neutral-500 mt-0.5">{{ article.summary }}</p>
              </div>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Contact support -->
    <section class="py-14 px-4 bg-white">
      <div class="max-w-3xl mx-auto text-center">
        <p class="text-2xl font-black text-neutral-900 mb-3">Still need help?</p>
        <p class="text-neutral-500 mb-6">Our support team responds within 2 business hours during working hours.</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <a href="/contact" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold transition text-sm">Open a support ticket</a>
          <a href="mailto:support@lendr.app" class="border border-neutral-300 hover:border-neutral-400 text-neutral-700 px-6 py-3 rounded-xl font-medium transition text-sm">Email support@lendr.app</a>
        </div>
      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const search = ref('')
const activeCategory = ref('All')

const categories = [
  { icon: '🔀', label: 'All' },
  { icon: '🚀', label: 'Getting Started' },
  { icon: '💰', label: 'Loans' },
  { icon: '💳', label: 'Payments' },
  { icon: '👥', label: 'Borrowers' },
  { icon: '⚙️', label: 'Settings' },
]

const allArticles = [
  {
    category: 'Getting Started',
    articles: [
      { title: 'Creating your LENDR account', summary: 'Step-by-step onboarding guide for new lenders.' },
      { title: 'Inviting staff members', summary: 'How to add loan officers and admins to your account.' },
      { title: 'Setting up your first loan product', summary: 'Configure interest rates, terms, and fees.' },
      { title: 'Connecting mobile money', summary: 'Link Airtel Money, MTN MoMo, or Zamtel Kwacha.' },
    ],
  },
  {
    category: 'Loans',
    articles: [
      { title: 'Creating a loan application', summary: 'How to originate a new loan for a borrower.' },
      { title: 'Loan state machine explained', summary: 'Understand Pending, Active, Overdue, Closed states.' },
      { title: 'Generating a repayment schedule', summary: 'How LENDR calculates reducing balance vs flat rate.' },
      { title: 'Loan write-offs', summary: 'When and how to write off a non-performing loan.' },
    ],
  },
  {
    category: 'Payments',
    articles: [
      { title: 'Recording a manual payment', summary: 'How to capture cash or bank transfer repayments.' },
      { title: 'Mobile money webhooks', summary: 'How Flutterwave and PawaPay notify LENDR of payments.' },
      { title: 'Generating a receipt', summary: 'Print or email a PDF receipt for any payment.' },
      { title: 'Handling payment reversals', summary: 'How to reverse an incorrectly recorded payment.' },
    ],
  },
  {
    category: 'Borrowers',
    articles: [
      { title: 'Adding a new borrower', summary: 'Create a borrower profile with KYC documents.' },
      { title: 'Borrower PWA access', summary: 'How borrowers log in and check their loans on mobile.' },
      { title: 'KYC document review', summary: 'Approve or reject uploaded ID and proof of income.' },
      { title: 'Merging duplicate borrower records', summary: 'Fix duplicate entries without losing loan history.' },
    ],
  },
  {
    category: 'Settings',
    articles: [
      { title: 'Configuring SMS notifications', summary: 'Set up automated repayment and arrears reminders.' },
      { title: 'Two-factor authentication (2FA)', summary: 'Enable 2FA for staff logins.' },
      { title: 'Custom subdomain setup', summary: 'Point your own domain to your LENDR portal.' },
      { title: 'Exporting reports', summary: 'Download PDF, Excel, or CSV reports.' },
    ],
  },
]

const filteredArticles = computed(() => {
  let data = allArticles
  if (activeCategory.value !== 'All') {
    data = data.filter(cat => cat.category === activeCategory.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    data = data.map(cat => ({
      ...cat,
      articles: cat.articles.filter(a => a.title.toLowerCase().includes(q) || a.summary.toLowerCase().includes(q)),
    })).filter(cat => cat.articles.length > 0)
  }
  return data
})
</script>
