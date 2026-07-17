<template>
  <PwaLayout title="My Credit Score" :show-back="true" back-route="pwa.dashboard">
    <div class="px-4 py-5 space-y-5">

      <!-- Loading skeleton -->
      <div v-if="loading">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-pulse flex flex-col items-center gap-4">
          <div class="w-36 h-36 bg-gray-200 rounded-full"></div>
          <div class="h-4 bg-gray-200 rounded w-24"></div>
          <div class="h-3 bg-gray-100 rounded w-40"></div>
        </div>
      </div>

      <!-- Score card -->
      <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">

        <!-- Score ring -->
        <div class="flex flex-col items-center mb-6">
          <div class="relative w-40 h-40">
            <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
              <!-- Track -->
              <circle cx="50" cy="50" r="42" fill="none" stroke="#f3f4f6" stroke-width="9"/>
              <!-- Score arc -->
              <circle
                cx="50" cy="50" r="42"
                fill="none"
                :stroke="scoreColor"
                stroke-width="9"
                stroke-linecap="round"
                :stroke-dasharray="`${scoreDash} 264`"
                class="transition-all duration-1000 ease-out"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-3xl font-bold text-gray-900">{{ score.score }}</span>
              <span class="text-xs text-gray-400 mt-0.5">out of 850</span>
            </div>
          </div>

          <!-- Band badge -->
          <span class="mt-3 px-4 py-1.5 rounded-full text-sm font-semibold" :class="bandBadge">
            {{ bandLabel }}
          </span>

          <!-- Last updated -->
          <p class="text-xs text-gray-400 mt-2">
            Updated {{ lastUpdated }}
          </p>
        </div>

        <!-- Score range bar -->
        <div class="mb-6">
          <div class="flex justify-between text-xs text-gray-400 mb-1">
            <span>300 Poor</span>
            <span>550 Fair</span>
            <span>650 Good</span>
            <span>850 Excellent</span>
          </div>
          <div class="relative h-2.5 bg-gradient-to-r from-red-400 via-amber-400 via-blue-400 to-emerald-500 rounded-full">
            <!-- Indicator -->
            <div
              class="absolute top-1/2 -translate-y-1/2 w-4 h-4 bg-white border-2 rounded-full shadow-md transition-all duration-700"
              :class="`border-[${scoreColor}]`"
              :style="{ left: `calc(${scorePercent}% - 8px)`, borderColor: scoreColor }"
            ></div>
          </div>
        </div>

        <!-- Summary stats -->
        <div class="grid grid-cols-3 gap-3 mb-2">
          <div class="bg-gray-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-gray-900">{{ score.total_loans }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Loans</p>
          </div>
          <div class="bg-emerald-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-emerald-700">{{ score.total_completed }}</p>
            <p class="text-xs text-emerald-500 mt-0.5">Completed</p>
          </div>
          <div class="bg-red-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-red-600">{{ score.total_defaulted }}</p>
            <p class="text-xs text-red-400 mt-0.5">Defaults</p>
          </div>
        </div>
      </div>

      <!-- Factor breakdown -->
      <div v-if="!loading" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Score Factors</h2>
        <div class="space-y-4">

          <div v-for="factor in factors" :key="factor.key" class="space-y-1.5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-sm text-gray-700">{{ factor.label }}</span>
                <span class="text-xs text-gray-400">({{ factor.weight }})</span>
              </div>
              <span class="text-sm font-semibold" :class="factorColor(factor.value)">
                {{ factor.value }}/100
              </span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="factorBarColor(factor.value)"
                :style="{ width: factor.value + '%' }"
              ></div>
            </div>
          </div>

        </div>
      </div>

      <!-- Improvement tips -->
      <div v-if="!loading" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">How to Improve Your Score</h2>
        <div class="space-y-3">
          <div v-for="tip in tips" :key="tip.title" class="flex gap-3">
            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="tip.iconBg">
              <svg class="w-4 h-4" :class="tip.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tip.icon"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-800">{{ tip.title }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ tip.detail }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Marketplace CTA -->
      <div v-if="!loading && score.score >= 550"
           class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5 flex items-center gap-4">
        <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-sm font-semibold text-emerald-800">You qualify for the Marketplace</p>
          <p class="text-xs text-emerald-600 mt-0.5">Post a listing to attract lenders across the network.</p>
        </div>
        <button
          @click="$inertia.visit(route('pwa.marketplace.home'))"
          class="bg-emerald-600 text-white text-xs font-semibold px-4 py-2 rounded-lg shrink-0 hover:bg-emerald-700 transition-colors"
        >
          Go
        </button>
      </div>

    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import PwaLayout from '@/pwa/layouts/PwaLayout.vue'
import axios from 'axios'

const loading = ref(true)
const score   = ref({
  score:            450,
  score_band:       'fair',
  repayment_history_score: 50,
  debt_load_score:         50,
  history_length_score:    20,
  account_mix_score:       25,
  new_credit_score:        80,
  total_loans:       0,
  total_completed:   0,
  total_defaulted:   0,
  last_updated:      null,
})

onMounted(async () => {
  try {
    const res = await axios.get('/api/v1/borrower/credit-score')
    score.value = res.data.data ?? score.value
  } catch {
    // keep defaults — shows baseline
  } finally {
    loading.value = false
  }
})

// ─── Computed ──────────────────────────────────────────────────────────────

const scorePercent = computed(() => Math.round(((score.value.score - 300) / 550) * 100))

const scoreDash = computed(() => Math.round((scorePercent.value / 100) * 264))

const scoreColor = computed(() => {
  const s = score.value.score
  if (s >= 750) return '#10b981'
  if (s >= 650) return '#3b82f6'
  if (s >= 550) return '#f59e0b'
  return '#ef4444'
})

const bandLabel = computed(() => {
  const s = score.value.score
  if (s >= 750) return 'Excellent'
  if (s >= 650) return 'Good'
  if (s >= 550) return 'Fair'
  return 'Poor'
})

const bandBadge = computed(() => {
  const s = score.value.score
  if (s >= 750) return 'bg-emerald-100 text-emerald-700'
  if (s >= 650) return 'bg-blue-100 text-blue-700'
  if (s >= 550) return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-600'
})

const lastUpdated = computed(() => {
  if (!score.value.last_updated) return 'never'
  return new Date(score.value.last_updated).toLocaleDateString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
})

const factors = computed(() => [
  {
    key:   'repayment_history_score',
    label: 'Repayment History',
    weight: '40%',
    value:  score.value.repayment_history_score,
  },
  {
    key:   'debt_load_score',
    label: 'Debt Load',
    weight: '25%',
    value:  score.value.debt_load_score,
  },
  {
    key:   'history_length_score',
    label: 'Credit History Length',
    weight: '15%',
    value:  score.value.history_length_score,
  },
  {
    key:   'account_mix_score',
    label: 'Account Mix',
    weight: '10%',
    value:  score.value.account_mix_score,
  },
  {
    key:   'new_credit_score',
    label: 'New Credit',
    weight: '10%',
    value:  score.value.new_credit_score,
  },
])

const tips = [
  {
    title:   'Pay on time, every time',
    detail:  'Payment history makes up 40% of your score. Set up reminders or auto-pay.',
    icon:    'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    iconBg:  'bg-emerald-50',
    iconColor: 'text-emerald-500',
  },
  {
    title:   'Keep outstanding balances low',
    detail:  'High debt relative to your income lowers your Debt Load score.',
    icon:    'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    iconBg:  'bg-blue-50',
    iconColor: 'text-blue-500',
  },
  {
    title:   'Build a longer credit history',
    detail:  'Older accounts and longer borrowing history demonstrate reliability.',
    icon:    'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    iconBg:  'bg-amber-50',
    iconColor: 'text-amber-500',
  },
  {
    title:   'Complete your loans',
    detail:  'Every fully repaid loan boosts your score significantly.',
    icon:    'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    iconBg:  'bg-purple-50',
    iconColor: 'text-purple-500',
  },
]

// ─── Helpers ───────────────────────────────────────────────────────────────

const factorColor = (v) => {
  if (v >= 70) return 'text-emerald-600'
  if (v >= 40) return 'text-amber-600'
  return 'text-red-500'
}

const factorBarColor = (v) => {
  if (v >= 70) return 'bg-emerald-400'
  if (v >= 40) return 'bg-amber-400'
  return 'bg-red-400'
}
</script>
