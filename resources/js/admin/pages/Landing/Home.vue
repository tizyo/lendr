<template>
  <LandingLayout>

    <!-- ─── Hero ─────────────────────────────────────────────────────────────── -->
    <section class="relative bg-[#0A1628] text-white overflow-hidden">
      <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-1/4 w-80 h-80 bg-blue-800/20 rounded-full blur-3xl translate-y-1/3"></div>
      </div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 md:py-32 grid md:grid-cols-2 gap-12 items-center relative">
        <div>
          <div class="inline-flex items-center gap-2 bg-blue-600/20 text-blue-300 text-xs font-semibold px-3 py-1.5 rounded-full border border-blue-500/30 mb-6">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
            Zambia's #1 Loan Management Platform
          </div>
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-tight mb-6">
            The best loan<br>management system<br>
            <span class="text-blue-400">for smart lenders</span>
          </h1>
          <p class="text-lg text-white/70 mb-8 leading-relaxed max-w-lg">
            Lending is profitable but also difficult — but not with LENDR. Start, launch and scale your loan business with real-time automation, mobile money collections, and smart decisions.
          </p>
          <div class="flex flex-col sm:flex-row gap-3 mb-10">
            <a href="#onboarding" class="bg-blue-600 hover:bg-blue-500 text-white px-7 py-3.5 rounded-xl font-bold text-center transition shadow-xl shadow-blue-900/40 text-base">Get started free</a>
            <a href="#lifecycle"  class="border border-white/25 text-white hover:bg-white/10 px-7 py-3.5 rounded-xl font-semibold text-center transition text-base">Book a Demo</a>
          </div>
          <div class="space-y-2.5">
            <div v-for="vp in valueProps" :key="vp" class="flex items-center gap-3 text-sm text-white/75">
              <span class="w-5 h-5 rounded-full bg-blue-600/30 border border-blue-500/40 flex items-center justify-center shrink-0">
                <svg class="w-3 h-3 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              </span>
              {{ vp }}
            </div>
          </div>
        </div>

        <!-- Dashboard mock -->
        <div class="hidden md:block">
          <div class="bg-white/5 border border-white/10 rounded-2xl p-5 backdrop-blur shadow-2xl">
            <div class="flex items-center gap-1.5 mb-4">
              <div class="w-2.5 h-2.5 rounded-full bg-red-400/60"></div>
              <div class="w-2.5 h-2.5 rounded-full bg-yellow-400/60"></div>
              <div class="w-2.5 h-2.5 rounded-full bg-green-400/60"></div>
              <span class="text-white/30 text-xs ml-2">LENDR — Admin Dashboard</span>
            </div>
            <div class="grid grid-cols-3 gap-2.5 mb-3">
              <div v-for="kpi in demoKpis" :key="kpi.label" class="bg-white/5 rounded-xl p-3 border border-white/10">
                <p class="text-white/40 text-xs leading-none mb-1">{{ kpi.label }}</p>
                <p class="text-white font-bold text-base leading-none mb-1">{{ kpi.value }}</p>
                <p class="text-green-400 text-xs">{{ kpi.change }}</p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2.5 mb-3">
              <div class="bg-white/5 rounded-xl p-3 border border-white/10">
                <p class="text-white/40 text-xs mb-2">Loan Status Breakdown</p>
                <div class="space-y-1.5">
                  <div v-for="ls in loanStatus" :key="ls.label" class="flex items-center gap-2">
                    <div class="h-1.5 rounded-full" :class="ls.color" :style="{width: ls.pct + '%'}"></div>
                    <span class="text-white/50 text-xs">{{ ls.label }}</span>
                  </div>
                </div>
              </div>
              <div class="bg-white/5 rounded-xl p-3 border border-white/10">
                <p class="text-white/40 text-xs mb-2">Collections vs Disburse</p>
                <div class="flex items-end gap-1 h-14">
                  <div v-for="(bar, i) in demoBars" :key="i" class="flex-1 flex flex-col gap-0.5">
                    <div class="rounded-t bg-blue-500/60" :style="{ height: bar.d + 'px' }"></div>
                    <div class="rounded-t bg-green-500/60" :style="{ height: bar.c + 'px' }"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-white/5 rounded-xl border border-white/10 overflow-hidden">
              <div class="flex justify-between items-center px-3 py-2 border-b border-white/5">
                <span class="text-white/50 text-xs font-medium">Recent Loans</span>
                <span class="text-blue-400 text-xs">View all</span>
              </div>
              <div v-for="row in recentLoans" :key="row.name" class="flex items-center justify-between px-3 py-2">
                <div class="flex items-center gap-2">
                  <div class="w-5 h-5 rounded-full bg-blue-600/40 flex items-center justify-center text-xs text-blue-300 font-bold">{{ row.name[0] }}</div>
                  <span class="text-white/70 text-xs">{{ row.name }}</span>
                </div>
                <span class="text-white/60 text-xs">{{ row.amount }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="row.statusClass">{{ row.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── Trusted by — scrolling marquee ───────────────────────────────────── -->
    <section class="bg-neutral-50 border-y border-neutral-200 py-6 overflow-hidden">
      <p class="text-center text-xs font-semibold text-neutral-400 uppercase tracking-widest mb-4">Trusted by lenders across Zambia</p>
      <div class="relative flex">
        <div class="flex gap-12 animate-marquee whitespace-nowrap">
          <span v-for="(name, i) in [...marqueeNames, ...marqueeNames]" :key="i" class="text-neutral-400 font-bold text-sm tracking-wide shrink-0">{{ name }}</span>
        </div>
      </div>
    </section>

    <!-- ─── Stats ─────────────────────────────────────────────────────────────── -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
        <div v-for="stat in stats" :key="stat.label" class="p-6 rounded-2xl bg-blue-50 border border-blue-100">
          <p class="text-3xl md:text-4xl font-black text-blue-700 mb-1">{{ stat.value }}</p>
          <p class="text-sm text-neutral-500 font-medium">{{ stat.label }}</p>
        </div>
      </div>
    </section>

    <!-- ─── Lending Lifecycle Tabs ────────────────────────────────────────────── -->
    <section id="lifecycle" class="bg-neutral-50 py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-12">
          <p class="text-blue-600 font-semibold text-sm uppercase tracking-widest mb-3">End-to-End Platform</p>
          <h2 class="text-3xl md:text-4xl font-black">All the capabilities you need to<br class="hidden md:block"> simplify your lending process</h2>
          <p class="text-neutral-500 mt-3 max-w-xl mx-auto">Our robust loan management system covers the entire lending lifecycle — from first application to final repayment.</p>
        </div>
        <div class="flex flex-wrap justify-center gap-2 mb-10">
          <button v-for="tab in lifecycleTabs" :key="tab.id"
            @click="activeTab = tab.id"
            class="px-5 py-2.5 rounded-full text-sm font-semibold transition"
            :class="activeTab === tab.id ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-white text-neutral-600 border border-neutral-200 hover:border-blue-300 hover:text-blue-600'">
            {{ tab.label }}
          </button>
        </div>
        <Transition name="tab-fade" mode="out-in">
          <div :key="activeTab" class="grid md:grid-cols-2 gap-10 items-center">
            <div>
              <div class="inline-flex items-center gap-2 bg-blue-600/10 text-blue-700 text-xs font-bold px-3 py-1.5 rounded-full mb-4">{{ lifecycleTabContent[activeTab].badge }}</div>
              <h3 class="text-2xl md:text-3xl font-black mb-4">{{ lifecycleTabContent[activeTab].title }}</h3>
              <p class="text-neutral-500 leading-relaxed mb-6">{{ lifecycleTabContent[activeTab].desc }}</p>
              <ul class="space-y-3">
                <li v-for="point in lifecycleTabContent[activeTab].points" :key="point" class="flex items-start gap-3 text-sm text-neutral-700">
                  <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                  {{ point }}
                </li>
              </ul>
            </div>
            <div class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-sm">
              <div class="text-4xl mb-4">{{ lifecycleTabContent[activeTab].icon }}</div>
              <div class="space-y-3">
                <div v-for="item in lifecycleTabContent[activeTab].preview" :key="item.label" class="flex items-center justify-between py-2 border-b border-neutral-100 last:border-0">
                  <span class="text-sm text-neutral-600">{{ item.label }}</span>
                  <span class="text-sm font-semibold text-neutral-900">{{ item.value }}</span>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </section>

    <!-- ─── Features Grid ─────────────────────────────────────────────────────── -->
    <section id="features" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <p class="text-blue-600 font-semibold text-sm uppercase tracking-widest mb-3">Everything you need</p>
        <h2 class="text-3xl md:text-4xl font-black">Built to empower lenders</h2>
        <p class="text-neutral-500 mt-3 max-w-xl mx-auto">One platform. Every module. No spreadsheets.</p>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div v-for="feat in features" :key="feat.title" class="border border-neutral-200 rounded-2xl p-6 hover:shadow-md hover:border-blue-200 transition group bg-white">
          <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center text-2xl mb-4 group-hover:bg-blue-600/10 transition">{{ feat.icon }}</div>
          <h3 class="font-bold text-neutral-900 mb-2 text-base">{{ feat.title }}</h3>
          <p class="text-sm text-neutral-500 leading-relaxed">{{ feat.desc }}</p>
        </div>
      </div>
    </section>

    <!-- ─── Why LENDR ─────────────────────────────────────────────────────────── -->
    <section class="bg-[#0A1628] text-white py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
          <p class="text-blue-400 font-semibold text-sm uppercase tracking-widest mb-3">WHY LENDR?</p>
          <h2 class="text-3xl md:text-4xl font-black">We've overcome every challenge<br class="hidden md:block"> to empower your success</h2>
          <p class="text-white/60 mt-3 max-w-xl mx-auto">Having encountered nearly every issue a lender might face, we offer comprehensive solutions that guarantee growth.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <div v-for="why in whyLendr" :key="why.title" class="bg-white/5 border border-white/10 rounded-2xl p-6 hover:border-blue-500/30 transition">
            <div class="w-11 h-11 bg-blue-600/20 rounded-xl flex items-center justify-center text-2xl mb-4">{{ why.icon }}</div>
            <h3 class="font-bold text-white mb-2">{{ why.title }}</h3>
            <p class="text-sm text-white/55 leading-relaxed">{{ why.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── Borrower PWA ──────────────────────────────────────────────────────── -->
    <section class="py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">
        <div class="flex justify-center">
          <div class="relative">
            <div class="w-52 h-96 bg-[#0A1628] border-4 border-neutral-800 rounded-[2.5rem] shadow-2xl flex flex-col overflow-hidden">
              <div class="bg-blue-600 px-4 pt-6 pb-4">
                <p class="text-white text-xs opacity-70">Good morning,</p>
                <p class="text-white font-bold text-base">Chanda Mwale</p>
                <div class="mt-3 bg-white/10 rounded-xl p-3">
                  <p class="text-white/60 text-xs">Outstanding Balance</p>
                  <p class="text-white font-black text-xl">K 3,200.00</p>
                  <p class="text-blue-200 text-xs">Due: 15 May 2026</p>
                </div>
              </div>
              <div class="flex-1 bg-white px-3 py-3 space-y-2">
                <div v-for="item in pwaPreview" :key="item.label" class="flex items-center gap-3 bg-neutral-50 rounded-xl p-2.5">
                  <span class="text-lg">{{ item.icon }}</span>
                  <div>
                    <p class="text-xs font-semibold text-neutral-800">{{ item.label }}</p>
                    <p class="text-xs text-neutral-400">{{ item.sub }}</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="absolute -right-6 top-16 bg-green-500 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-lg">✓ Payment received</div>
          </div>
        </div>
        <div>
          <p class="text-blue-600 font-semibold text-sm uppercase tracking-widest mb-3">Borrower PWA</p>
          <h2 class="text-3xl md:text-4xl font-black mb-4">Your Borrowers Deserve Better</h2>
          <p class="text-neutral-500 mb-6 leading-relaxed">LENDR's borrower PWA gives your clients everything they need — apply for loans, repay via mobile money, download statements — all from their phone, no app store required.</p>
          <ul class="space-y-3 mb-8">
            <li v-for="f in pwaFeatures" :key="f" class="flex items-center gap-3 text-sm text-neutral-700">
              <span class="w-5 h-5 rounded-full bg-blue-600 flex items-center justify-center shrink-0">
                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              </span>
              {{ f }}
            </li>
          </ul>
          <div class="flex flex-wrap gap-3">
            <div class="inline-flex items-center gap-2 bg-neutral-100 border border-neutral-200 text-neutral-700 px-4 py-2 rounded-lg text-sm font-semibold">📱 Install as PWA</div>
            <div class="inline-flex items-center gap-2 bg-neutral-100 border border-neutral-200 text-neutral-700 px-4 py-2 rounded-lg text-sm font-semibold">✅ Works on Android &amp; iOS</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── Testimonials ──────────────────────────────────────────────────────── -->
    <section class="bg-neutral-50 py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
          <p class="text-blue-600 font-semibold text-sm uppercase tracking-widest mb-3">Lenders love LENDR</p>
          <h2 class="text-3xl md:text-4xl font-black">Don't just take our word for it</h2>
          <p class="text-neutral-500 mt-3">Hear what our lenders say about LENDR</p>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
          <div v-for="t in testimonials" :key="t.name" class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
            <div class="flex gap-0.5 mb-4">
              <svg v-for="i in 5" :key="i" class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            </div>
            <p class="text-neutral-600 text-sm leading-relaxed mb-5 italic">"{{ t.quote }}"</p>
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm text-white" :class="t.avatarColor">{{ t.name[0] }}</div>
              <div>
                <p class="font-semibold text-sm text-neutral-900">{{ t.name }}</p>
                <p class="text-xs text-neutral-400">{{ t.role }}</p>
              </div>
              <span class="ml-auto text-lg">🇿🇲</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── Industry Solutions ────────────────────────────────────────────────── -->
    <section class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="text-center mb-12">
        <p class="text-blue-600 font-semibold text-sm uppercase tracking-widest mb-3">Tailored solutions</p>
        <h2 class="text-3xl md:text-4xl font-black">For your industry</h2>
      </div>
      <div class="grid md:grid-cols-3 gap-5">
        <a v-for="ind in industries" :key="ind.title" :href="ind.href"
          class="group relative border border-neutral-200 rounded-2xl p-6 hover:border-blue-400 hover:shadow-lg transition overflow-hidden">
          <div class="absolute inset-0 group-hover:bg-blue-50/50 transition-all duration-300 rounded-2xl"></div>
          <div class="relative">
            <div class="text-3xl mb-4">{{ ind.icon }}</div>
            <h3 class="font-black text-lg mb-2">{{ ind.title }}</h3>
            <p class="text-sm text-neutral-500 leading-relaxed mb-4">{{ ind.desc }}</p>
            <div class="flex items-center gap-1 text-blue-600 text-sm font-semibold group-hover:gap-2 transition-all">
              Learn more <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </div>
          </div>
        </a>
      </div>
    </section>

    <!-- ─── How It Works ─────────────────────────────────────────────────────── -->
    <section class="bg-neutral-50 py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-5xl mx-auto text-center mb-14">
        <p class="text-blue-600 font-semibold text-sm uppercase tracking-widest mb-3">Quick setup</p>
        <h2 class="text-3xl md:text-4xl font-black">Up and running in 30 minutes</h2>
      </div>
      <div class="max-w-5xl mx-auto grid md:grid-cols-3 gap-8 relative">
        <div class="hidden md:block absolute top-6 left-[16.5%] right-[16.5%] h-0.5 bg-blue-100"></div>
        <div v-for="(step, i) in steps" :key="step.title" class="text-center relative">
          <div class="w-12 h-12 rounded-full bg-blue-600 text-white text-lg font-black flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-200 relative z-10">{{ i + 1 }}</div>
          <h3 class="font-bold text-lg mb-2">{{ step.title }}</h3>
          <p class="text-sm text-neutral-500 max-w-xs mx-auto">{{ step.desc }}</p>
        </div>
      </div>
    </section>

    <!-- ─── Pricing ───────────────────────────────────────────────────────────── -->
    <section id="pricing" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <p class="text-blue-600 font-semibold text-sm uppercase tracking-widest mb-3">Transparent pricing</p>
        <h2 class="text-3xl md:text-4xl font-black">Simple, Transparent Pricing</h2>
        <p class="text-neutral-500 mt-3">No hidden fees. Cancel anytime. Launch at a fraction of building your own.</p>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
        <div v-for="plan in plans" :key="plan.name"
          class="border-2 rounded-2xl p-6 flex flex-col relative"
          :class="plan.featured ? 'border-blue-600 shadow-2xl shadow-blue-100' : 'border-neutral-200'">
          <div v-if="plan.featured" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs px-3 py-1 rounded-full font-bold whitespace-nowrap">Most Popular</div>
          <h3 class="font-black text-lg mb-1">{{ plan.name }}</h3>
          <p v-if="plan.tagline" class="text-xs text-neutral-400 mb-3">{{ plan.tagline }}</p>
          <div class="mb-5">
            <span class="text-3xl font-black">{{ plan.price }}</span>
            <span v-if="plan.price !== 'Free' && plan.price !== 'Custom'" class="text-neutral-400 text-sm">/mo</span>
          </div>
          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="f in plan.features" :key="f" class="flex items-start gap-2 text-sm text-neutral-600">
              <svg class="w-4 h-4 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
              {{ f }}
            </li>
          </ul>
          <a href="/onboarding" class="block text-center py-2.5 rounded-xl font-semibold text-sm transition"
            :class="plan.featured ? 'bg-blue-600 text-white hover:bg-blue-500 shadow-lg shadow-blue-200' : 'border border-neutral-300 text-neutral-700 hover:bg-neutral-50'">
            {{ plan.cta || 'Get Started' }}
          </a>
        </div>
      </div>
    </section>

    <!-- ─── FAQ ───────────────────────────────────────────────────────────────── -->
    <section id="faq" class="bg-neutral-50 py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-3xl mx-auto">
        <div class="text-center mb-12">
          <p class="text-blue-600 font-semibold text-sm uppercase tracking-widest mb-3">FAQ</p>
          <h2 class="text-3xl font-black">Frequently Asked Questions</h2>
        </div>
        <div class="space-y-3">
          <div v-for="faq in faqs" :key="faq.q" class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
            <button @click="faq.open = !faq.open" class="w-full text-left px-5 py-4 flex items-center justify-between font-semibold text-neutral-800 hover:bg-neutral-50 transition">
              {{ faq.q }}
              <svg class="w-5 h-5 text-neutral-400 shrink-0 transition-transform" :class="faq.open ? 'rotate-45' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </button>
            <Transition name="faq">
              <div v-if="faq.open" class="px-5 pb-5 text-sm text-neutral-600 leading-relaxed border-t border-neutral-100 pt-3">{{ faq.a }}</div>
            </Transition>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── Final CTA ─────────────────────────────────────────────────────────── -->
    <section id="onboarding" class="py-20 px-4 sm:px-6 lg:px-8 bg-blue-600 text-white relative overflow-hidden">
      <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500/30 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-blue-800/30 rounded-full blur-3xl translate-y-1/3 -translate-x-1/4"></div>
      </div>
      <div class="max-w-3xl mx-auto text-center relative">
        <p class="text-blue-200 font-semibold text-sm uppercase tracking-widest mb-3">Get started with LENDR</p>
        <h2 class="text-3xl md:text-4xl font-black mb-4">Ready to Modernise Your Lending?</h2>
        <p class="text-blue-100 mb-8 text-lg">Start your 14-day free trial. No credit card required.<br>We'll be with you every step of the way.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/onboarding" class="inline-block bg-white text-blue-700 font-bold px-8 py-4 rounded-xl text-base hover:bg-blue-50 transition shadow-xl">Register as a Lender →</a>
          <a href="/contact"    class="inline-block border-2 border-white/40 text-white font-semibold px-8 py-4 rounded-xl text-base hover:bg-white/10 transition">Book a Demo</a>
        </div>
      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { usePage } from '@inertiajs/vue3'
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const plans     = usePage().props.plans ?? []
const activeTab = ref('origination')

const valueProps = [
  'Quick Integration — set up your lending business with time-saving integrations.',
  'Automated Processing — real-time automation to optimise your operations.',
  'Fully Customizable — choose from powerful features that work for your business.',
]

const demoKpis = [
  { label: 'Active Loans',  value: '1,247',  change: '+12% this month'   },
  { label: 'Fund Balance',  value: 'K 842K', change: '↑ K 42K today'    },
  { label: 'Collected MTD', value: 'K 318K', change: '+8% vs last month' },
]

const loanStatus = [
  { label: 'Active',    pct: 65, color: 'bg-blue-500'  },
  { label: 'Overdue',   pct: 12, color: 'bg-red-400'   },
  { label: 'Completed', pct: 23, color: 'bg-green-500' },
]

const demoBars = [
  { d: 28, c: 20 }, { d: 35, c: 28 }, { d: 30, c: 32 },
  { d: 42, c: 36 }, { d: 38, c: 40 }, { d: 50, c: 45 },
]

const recentLoans = [
  { name: 'Chanda M.',  amount: 'K 5,000', status: 'Active',   statusClass: 'bg-blue-100 text-blue-700'   },
  { name: 'Patrick N.', amount: 'K 2,500', status: 'Overdue',  statusClass: 'bg-red-100 text-red-700'    },
  { name: 'Grace B.',   amount: 'K 8,000', status: 'Approved', statusClass: 'bg-green-100 text-green-700' },
]

const marqueeNames = [
  'Kwacha Finance', 'ZedLend', 'CopperBelt Credit', 'SinoLoan Zambia',
  'Victoria Microfinance', 'Lusaka Capital', 'ZamCredit', 'Chibombo SACCO',
  'Ndola MFI', 'Livingstone Loans', 'Kafue Cooperative', 'EagleMoney',
]

const stats = [
  { value: '500+',   label: 'Active Loans Managed Daily'  },
  { value: '98%',    label: 'Repayment Collection Rate'   },
  { value: '30 min', label: 'Average Time to First Loan'  },
  { value: '5×',     label: 'Cheaper Than Building Yours' },
]

const lifecycleTabs = [
  { id: 'origination',  label: 'Origination'  },
  { id: 'decisioning',  label: 'Decisioning'  },
  { id: 'disbursement', label: 'Disbursement' },
  { id: 'collections',  label: 'Collections'  },
  { id: 'reporting',    label: 'Reporting'    },
]

const lifecycleTabContent = {
  origination: {
    badge: 'Loan Origination', icon: '📝',
    title: "We've digitized the entire loan origination process",
    desc:  'Launch your lending business effortlessly across multiple channels including web, mobile, and APIs. Create and manage diverse loan products that cater to exactly what your customers need.',
    points: ['Multi-channel loan applications (web, mobile PWA)', 'Configurable loan products with custom rates and fees', 'Digital KYC with document upload and review workflow', 'Guarantor and collateral management built in'],
    preview: [{ label: 'Applications Today', value: '24' }, { label: 'Avg Processing Time', value: '12 min' }, { label: 'KYC Approval Rate', value: '91%' }, { label: 'Active Loan Products', value: '6' }],
  },
  decisioning: {
    badge: 'Loan Decisioning', icon: '⚖️',
    title: 'Smart decisioning tools for confident approvals',
    desc:  'LENDR gives loan officers and managers a complete borrower history, credit score, and repayment track record — so every lending decision is backed by data.',
    points: ['Full borrower credit history and PAR exposure', 'Marketplace credit scoring (300–850 range)', 'Multi-level approval workflows with role-based access', 'Blacklist and repeat-defaulter detection'],
    preview: [{ label: 'Pending Approvals', value: '8' }, { label: 'Avg Approval Time', value: '4 hrs' }, { label: 'Auto-Decline Rate', value: '18%' }, { label: 'Avg Credit Score', value: '682' }],
  },
  disbursement: {
    badge: 'Disbursement', icon: '💸',
    title: 'Disburse directly to mobile wallets in minutes',
    desc:  'LENDR integrates with Airtel Money, MTN MoMo, Zamtel Kwacha, Flutterwave, and PawaPay — so funds reach borrowers instantly, with automatic reconciliation.',
    points: ['One-click disbursement to Airtel, MTN, Zamtel', 'Automatic payment schedules generated on approval', 'Disbursement fund ledger with real-time balance', 'Full audit trail on every transaction'],
    preview: [{ label: 'Disbursed Today', value: 'K 124,000' }, { label: 'Avg Disbursement', value: '3.2 min' }, { label: 'Mobile Money Rate', value: '78%' }, { label: 'Fund Utilisation', value: '64%' }],
  },
  collections: {
    badge: 'Collections', icon: '🏦',
    title: 'Automated collections to protect your portfolio',
    desc:  "LENDR's collections engine sends automated payment reminders via SMS, processes mobile money repayments, and escalates overdue accounts — so your team focuses on growth.",
    points: ['Automated SMS reminders at configurable intervals', 'Borrower-initiated repayment via the LENDR PWA', 'Real-time PAR30 monitoring and overdue alerts', 'Penalty and late fee calculation (BCMath precise)'],
    preview: [{ label: 'Collected Today', value: 'K 89,400' }, { label: 'Collection Rate', value: '97.8%' }, { label: 'PAR 30', value: '3.2%' }, { label: 'Reminders Sent', value: '142' }],
  },
  reporting: {
    badge: 'Analytics & Reports', icon: '📊',
    title: 'Data-driven insights at every level',
    desc:  'From real-time dashboards to monthly board reports — LENDR gives managers, loan officers, and executives the data they need, in the format they need it.',
    points: ['Real-time KPI dashboard with fund utilisation', '11 report types: PAR, aging, cohort, demographics', 'Export to PDF, Excel, or CSV in one click', 'Loan officer league tables and performance tracking'],
    preview: [{ label: 'Reports Generated', value: '1,284 MTD' }, { label: 'Avg Report Time', value: '< 2 sec' }, { label: 'Export Formats', value: 'PDF, Excel, CSV' }, { label: 'Dashboard Charts', value: '4 live' }],
  },
}

const features = [
  { icon: '📋', title: 'Loan Lifecycle Management',       desc: 'Draft → Approve → Disburse → Collect. Full audit trail on every status change.' },
  { icon: '📱', title: 'Borrower Self-Service PWA',       desc: 'Borrowers apply, pay, and view statements from their phone. No app store needed.' },
  { icon: '💸', title: 'Mobile Money Disbursement',       desc: 'Airtel Money, MTN MoMo, Zamtel Kwacha — direct to borrower wallets in minutes.' },
  { icon: '🪪', title: 'Smart KYC & Document Management', desc: 'Digital KYC with document upload, review workflow, and status notifications.' },
  { icon: '💰', title: 'Expense & Fund Tracking',         desc: 'Full disbursement fund ledger, expense approvals, and budget monitoring.' },
  { icon: '📊', title: 'Real-Time Analytics & Reports',   desc: 'PAR ratios, aging analysis, collections reports — export PDF, Excel, or CSV.' },
  { icon: '🌐', title: 'Multi-Branch Support',            desc: 'Manage multiple branches with scoped visibility and branch-level reporting.' },
  { icon: '🔔', title: 'SMS & Push Notifications',        desc: 'Automated SMS reminders, approval alerts, and WebSocket real-time updates.' },
  { icon: '🔒', title: 'Bank-Grade Security',             desc: 'TLS 1.3, dedicated schema per tenant, 2FA, full audit trail. ZDPA 2021 compliant.' },
]

const whyLendr = [
  { icon: '💡', title: 'Cost Effective',        desc: 'Launch at a fraction of the cost of building your own proprietary platform. No infrastructure headaches.' },
  { icon: '🚀', title: 'Faster Time to Market', desc: 'Be accepting loan applications within 30 minutes of signing up. No technical team required.' },
  { icon: '🤝', title: 'Local Expertise',        desc: 'Built specifically for the Zambian market — Kwacha, mobile money, Zambian phone validation, ZDPA.' },
  { icon: '📈', title: 'Scales With You',        desc: 'From 10 loans to 10,000. Upgrade your plan as you grow with no downtime or data migration.' },
]

const pwaPreview = [
  { icon: '💰', label: 'Make a Payment', sub: 'via Airtel · MTN · Zamtel' },
  { icon: '📄', label: 'View Statement', sub: 'Download PDF instantly'    },
  { icon: '📋', label: 'My Loan Details', sub: '12 of 24 payments made'   },
]

const pwaFeatures = [
  'Apply for a loan in under 10 minutes',
  'Repay via Airtel Money, MTN MoMo, or Zamtel Kwacha',
  'View full repayment schedule and outstanding balance',
  'Download PDF receipts instantly after payment',
  'Works offline for last-synced data',
]

const testimonials = [
  { quote: 'LENDR significantly expedited our entry into the retail lending market. Their solution not only shortened our time to market but also at a significantly reduced cost.', name: 'Mwamba Chileshe', role: 'CEO, Copperbelt Microfinance',    avatarColor: 'bg-blue-600'   },
  { quote: 'With LENDR we were able to launch in 20% of the time and at a fraction of the cost it would have taken us to build our own stack. The mobile money integration is seamless.', name: 'Thandiwe Banda', role: 'Founder, ZedLend Finance',         avatarColor: 'bg-purple-600' },
  { quote: "The LENDR support team is truly exceptional. The borrower PWA has transformed how our clients interact with us — repayment rates have improved noticeably.", name: 'Patrick Nkonde', role: 'Director, Kafue Cooperative Credit', avatarColor: 'bg-green-600'  },
]

const industries = [
  { icon: '🏦', title: 'MFIs & SACCOs',   href: '/about#mfi',  desc: 'Full lifecycle management for microfinance institutions and savings cooperatives — loan products, member management, and fund tracking.' },
  { icon: '🤝', title: 'Cooperatives',     href: '/about#coop', desc: 'Manage member loans, group repayments, and cooperative fund balances with multi-branch support and branch-level reporting.' },
  { icon: '💼', title: 'SME Lenders',      href: '/about#sme',  desc: 'Tailored digital lending solutions and expert support for SME-focused lenders at a fraction of the cost of enterprise software.' },
]

const steps = [
  { title: 'Create Your Account',     desc: 'Register your organisation in under 5 minutes. Your subdomain is live instantly.' },
  { title: 'Configure Your Products', desc: 'Set up loan types, interest rates, repayment plans, and fee structures.' },
  { title: 'Start Lending',           desc: 'Add staff, onboard borrowers, and disburse funds via mobile money.' },
]

const faqs = reactive([
  { q: 'Is my data secure?',                         a: 'Yes. All data is encrypted in transit (TLS 1.3) and at rest. Each lender has a dedicated database schema. We comply with the Zambia Data Protection Act 2021.',                                                                   open: false },
  { q: 'What mobile money providers are supported?', a: 'LENDR integrates with Airtel Money, MTN MoMo, Zamtel Kwacha, Flutterwave, and PawaPay. Both disbursements and collections are supported.',                                                                                        open: false },
  { q: 'Can I use my own domain?',                   a: 'Yes. Growth plan and above support custom domain mapping (e.g. loans.yourcompany.com). Setup takes less than 24 hours.',                                                                                                          open: false },
  { q: 'How does billing work?',                     a: 'Monthly or annual billing in ZMW or USD. Annual plans save 20%. No commitment — cancel anytime. Your 14-day trial includes all Growth plan features.',                                                                            open: false },
  { q: 'What happens when I exceed my plan limit?',  a: "You'll receive an alert at 90% usage. You can upgrade at any time. We never cut off active loans — we just pause new loan creation until you upgrade.",                                                                           open: false },
  { q: 'Can borrowers repay online?',                a: 'Yes. Borrowers use the LENDR PWA to initiate mobile money payments. A USSD prompt is sent to their phone for confirmation. Receipts are generated instantly.',                                                                    open: false },
  { q: 'Do you support multiple branches?',          a: 'Yes. LENDR includes multi-branch support with scoped visibility — each branch sees only their borrowers and loans, while managers get a consolidated view.',                                                                       open: false },
])
</script>

<style scoped>
.faq-enter-active, .faq-leave-active { transition: all 0.2s ease; overflow: hidden; }
.faq-enter-from, .faq-leave-to { opacity: 0; max-height: 0; }
.faq-enter-to, .faq-leave-from { max-height: 300px; }

.tab-fade-enter-active, .tab-fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.tab-fade-enter-from { opacity: 0; transform: translateY(6px); }
.tab-fade-leave-to   { opacity: 0; transform: translateY(-6px); }

@keyframes marquee {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
.animate-marquee { animation: marquee 28s linear infinite; }
</style>
