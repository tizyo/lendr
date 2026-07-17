<template>
  <PwaLayout title="Repo Marketplace" :show-nav="false">

    <!-- ─── Top Nav ─────────────────────────────────────────────────── -->
    <div class="sticky top-0 z-30 bg-white border-b border-neutral-100 shadow-sm">
      <!-- Brand row -->
      <div class="flex items-center justify-between px-4 h-14">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-white">
              <path d="M5.223 2.25c-.497 0-.974.198-1.325.55l-1.3 1.298A3.75 3.75 0 007.5 9.75c.627.47 1.406.75 2.25.75.844 0 1.624-.28 2.25-.75.626.47 1.406.75 2.25.75.844 0 1.623-.28 2.25-.75a3.75 3.75 0 004.902-5.652l-1.3-1.299a1.875 1.875 0 00-1.325-.549H5.223z" />
              <path fill-rule="evenodd" d="M3 20.25v-8.755c1.42.674 3.08.673 4.5 0A5.234 5.234 0 009.75 12c.804 0 1.568-.182 2.25-.506a5.234 5.234 0 002.25.506c.804 0 1.567-.182 2.25-.506 1.42.674 3.08.675 4.5.001v8.755h.75a.75.75 0 010 1.5H2.25a.75.75 0 010-1.5H3zm3-6a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v3a.75.75 0 01-.75.75h-3a.75.75 0 01-.75-.75v-3zm8.25-.75a.75.75 0 00-.75.75v5.25c0 .414.336.75.75.75h3a.75.75 0 00.75-.75v-5.25a.75.75 0 00-.75-.75h-3z" clip-rule="evenodd" />
            </svg>
          </div>
          <div>
            <p class="font-black text-sm text-neutral-900 leading-tight">LENDR Repo</p>
            <p class="text-[10px] text-neutral-400 leading-tight">Repossessed Assets Marketplace</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <!-- Cart icon -->
          <a href="/app/repo/cart" class="relative p-2 rounded-lg hover:bg-neutral-100">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-neutral-600">
              <path d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.358 60.358 0 002.96-7.228.75.75 0 00-.525-.965A60.864 60.864 0 005.68 4.509l-.232-.867A1.875 1.875 0 003.636 2.25H2.25zM3.75 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM16.5 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z" />
            </svg>
          </a>
          <!-- Login -->
          <a href="/app/login" class="text-xs font-semibold text-primary-600 bg-primary-50 px-3 py-1.5 rounded-lg">
            Sign In
          </a>
        </div>
      </div>

      <!-- Tab nav -->
      <div class="flex border-t border-neutral-100">
        <button
          @click="activeTab = 'browse'"
          :class="['flex-1 py-2.5 text-xs font-semibold transition border-b-2', activeTab === 'browse' ? 'border-primary-600 text-primary-600' : 'border-transparent text-neutral-500']"
        >
          <span class="flex items-center justify-center gap-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
              <path d="M11.25 4.533A9.707 9.707 0 006 3a9.735 9.735 0 00-3.25.555.75.75 0 00-.5.707v14.25a.75.75 0 001 .707A8.237 8.237 0 016 18.75c1.995 0 3.823.707 5.25 1.886V4.533zM12.75 20.636A8.214 8.214 0 0118 18.75c.966 0 1.89.166 2.75.47a.75.75 0 001-.708V4.262a.75.75 0 00-.5-.707A9.735 9.735 0 0018 3a9.707 9.707 0 00-5.25 1.533v16.103z" />
            </svg>
            Browse Items
          </span>
        </button>
        <button
          @click="activeTab = 'deals'"
          :class="['flex-1 py-2.5 text-xs font-semibold transition border-b-2 relative', activeTab === 'deals' ? 'border-orange-500 text-orange-500' : 'border-transparent text-neutral-500']"
        >
          <span class="flex items-center justify-center gap-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
              <path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd" />
            </svg>
            Hot Deals
            <span v-if="hotDeals.length" class="ml-0.5 bg-orange-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">{{ hotDeals.length }}</span>
          </span>
        </button>
        <button
          @click="activeTab = 'featured'"
          :class="['flex-1 py-2.5 text-xs font-semibold transition border-b-2', activeTab === 'featured' ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-neutral-500']"
        >
          <span class="flex items-center justify-center gap-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
              <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
            </svg>
            Featured
          </span>
        </button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TAB: Browse Items                                               -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div v-show="activeTab === 'browse'">
      <!-- Search -->
      <div class="px-4 pt-3 pb-2">
        <input
          v-model="search"
          @input="debouncedSearch"
          placeholder="Search items, sellers…"
          class="w-full px-4 py-2.5 rounded-xl border border-neutral-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-neutral-50"
        />
      </div>

      <!-- Category pills -->
      <div class="flex gap-2 px-4 pb-3 overflow-x-auto scrollbar-hide">
        <button
          v-for="cat in categories"
          :key="cat.value"
          @click="setCategory(cat.value)"
          :class="['shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition',
            categoryFilter === cat.value ? 'bg-primary-600 text-white shadow-sm' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200']"
        >{{ cat.label }}</button>
      </div>

      <!-- Featured strip (if any) -->
      <div v-if="featuredItems.length" class="px-4 mb-4">
        <div class="flex items-center gap-2 mb-2">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-yellow-500">
            <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-bold text-neutral-700 uppercase tracking-wide">Featured Picks</span>
        </div>
        <div class="flex gap-3 overflow-x-auto scrollbar-hide pb-1">
          <div
            v-for="item in featuredItems.slice(0, 6)"
            :key="'f' + item.id"
            @click="goToItem(item.id)"
            class="shrink-0 w-36 bg-white rounded-2xl shadow border border-yellow-100 overflow-hidden cursor-pointer active:scale-95 transition"
          >
            <div class="aspect-square bg-neutral-100 relative">
              <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center text-3xl">📦</div>
              <!-- Featured badge -->
              <span class="absolute top-1.5 right-1.5 bg-yellow-400 text-yellow-900 text-[9px] font-bold px-1.5 py-0.5 rounded-full flex items-center gap-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5">
                  <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
                </svg>
                Featured
              </span>
            </div>
            <div class="p-2">
              <p class="text-xs font-semibold truncate text-neutral-800">{{ item.title }}</p>
              <p class="text-primary-600 font-bold text-xs">K {{ formatNum(item.price) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-16">
        <svg class="animate-spin w-8 h-8 text-primary-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
      </div>

      <!-- Empty -->
      <div v-else-if="items.length === 0" class="text-center py-16 text-neutral-400 px-4">
        <p class="text-4xl mb-3">🏪</p>
        <p class="font-medium">No items found</p>
        <p class="text-sm mt-1">Try a different search or category.</p>
      </div>

      <!-- Grid -->
      <div v-else class="px-4 grid grid-cols-2 gap-3 pb-6">
        <div
          v-for="item in items"
          :key="item.id"
          @click="goToItem(item.id)"
          class="bg-white rounded-2xl shadow-sm overflow-hidden cursor-pointer active:scale-95 transition border border-neutral-50"
        >
          <div class="aspect-square bg-neutral-100 relative">
            <img v-if="item.primary_image" :src="item.primary_image" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center text-neutral-300 text-4xl">📦</div>
            <span class="absolute top-2 left-2 bg-black/60 text-white text-[10px] px-2 py-0.5 rounded-full capitalize">{{ item.category }}</span>
          </div>
          <div class="p-3">
            <p class="font-semibold text-sm text-neutral-900 truncate">{{ item.title }}</p>
            <p class="text-primary-600 font-bold text-sm mt-0.5">K {{ formatNum(item.price) }}</p>
            <p v-if="item.original_value" class="text-xs text-neutral-400 line-through">K {{ formatNum(item.original_value) }}</p>
            <div class="flex items-center justify-between mt-1.5">
              <span class="text-xs text-neutral-500 capitalize">{{ item.condition }}</span>
              <span v-if="item.location" class="text-xs text-neutral-400 truncate max-w-20">📍 {{ item.location }}</span>
            </div>
            <div v-if="item.tenant_name" class="flex items-center gap-1 mt-1.5">
              <span class="text-xs text-neutral-400 truncate">{{ item.tenant_name }}</span>
              <svg v-if="item.tenant_badge === 'gold'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" title="Gold Verified Lender" class="w-3.5 h-3.5 text-yellow-500 shrink-0">
                <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.491 4.491 0 01-3.497-1.307 4.491 4.491 0 01-1.307-3.497A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Load more -->
      <div v-if="nextPageUrl && !loading" class="px-4 pb-6">
        <button @click="loadMore" :disabled="loadingMore" class="w-full py-3 rounded-xl bg-neutral-100 text-sm font-medium text-neutral-600">
          {{ loadingMore ? 'Loading…' : 'Load more' }}
        </button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TAB: Hot Deals                                                  -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div v-show="activeTab === 'deals'" class="px-4 pt-4 pb-6">

      <!-- Header -->
      <div class="flex items-center gap-2 mb-4">
        <div class="w-8 h-8 rounded-xl bg-orange-500 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-white">
            <path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd" />
          </svg>
        </div>
        <div>
          <h2 class="font-black text-base text-neutral-900">Hot Deals</h2>
          <p class="text-xs text-neutral-500">Exclusive loan offers from verified lenders</p>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loadingDeals" class="flex justify-center py-12">
        <svg class="animate-spin w-6 h-6 text-orange-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
      </div>

      <!-- Empty -->
      <div v-else-if="hotDeals.length === 0" class="text-center py-16 text-neutral-400">
        <p class="text-4xl mb-3">🔥</p>
        <p class="font-medium">No Hot Deals right now</p>
        <p class="text-sm mt-1">Check back soon for exclusive loan offers.</p>
      </div>

      <!-- Deals list -->
      <div v-else class="space-y-4">
        <div
          v-for="deal in hotDeals"
          :key="deal.id"
          class="bg-white rounded-2xl shadow-sm border border-neutral-100 overflow-hidden"
        >
          <!-- Deal image / header -->
          <div v-if="deal.image_url" class="h-36 relative">
            <img :src="deal.image_url" class="w-full h-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
            <div class="absolute bottom-3 left-3 right-3">
              <p class="text-white font-black text-base leading-tight">{{ deal.title }}</p>
            </div>
            <span v-if="deal.badge_label" class="absolute top-3 right-3 bg-orange-500 text-white text-[10px] font-bold px-2 py-1 rounded-full">
              🔥 {{ deal.badge_label }}
            </span>
          </div>
          <div v-else class="bg-gradient-to-br from-orange-500 to-red-600 px-4 py-4">
            <div class="flex items-start justify-between">
              <p class="text-white font-black text-base leading-snug flex-1">{{ deal.title }}</p>
              <span v-if="deal.badge_label" class="ml-2 shrink-0 bg-white/20 text-white text-[10px] font-bold px-2 py-1 rounded-full">
                🔥 {{ deal.badge_label }}
              </span>
            </div>
          </div>

          <div class="p-4">
            <!-- Lender -->
            <div class="flex items-center gap-1.5 mb-3">
              <div class="w-5 h-5 rounded-full bg-primary-100 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-primary-600">
                  <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4z" clip-rule="evenodd" />
                </svg>
              </div>
              <span class="text-xs font-semibold text-neutral-700">{{ deal.tenant_name }}</span>
            </div>

            <!-- Key deal stats -->
            <div class="grid grid-cols-3 gap-2 mb-3">
              <div v-if="deal.interest_rate" class="bg-green-50 rounded-xl p-2 text-center">
                <p class="text-xs text-green-700 font-bold">{{ deal.interest_rate }}%</p>
                <p class="text-[10px] text-green-600">Monthly Rate</p>
              </div>
              <div v-if="deal.max_amount" class="bg-blue-50 rounded-xl p-2 text-center">
                <p class="text-xs text-blue-700 font-bold">K{{ formatNum(deal.max_amount) }}</p>
                <p class="text-[10px] text-blue-600">Up to</p>
              </div>
              <div v-if="deal.tenure" class="bg-purple-50 rounded-xl p-2 text-center">
                <p class="text-xs text-purple-700 font-bold">{{ deal.tenure }}</p>
                <p class="text-[10px] text-purple-600">Tenure</p>
              </div>
            </div>

            <!-- Description -->
            <p v-if="deal.description" class="text-xs text-neutral-600 mb-3 leading-relaxed">{{ deal.description }}</p>

            <!-- Loan product -->
            <div v-if="deal.loan_product" class="flex items-center gap-1.5 mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-neutral-400">
                <path d="M12 7.5a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
                <path fill-rule="evenodd" d="M1.5 4.875C1.5 3.839 2.34 3 3.375 3h17.25c1.035 0 1.875.84 1.875 1.875v9.75c0 1.036-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 14.625v-9.75zM8.25 9.75a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0zM18.75 9a.75.75 0 00-.75.75v.008c0 .414.336.75.75.75h.008a.75.75 0 00.75-.75V9.75a.75.75 0 00-.75-.75h-.008zM4.5 9.75A.75.75 0 015.25 9h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75H5.25a.75.75 0 01-.75-.75V9.75z" clip-rule="evenodd" />
                <path d="M2.25 18a.75.75 0 000 1.5c5.4 0 10.63.722 15.6 2.075 1.19.324 2.4-.558 2.4-1.82V18.75a.75.75 0 00-.75-.75H2.25z" />
              </svg>
              <span class="text-xs text-neutral-500">Product: <span class="font-semibold text-neutral-700">{{ deal.loan_product }}</span></span>
            </div>

            <!-- Expiry -->
            <div v-if="deal.expires_at" class="flex items-center gap-1 mb-3 text-xs text-amber-600 bg-amber-50 px-2 py-1.5 rounded-lg">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z" clip-rule="evenodd" />
              </svg>
              Offer expires: {{ deal.expires_at }}
            </div>

            <!-- CTA button -->
            <button
              @click="openEnquire(deal)"
              class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded-xl text-sm transition active:scale-95"
            >
              Apply / Get More Info
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TAB: Featured Items (full list)                                 -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div v-show="activeTab === 'featured'" class="px-4 pt-4 pb-6">
      <div class="flex items-center gap-2 mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-yellow-500">
          <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
        </svg>
        <div>
          <h2 class="font-black text-base text-neutral-900">Featured Items</h2>
          <p class="text-xs text-neutral-500">Handpicked and promoted listings</p>
        </div>
      </div>

      <div v-if="loadingFeatured" class="flex justify-center py-12">
        <svg class="animate-spin w-6 h-6 text-yellow-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
      </div>

      <div v-else-if="featuredItems.length === 0" class="text-center py-16 text-neutral-400">
        <p class="text-4xl mb-3">⭐</p>
        <p class="font-medium">No featured items right now</p>
      </div>

      <div v-else class="grid grid-cols-2 gap-3">
        <div
          v-for="item in featuredItems"
          :key="'ff' + item.id"
          @click="goToItem(item.id)"
          class="bg-white rounded-2xl shadow-sm border border-yellow-100 overflow-hidden cursor-pointer active:scale-95 transition"
        >
          <div class="aspect-square bg-neutral-100 relative">
            <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center text-3xl">📦</div>
            <span class="absolute top-2 right-2 bg-yellow-400 text-yellow-900 text-[9px] font-bold px-1.5 py-0.5 rounded-full">
              ⭐ {{ item.featured_type === 'manual' ? 'Editor Pick' : 'Promoted' }}
            </span>
          </div>
          <div class="p-3">
            <p class="font-semibold text-sm text-neutral-900 truncate">{{ item.title }}</p>
            <p class="text-primary-600 font-bold text-sm">K {{ formatNum(item.price) }}</p>
            <div class="flex items-center gap-1 mt-1.5">
              <span class="text-xs text-neutral-400 truncate">{{ item.tenant_name }}</span>
              <svg v-if="item.tenant_badge === 'gold'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-yellow-500 shrink-0">
                <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.491 4.491 0 01-3.497-1.307 4.491 4.491 0 01-1.307-3.497A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
              </svg>
            </div>
            <p v-if="item.days_remaining >= 0" class="text-[10px] text-amber-600 mt-1">⏳ {{ item.days_remaining }}d remaining</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── Hot Deal Enquiry Modal ───────────────────────────────────── -->
    <Transition name="fade">
      <div v-if="enquireModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-4 pb-0">
        <div class="bg-white w-full max-w-lg rounded-t-3xl p-6 pb-8 shadow-2xl">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-black text-base text-neutral-900">Apply for Deal</h3>
            <button @click="enquireModal = false" class="text-neutral-400 hover:text-neutral-600 p-1">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 11-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
          <p class="text-xs text-neutral-500 mb-4">Enquiring about: <span class="font-semibold text-neutral-700">{{ enquireDeal?.title }}</span> from <span class="font-semibold">{{ enquireDeal?.tenant_name }}</span></p>

          <div class="space-y-3">
            <input v-model="enquireForm.full_name" placeholder="Your full name *" class="w-full px-4 py-2.5 rounded-xl border border-neutral-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
            <input v-model="enquireForm.phone" placeholder="Phone number *" class="w-full px-4 py-2.5 rounded-xl border border-neutral-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
            <input v-model="enquireForm.email" placeholder="Email (optional)" class="w-full px-4 py-2.5 rounded-xl border border-neutral-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
            <textarea v-model="enquireForm.message" placeholder="Message (optional)" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-neutral-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none" />
          </div>

          <p v-if="enquireError" class="text-xs text-red-500 mt-2">{{ enquireError }}</p>
          <p v-if="enquireSuccess" class="text-xs text-green-600 mt-2 font-medium">{{ enquireSuccess }}</p>

          <button
            @click="submitEnquiry"
            :disabled="enquiring"
            class="mt-4 w-full bg-orange-500 disabled:opacity-60 text-white font-bold py-3 rounded-xl text-sm transition"
          >
            {{ enquiring ? 'Sending…' : 'Submit Enquiry' }}
          </button>
        </div>
      </div>
    </Transition>

  </PwaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import axios from 'axios'

// ─── State ────────────────────────────────────────────────────────────
const activeTab       = ref('browse')
const items           = ref([])
const loading         = ref(true)
const loadingMore     = ref(false)
const nextPageUrl     = ref(null)
const search          = ref('')
const categoryFilter  = ref('')

const featuredItems   = ref([])
const loadingFeatured = ref(true)

const hotDeals        = ref([])
const loadingDeals    = ref(true)

// Enquiry modal
const enquireModal    = ref(false)
const enquireDeal     = ref(null)
const enquireForm     = ref({ full_name: '', phone: '', email: '', message: '' })
const enquiring       = ref(false)
const enquireError    = ref('')
const enquireSuccess  = ref('')

// ─── Categories ───────────────────────────────────────────────────────
const categories = [
  { value: '', label: 'All' },
  { value: 'electronics', label: 'Electronics' },
  { value: 'furniture', label: 'Furniture' },
  { value: 'vehicle', label: 'Vehicle' },
  { value: 'land', label: 'Land' },
  { value: 'equipment', label: 'Equipment' },
  { value: 'other', label: 'Other' },
]

// ─── Browse ───────────────────────────────────────────────────────────
async function fetchItems(append = false) {
  if (!append) loading.value = true
  try {
    const { data } = await axios.get('/api/v1/public/items', {
      params: {
        q:        search.value || undefined,
        category: categoryFilter.value || undefined,
        page:     append && nextPageUrl.value
          ? new URL(nextPageUrl.value).searchParams.get('page')
          : 1,
      },
    })
    if (append) items.value.push(...(data.data ?? []))
    else        items.value = data.data ?? []

    nextPageUrl.value = data.meta?.current_page < data.meta?.last_page
      ? `/api/v1/public/items?page=${data.meta.current_page + 1}`
      : null
  } finally {
    loading.value    = false
    loadingMore.value = false
  }
}

async function loadMore() {
  loadingMore.value = true
  await fetchItems(true)
}

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchItems(), 400)
}

function setCategory(cat) {
  categoryFilter.value = cat
  fetchItems()
}

// ─── Featured Items ───────────────────────────────────────────────────
async function fetchFeatured() {
  loadingFeatured.value = true
  try {
    const { data } = await axios.get('/api/v1/public/featured-items')
    featuredItems.value = data.data ?? []
  } catch {
    featuredItems.value = []
  } finally {
    loadingFeatured.value = false
  }
}

// ─── Hot Deals ────────────────────────────────────────────────────────
async function fetchHotDeals() {
  loadingDeals.value = true
  try {
    const { data } = await axios.get('/api/v1/public/hot-deals')
    hotDeals.value = data.data ?? []
  } catch {
    hotDeals.value = []
  } finally {
    loadingDeals.value = false
  }
}

// ─── Enquiry ──────────────────────────────────────────────────────────
function openEnquire(deal) {
  enquireDeal.value    = deal
  enquireForm.value    = { full_name: '', phone: '', email: '', message: '' }
  enquireError.value   = ''
  enquireSuccess.value = ''
  enquireModal.value   = true
}

async function submitEnquiry() {
  enquireError.value   = ''
  enquireSuccess.value = ''
  if (!enquireForm.value.full_name || !enquireForm.value.phone) {
    enquireError.value = 'Full name and phone are required.'
    return
  }
  enquiring.value = true
  try {
    const { data } = await axios.post(`/api/v1/public/hot-deals/${enquireDeal.value.id}/enquire`, enquireForm.value)
    enquireSuccess.value = data.message ?? 'Enquiry submitted!'
    setTimeout(() => { enquireModal.value = false }, 2500)
  } catch (e) {
    enquireError.value = e?.response?.data?.message ?? 'Failed to submit enquiry.'
  } finally {
    enquiring.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────
function goToItem(id) {
  router.visit(`/app/repo/${id}`)
}

function formatNum(n) {
  return Number(n).toLocaleString()
}

// ─── Init ─────────────────────────────────────────────────────────────
onMounted(() => {
  fetchItems()
  fetchFeatured()
  fetchHotDeals()
})
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
