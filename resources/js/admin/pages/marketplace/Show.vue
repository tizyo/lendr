<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <button @click="$inertia.visit(route('marketplace.index'))" class="p-2 hover:bg-neutral-100 rounded-lg transition-colors">
          <svg class="w-5 h-5 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Listing Detail</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ listing?.title ?? '…' }}</p>
        </div>
      </div>
    </template>

    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-4">
        <div v-for="i in 3" :key="i" class="lendr-card p-6 animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-1/3 mb-4"></div>
          <div class="space-y-2">
            <div class="h-3 bg-gray-100 rounded w-full"></div>
            <div class="h-3 bg-gray-100 rounded w-4/5"></div>
          </div>
        </div>
      </div>
      <div class="lendr-card p-6 animate-pulse h-48"></div>
    </div>

    <div v-else-if="listing" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- ── Left: Listing detail ──────────────────────────────────── -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Summary card -->
        <div class="lendr-card p-6">
          <div class="flex items-start justify-between gap-4 mb-4">
            <div>
              <h2 class="text-lg font-semibold text-neutral-900">{{ listing.title }}</h2>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="purposeBadge(listing.purpose)">
                  {{ listing.purpose }}
                </span>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="statusBadge(listing.status)">
                  {{ listing.status }}
                </span>
              </div>
            </div>
            <div class="text-right shrink-0">
              <p class="text-2xl font-bold text-neutral-900">ZMW {{ fmt(listing.amount_requested) }}</p>
              <p class="text-xs text-neutral-400">{{ listing.tenure_months }} months</p>
            </div>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed">{{ listing.description }}</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-neutral-100">
            <div>
              <p class="text-xs text-neutral-400">Interest offered</p>
              <p class="text-sm font-semibold text-neutral-700">{{ listing.interest_rate_offered ? listing.interest_rate_offered + '%' : '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-400">Published</p>
              <p class="text-sm font-semibold text-neutral-700">{{ fmtDate(listing.published_at) }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-400">Expires</p>
              <p class="text-sm font-semibold text-neutral-700">{{ fmtDate(listing.expires_at) }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-400">Interests received</p>
              <p class="text-sm font-semibold text-neutral-700">{{ listing.interests_count ?? 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-400">Views</p>
              <p class="text-sm font-semibold text-neutral-700">{{ listing.views_count ?? 0 }}</p>
            </div>
          </div>
        </div>

        <!-- Interests from lenders -->
        <div class="lendr-card p-6">
          <h3 class="text-sm font-semibold text-neutral-700 mb-4">Lender Interests ({{ interests.length }})</h3>
          <div v-if="!interests.length" class="text-sm text-neutral-400 text-center py-6">No interests yet.</div>
          <div v-else class="space-y-3">
            <div v-for="interest in interests" :key="interest.id"
                 class="flex items-center justify-between p-3 rounded-lg bg-neutral-50 border border-neutral-100">
              <div>
                <p class="text-sm font-medium text-neutral-800">{{ interest.user ?? 'Lender' }}</p>
                <p class="text-xs text-neutral-400 mt-0.5">{{ interest.message ?? '—' }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-semibold text-neutral-900">
                  {{ interest.amount_offered ? 'ZMW ' + fmt(interest.amount_offered) : '—' }}
                </p>
                <span class="text-xs px-1.5 py-0.5 rounded font-medium" :class="interestStatusClass(interest.status)">
                  {{ interest.status }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Reviews -->
        <div class="lendr-card p-6">
          <h3 class="text-sm font-semibold text-neutral-700 mb-4">
            Reviews
            <span v-if="reviews.length" class="text-neutral-400 font-normal">(avg {{ avgRating }}★)</span>
          </h3>
          <div v-if="!reviews.length" class="text-sm text-neutral-400 text-center py-6">No reviews yet.</div>
          <div v-else class="space-y-3">
            <div v-for="r in reviews" :key="r.id" class="p-3 bg-neutral-50 rounded-lg border border-neutral-100">
              <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-medium text-neutral-800">{{ r.reviewer }}</p>
                <span class="text-yellow-500 text-sm">{{ '★'.repeat(r.rating) }}{{ '☆'.repeat(5 - r.rating) }}</span>
              </div>
              <p class="text-xs text-neutral-600">{{ r.comment }}</p>
              <p class="text-xs text-neutral-400 mt-1">{{ fmtDate(r.created_at) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Right: Borrower credit profile ──────────────────────────── -->
      <div class="space-y-4">

        <!-- Credit profile card -->
        <div class="lendr-card p-6">
          <h3 class="text-sm font-semibold text-neutral-700 mb-4">Borrower Credit Profile</h3>

          <!-- Score ring -->
          <div class="flex flex-col items-center mb-5">
            <div class="relative w-28 h-28">
              <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                <circle cx="50" cy="50" r="42" fill="none" stroke="#f3f4f6" stroke-width="10"/>
                <circle cx="50" cy="50" r="42" fill="none"
                        :stroke="scoreColor" stroke-width="10" stroke-linecap="round"
                        :stroke-dasharray="`${scoreDash} 264`" class="transition-all duration-700"/>
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-2xl font-bold text-neutral-900">{{ listing.borrower?.credit_score ?? '—' }}</span>
                <span class="text-xs text-neutral-400">/ 850</span>
              </div>
            </div>
            <span class="mt-2 text-xs font-semibold px-3 py-1 rounded-full" :class="bandBadge">
              {{ scoreBand }}
            </span>
          </div>

          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-neutral-500">Name</span>
              <span class="font-medium text-neutral-800">{{ listing.borrower?.name }}</span>
            </div>
          </div>
        </div>

        <!-- Express Interest CTA -->
        <button
          v-if="listing.status === 'active'"
          @click="openInterestModal = true"
          class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl transition-colors text-sm"
        >
          Express Interest
        </button>

        <!-- Withdraw (for admin) -->
        <button
          v-if="['active','draft'].includes(listing.status)"
          @click="confirmWithdraw"
          class="w-full border border-red-200 text-red-600 hover:bg-red-50 font-medium py-2.5 rounded-xl transition-colors text-sm"
        >
          Withdraw Listing
        </button>
      </div>
    </div>

    <!-- Express Interest Modal -->
    <div v-if="openInterestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-4">Express Interest</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Offer Amount (ZMW)</label>
            <input v-model="interestForm.amount_offered" type="number" min="1" class="input w-full"
                   :placeholder="`Up to ${fmt(listing?.amount_requested)}`"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Interest Rate (%)</label>
            <input v-model="interestForm.interest_rate" type="number" step="0.1" min="0" max="100" class="input w-full"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Message (optional)</label>
            <textarea v-model="interestForm.message" rows="3" class="input w-full resize-none" maxlength="500"></textarea>
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button @click="openInterestModal = false" class="flex-1 border border-neutral-200 text-neutral-700 py-2.5 rounded-xl text-sm font-medium hover:bg-neutral-50">
            Cancel
          </button>
          <button @click="submitInterest" :disabled="submitting" class="flex-1 bg-emerald-600 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-700 disabled:opacity-50">
            {{ submitting ? 'Sending…' : 'Submit Interest' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/admin/layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ id: Number })

const listing          = ref(null)
const interests        = ref([])
const reviews          = ref([])
const loading          = ref(true)
const openInterestModal = ref(false)
const submitting       = ref(false)
const interestForm     = ref({ amount_offered: '', interest_rate: '', message: '' })

onMounted(async () => {
  try {
    const [listingRes, reviewRes] = await Promise.all([
      axios.get(`/api/v1/marketplace/listings/${props.id}`),
      axios.get(`/api/v1/marketplace/reviews/${props.id}`),
    ])
    listing.value   = listingRes.data.data
    interests.value = listing.value.interests ?? []
    reviews.value   = reviewRes.data.data?.reviews ?? []
  } catch {
    // listing not found — handled by empty state
  } finally {
    loading.value = false
  }
})

const fmt     = (n) => Number(n).toLocaleString('en-ZM', { minimumFractionDigits: 2 })
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '—'

const scoreVal = computed(() => listing.value?.borrower?.credit_score ?? 300)
const scoreDash = computed(() => Math.round(((scoreVal.value - 300) / 550) * 264))
const scoreColor = computed(() => {
  if (scoreVal.value >= 750) return '#10b981'
  if (scoreVal.value >= 650) return '#3b82f6'
  if (scoreVal.value >= 550) return '#f59e0b'
  return '#ef4444'
})
const scoreBand = computed(() => {
  if (scoreVal.value >= 750) return 'Excellent'
  if (scoreVal.value >= 650) return 'Good'
  if (scoreVal.value >= 550) return 'Fair'
  return 'Poor'
})
const bandBadge = computed(() => {
  if (scoreVal.value >= 750) return 'bg-emerald-100 text-emerald-700'
  if (scoreVal.value >= 650) return 'bg-blue-100 text-blue-700'
  if (scoreVal.value >= 550) return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-700'
})
const avgRating = computed(() => {
  if (!reviews.value.length) return 0
  return (reviews.value.reduce((s, r) => s + r.rating, 0) / reviews.value.length).toFixed(1)
})

const statusBadge = (s) => ({
  active: 'bg-emerald-100 text-emerald-700',
  draft: 'bg-neutral-100 text-neutral-600',
  funded: 'bg-blue-100 text-blue-700',
  expired: 'bg-red-100 text-red-700',
  withdrawn: 'bg-gray-100 text-gray-500',
}[s] ?? 'bg-neutral-100 text-neutral-600')

const purposeBadge = (p) => 'bg-indigo-100 text-indigo-700'

const interestStatusClass = (s) => ({
  pending: 'bg-amber-100 text-amber-700',
  accepted: 'bg-emerald-100 text-emerald-700',
  declined: 'bg-red-100 text-red-700',
  withdrawn: 'bg-gray-100 text-gray-500',
}[s] ?? 'bg-neutral-100 text-neutral-600')

async function submitInterest() {
  submitting.value = true
  try {
    await axios.post(`/api/v1/marketplace/listings/${props.id}/express-interest`, interestForm.value)
    openInterestModal.value = false
    interests.value.push({ ...interestForm.value, status: 'pending', user: 'You' })
    interestForm.value = { amount_offered: '', interest_rate: '', message: '' }
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to submit interest.')
  } finally {
    submitting.value = false
  }
}

async function confirmWithdraw() {
  if (!confirm('Withdraw this listing? This cannot be undone.')) return
  try {
    await axios.put(`/api/v1/marketplace/listings/${props.id}/withdraw`)
    listing.value.status = 'withdrawn'
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to withdraw listing.')
  }
}
</script>
