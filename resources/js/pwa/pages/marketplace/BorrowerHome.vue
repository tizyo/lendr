<template>
  <PwaLayout title="My Marketplace" :show-back="true" back-route="pwa.dashboard">
    <div class="px-4 py-5 space-y-5">

      <!-- Post new listing CTA -->
      <button
        @click="$inertia.visit(route('pwa.marketplace.create'))"
        class="w-full bg-emerald-600 text-white rounded-xl py-3.5 font-semibold text-sm flex items-center justify-center gap-2 active:bg-emerald-700"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Post a New Listing
      </button>

      <!-- ── Active listings ─────────────────────────────────────────── -->
      <section>
        <h2 class="text-sm font-semibold text-gray-700 mb-3">My Listings</h2>

        <div v-if="loadingListings" class="space-y-3">
          <div v-for="i in 2" :key="i" class="bg-white rounded-xl border border-gray-100 p-4 animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-2/3 mb-2"></div>
            <div class="h-3 bg-gray-100 rounded w-1/3"></div>
          </div>
        </div>

        <div v-else-if="!listings.length"
             class="bg-white rounded-xl border border-gray-100 p-6 text-center text-sm text-gray-400">
          No listings yet. Post one to attract lenders.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="listing in listings"
            :key="listing.id"
            class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"
          >
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ listing.title }}</p>
                <p class="text-xs text-gray-400 capitalize">{{ listing.purpose }}</p>
              </div>
              <span :class="statusClass(listing.status)"
                    class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0">
                {{ listing.status }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
              <span>ZMW {{ fmt(listing.amount_requested) }}</span>
              <span>{{ listing.tenure_months }} months</span>
              <span>{{ listing.interests_count ?? 0 }} interest{{ listing.interests_count !== 1 ? 's' : '' }}</span>
            </div>

            <!-- Withdraw button -->
            <button
              v-if="['active', 'draft'].includes(listing.status)"
              @click="withdrawListing(listing)"
              class="w-full border border-red-200 text-red-500 rounded-lg py-2 text-xs font-medium hover:bg-red-50 transition-colors"
            >
              Withdraw Listing
            </button>
          </div>
        </div>
      </section>

      <!-- ── Received interests ──────────────────────────────────────── -->
      <section>
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Received Offers</h2>

        <div v-if="loadingInterests" class="space-y-3">
          <div v-for="i in 2" :key="i" class="bg-white rounded-xl border border-gray-100 p-4 animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-gray-100 rounded w-3/4"></div>
          </div>
        </div>

        <div v-else-if="!pendingInterests.length"
             class="bg-white rounded-xl border border-gray-100 p-6 text-center text-sm text-gray-400">
          No pending offers yet.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="interest in pendingInterests"
            :key="interest.id"
            class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"
          >
            <!-- Lender info -->
            <div class="flex items-center gap-3 mb-3">
              <div class="w-9 h-9 bg-emerald-100 rounded-full flex items-center justify-center shrink-0">
                <span class="text-sm font-bold text-emerald-600">
                  {{ (interest.user ?? 'L').charAt(0).toUpperCase() }}
                </span>
              </div>
              <div>
                <p class="text-sm font-semibold text-gray-900">{{ interest.user ?? 'Lender' }}</p>
                <p class="text-xs text-gray-400">For: {{ interest.listing_title ?? 'your listing' }}</p>
              </div>
              <span :class="interestStatusClass(interest.status)"
                    class="ml-auto text-xs px-2 py-0.5 rounded-full font-medium">
                {{ interest.status }}
              </span>
            </div>

            <!-- Offer details -->
            <div class="grid grid-cols-2 gap-3 mb-3">
              <div class="bg-gray-50 rounded-lg p-2.5">
                <p class="text-xs text-gray-400">Offered Amount</p>
                <p class="text-sm font-bold text-gray-900 mt-0.5">
                  {{ interest.amount_offered ? 'ZMW ' + fmt(interest.amount_offered) : '—' }}
                </p>
              </div>
              <div class="bg-gray-50 rounded-lg p-2.5">
                <p class="text-xs text-gray-400">Interest Rate</p>
                <p class="text-sm font-bold text-gray-900 mt-0.5">
                  {{ interest.interest_rate ? interest.interest_rate + '%' : '—' }}
                </p>
              </div>
            </div>

            <p v-if="interest.message" class="text-xs text-gray-500 italic mb-3">
              "{{ interest.message }}"
            </p>

            <!-- Accept / Decline (only for pending) -->
            <div v-if="interest.status === 'pending'" class="flex gap-2">
              <button
                @click="respondToInterest(interest, 'decline')"
                :disabled="responding === interest.id"
                class="flex-1 border border-gray-200 text-gray-600 rounded-lg py-2.5 text-xs font-medium hover:bg-gray-50 disabled:opacity-50 transition-colors"
              >
                Decline
              </button>
              <button
                @click="respondToInterest(interest, 'accept')"
                :disabled="responding === interest.id"
                class="flex-1 bg-emerald-600 text-white rounded-lg py-2.5 text-xs font-semibold hover:bg-emerald-700 disabled:opacity-50 transition-colors"
              >
                {{ responding === interest.id ? '…' : 'Accept Offer' }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Past / declined ────────────────────────────────────────── -->
      <section v-if="resolvedInterests.length">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Past Offers</h2>
        <div class="space-y-2">
          <div
            v-for="interest in resolvedInterests"
            :key="interest.id"
            class="bg-white rounded-xl border border-gray-100 p-3 flex items-center justify-between"
          >
            <div>
              <p class="text-sm font-medium text-gray-800">{{ interest.user ?? 'Lender' }}</p>
              <p class="text-xs text-gray-400">
                {{ interest.amount_offered ? 'ZMW ' + fmt(interest.amount_offered) : '—' }}
              </p>
            </div>
            <span :class="interestStatusClass(interest.status)"
                  class="text-xs px-2 py-0.5 rounded-full font-medium">
              {{ interest.status }}
            </span>
          </div>
        </div>
      </section>

    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import PwaLayout from '@/pwa/layouts/PwaLayout.vue'
import axios from 'axios'

const listings         = ref([])
const allInterests     = ref([])
const loadingListings  = ref(true)
const loadingInterests = ref(true)
const responding       = ref(null)

onMounted(async () => {
  await Promise.all([fetchListings(), fetchInterests()])
})

async function fetchListings() {
  try {
    const res = await axios.get('/api/v1/borrower/marketplace/listings')
    listings.value = res.data.data ?? []
  } catch {
    listings.value = []
  } finally {
    loadingListings.value = false
  }
}

async function fetchInterests() {
  try {
    // Aggregate interests across all listings
    const all = []
    for (const listing of listings.value) {
      const res = await axios.get(`/api/v1/marketplace/listings/${listing.id}`)
      const detail = res.data.data
      ;(detail.interests ?? []).forEach(i => {
        all.push({ ...i, listing_title: listing.title })
      })
    }
    allInterests.value = all
  } catch {
    allInterests.value = []
  } finally {
    loadingInterests.value = false
  }
}

const pendingInterests  = computed(() => allInterests.value.filter(i => i.status === 'pending'))
const resolvedInterests = computed(() => allInterests.value.filter(i => i.status !== 'pending'))

async function respondToInterest(interest, action) {
  responding.value = interest.id
  try {
    if (action === 'accept') {
      await axios.post(`/api/v1/borrower/marketplace/interests/${interest.id}/accept`)
      interest.status = 'accepted'
      // Close all other pending interests for same listing
      allInterests.value
        .filter(i => i.listing_id === interest.listing_id && i.id !== interest.id && i.status === 'pending')
        .forEach(i => { i.status = 'withdrawn' })
    } else {
      await axios.put(`/api/v1/marketplace/interests/${interest.id}`, { status: 'declined' })
      interest.status = 'declined'
    }
  } catch (e) {
    alert(e.response?.data?.message ?? `Failed to ${action} offer.`)
  } finally {
    responding.value = null
  }
}

async function withdrawListing(listing) {
  if (!confirm('Withdraw this listing?')) return
  try {
    await axios.put(`/api/v1/borrower/marketplace/listings/${listing.id}/withdraw`)
    listing.status = 'withdrawn'
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to withdraw listing.')
  }
}

const fmt = (n) => Number(n ?? 0).toLocaleString('en-ZM', { minimumFractionDigits: 2 })

const statusClass = (s) => ({
  active:    'bg-emerald-100 text-emerald-700',
  draft:     'bg-gray-100 text-gray-600',
  funded:    'bg-blue-100 text-blue-700',
  expired:   'bg-red-100 text-red-600',
  withdrawn: 'bg-gray-100 text-gray-400',
}[s] ?? 'bg-gray-100 text-gray-500')

const interestStatusClass = (s) => ({
  pending:   'bg-amber-100 text-amber-700',
  accepted:  'bg-emerald-100 text-emerald-700',
  declined:  'bg-red-100 text-red-600',
  withdrawn: 'bg-gray-100 text-gray-400',
}[s] ?? 'bg-gray-100 text-gray-500')
</script>
