<template>
  <div class="min-h-screen font-sans antialiased bg-white text-neutral-900">

    <!-- ─── Announcement Bar ──────────────────────────────────────────────── -->
    <div class="bg-blue-600 text-white text-center text-xs sm:text-sm py-2.5 px-4 flex items-center justify-center gap-2">
      <span class="inline-flex items-center gap-1.5 font-semibold">
        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
        </svg>
        NEW
      </span>
      <span class="text-blue-100">LENDR v2 is live — multi-branch, real-time analytics &amp; mobile money collections</span>
      <a href="/onboarding" class="ml-2 underline underline-offset-2 font-semibold hover:text-blue-200 whitespace-nowrap">Get started free →</a>
    </div>

    <!-- ─── Nav ──────────────────────────────────────────────────────────── -->
    <nav class="sticky top-0 z-50 bg-[#0A1628]/97 backdrop-blur border-b border-white/10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

        <!-- Logo -->
        <a href="/" class="text-white font-black text-xl tracking-tight flex items-center gap-2">
          <span class="w-7 h-7 bg-blue-600 rounded-lg flex items-center justify-center text-white text-sm font-black">L</span>
          LENDR
        </a>

        <!-- Desktop links -->
        <div class="hidden md:flex items-center gap-7 text-sm text-white/70 font-medium">

          <!-- Solutions dropdown -->
          <div class="relative group">
            <button class="flex items-center gap-1 hover:text-white transition py-1">
              Solutions
              <svg class="w-3.5 h-3.5 mt-0.5 group-hover:rotate-180 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div class="absolute top-full left-0 mt-2 w-52 bg-[#0D1F3C] border border-white/10 rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 py-1">
              <a href="/about#mfi"   class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 text-white/70 hover:text-white text-sm">🏦 For MFIs &amp; SACCOs</a>
              <a href="/about#coop"  class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 text-white/70 hover:text-white text-sm">🤝 For Cooperatives</a>
              <a href="/about#sme"   class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 text-white/70 hover:text-white text-sm">💼 For SME Lenders</a>
            </div>
          </div>

          <a href="/#features"   class="hover:text-white transition">Features</a>
          <a href="/#pricing"    class="hover:text-white transition">Pricing</a>
          <a href="/marketplace" class="hover:text-white transition flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
            Marketplace
          </a>

          <!-- Resources dropdown -->
          <div class="relative group">
            <button class="flex items-center gap-1 hover:text-white transition py-1">
              Resources
              <svg class="w-3.5 h-3.5 mt-0.5 group-hover:rotate-180 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div class="absolute top-full left-0 mt-2 w-44 bg-[#0D1F3C] border border-white/10 rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 py-1">
              <a href="/help"      class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 text-white/70 hover:text-white text-sm">❓ Help Center</a>
              <a href="/blog"      class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 text-white/70 hover:text-white text-sm">📝 Blog</a>
              <a href="/changelog" class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 text-white/70 hover:text-white text-sm">🔄 Changelog</a>
              <a href="/docs"      class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5 text-white/70 hover:text-white text-sm">📖 API Docs</a>
            </div>
          </div>
        </div>

        <!-- Desktop CTAs -->
        <div class="hidden md:flex items-center gap-3">
          <a href="/login"      class="text-white/80 hover:text-white text-sm font-medium transition px-3 py-2">Log in</a>
          <a href="/onboarding" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition shadow-lg shadow-blue-900/30">
            Create free account
          </a>
        </div>

        <!-- Mobile hamburger -->
        <button @click="mobileOpen = !mobileOpen" class="md:hidden text-white p-2">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path v-if="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Mobile menu -->
      <Transition name="slide-down">
        <div v-if="mobileOpen" class="md:hidden bg-[#0A1628] border-t border-white/10 px-4 py-4 space-y-1">
          <a href="/about"       @click="mobileOpen=false" class="block text-white/70 hover:text-white py-2.5 text-sm font-medium">Solutions</a>
          <a href="/#features"   @click="mobileOpen=false" class="block text-white/70 hover:text-white py-2.5 text-sm font-medium">Features</a>
          <a href="/#pricing"    @click="mobileOpen=false" class="block text-white/70 hover:text-white py-2.5 text-sm font-medium">Pricing</a>
          <a href="/marketplace" @click="mobileOpen=false" class="block text-white/70 hover:text-white py-2.5 text-sm font-medium">Marketplace</a>
          <a href="/help"        @click="mobileOpen=false" class="block text-white/70 hover:text-white py-2.5 text-sm font-medium">Help Center</a>
          <a href="/blog"        @click="mobileOpen=false" class="block text-white/70 hover:text-white py-2.5 text-sm font-medium">Blog</a>
          <div class="pt-3 flex flex-col gap-2 border-t border-white/10 mt-2">
            <a href="/login"      class="block text-white/80 hover:text-white text-center py-2.5 text-sm font-medium border border-white/20 rounded-lg">Log in</a>
            <a href="/onboarding" class="block bg-blue-600 text-white text-center px-4 py-2.5 rounded-lg font-bold text-sm">Create free account</a>
          </div>
        </div>
      </Transition>
    </nav>

    <!-- ─── Page content slot ─────────────────────────────────────────────── -->
    <slot />

    <!-- ─── Footer ───────────────────────────────────────────────────────── -->
    <footer class="bg-[#0A1628] text-white/60 pt-16 pb-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-8 mb-12">

          <!-- Brand -->
          <div class="col-span-2">
            <div class="flex items-center gap-2 mb-4">
              <span class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-black">L</span>
              <span class="text-white font-black text-lg">LENDR</span>
            </div>
            <p class="text-sm leading-relaxed mb-4 max-w-xs">The loan management system for smart Zambian lenders. Start, launch and scale your lending business.</p>
            <div class="flex gap-3 mb-4">
              <a href="#" aria-label="LinkedIn"  class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center hover:bg-blue-600 transition text-xs font-bold">in</a>
              <a href="#" aria-label="Twitter/X" class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center hover:bg-blue-600 transition text-xs font-bold">𝕏</a>
              <a href="#" aria-label="Facebook"  class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center hover:bg-blue-600 transition text-xs font-bold">f</a>
              <a href="#" aria-label="YouTube"   class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center hover:bg-blue-600 transition text-xs font-bold">▶</a>
            </div>
            <p class="text-xs text-white/40">support@lendr.app</p>
          </div>

          <!-- Solutions -->
          <div>
            <p class="text-white font-bold text-sm mb-4">Solutions</p>
            <ul class="space-y-2.5 text-sm">
              <li><a href="/about#mfi"   class="hover:text-white transition">For MFIs</a></li>
              <li><a href="/about#sacco" class="hover:text-white transition">For SACCOs</a></li>
              <li><a href="/about#coop"  class="hover:text-white transition">For Cooperatives</a></li>
              <li><a href="/about#sme"   class="hover:text-white transition">For SME Lenders</a></li>
            </ul>
          </div>

          <!-- Product -->
          <div>
            <p class="text-white font-bold text-sm mb-4">Product</p>
            <ul class="space-y-2.5 text-sm">
              <li><a href="/#features"   class="hover:text-white transition">Features</a></li>
              <li><a href="/#pricing"    class="hover:text-white transition">Pricing</a></li>
              <li><a href="/marketplace" class="hover:text-white transition">Marketplace</a></li>
              <li><a href="/changelog"   class="hover:text-white transition">Changelog</a></li>
            </ul>
          </div>

          <!-- Resources -->
          <div>
            <p class="text-white font-bold text-sm mb-4">Resources</p>
            <ul class="space-y-2.5 text-sm">
              <li><a href="/help"      class="hover:text-white transition">Help Center</a></li>
              <li><a href="/blog"      class="hover:text-white transition">Blog</a></li>
              <li><a href="/docs"      class="hover:text-white transition">API Docs</a></li>
              <li><a href="/changelog" class="hover:text-white transition">Changelog</a></li>
            </ul>
          </div>

          <!-- Company -->
          <div>
            <p class="text-white font-bold text-sm mb-4">Company</p>
            <ul class="space-y-2.5 text-sm">
              <li><a href="/about"    class="hover:text-white transition">About Us</a></li>
              <li><a href="/careers"  class="hover:text-white transition">Careers <span class="text-xs bg-green-600 text-white px-1.5 py-0.5 rounded-full ml-1">Hiring</span></a></li>
              <li><a href="/contact"  class="hover:text-white transition">Contact</a></li>
              <li><a href="/privacy"  class="hover:text-white transition">Privacy Policy</a></li>
              <li><a href="/terms"    class="hover:text-white transition">Terms of Use</a></li>
            </ul>
          </div>
        </div>

        <!-- Bottom bar -->
        <div class="border-t border-white/10 pt-6">
          <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 text-xs">
            <div>
              <p class="text-white/70 font-semibold mb-0.5">🇿🇲 Zambia</p>
              <p>RISKCAPE Enterprises Limited · Lusaka, Zambia</p>
            </div>
            <p class="text-white/40">© {{ new Date().getFullYear() }} LENDR · All rights reserved</p>
            <div class="flex gap-4 text-xs">
              <a href="/privacy" class="hover:text-white transition">Privacy</a>
              <a href="/terms"   class="hover:text-white transition">Terms</a>
              <a href="/contact" class="hover:text-white transition">Contact</a>
            </div>
          </div>
          <p class="text-xs text-white/25 mt-4 leading-relaxed max-w-3xl">
            LENDR is a loan management software platform for licensed lenders. It is not a lender and does not offer or broker loans. All data is stored securely in the Africa region in compliance with the Zambia Data Protection Act 2021.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const mobileOpen = ref(false)
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
