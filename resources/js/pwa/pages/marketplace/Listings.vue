<template>
  <PwaLayout title="My Listings" :show-back="true" back-route="pwa.dashboard">
    <div class="px-4 py-5 space-y-4">

      <!-- New listing button -->
      <button
        @click="$inertia.visit(route('pwa.marketplace.create'))"
        class="w-full bg-emerald-600 text-white rounded-xl py-3.5 font-semibold text-sm flex items-center justify-center gap-2 active:bg-emerald-700"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Create New Listing
      </button>

      <!-- Loading -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="bg-white rounded-xl border border-gray-100 p-4 animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
          <div class="h-3 bg-gray-100 rounded w-1/2"></div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="!listings.length" class="bg-white rounded-xl border border-gray-100 p-8 text-center">
        <div class="w-14 h-14 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
          </svg>
        </div>
        <p class="text-sm font-semibold text-gray-700">No listings yet</p>
        <p class="text-xs text-gray-400 mt-1">Create a listing to attract lenders</p>
      </div>

      <!-- Listings -->
      <div v-else class="space-y-3">
        <div
          v-for="listing in listings"
          :key="listing.id"
          class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"
        >
          <!-- Header -->
          <div class="flex items-start justify-between gap-2 mb-3">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-900 truncate">{{ listing.title }}</p>
              <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ listing.purpose }}</p>
            </div>
            <span :class="statusClass(listing.status)" class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0">
              {{ listing.status }}
            </span>
          </div>

          <!-- Stats row -->
          <div class="grid grid-cols-3 gap-2 mb-3">
            <div class="bg-gray-50 rounded-lg p-2 text-center">
              <p class="text-xs text-gray-400">Amount</p>
              <p class="text-xs font-bold text-gray-800 mt-0.5">K {{ formatAmount(listing.amount_requested) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-2 text-center">
              <p class="text-xs text-gray-400">Rate</p>
              <p class="text-xs font-bold text-gray-800 mt-0.5">{{ listing.interest_rate_offered }}%</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-2 text-center">
              <p class="text-xs text-gray-400">Interests</p>
              <p class="text-xs font-bold text-gray-800 mt-0.5">{{ listing.interests_count ?? 0 }}</p>
            </div>
          </div>

          <!-- Interests to review -->
          <div v-if="listing.status === 'active' && listing.interests?.length" class="border-t border-gray-100 pt-3 space-y-2">
            <p class="text-xs font-semibold text-gray-600 mb-2">Offers received</p>
            <div
              v-for="interest in listing.interests"
              :key="interest.id"
              class="flex items-center justify-between gap-2"
            >
              <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-gray-800">K {{ formatAmount(interest.amount_offered) }} @ {{ interest.interest_rate }}%</p>
                <p class="text-xs text-gray-400">{{ interest.message || 'No message' }}</p>
              </div>
              <div class="flex gap-1.5 shrink-0">
                <span
                  v-if="interest.status !== 'pending'"
                  :class="interest.status === 'accepted' ? 'text-emerald-600' : 'text-gray-400'"
                  class="text-xs font-medium capitalize"
                >
                  {{ interest.status }}
                </span>
                <template v-else>
                  <button
                    @click="declineInterest(listing, interest)"
                    :disabled="accepting === interest.id || declining === interest.id"
                    class="text-xs border border-gray-200 text-gray-500 px-2.5 py-1 rounded-lg font-medium active:bg-gray-50 disabled:opacity-50"
                  >
                    {{ declining === interest.id ? '…' : 'Decline' }}
                  </button>
                  <button
                    @click="acceptInterest(listing, interest)"
                    :disabled="accepting === interest.id || declining === interest.id"
                    class="text-xs bg-emerald-600 text-white px-2.5 py-1 rounded-lg font-medium active:bg-emerald-700 disabled:opacity-50"
                  >
                    {{ accepting === interest.id ? '…' : 'Accept' }}
                  </button>
                </template>
              </div>
            </div>
          </div>

          <!-- Withdraw button -->
          <div v-if="['active', 'draft'].includes(listing.status)" class="border-t border-gray-100 pt-3 mt-3">
            <button
              @click="withdrawListing(listing)"
              :disabled="withdrawing === listing.id"
              class="text-xs text-red-500 font-medium disabled:opacity-50"
            >
              {{ withdrawing === listing.id ? 'Withdrawing…' : 'Withdraw listing' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'

const listings   = ref([])
const loading    = ref(false)
const accepting  = ref(null)
const declining  = ref(null)
const withdrawing = ref(null)

async function load() {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/me/marketplace/listings')
    listings.value = res.data.data ?? []
  } catch {
    listings.value = []
  } finally {
    loading.value = false
  }
}

async function acceptInterest(listing, interest) {
  accepting.value = interest.id
  try {
    await axios.post(`/api/v1/me/marketplace/interests/${interest.id}/accept`)
    await load()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to accept interest.')
  } finally {
    accepting.value = null
  }
}

async function declineInterest(listing, interest) {
  declining.value = interest.id
  try {
    await axios.put(`/api/v1/me/marketplace/interests/${interest.id}/decline`)
    await load()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to decline interest.')
  } finally {
    declining.value = null
  }
}

async function withdrawListing(listing) {
  if (! confirm('Withdraw this listing? Lenders will no longer see it.')) return
  withdrawing.value = listing.id
  try {
    await axios.put(`/api/v1/me/marketplace/listings/${listing.id}/withdraw`)
    await load()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to withdraw listing.')
  } finally {
    withdrawing.value = null
  }
}

function statusClass(status) {
  return {
    active:    'bg-emerald-100 text-emerald-700',
    funded:    'bg-blue-100 text-blue-700',
    draft:     'bg-gray-100 text-gray-600',
    expired:   'bg-amber-100 text-amber-700',
    withdrawn: 'bg-gray-100 text-gray-500',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

function formatAmount(val) {
  return Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(load)
</script>
