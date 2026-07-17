<template>
  <LandingLayout>

    <!-- Hero -->
    <section class="bg-[#0A1628] text-white py-20 px-4 text-center">
      <div class="max-w-2xl mx-auto">
        <span class="inline-block bg-green-500/20 text-green-400 text-xs font-bold px-3 py-1 rounded-full mb-4 uppercase tracking-wider">We're Hiring</span>
        <h1 class="text-4xl sm:text-5xl font-black mb-5">Build the Future of African Finance</h1>
        <p class="text-white/60 text-lg leading-relaxed">Join a small, ambitious team building financial infrastructure for Zambia and beyond. We're remote-friendly, mission-driven, and growing fast.</p>
      </div>
    </section>

    <!-- Culture -->
    <section class="py-16 px-4 bg-white">
      <div class="max-w-5xl mx-auto">
        <h2 class="text-3xl font-black text-neutral-900 text-center mb-10">Why RISKCAPE?</h2>
        <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-6">
          <div v-for="value in values" :key="value.title" class="text-center p-6">
            <div class="text-3xl mb-3">{{ value.icon }}</div>
            <h3 class="font-bold text-neutral-800 mb-2">{{ value.title }}</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">{{ value.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Open roles -->
    <section class="py-16 px-4 bg-neutral-50">
      <div class="max-w-4xl mx-auto">
        <h2 class="text-3xl font-black text-neutral-900 text-center mb-10">Open Positions</h2>
        <div class="space-y-4">
          <div v-for="role in roles" :key="role.title"
            class="bg-white rounded-2xl border border-neutral-200 p-6 hover:shadow-md transition">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-xs bg-blue-100 text-blue-700 font-bold px-2 py-0.5 rounded-full">{{ role.department }}</span>
                  <span class="text-xs bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded-full">{{ role.type }}</span>
                  <span v-if="role.remote" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Remote OK</span>
                </div>
                <h3 class="text-lg font-bold text-neutral-900">{{ role.title }}</h3>
                <p class="text-sm text-neutral-500 mt-1">{{ role.description }}</p>
              </div>
              <a :href="`mailto:careers@lendr.app?subject=Application: ${role.title}`"
                class="shrink-0 bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold px-4 py-2 rounded-lg transition whitespace-nowrap">
                Apply now
              </a>
            </div>
          </div>
        </div>

        <div class="mt-8 bg-blue-50 rounded-2xl border border-blue-100 p-6 text-center">
          <p class="font-semibold text-neutral-800 mb-2">Don't see your role?</p>
          <p class="text-sm text-neutral-600 mb-4">We're always interested in talented people. Send us your CV and a note about what you'd like to build.</p>
          <a href="mailto:careers@lendr.app" class="text-blue-600 font-bold text-sm hover:underline">careers@lendr.app</a>
        </div>
      </div>
    </section>

    <!-- Benefits -->
    <section class="py-16 px-4 bg-white">
      <div class="max-w-5xl mx-auto">
        <h2 class="text-2xl font-black text-neutral-900 text-center mb-8">Benefits & Perks</h2>
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
          <div v-for="benefit in benefits" :key="benefit" class="flex items-center gap-3 p-4 rounded-xl bg-neutral-50">
            <svg class="w-4 h-4 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            <span class="text-sm text-neutral-700">{{ benefit }}</span>
          </div>
        </div>
      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const values = [
  { icon: '🚀', title: 'Move Fast', description: 'We ship every two weeks. You\'ll see your work in production quickly and get immediate real-world feedback.' },
  { icon: '🌍', title: 'Mission-Driven', description: 'We\'re building products that matter — helping lenders serve millions of under-banked Zambians.' },
  { icon: '🧠', title: 'Ownership', description: 'Small teams mean high ownership. You design, build, and own features end-to-end.' },
  { icon: '🤝', title: 'Collaborative', description: 'No politics, no silos. We debate ideas openly and support each other through challenges.' },
]

const roles = [
  {
    title: 'Senior Full-Stack Engineer (Laravel + Vue)',
    department: 'Engineering',
    type: 'Full-time',
    remote: true,
    description: '5+ years PHP/Laravel, Vue 3, MySQL. Experience with multi-tenant SaaS preferred.',
  },
  {
    title: 'Mobile Developer (React Native / PWA)',
    department: 'Engineering',
    type: 'Full-time',
    remote: true,
    description: 'Build and maintain our borrower-facing PWA and native app features.',
  },
  {
    title: 'Product Designer (UX/UI)',
    department: 'Design',
    type: 'Full-time',
    remote: true,
    description: 'Own design from research to Figma to final QA. Fintech or B2B SaaS experience a plus.',
  },
  {
    title: 'Customer Success Manager',
    department: 'Customer Success',
    type: 'Full-time',
    remote: false,
    description: 'Onboard and support lender clients across Zambia. Must be based in Lusaka.',
  },
  {
    title: 'Business Development Executive',
    department: 'Sales',
    type: 'Full-time',
    remote: false,
    description: 'Drive lender acquisition, manage partnerships, and grow LENDR\'s footprint. Lusaka-based.',
  },
]

const benefits = [
  'Competitive salary in ZMW',
  'Remote-friendly work culture',
  'Flexible working hours',
  'Professional development budget',
  'Health & wellness allowance',
  'Latest equipment provided',
  'Early equity / profit share for senior roles',
  'Direct impact on product direction',
  'Generous leave policy',
]
</script>
