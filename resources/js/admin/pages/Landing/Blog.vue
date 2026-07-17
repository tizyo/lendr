<template>
  <LandingLayout>

    <!-- Header -->
    <section class="bg-[#0A1628] text-white py-16 px-4 text-center">
      <div class="max-w-2xl mx-auto">
        <h1 class="text-4xl font-black mb-3">LENDR Blog</h1>
        <p class="text-white/60">Insights on lending, fintech, and building financial products in Zambia.</p>
      </div>
    </section>

    <!-- Category filter -->
    <section class="py-6 px-4 bg-white border-b border-neutral-100 sticky top-16 z-10">
      <div class="max-w-5xl mx-auto flex gap-2 overflow-x-auto pb-1 scrollbar-none">
        <button v-for="tag in tags" :key="tag"
          @click="activeTag = tag"
          :class="['px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition', activeTag === tag ? 'bg-blue-600 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200']">
          {{ tag }}
        </button>
      </div>
    </section>

    <!-- Featured post -->
    <section class="py-12 px-4 bg-white">
      <div class="max-w-5xl mx-auto">
        <div class="bg-gradient-to-br from-[#0A1628] to-blue-900 rounded-2xl p-8 text-white mb-12">
          <span class="text-xs font-bold bg-blue-500/30 text-blue-200 px-2.5 py-1 rounded-full">Featured</span>
          <h2 class="text-2xl sm:text-3xl font-black mt-4 mb-3 max-w-xl">{{ featured.title }}</h2>
          <p class="text-white/60 text-sm mb-4 max-w-lg">{{ featured.excerpt }}</p>
          <div class="flex items-center gap-3 text-xs text-white/40">
            <span>{{ featured.author }}</span>
            <span>·</span>
            <span>{{ featured.date }}</span>
            <span>·</span>
            <span>{{ featured.readTime }}</span>
          </div>
          <a href="#" class="mt-5 inline-block bg-white text-blue-900 px-5 py-2 rounded-lg text-sm font-bold hover:bg-blue-50 transition">Read article</a>
        </div>

        <!-- Posts grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <article v-for="post in filteredPosts" :key="post.title"
            class="bg-white rounded-2xl border border-neutral-200 overflow-hidden hover:shadow-md transition">
            <div :class="['h-32 flex items-center justify-center text-4xl', post.bgClass]">{{ post.emoji }}</div>
            <div class="p-5">
              <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">{{ post.tag }}</span>
              <h3 class="font-bold text-neutral-900 mt-2 mb-2 leading-snug text-sm">{{ post.title }}</h3>
              <p class="text-xs text-neutral-500 leading-relaxed mb-3">{{ post.excerpt }}</p>
              <div class="flex items-center justify-between text-xs text-neutral-400">
                <span>{{ post.author }}</span>
                <span>{{ post.readTime }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- Subscribe -->
    <section class="py-14 px-4 bg-neutral-50">
      <div class="max-w-xl mx-auto text-center">
        <h2 class="text-2xl font-black text-neutral-900 mb-3">Stay in the loop</h2>
        <p class="text-neutral-500 text-sm mb-6">Get new articles, product updates, and fintech insights delivered to your inbox. No spam.</p>
        <div class="flex gap-2">
          <input v-model="email" type="email" placeholder="your@email.com"
            class="flex-1 border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <button @click="subscribe" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-lg text-sm font-bold transition">
            Subscribe
          </button>
        </div>
        <p v-if="subscribed" class="mt-3 text-sm text-green-600 font-medium">You're subscribed!</p>
      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const activeTag = ref('All')
const email = ref('')
const subscribed = ref(false)

const tags = ['All', 'Product', 'Lending', 'Compliance', 'Technology', 'Operations']

const featured = {
  title: 'How LENDR v2 Multi-Branch Support Changes Everything for Zambian MFIs',
  excerpt: 'Managing multiple branches with separate loan books, officer teams, and reporting has historically required expensive enterprise software. Here\'s how we changed that.',
  author: 'LENDR Team',
  date: 'April 2025',
  readTime: '6 min read',
}

const posts = [
  { title: 'Understanding PAR: What Portfolio at Risk Means for Your Lending Business', excerpt: 'PAR is the single most important health metric for any lender. Here\'s how to track, interpret, and act on it.', tag: 'Lending', author: 'LENDR Team', readTime: '5 min', emoji: '📊', bgClass: 'bg-blue-50', date: 'March 2025' },
  { title: 'Mobile Money vs Bank Transfer: Which Disbursement Method Works Better in Zambia?', excerpt: 'A data-driven comparison of Airtel Money, MTN MoMo, and bank transfer for loan disbursements.', tag: 'Operations', author: 'LENDR Team', readTime: '4 min', emoji: '💸', bgClass: 'bg-green-50', date: 'March 2025' },
  { title: 'The Zambia Data Protection Act 2021: What Lenders Need to Know', excerpt: 'Loan officers collect sensitive personal data every day. Here\'s how to stay compliant with the ZDPA.', tag: 'Compliance', author: 'LENDR Team', readTime: '7 min', emoji: '🔒', bgClass: 'bg-purple-50', date: 'February 2025' },
  { title: 'Reducing Loan Officer Fraud: 5 Features Every MFI Should Enable', excerpt: 'Loan officer fraud costs Zambian MFIs millions annually. These LENDR features can dramatically reduce your exposure.', tag: 'Operations', author: 'LENDR Team', readTime: '5 min', emoji: '🛡️', bgClass: 'bg-red-50', date: 'February 2025' },
  { title: 'LENDR Changelog: Everything in v2.0', excerpt: 'Multi-branch, WebSockets, PawaPay, Marketplace, and 40+ smaller improvements — the full v2 release notes.', tag: 'Product', author: 'LENDR Team', readTime: '3 min', emoji: '🚀', bgClass: 'bg-orange-50', date: 'April 2025' },
  { title: 'Flat Rate vs Reducing Balance: Which Loan Structure is Right for Your Product?', excerpt: 'The choice between flat and reducing balance affects borrower affordability and your portfolio yield. Here\'s the maths.', tag: 'Lending', author: 'LENDR Team', readTime: '6 min', emoji: '🧮', bgClass: 'bg-yellow-50', date: 'January 2025' },
]

const filteredPosts = computed(() => {
  if (activeTag.value === 'All') return posts
  return posts.filter(p => p.tag === activeTag.value)
})

function subscribe() {
  if (email.value) subscribed.value = true
}
</script>

<style scoped>
.scrollbar-none::-webkit-scrollbar { display: none; }
.scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
</style>
