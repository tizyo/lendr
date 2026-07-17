<template>
  <LandingLayout>

    <section class="bg-[#0A1628] text-white py-16 px-4 text-center">
      <div class="max-w-2xl mx-auto">
        <h1 class="text-4xl font-black mb-3">Privacy Policy</h1>
        <p class="text-white/60">Last updated: 1 April 2025</p>
      </div>
    </section>

    <section class="py-14 px-4">
      <div class="max-w-3xl mx-auto prose prose-neutral prose-sm sm:prose-base">

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-8 text-sm text-blue-800">
          <strong>Summary:</strong> LENDR collects only the data needed to operate the platform. We store all data in the Africa region, never sell your data to third parties, and comply with the Zambia Data Protection Act 2021 (ZDPA).
        </div>

        <div v-for="section in sections" :key="section.title" class="mb-10">
          <h2 class="text-xl font-bold text-neutral-900 mb-3">{{ section.title }}</h2>
          <p v-for="(para, i) in section.paragraphs" :key="i" class="text-neutral-600 leading-relaxed mb-3">{{ para }}</p>
          <ul v-if="section.bullets" class="list-disc list-inside space-y-1 text-neutral-600 text-sm">
            <li v-for="b in section.bullets" :key="b">{{ b }}</li>
          </ul>
        </div>

        <div class="border-t border-neutral-200 pt-8 mt-8 text-sm text-neutral-500">
          <p>Questions about this policy? Contact us at <a href="mailto:privacy@lendr.app" class="text-blue-600 hover:underline">privacy@lendr.app</a>.</p>
          <p class="mt-2">RISKCAPE Enterprises Limited · Lusaka, Zambia · Registered under the Companies Act, Chapter 388 of the Laws of Zambia.</p>
        </div>

      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const sections = [
  {
    title: '1. Who We Are',
    paragraphs: [
      'LENDR is a loan management software platform operated by RISKCAPE Enterprises Limited ("RISKCAPE", "we", "us"), a company registered in Lusaka, Zambia. We act as a data processor on behalf of licensed lenders (our tenants) and as a data controller for our own onboarding and marketing data.',
    ],
  },
  {
    title: '2. What Data We Collect',
    paragraphs: ['We collect data in the following categories:'],
    bullets: [
      'Account data: name, email address, phone number, and role for staff accounts created during onboarding.',
      'Borrower data: personal details, loan information, repayment history, and KYC documents entered by lender tenants.',
      'Usage data: log files, IP addresses, browser/device information, and feature usage metrics for platform improvement.',
      'Payment data: mobile money transaction references (no raw card numbers are stored; payment processing is handled by Flutterwave and PawaPay).',
      'Communication data: support ticket content, emails, and chat transcripts when you contact us.',
    ],
  },
  {
    title: '3. How We Use Your Data',
    paragraphs: ['We use personal data to:'],
    bullets: [
      'Provide, maintain, and improve the LENDR platform.',
      'Process tenant subscription payments and send billing notifications.',
      'Respond to support tickets and product enquiries.',
      'Send transactional emails and SMS (e.g., OTP codes, repayment reminders) on behalf of lender tenants.',
      'Detect, investigate, and prevent security incidents and fraud.',
      'Comply with legal obligations under Zambian law.',
    ],
  },
  {
    title: '4. Data Storage & Security',
    paragraphs: [
      'All LENDR data is stored on servers located in the Africa region (AWS af-south-1, Cape Town). We do not transfer personal data outside Africa without appropriate safeguards.',
      'We implement industry-standard security measures including AES-256 encryption at rest, TLS 1.3 in transit, bcrypt password hashing, role-based access controls, and regular security audits.',
    ],
  },
  {
    title: '5. Data Sharing',
    paragraphs: [
      'We do not sell, rent, or trade personal data. We share data only with trusted sub-processors necessary to operate the platform:',
    ],
    bullets: [
      'Flutterwave / PawaPay — mobile money payment processing.',
      'Twilio / Clickatell — SMS delivery.',
      'AWS — cloud infrastructure and storage.',
      'Sentry — error monitoring (anonymised stack traces only).',
    ],
  },
  {
    title: '6. Tenant Data Responsibility',
    paragraphs: [
      'Each lender tenant is responsible for ensuring that their collection and use of borrower data complies with applicable law, including obtaining necessary consent from borrowers. LENDR provides the tools; the lender is the data controller for their borrowers\' personal data.',
    ],
  },
  {
    title: '7. Your Rights (ZDPA 2021)',
    paragraphs: ['Under the Zambia Data Protection Act 2021, you have the right to:'],
    bullets: [
      'Access the personal data we hold about you.',
      'Request correction of inaccurate data.',
      'Request deletion of your data where it is no longer necessary.',
      'Object to processing based on legitimate interests.',
      'Lodge a complaint with the Data Protection Commission of Zambia.',
    ],
  },
  {
    title: '8. Cookies',
    paragraphs: [
      'LENDR uses session cookies for authentication and analytics cookies to understand platform usage. You can disable non-essential cookies in your browser settings. Disabling session cookies will prevent you from logging in.',
    ],
  },
  {
    title: '9. Data Retention',
    paragraphs: [
      'Account and loan data is retained for the duration of the subscription and for 7 years after account closure, in line with Zambian financial record-keeping requirements. Support communications are retained for 2 years.',
    ],
  },
  {
    title: '10. Changes to This Policy',
    paragraphs: [
      'We may update this Privacy Policy from time to time. We will notify account owners by email at least 14 days before material changes take effect. Continued use of LENDR after that date constitutes acceptance of the updated policy.',
    ],
  },
]
</script>
