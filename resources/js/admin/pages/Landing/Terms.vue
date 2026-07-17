<template>
  <LandingLayout>

    <section class="bg-[#0A1628] text-white py-16 px-4 text-center">
      <div class="max-w-2xl mx-auto">
        <h1 class="text-4xl font-black mb-3">Terms of Use</h1>
        <p class="text-white/60">Last updated: 1 April 2025</p>
      </div>
    </section>

    <section class="py-14 px-4">
      <div class="max-w-3xl mx-auto">

        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-8 text-sm text-yellow-800">
          <strong>Important:</strong> By accessing or using LENDR, you agree to these Terms of Use. If you do not agree, do not use the platform.
        </div>

        <div v-for="section in sections" :key="section.title" class="mb-10">
          <h2 class="text-xl font-bold text-neutral-900 mb-3">{{ section.title }}</h2>
          <p v-for="(para, i) in section.paragraphs" :key="i" class="text-neutral-600 leading-relaxed mb-3 text-sm sm:text-base">{{ para }}</p>
          <ul v-if="section.bullets" class="list-disc list-inside space-y-1 text-neutral-600 text-sm">
            <li v-for="b in section.bullets" :key="b">{{ b }}</li>
          </ul>
        </div>

        <div class="border-t border-neutral-200 pt-8 mt-8 text-sm text-neutral-500">
          <p>Legal queries: <a href="mailto:legal@lendr.app" class="text-blue-600 hover:underline">legal@lendr.app</a></p>
          <p class="mt-2">RISKCAPE Enterprises Limited · Lusaka, Zambia</p>
        </div>

      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const sections = [
  {
    title: '1. Acceptance of Terms',
    paragraphs: [
      'These Terms of Use ("Terms") govern your access to and use of the LENDR platform, website, and related services (collectively, the "Service") operated by RISKCAPE Enterprises Limited ("RISKCAPE", "we", "us", "our").',
      'By creating an account, clicking "I agree", or otherwise accessing the Service, you confirm that you have read, understood, and agree to be bound by these Terms.',
    ],
  },
  {
    title: '2. The Service',
    paragraphs: [
      'LENDR is a software-as-a-service (SaaS) loan management platform. We provide tools for licensed lenders to originate, disburse, and collect loans. LENDR is not a lender and does not offer, broker, or guarantee any loans.',
      'We reserve the right to modify, suspend, or discontinue any part of the Service at any time with reasonable notice.',
    ],
  },
  {
    title: '3. Eligibility & Account Registration',
    paragraphs: ['To use LENDR you must:'],
    bullets: [
      'Be a legally registered entity or individual operating under applicable Zambian or regional law.',
      'Be authorised to act on behalf of the organisation you register.',
      'Provide accurate, complete, and current registration information.',
      'Maintain the security of your account credentials and notify us immediately of any unauthorised access.',
    ],
  },
  {
    title: '4. Permitted Use',
    paragraphs: [
      'You may use LENDR solely for legitimate lending operations. You must not use the Service to:',
    ],
    bullets: [
      'Engage in predatory, fraudulent, or illegal lending practices.',
      'Process or store payment card data in violation of PCI-DSS standards.',
      'Attempt to gain unauthorised access to other tenants\' data.',
      'Reverse-engineer, decompile, or scrape the platform.',
      'Transmit malware, spam, or disruptive content.',
    ],
  },
  {
    title: '5. Subscription & Payment',
    paragraphs: [
      'LENDR offers tiered subscription plans (Starter, Growth, Enterprise) billed monthly or annually in Zambian Kwacha (ZMW). Prices are listed on the Pricing page and are subject to change with 30 days\' notice.',
      'Subscriptions auto-renew until cancelled. Failure to pay will result in service suspension after a 7-day grace period. No refunds are issued for unused partial subscription periods unless required by law.',
    ],
  },
  {
    title: '6. Data Ownership',
    paragraphs: [
      'You retain ownership of all data you enter into LENDR, including borrower records, loan data, and documents. We process this data solely to provide the Service as described in our Privacy Policy.',
      'Upon account termination, you may export your data for 30 days. After that period, data will be deleted in accordance with our retention policy.',
    ],
  },
  {
    title: '7. Intellectual Property',
    paragraphs: [
      'LENDR, the RISKCAPE name, logos, and all platform code, design, and content are the intellectual property of RISKCAPE Enterprises Limited. Nothing in these Terms transfers any intellectual property rights to you.',
    ],
  },
  {
    title: '8. Limitation of Liability',
    paragraphs: [
      'To the maximum extent permitted by Zambian law, RISKCAPE\'s total liability for any claims arising from use of the Service shall not exceed the fees paid by you in the 3 months preceding the claim.',
      'We are not liable for indirect, incidental, or consequential damages including lost profits, data loss, or business interruption.',
    ],
  },
  {
    title: '9. Indemnification',
    paragraphs: [
      'You agree to indemnify and hold harmless RISKCAPE Enterprises Limited, its officers, employees, and agents from any claims, damages, or expenses arising from your use of the Service, your violation of these Terms, or your violation of applicable law.',
    ],
  },
  {
    title: '10. Governing Law',
    paragraphs: [
      'These Terms are governed by the laws of the Republic of Zambia. Any disputes shall be resolved in the courts of Lusaka, Zambia.',
    ],
  },
  {
    title: '11. Changes to Terms',
    paragraphs: [
      'We may update these Terms from time to time. We will notify account owners by email at least 14 days before material changes take effect. Your continued use of the Service constitutes acceptance.',
    ],
  },
]
</script>
